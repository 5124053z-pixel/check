<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/ProofreadPipeline.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
$text = $data['text'] ?? '';
$useAi = isset($data['use_ai']) ? (bool)$data['use_ai'] : true;

if ($text === '') {
    echo json_encode([
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

$pipeline = new ProofreadPipeline();
$aiStatus = null;
$matches = $pipeline->analyzeText($text, $useAi, $aiStatus);

echo json_encode(['matches' => $matches, 'ai_status' => $aiStatus]);
