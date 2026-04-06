<?php

require_once __DIR__ . '/SudachiClient.php';
require_once __DIR__ . '/Proofreader.php';
require_once __DIR__ . '/DateChecker.php';
require_once __DIR__ . '/FactChecker.php';

class ProofreadPipeline {
    private $proofreader;
    private $dateChecker;
    private $factChecker;
    private $sudachiClient;

    public function __construct() {
        $this->proofreader = new Proofreader();
        $this->dateChecker = new DateChecker();
        $this->factChecker = new FactChecker();
        $this->sudachiClient = new SudachiClient();
    }

    public function analyzeText($text, $useAi = true, &$aiStatus = null) {
        $aiStatus = [
            'requested' => (bool)$useAi,
            'available' => false,
            'executed' => false,
            'suggestions_count' => 0,
            'error' => null,
            'outcome' => $useAi ? 'pending' : 'disabled'
        ];

        if ($text === '') {
            return [];
        }

        // Layer 2: 定番タイポ
        $typoMatches = $this->proofreader->getTypoMatches($text);

        // Layer 4/5: 事前チェック
        $dateMatches = $this->dateChecker->check($text);
        $factMatches = $this->factChecker->check($text);

        // Geminiへ送る前に既知の誤りをマスク
        $maskedText = $this->maskPreDetected($text, array_merge($typoMatches, $dateMatches, $factMatches));

        // Layer 3: Sudachi + AI
        $apiResult = $this->sudachiClient->analyze($maskedText !== $text ? $maskedText : $text, (bool)$useAi);
        if ($apiResult === false) {
            $aiStatus['error'] = 'python_api_unavailable';
            $aiStatus['outcome'] = 'python_api_unavailable';
            $apiResult = ['tokens' => [], 'ai_suggestions' => []];
        }

        $tokens = $apiResult['tokens'] ?? [];
        $aiSuggestions = $apiResult['ai_suggestions'] ?? [];
        if (isset($apiResult['ai_status']) && is_array($apiResult['ai_status'])) {
            $aiStatus = array_merge($aiStatus, $apiResult['ai_status']);
        }

        // Layer 1 + 3
        $matches = $this->proofreader->analyze($text, $tokens, $aiSuggestions);

        // Layer 2/4/5 を重複なしでマージ
        $matches = $this->mergeNonOverlapping($matches, $typoMatches);
        $matches = $this->mergeNonOverlapping($matches, $dateMatches);
        $matches = $this->mergeNonOverlapping($matches, $factMatches);

        usort($matches, fn($a, $b) => $a['start'] - $b['start']);
        return $matches;
    }

    private function maskPreDetected($text, $preDetectedMatches)
    {
        $maskedText = $text;
        usort($preDetectedMatches, fn($a, $b) => $b['start'] - $a['start']);

        foreach ($preDetectedMatches as $m) {
            // replacementが元の語と同じ場合（FactChecker等）はスキップ
            if (
                mb_strlen($m['word']) > 0 && !empty($m['replacement'])
                && $m['replacement'] !== $m['word']
            ) {  // ← これを追加
                $maskedText = mb_substr($maskedText, 0, $m['start'])
                    . $m['replacement']
                    . mb_substr($maskedText, $m['end']);
            }
        }
        return $maskedText;
    }

    private function mergeNonOverlapping($currentMatches, $newMatches) {
        foreach ($newMatches as $candidate) {
            if (!$this->isOverlapping($currentMatches, $candidate)) {
                $currentMatches[] = $candidate;
            }
        }
        return $currentMatches;
    }

    private function isOverlapping($currentMatches, $newMatch) {
        foreach ($currentMatches as $m) {
            if (max($newMatch['start'], $m['start']) < min($newMatch['end'], $m['end'])) {
                return true;
            }
        }
        return false;
    }
}
