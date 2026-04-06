<?php

class Proofreader {
    private $dictionaryRules;
    private $exactPhraseRules;
    private $typoRules; // Layer2: 定番タイポ辞書（Gemini呼び出し削減用）

    public function __construct() {
        // Sudachiの基本形ベースルックアップ用ルール（連語ではなく、１つの単語として解析されるもの）
        $this->dictionaryRules = [
            'あいまい' => ['replace' => '曖昧'],
            'やばい' => ['replace' => '危険'],
            'すごい' => ['replace' => '素晴らしい'],
            'うざい' => ['replace' => '煩わしい'],
            'ムズい' => ['replace' => '難しい'],
            'あきらめる' => ['replace' => '諦める'],
            '後々' => ['replace' => '後々'],
            '侮る' => ['replace' => 'あなどる'],
            '溢れる' => ['replace' => 'あふれる'],
            'あらかじめ' => ['replace' => '予め'],
            '有る' => ['replace' => 'ある'],
            '在る' => ['replace' => 'ある'],
            'よい' => ['replace' => '良い'],
            '言える' => ['replace' => 'いえる'],
            '如何に' => ['replace' => 'いかに'],
            'いす' => ['replace' => '椅子'],
            'イス' => ['replace' => '椅子'],
            '一旦' => ['replace' => 'いったん'],
            '居る' => ['replace' => 'いる'],
            '色々' => ['replace' => 'いろいろ'],
            '色んな' => ['replace' => 'いろんな'],
            'その上' => ['replace' => 'そのうえ'],
            '上手い' => ['replace' => 'うまい'],
            '嬉しい' => ['replace' => 'うれしい'],
            '映像授業' => ['replace' => 'オンデマンド授業'],
            'おこなう' => ['replace' => '行う'],
            'お勧め' => ['replace' => 'おすすめ'],
            'おもに' => ['replace' => '主に'],
            'および' => ['replace' => '及び'],
            '疎か' => ['replace' => 'おろそか'],
            '下さる' => ['replace' => 'くださる'],
            '繰り返す' => ['replace' => '繰り返す'],
            'けが' => ['replace' => '怪我'],
            'けっこう' => ['replace' => '結構'],
            '心掛ける' => ['replace' => '心掛ける'],
            '子供' => ['replace' => '子ども'],
            '籠る' => ['replace' => 'こもる'],
            '篭る' => ['replace' => 'こもる'],
            '流石' => ['replace' => 'さすが'],
            '様々' => ['replace' => 'さまざま'],
            '更に' => ['replace' => 'さらに'],
            '定期考査' => ['replace' => '定期試験'],
            'テスト' => ['replace' => '試験'],
            'し得る' => ['replace' => 'しうる'],
            '次第' => ['replace' => 'しだい'],
            '締め切り' => ['replace' => '締切'],
            '〆切' => ['replace' => '締切'],
            'zoom' => ['replace' => 'Zoom'],
            'ZOOM' => ['replace' => 'Zoom'],
            '随分' => ['replace' => 'ずいぶん'],
            '隙間時間' => ['replace' => 'スキマ時間'],
            '勧める' => ['replace' => 'すすめる'],
            '薦める' => ['replace' => 'すすめる'],
            '既に' => ['replace' => 'すでに'],
            '素早い' => ['replace' => 'すばやい'],
            'すべて' => ['replace' => '全て'],
            'すみやか' => ['replace' => '速やか'],
            '精一杯' => ['replace' => '精いっぱい'],
            '製作' => ['replace' => '制作'],
            '折角' => ['replace' => 'せっかく'],
            '是非' => ['replace' => 'ぜひ'],
            'ゼミナール' => ['replace' => 'ゼミ'],
            '前述' => ['replace' => '先述'],
            'ぜんぜん' => ['replace' => '全然'],
            '揃う' => ['replace' => 'そろう'],
            'たいした' => ['replace' => '大した'],
            '沢山' => ['replace' => 'たくさん'],
            'たとえば' => ['replace' => '例えば'],
            '為' => ['replace' => 'ため'],
            'ためしに' => ['replace' => '試しに'],
            '段々' => ['replace' => 'だんだん'],
            '掴む' => ['replace' => 'つかむ'],
            '付ける' => ['replace' => 'つける'],
            '着ける' => ['replace' => 'つける'],
            '都度' => ['replace' => 'つど'],
            '繋ぐ' => ['replace' => 'つなぐ'],
            '辛い' => ['replace' => 'つらい'],
            '出来る' => ['replace' => 'できる'],
            '手応え' => ['replace' => '手応え'],
            '問い合わせ' => ['replace' => '問い合わせ'],
            '問いあわせ' => ['replace' => '問い合わせ'],
            '通り' => ['replace' => 'とおり'],
            '留める' => ['replace' => 'とどめる'],
            '飛ばす' => ['replace' => 'とばす'],
            '友達' => ['replace' => '友人'],
            '共' => ['replace' => 'とも'],
            '捉える' => ['replace' => 'とらえる'],
            'やり取り' => ['replace' => 'やりとり'],
            '無い' => ['replace' => 'ない'],
            '尚' => ['replace' => 'なお'],
            '尚更' => ['replace' => 'なおさら'],
            '解き直す' => ['replace' => '解き直す'],
            '無くす' => ['replace' => 'なくす'],
            '成る' => ['replace' => 'なる'],
            '何でも' => ['replace' => 'なんでも'],
            '何とか' => ['replace' => 'なんとか'],
            '伸び伸び' => ['replace' => 'のびのび'],
            '延び延び' => ['replace' => 'のびのび'],
            '捗る' => ['replace' => 'はかどる'],
            '一際' => ['replace' => 'ひときわ'],
            '一通り' => ['replace' => 'ひととおり'],
            '一時' => ['replace' => 'ひととき'],
            '1つ' => ['replace' => '一つ'],
            'ひとつ' => ['replace' => '一つ'],
            '一つ一つ' => ['replace' => '一つひとつ'],
            '一人一人' => ['replace' => '一人ひとり'],
            '紐解く' => ['replace' => 'ひもとく'],
            '風' => ['replace' => 'ふう'],
            '部活動' => ['replace' => '部活'],
            '相応しい' => ['replace' => 'ふさわしい'],
            '踏まえる' => ['replace' => 'ふまえる'],
            '踏み込む' => ['replace' => 'ふみこむ'],
            '触れ合い' => ['replace' => '触れ合い'],
            '他' => ['replace' => 'ほか'],
            '程' => ['replace' => 'ほど'],
            'ホームページ' => ['replace' => 'WEBページ'],
            'HP' => ['replace' => 'WEBページ'],
            '参る' => ['replace' => 'まいる'],
            '正に' => ['replace' => 'まさに'],
            '益々' => ['replace' => 'ますます'],
            '又は' => ['replace' => 'または'],
            '全く' => ['replace' => 'まったく'],
            '満遍なく' => ['replace' => 'まんべんなく'],
            '万遍なく' => ['replace' => 'まんべんなく'],
            '皆さま' => ['replace' => '皆さま'],
            '皆さん' => ['replace' => '皆さん'],
            '皆様' => ['replace' => '皆さま'],
            '無暗' => ['replace' => 'むやみ'],
            '目途' => ['replace' => 'めど'],
            '目処' => ['replace' => 'めど'],
            '申込' => ['replace' => '申し込み'],
            '申し込み' => ['replace' => '申し込み'],
            'モチベ' => ['replace' => 'モチベーション'],
            '勿論' => ['replace' => 'もちろん'],
            '元々' => ['replace' => 'もともと'],
            '貰う' => ['replace' => 'もらう'],
            '闇雲' => ['replace' => 'やみくも'],
            '余程' => ['replace' => 'よほど'],
            '読込' => ['replace' => '読み込み'],
            '読込み' => ['replace' => '読み込み'],
            '分かれる' => ['replace' => '分かれる'],
            '分かる' => ['replace' => 'わかる'],
            '合否発表' => ['replace' => '合格発表'],
            '高卒生' => ['replace' => '浪人生'],
            '既卒' => ['replace' => '浪人'],
            '天下一' => ['replace' => '天下市'],
            'LINK国立' => ['replace' => 'LINKくにたち'],
            '国立旧駅舎' => ['replace' => '旧国立駅舎'],
            'それ故' => ['replace' => 'それゆえ']
        ];

        // Sudachiで分離してしまうフレーズや連語。これらは文字列全体の正規表現マッチ・文字列マッチ等と同様の処理。
        $this->exactPhraseRules = [
            'あってい' => '合ってい',
            'お願いいたします' => 'お願いいたします',
            'て頂く' => 'ていただく',
            '力をいれる' => '力を入れる',
            '手を付け' => '手をつけ',
            '手を着け' => '手をつけ',
            '身に付け' => '身につけ',
            '国立秋の市民祭り' => 'くにたち秋の市民まつり',
            'くにたち秋の市民祭り' => 'くにたち秋の市民まつり',
            '国立秋の市民まつり' => 'くにたち秋の市民まつり',
            '受験生情報冊子編集者' => '受験情報冊子編集者',
            '１' => '1','２' => '2','３' => '3','４' => '4','５' => '5','６' => '6','７' => '7','８' => '8','９' => '9','０' => '0',
        ];

        // Layer2: 定番タイポ・変換ミス辞書（Geminiを呼ぶ前に先に処理して節約）
        // 形式: '誤り' => ['正しい表記', '理由の説明']
        $this->typoRules = [
            // カタカナ外来語の定番誤り
            'シュミレーション'   => ['シミュレーション',   '「シュミレ」は「シミュレ」の誤り'],
            'シュミレート'       => ['シミュレート',       '「シュミレ」は「シミュレ」の誤り'],
            'アルバイト'         => ['アルバイト',         '正しい表記'],
            'コミュニティー'     => ['コミュニティ',       'カタカナ語尾の「ー」は省略が一般的'],
            'エネルギー'         => ['エネルギー',         '正しい表記'],

            // かな文字の打ち間違い
            'こんにちわ'         => ['こんにちは',         '「は」を「わ」と書くのは誤り'],
            'こんばんわ'         => ['こんばんは',         '「は」を「わ」と書くのは誤り'],
            'ふいんき'           => ['ふんいき',           '「雰囲気」の読み方の誤り'],

            // 同音異義語の明らかな変換ミス
            '以外と'             => ['意外と',             '「意外と」の意味なら「意外」が正しい'],
            '一所懸命'           => ['一生懸命',           '「一生懸命」が正しい定着した表記'],

            // ら抜き言葉
            '見れる'             => ['見られる',           'ら抜き言葉（見る→見られる）'],
            '食べれる'           => ['食べられる',         'ら抜き言葉（食べる→食べられる）'],
            '起きれる'           => ['起きられる',         'ら抜き言葉（起きる→起きられる）'],
            '着れる'             => ['着られる',           'ら抜き言葉（着る→着られる）'],
            '出れる'             => ['出られる',           'ら抜き言葉（出る→出られる）'],
            '来れる'             => ['来られる',           'ら抜き言葉（来る→来られる）'],
        ];
    }

