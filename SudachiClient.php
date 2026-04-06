<?php

require_once __DIR__ . '/PythonApiManager.php';

class SudachiClient {
    private $apiUrl = "http://127.0.0.1:8000/analyze";
    private $pdo;
    private $apiManager;

    public function __construct() {
        $dbPath = __DIR__ . '/cache.sqlite';
        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->apiManager = new PythonApiManager();
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS grammar_cache (
                hash_key TEXT PRIMARY KEY,
                json_result TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    public function analyze($text, $useAi = true) {
        if (trim($text) === '') {
            return [
                'tokens' => [],
                'ai_suggestions' => [],
                'ai_status' => [
                    'requested' => (bool)$useAi,
                    'available' => false,
                    'executed' => false,
                    'suggestions_count' => 0,
                    'error' => null,
                    'outcome' => $useAi ? 'empty_text' : 'disabled'
                ]
            ];
        }

        if (!$this->apiManager->ensureRunning()) {
            return false;
        }

        $hash = md5($text);

        // キャッシュチェック（AI提案が存在する結果のみ有効とする）
        $stmt = $this->pdo->prepare("SELECT json_result FROM grammar_cache WHERE hash_key = :hash");
        $stmt->execute([':hash' => $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $useAi) {
            $cached = json_decode($row['json_result'], true);
            // ai_suggestionsが存在して非空なキャッシュのみ有効
            if (!empty($cached['ai_suggestions'])) {
                if (!isset($cached['ai_status'])) {
                    $cached['ai_status'] = [
                        'requested' => true,
                        'available' => true,
                        'executed' => true,
                        'suggestions_count' => count($cached['ai_suggestions'] ?? []),
                        'error' => null,
                        'outcome' => count($cached['ai_suggestions'] ?? []) > 0 ? 'suggestions_found' : 'no_suggestions'
                    ];
                }
                return $cached;
            }
            // AI提案が空のキャッシュは無視して再リクエスト
        }

        $data = json_encode(['text' => $text, 'use_ai' => (bool)$useAi]);
        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => $data,
                'timeout' => 15,
            ],
        ];

        $context  = stream_context_create($options);
        $result = @file_get_contents($this->apiUrl, false, $context);

        if ($result === false) {
            return false;
        }

        $responseObj = json_decode($result, true);
        
        if (isset($responseObj['tokens'])) {
            // AI提案がある場合のみキャッシュに保存
            if ($useAi && !empty($responseObj['ai_suggestions'])) {
                $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO grammar_cache (hash_key, json_result) VALUES (:hash, :result)");
                $stmt->execute([':hash' => $hash, ':result' => json_encode($responseObj, JSON_UNESCAPED_UNICODE)]);
            }
            if (!isset($responseObj['ai_status'])) {
                $responseObj['ai_status'] = [
                    'requested' => (bool)$useAi,
                    'available' => true,
                    'executed' => (bool)$useAi,
                    'suggestions_count' => count($responseObj['ai_suggestions'] ?? []),
                    'error' => null,
                    'outcome' => !$useAi
                        ? 'disabled'
                        : (count($responseObj['ai_suggestions'] ?? []) > 0 ? 'suggestions_found' : 'no_suggestions')
                ];
            }
            return $responseObj;
        }

        return false;
    }
}
