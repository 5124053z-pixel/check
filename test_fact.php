<?php
require 'ProofreadPipeline.php';
$pl = new ProofreadPipeline();
$aiStatus = null;
$res = $pl->analyzeText('オムそばは本館で開催', false, $aiStatus);
print_r($res);