    // 定番タイポのマッチ一覧を返す（analyze.phpからGeminiへ渡す前に使用）
    public function getTypoMatches($text) {
        $matches = [];
        $uniqueId = 0;
        $checkOverlap = function($pos, $len) use (&$matches) {
            foreach ($matches as $m) {
                if (max($pos, $m['start']) < min($pos + $len, $m['end'])) return true;
            }
            return false;
        };

        foreach ($this->typoRules as $target => [$replacement, $reason]) {
            $offset = 0;
            while (($pos = mb_strpos($text, $target, $offset)) !== false) {
                $len = mb_strlen($target);
                if (!$checkOverlap($pos, $len)) {
                    $matches[] = [
                        'start'       => $pos,
                        'end'         => $pos + $len,
                        'word'        => $target,
                        'replacement' => $replacement,
                        'reason'      => '📖 定番ミス: ' . $reason,
                        'id'          => 'typo-' . ($uniqueId++)
                    ];
                }
                $offset = $pos + $len;
            }
        }
        return $matches;
    }

    public function analyze($text, $tokens, $aiSuggestions = []) {
        $matches = [];
        $uniqueIdCounter = 0;
        
        // --- 1. Sudachi トークンベースの校正（語幹置換対応） ---
        $offset = 0;
        foreach ($tokens as $token) {
            $surface = $token['surface'];
            $base = $token['base_form'];
            $len = mb_strlen($surface);

            $pos = mb_strpos($text, $surface, $offset);
            if ($pos !== false) {
                $offset = $pos;
            }

            if (isset($this->dictionaryRules[$base])) {
                $replaceBase = $this->dictionaryRules[$base]['replace'];
                
                if ($base === $surface) {
                    $proposed = $replaceBase;
                } else {
                    $proposed = $this->calculateStemReplacement($base, $replaceBase, $surface);
                }

                if ($surface !== $proposed) {
                    $matches[] = [
                        'start' => $offset,
                        'end' => $offset + $len,
                        'word' => $surface,
                        'replacement' => $proposed,
                        'id' => 'match-' . ($uniqueIdCounter++)
                    ];
                }
            }
            $offset += $len;
        }

        // Sudachiトークンが取得できない場合のフォールバック。
        // Python API停止時でも基本の辞書置換が働くようにする。
        if (empty($tokens)) {
            foreach ($this->dictionaryRules as $target => $rule) {
                $replacement = $rule['replace'];
                if ($target === $replacement) {
                    continue;
                }

                $offset = 0;
                while (($pos = mb_strpos($text, $target, $offset)) !== false) {
                    $len = mb_strlen($target);
                    $overlapping = false;
                    foreach ($matches as $m) {
                        if (max($pos, $m['start']) < min($pos + $len, $m['end'])) {
                            $overlapping = true;
                            break;
                        }
                    }

                    if (!$overlapping) {
                        $matches[] = [
                            'start' => $pos,
                            'end' => $pos + $len,
                            'word' => $target,
                            'replacement' => $replacement,
                            'id' => 'match-' . ($uniqueIdCounter++)
                        ];
                    }
                    $offset = $pos + $len;
                }
            }
        }

        // --- 2. 連語・フレーズの校正（正規表現・表層完全一致マッチ） ---
        foreach ($this->exactPhraseRules as $target => $replacement) {
            $offset = 0;
            while (($pos = mb_strpos($text, $target, $offset)) !== false) {
                $len = mb_strlen($target);
                
                $overlapping = false;
                foreach ($matches as $m) {
                    if (max($pos, $m['start']) < min($pos + $len, $m['end'])) {
                        $overlapping = true;
                        break;
                    }
                }

                if (!$overlapping && $target !== $replacement) {
                    $matches[] = [
                        'start' => $pos,
                        'end' => $pos + $len,
                        'word' => $target,
                        'replacement' => $replacement,
                        'id' => 'match-' . ($uniqueIdCounter++)
                    ];
                }
                $offset = $pos + $len;
            }
        }

        // --- 3. AIによるコンテキストベースのタイポ・変換ミス提案 ---
        foreach ($aiSuggestions as $ai) {
            $target = $ai['word'] ?? '';
            $replacement = $ai['replacement'] ?? '';
            $reason = $ai['reason'] ?? '';
            
            if ($target === '' || $replacement === '' || $target === $replacement) {
                continue;
            }

            $offset = 0;
            while (($pos = mb_strpos($text, $target, $offset)) !== false) {
                $len = mb_strlen($target);
                
                $overlapping = false;
                foreach ($matches as $m) {
                    if (max($pos, $m['start']) < min($pos + $len, $m['end'])) {
                        $overlapping = true;
                        break;
                    }
                }

                if (!$overlapping) {
                    $matches[] = [
                        'start' => $pos,
                        'end' => $pos + $len,
                        'word' => $target,
                        'replacement' => $replacement,
                        'reason' => '✨ AI: ' . $reason,
                        'id' => 'match-' . ($uniqueIdCounter++)
                    ];
                }
                $offset = $pos + $len;
            }
        }

        usort($matches, function ($a, $b) {
            return $a['start'] - $b['start'];
        });

        return $matches;
    }

    private function calculateStemReplacement($base, $replace, $surface) {
        $b_len = mb_strlen($base);
        $r_len = mb_strlen($replace);
        $common_suffix = '';
        $i = 1;
        
        while ($i <= $b_len && $i <= $r_len) {
            if (mb_substr($base, -$i, 1) === mb_substr($replace, -$i, 1)) {
                $common_suffix = mb_substr($base, -$i, 1) . $common_suffix;
                $i++;
            } else {
                break;
            }
        }

        $base_stem = mb_substr($base, 0, $b_len - mb_strlen($common_suffix));
        $replace_stem = mb_substr($replace, 0, $r_len - mb_strlen($common_suffix));

        if ($base_stem !== '') {
            $pos = mb_strpos($surface, $base_stem);
            if ($pos === 0) {
                return $replace_stem . mb_substr($surface, mb_strlen($base_stem));
            }
        }
        
        return $replace;
    }
}
