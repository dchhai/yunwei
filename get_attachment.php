<?php
/**
 * 获取附件文件接口
 * 从数据库中读取附件二进制数据并输出
 */
header("Access-Control-Allow-Origin: *");

// 引入数据库工具
require_once __DIR__ . '/db.php';

try {
    // 获取附件ID
    $attachmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($attachmentId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '附件ID不能为空']);
        exit;
    }
    
    // 获取数据库连接
    $pdo = DB::getInstance();
    
    // 检查ticket_attachment_blob表是否存在
    $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'ticket_attachment_blob'");
    $hasBlobTable = $checkTableStmt->rowCount() > 0;
    
    if (!$hasBlobTable) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '附件表不存在']);
        exit;
    }
    
    // 查询附件信息
    $stmt = $pdo->prepare("
        SELECT file_name, file_type, file_data, file_size
        FROM ticket_attachment_blob
        WHERE id = :id
    ");
    $stmt->execute([':id' => $attachmentId]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attachment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '附件不存在']);
        exit;
    }
    
    // 设置响应头
    $fileName = $attachment['file_name'];
    $fileType = $attachment['file_type'] ?: 'application/octet-stream';
    $fileSize = $attachment['file_size'];
    $fileData = $attachment['file_data'];
    
    // 设置内容类型
    header('Content-Type: ' . $fileType);
    header('Content-Length: ' . $fileSize);
    header('Content-Disposition: inline; filename="' . $fileName . '"');
    header('Cache-Control: public, max-age=86400');
    
    // 输出文件内容
    echo $fileData;
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
