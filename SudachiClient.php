<?php

class SudachiClient {
    private $apiUrl;
    private $db;

    public function __construct() {
        // Docker環境では環境変数から取得、デフォルトはlocalhost
        $this->apiUrl = getenv('PYTHON_API_URL') ?: 'http://127.0.0.1:8000/analyze';
        
        // キャッシュDBの初期化
        $dbPath = __DIR__ . '/cache.sqlite';
        try {
            $this->db = new PDO("sqlite:$dbPath");
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->exec("CREATE TABLE IF NOT EXISTS grammar_cache (
                hash_key TEXT PRIMARY KEY,
                json_result TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (PDOException $e) {
            error_log("SudachiClient: Cache DB error: " . $e->getMessage());
            $this->db = null;
        }
    }

    public function analyze($text, $aiText = null, $useAi = true) {
        if (empty($text)) {
            return ['tokens' => [], 'ai_suggestions' => [], 'ai_status' => ['outcome' => 'empty']];
        }

        $hashKey = md5($text . ($aiText ?? '') . ($useAi ? '1' : '0'));

        // キャッシュチェック
        if ($this->db) {
            $stmt = $this->db->prepare("SELECT json_result FROM grammar_cache WHERE hash_key = ?");
            $stmt->execute([$hashKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return json_decode($row['json_result'], true);
            }
        }

        // APIリクエスト
        $data = [
            'text' => $text,
            'ai_text' => $aiText,
            'use_ai' => $useAi
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // タイムアウト設定

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("SudachiClient: API error (HTTP $httpCode): $response");
            return null;
        }

        $result = json_decode($response, true);
        
        // キャッシュ保存 (AI提案がある場合、またはAI不使用時)
        if ($this->db && $result && ($useAi === false || !empty($result['ai_suggestions']))) {
            try {
                $stmt = $this->db->prepare("INSERT OR REPLACE INTO grammar_cache (hash_key, json_result) VALUES (?, ?)");
                $stmt->execute([$hashKey, $response]);
            } catch (PDOException $e) {
                error_log("SudachiClient: Cache write error: " . $e->getMessage());
            }
        }

        return $result;
    }
}
