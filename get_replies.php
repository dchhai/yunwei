<?php
/**
 * 获取工单回复记录接口
 * 支持GET方法，通过ticket_id查询
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

// 只允许GET方法
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

    // 查询该工单的所有回复记录（按时间升序排列）
    $stmt = $pdo->prepare("
        SELECT 
            r.id,
            r.content,
            r.create_time,
            u.name AS user_name
        FROM ticket_reply r
        LEFT JOIN user u ON r.user_id = u.id
        WHERE r.ticket_id = :ticket_id
        ORDER BY r.create_time ASC
    ");
    $stmt->execute([':ticket_id' => $ticketId]);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 检查ticket_attachment_blob表是否存在
    $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'ticket_attachment_blob'");
    $hasBlobTable = $checkTableStmt->rowCount() > 0;
    
    if ($hasBlobTable) {
        // 查询每个回复的附件
        foreach ($replies as &$reply) {
            $attachStmt = $pdo->prepare("
                SELECT 
                    id, file_name, file_type, file_size, create_time
                FROM ticket_attachment_blob
                WHERE reply_id = :reply_id
                ORDER BY create_time ASC
            ");
            $attachStmt->execute([':reply_id' => $reply['id']]);
            $attachments = $attachStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 格式化文件大小
            foreach ($attachments as &$attach) {
                if (isset($attach['file_size'])) {
                    $attach['file_size'] = number_format($attach['file_size'] / 1024, 2);
                }
            }
            
            $reply['attachments'] = $attachments;
        }
    } else {
        // 表不存在，返回空附件数组
        foreach ($replies as &$reply) {
            $reply['attachments'] = [];
        }
    }
    
    // 返回结果
    echo json_encode([
        'success' => true,
        'data' => $replies
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => '加载回复记录失败：' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '加载回复记录失败：' . $e->getMessage()
    ]);
}
