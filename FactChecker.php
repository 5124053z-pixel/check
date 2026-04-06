<?php

class FactChecker {
    private $db;

    public function __construct() {
        $dbPath = __DIR__ . '/event56th.sqlite';
        try {
            $this->db = new PDO("sqlite:$dbPath");
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("FactChecker: Could not connect to SQLite. " . $e->getMessage());
            $this->db = null;
        }
    }

    /**
     * テキスト内の事実整合性をチェックして結果を返す
     */
    public function check($text) {
        if (!$this->db) return [];

        $matches = [];
        $uniqueId = 0;

        // 全件取得してメモリ上でマッチング（データ量が少ない場合）
        // 大量にある場合は検索クエリを工夫する
        $stmt = $this->db->query("SELECT event_name, circle_name, place_detail, event_date FROM event56th WHERE cancel = ''");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($events as $event) {
            $names = array_unique([$event['event_name'], $event['circle_name']]);
            foreach ($names as $name) {
                if (mb_strlen($name) < 2) continue; // 短すぎる名前はスキップ

                $pos = mb_strpos($text, $name);
                while ($pos !== false) {
                    // 名前が見つかったら、その周辺（前後50文字）を取得
                    $start = max(0, $pos - 50);
                    $end = min(mb_strlen($text), $pos + mb_strlen($name) + 50);
                    $context = mb_substr($text, $start, $end - $start);

                    // --- 場所のチェック ---
                    if ($event['place_detail']) {
                        // 周辺に「別の場所」っぽいキーワードがあり、かつ正解が含まれていない場合
                        // シンプルな実装: 他の場所の名前が出てきたら警告
                        // ※ ここでは「場所」として登録されている単語リストがあるとより正確
                        // 今回は「DBの正解が周辺に含まれていない + 地名っぽい単語がある」を簡易判定
                        if (mb_strpos($context, $event['place_detail']) === false) {
                            // もし別の場所を示唆する単語（本館, 図書館, 西プラザ, ステージ, 講義棟）があれば
                            $placeKeywords = ['本館', '図書館', '西プラザ', 'ステージ', '講義棟', '屋外'];
                            foreach ($placeKeywords as $pk) {
                                if (mb_strpos($context, $pk) !== false && mb_strpos($event['place_detail'], $pk) === false) {
                                     // 不一致の可能性がある
                                     $matches[] = [
                                        'start'       => $pos,
                                        'end'         => $pos + mb_strlen($name),
                                        'word'        => $name,
                                        'replacement' => $name, // 置換は名前自体ではなく、情報の提供
                                        'reason'      => "🔍 事実確認: 「{$name}」は通常「{$event['place_detail']}」で行われます。現在の記述と一致するか確認してください。",
                                        'id'          => 'fact-' . ($uniqueId++),
                                     ];
                                     break;
                                }
                            }
                        }
                    }

                    // --- 日程のチェック ---
                    // DBの event_date: "22日,23日,24日" などの形式
                    if ($event['event_date']) {
                        $dates = explode(',', $event['event_date']);
                        // コンテキスト内に「〇〇日」という記述があるか抽出
                        if (preg_match('/(\d{1,2})日/', $context, $m)) {
                            $foundDate = $m[1] . '日';
                            if (!in_array($foundDate, $dates)) {
                                $matches[] = [
                                    'start'       => $pos,
                                    'end'         => $pos + mb_strlen($name),
                                    'word'        => $name,
                                    'replacement' => $name,
                                    'reason'      => "🔍 事実確認: 「{$name}」の開催予定日は {$event['event_date']} です。記述の {$foundDate} と相違ないか確認してください。",
                                    'id'          => 'fact-' . ($uniqueId++),
                                ];
                            }
                        }
                    }

                    $pos = mb_strpos($text, $name, $pos + 1);
                }
            }
        }

        // 重複を除去（同じ箇所に複数の指摘が出ないように）
        return $this->deduplicate($matches);
    }

    private function deduplicate($matches) {
        $result = [];
        foreach ($matches as $m) {
            $overlapping = false;
            foreach ($result as $r) {
                if (max($m['start'], $r['start']) < min($m['end'], $r['end'])) {
                    $overlapping = true;
                    break;
                }
            }
            if (!$overlapping) $result[] = $m;
        }
        return $result;
    }
}
