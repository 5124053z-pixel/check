<?php
require_once __DIR__ . '/FactChecker.php';

$checker = new FactChecker();
$text = "今年のたこ拳伝説は、なんと本館エリア1で開催されます！";
$results = $checker->check($text);

echo "Test Text: $text\n";
if (empty($results)) {
    echo "❌ No issues found.\n";
} else {
    foreach ($results as $r) {
        echo "✅ Found: {$r['word']}\n";
        echo "   Reason: {$r['reason']}\n";
    }
}
