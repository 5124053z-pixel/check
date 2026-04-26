<?php

class ImageOcrHandler {
    private $ocrUrl;

    public function __construct() {
        // PYTHON_API_URL からベースURLを推測 (例: http://python-api:8000/analyze -> http://python-api:8000/ocr)
        $baseApiUrl = getenv('PYTHON_API_URL') ?: 'http://127.0.0.1:8000/analyze';
        $this->ocrUrl = str_replace('/analyze', '/ocr', $baseApiUrl);
    }

    public function extractText($file) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            throw new Exception("No file uploaded.");
        }

        $ch = curl_init($this->ocrUrl);
        $cfile = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
        $data = ['file' => $cfile];

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // OCRは時間がかかるため長めに設定

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("OCR API error (HTTP $httpCode): $response $error");
        }

        $result = json_decode($response, true);
        return $result['text'] ?? '';
    }
}
