<?php

require_once __DIR__ . '/PythonApiManager.php';

class ImageOcrHandler {
    private $pythonApiUrl = 'http://127.0.0.1:8000/ocr';
    private $apiManager;

    public function __construct() {
        $this->apiManager = new PythonApiManager();
    }

    /**
     * 画像ファイルを Python API に送信して OCR テキストを取得する
     * @param array $file $_FILES['image'] のような形式
     */
    public function extractText($file) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            throw new Exception("画像ファイルがアップロードされていません。");
        }

        if (!$this->apiManager->ensureRunning()) {
            throw new Exception("Python API の起動に失敗しました。");
        }

        $ch = curl_init();
        
        $cfile = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
        $data = ['file' => $cfile];

        curl_setopt($ch, CURLOPT_URL, $this->pythonApiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new Exception("OCR API 接続エラー: " . $error);
        }

        $result = json_decode($response, true);
        if ($httpCode !== 200 || !isset($result['text'])) {
            $msg = $result['detail'] ?? "不明なエラー";
            throw new Exception("OCR 解析エラー: " . $msg);
        }

        return $result['text'];
    }
}
