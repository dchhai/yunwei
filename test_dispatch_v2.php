<?php
// 测试dispatch.php接口的脚本
header('Content-Type: text/plain; charset=utf-8');

echo "测试工单分派接口...\n";
echo "====================================\n";

// 测试数据
$testData = [
    'ticket_id' => 7,  // 与用户提供的测试数据一致
    'handler_id' => 5,
    'remark' => ''
];

echo "测试数据: " . json_encode($testData) . "\n";

// 创建cURL资源
$curl = curl_init();

// 设置cURL选项
curl_setopt_array($curl, [
    CURLOPT_URL => 'http://localhost/yunwei/dispatch.php',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($testData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
]);

// 执行cURL请求
echo "发送请求...\n";
$response = curl_exec($curl);
$err = curl_error($curl);

// 检查请求是否成功
if ($err) {
    echo "cURL错误: " . $err . "\n";
} else {
    echo "====================================\n";
    echo "响应状态码: " . curl_getinfo($curl, CURLINFO_HTTP_CODE) . "\n";
    echo "====================================\n";
    echo "响应内容:\n";
    
    // 尝试格式化JSON响应以便更好地阅读
    $decodedResponse = json_decode($response, true);
    if ($decodedResponse) {
        echo json_encode($decodedResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo $response;
    }
    echo "\n";
}

// 关闭cURL资源
curl_close($curl);

echo "====================================\n";
echo "测试完成\n";