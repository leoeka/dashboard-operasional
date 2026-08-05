<?php
$ch = curl_init('https://api.zipwp.com/mcp/zipwp');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$start = microtime(true);
$result = curl_exec($ch);
$time = round(microtime(true) - $start, 2);

echo "Waktu: {$time}s" . PHP_EOL;
echo "cURL Error: " . curl_error($ch) . PHP_EOL;
echo "HTTP Code: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . PHP_EOL;
echo "Response: " . $result . PHP_EOL;