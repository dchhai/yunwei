<?php
/**
 * 工单附件查询接口
 * 根据ticket_id获取关联的所有附件信息
 */
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 只允许GET请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只允许GET请求']);
    exit;
}

require_once __DIR__ . '/db.php';

try {
    // 获取工单ID（从URL参数中获取）
    $ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
    if ($ticketId <= 0) {
        throw new Exception('无效的工单ID');
    }

    // 连接数据库
    $pdo = DB::getInstance();
    if (!$pdo) {
        throw new Exception('数据库连接失败');
    }

    // 检查ticket_attachment_blob表是否存在
    $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'ticket_attachment_blob'");
    $hasBlobTable = $checkTableStmt->rowCount() > 0;
    
    if (!$hasBlobTable) {
        echo json_encode([
            'success' => true,
            'data' => []
        ]);
        exit;
    }

    // 查询该工单的所有附件（包括工单提交时的附件和回复时的附件）
    $stmt = $pdo->prepare("
        SELECT 
            id,
            file_name,
            file_type,
            file_size,
            ticket_id,
            reply_id,
            create_time AS upload_time
        FROM ticket_attachment_blob
        WHERE ticket_id = :ticket_id OR reply_id IN (
            SELECT id FROM ticket_reply WHERE ticket_id = :ticket_id
        )
        ORDER BY create_time DESC
    ");
    $stmt->execute([':ticket_id' => $ticketId]);
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 格式化文件大小（转换为KB）
    foreach ($attachments as &$attach) {
        if (isset($attach['file_size'])) {
            $attach['file_size'] = number_format($attach['file_size'] / 1024, 2);
        }
    }

    // 返回结果
    echo json_encode([
        'success' => true,
        'data' => $attachments
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => '获取附件失败：' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '获取附件失败：' . $e->getMessage()
    ]);
}
