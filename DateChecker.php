<?php

class DateChecker {
    private $dayNames = ['日', '月', '火', '水', '木', '金', '土'];

    /**
     * テキスト内の日付+曜日表記を全て抽出・検証し、誤りがあれば提案リストとして返す
     */
    public function check($text, $year = null) {
        $matches = [];
        $uniqueId = 0;
        $targetYear = is_int($year) ? $year : (int)date('Y');

        // 対応する書き方のパターン一覧
        // グループ: (月) (日) (曜字) の3つを必ずキャプチャする
        $patterns = [
            // 4/29(水) / 4/29（水）/ 4/29(水曜) / 4/29（水曜日）
            '/(\d{1,2})\/(\d{1,2})[（(]([月火水木金土日])(?:曜日?)?[）)]/u',
            // 4月29日(水) / 4月29日（水曜日）
            '/(\d{1,2})月(\d{1,2})日[（(]([月火水木金土日])(?:曜日?)?[）)]/u',
            // 4月29日 水曜日 / 4/29 水曜日 (スペース区切り)
            '/(\d{1,2})[月\/](\d{1,2})日?\s+([月火水木金土日])曜日/u',
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $found, PREG_OFFSET_CAPTURE);

            foreach ($found[0] as $idx => $matchData) {
                [$fullMatch, $byteOffset] = $matchData;

                $month  = (int)$found[1][$idx][0];
                $day    = (int)$found[2][$idx][0];
                $dayStr = $found[3][$idx][0]; // 「水」など1文字

                // バイトオフセット→マルチバイト文字オフセットへ正しく変換
                // ※ PREG_OFFSET_CAPTURE のオフセットはバイト単位なので substr() で切り取る
                $mbOffset = mb_strlen(substr($text, 0, $byteOffset));
                $mbLen    = mb_strlen($fullMatch);

                // 判定対象年の日付として妥当か？
                if (!checkdate($month, $day, $targetYear)) {
                    continue; // 存在しない日付は無視
                }

                // 実際の曜日を計算
                $timestamp   = mktime(0, 0, 0, $month, $day, $targetYear);
                $actualDayIndex = (int)date('w', $timestamp); // 0=日, 6=土
                $actualDayStr   = $this->dayNames[$actualDayIndex];

                // 曜日が一致しているか
                if ($dayStr !== $actualDayStr) {
                    // 正しい曜日を含む文字列を提案
                    $corrected = $this->buildCorrectedString($fullMatch, $dayStr, $actualDayStr);

                    // 重複チェック
                    $overlapping = false;
                    foreach ($matches as $m) {
                        if (max($mbOffset, $m['start']) < min($mbOffset + $mbLen, $m['end'])) {
                            $overlapping = true;
                            break;
                        }
                    }

                    if (!$overlapping) {
                        $matches[] = [
                            'start'       => $mbOffset,
                            'end'         => $mbOffset + $mbLen,
                            'word'        => $fullMatch,
                            'replacement' => $corrected,
                            'reason'      => "📅 日付: {$month}/{$day}は{$targetYear}年の曜日と一致しません（正: {$actualDayStr}曜日）",
                            'id'          => 'date-' . ($uniqueId++),
                        ];
                    }
                }
            }
        }

        return $matches;
    }

    /**
     * マッチした文字列の曜日部分を正しいものに差し替えた文字列を返す
     */
    private function buildCorrectedString($fullMatch, $wrongDay, $correctDay) {
        return preg_replace(
            '/[（(]' . preg_quote($wrongDay, '/') . '(曜日?)?[）)]/u',
            '（' . $correctDay . '$1）',
            $fullMatch
        );
    }
}
