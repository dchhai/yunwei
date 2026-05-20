<?php
/**
 * 工单进展查询接口
 * 根据手机号查询该手机号上报的所有工单及其处理进展
 */
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => '只允许POST请求'
    ]);
    exit;
}

// 引入数据库工具
require_once __DIR__ . '/db.php';

try {
    // 获取请求数据
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['phone']) || !isset($input['verify_code'])) {
        throw new Exception('手机号和验证码不能为空');
    }
    
    $phone = trim($input['phone']);
    $verifyCode = trim($input['verify_code']);
    
    // 验证手机号格式
    if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
        throw new Exception('请输入正确的手机号码');
    }
    
    // 验证验证码格式
    if (!preg_match('/^\d{6}$/', $verifyCode)) {
        throw new Exception('请输入6位数字验证码');
    }
    
    // 获取数据库连接
    $pdo = DB::getInstance();
    
    // 检查ticket表是否有contact_phone字段
    $checkStmt = $pdo->query("SHOW COLUMNS FROM ticket LIKE 'contact_phone'");
    $hasContactPhone = $checkStmt->rowCount() > 0;
    
    if (!$hasContactPhone) {
        throw new Exception('系统暂不支持手机号查询功能');
    }
    
    // 查询该手机号上报的所有工单
    $ticketStmt = $pdo->prepare("
        SELECT 
            t.id,
            t.ticket_no,
            t.title,
            t.description,
            t.status,
            t.create_time,
            t.handle_time,
            t.finish_time,
            t.remark,
            u.name as handler_name
        FROM ticket t
        LEFT JOIN user u ON t.handler_id = u.id
        WHERE t.contact_phone = :phone
        ORDER BY t.create_time DESC
    ");
    $ticketStmt->execute([':phone' => $phone]);
    $tickets = $ticketStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tickets)) {
        echo json_encode([
            'success' => true,
            'message' => '未找到相关工单',
            'data' => [
                'tickets' => [],
                'replies' => [],
                'attachments' => []
            ]
        ]);
        exit;
    }
    
    // 获取所有工单ID
    $ticketIds = array_column($tickets, 'id');
    $ticketIdPlaceholders = implode(',', array_fill(0, count($ticketIds), '?'));
    
    // 查询这些工单的所有回复
    $replyStmt = $pdo->prepare("
        SELECT 
            tr.id,
            tr.ticket_id,
            tr.content,
            tr.create_time,
            u.name as user_name
        FROM ticket_reply tr
        LEFT JOIN user u ON tr.user_id = u.id
        WHERE tr.ticket_id IN ($ticketIdPlaceholders)
        ORDER BY tr.create_time ASC
    ");
    $replyStmt->execute($ticketIds);
    $replies = $replyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取所有回复ID
    $replyIds = array_column($replies, 'id');
    $attachments = [];
    
    if (!empty($replyIds)) {
        $replyIdPlaceholders = implode(',', array_fill(0, count($replyIds), '?'));
        
        // 检查ticket_attachment_blob表是否存在
        $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'ticket_attachment_blob'");
        $hasBlobTable = $checkTableStmt->rowCount() > 0;
        
        if ($hasBlobTable) {
            // 查询回复的附件
            $attachStmt = $pdo->prepare("
                SELECT 
                    id,
                    reply_id,
                    file_name,
                    file_type,
                    file_size
                FROM ticket_attachment_blob
                WHERE reply_id IN ($replyIdPlaceholders)
            ");
            $attachStmt->execute($replyIds);
            $attachments = $attachStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // 返回结果
    echo json_encode([
        'success' => true,
        'message' => '查询成功',
        'data' => [
            'tickets' => $tickets,
            'replies' => $replies,
            'attachments' => $attachments
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
