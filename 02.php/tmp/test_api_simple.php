<?php
/**
 * 简单的API测试
 */

// 模拟API调用
$postData = json_encode([]);

// 设置请求头
$headers = [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($postData)
];

// 测试全量同步API
echo "=== 测试全量同步API ===\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8080/api.php?c=WeComAdmin&a=FullSync&t=web');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP状态码: $httpCode\n";
echo "响应内容: $response\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if ($data) {
        echo "状态: " . ($data['status'] ?? 'unknown') . "\n";
        echo "消息: " . ($data['message'] ?? 'no message') . "\n";
    }
}

echo "\n=== 测试完成 ===\n";
?>
