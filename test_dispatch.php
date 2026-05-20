<?php
// 测试dispatch.php接口的文件
header('Content-Type: text/html; charset=utf-8');

// 测试数据
$testData = array(
    'ticket_id' => '7',
    'handler_id' => '5',
    'remark' => '测试分派'
);

// 使用cURL测试dispatch.php接口
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://yunwei/dispatch.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($testData))
));

// 执行请求
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 显示测试结果
echo '<h2>dispatch.php 接口测试结果</h2>';
echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0;">';
echo '<strong>HTTP状态码:</strong> ' . $httpCode . '<br>';
echo '<strong>请求数据:</strong><pre>' . json_encode($testData, JSON_PRETTY_PRINT) . '</pre><br>';
echo '<strong>响应数据:</strong><pre>' . $response . '</pre><br>';
if ($curlError) {
    echo '<strong>cURL错误:</strong> ' . $curlError . '<br>';
}

// 检查响应是否为有效的JSON
if ($response) {
    $jsonData = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo '<strong>响应解析:</strong> 成功 (是有效的JSON)<br>';
        if (isset($jsonData['success']) && $jsonData['success'] === true) {
            echo '<strong>操作结果:</strong> 成功<br>';
        } else {
            echo '<strong>操作结果:</strong> 失败<br>';
            if (isset($jsonData['message'])) {
                echo '<strong>错误信息:</strong> ' . $jsonData['message'] . '<br>';
            }
            if (isset($jsonData['error'])) {
                echo '<strong>详细错误:</strong> ' . $jsonData['error'] . '<br>';
            }
        }
    } else {
        echo '<strong>响应解析:</strong> 失败 (不是有效的JSON)<br>';
        echo '<strong>JSON错误:</strong> ' . json_last_error_msg() . '<br>';
    }
}

// 显示dispatch.php文件内容以便调试
echo '</div><h3>dispatch.php 文件内容:</h3>';
echo '<pre>';
echo htmlspecialchars(file_get_contents('dispatch.php'));
echo '</pre>';

// 检查文件权限
echo '<h3>文件权限检查:</h3>';
echo 'dispatch.php 文件存在: ' . (file_exists('dispatch.php') ? '是' : '否') . '<br>';
echo 'dispatch.php 文件可读: ' . (is_readable('dispatch.php') ? '是' : '否') . '<br>';
echo 'dispatch.php 文件可执行: ' . (is_executable('dispatch.php') ? '是' : '否') . '<br>';