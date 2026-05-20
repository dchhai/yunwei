<?php
/**
 * 工单附件上传接口
 */
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只允许POST请求']);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/common/auth.php';

try {
    if (!isset($_FILES['file'], $_POST['ticket_id'])) {
        throw new Exception('缺少参数或文件');
    }

    $ticketId = (int)$_POST['ticket_id'];
    $file = $_FILES['file'];
    
    // 验证ticket_id有效性（新增：防止无效ID）
    if ($ticketId <= 0) {
        throw new Exception('工单ID无效');
    }
    
    // 验证文件大小（≤50MB）
    if ($file['size'] > 50 * 1024 * 1024) {
        throw new Exception('文件大小不能超过50MB');
    }

    // 验证文件类型
    $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'text/plain'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('不支持的文件类型');
    }

    // 处理文件存储
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $fileName = uniqid() . '_' . $file['name'];
    $filePath = $uploadDir . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('文件上传失败');
    }

    // 记录到数据库（使用真实ticket_id）
    $pdo = DB::getInstance();
    $stmt = $pdo->prepare("
        INSERT INTO ticket_attachment (ticket_id, file_name, file_path, file_size, file_type)
        VALUES (:ticket_id, :file_name, :file_path, :file_size, :file_type)
    ");
    $stmt->execute([
        ':ticket_id' => $ticketId,
        ':file_name' => $file['name'],
        ':file_path' => 'uploads/' . $fileName,
        ':file_size' => round($file['size'] / (1024 * 1024), 1),
        ':file_type' => $file['type']
    ]);

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $pdo->lastInsertId(),
            'file_name' => $file['name'],
            'file_size' => round($file['size'] / (1024 * 1024), 1)
        ]
    ]);
} catch (Exception $e) {
    // 记录错误日志（便于排查问题）
    file_put_contents('upload_errors.log', date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}