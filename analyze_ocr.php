<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/ProofreadPipeline.php';
require_once __DIR__ . '/ImageOcrHandler.php';

if (!isset($_FILES['image'])) {
    echo json_encode(['error' => '画像がアップロードされていません']);
    exit;
}
$useAi = isset($_POST['use_ai']) ? (bool)$_POST['use_ai'] : true;

try {
    // 1. OCR でテキスト抽出
    $ocrHandler = new ImageOcrHandler();
    $text = $ocrHandler->extractText($_FILES['image']);

    if (empty($text)) {
        echo json_encode([
            'text' => '',
            'matches' => [],
            'ai_status' => [
                'requested' => $useAi,
                'available' => false,
                'executed' => false,
                'suggestions_count' => 0,
                'error' => null
            ]
        ]);
        exit;
    }

    // 2. テキスト校正と同一の共通パイプラインで解析
    $pipeline = new ProofreadPipeline();
    $aiStatus = null;
    $matches = $pipeline->analyzeText($text, $useAi, $aiStatus);

    echo json_encode([
        'text' => $text,
        'matches' => $matches,
        'ai_status' => $aiStatus
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
