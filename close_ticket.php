<?php
/**
 * 关闭工单接口
 * 运维工程师处理完成后，可以关闭工单
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

// 引入认证工具
require_once __DIR__ . '/common/auth.php';

// 开启session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // 获取用户ID - 兼容session和前端localStorage登录方式
    $userId = 0;
    
    // 尝试从session获取用户ID
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
        $userId = (int)$_SESSION['user_id'];
    } else {
        // 如果session中没有，尝试从请求头获取token或用户信息
        $authHeader = '';
        
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        } else if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    $authHeader = $value;
                    break;
                }
            }
        }
        
        // 处理Authorization头
        if (!empty($authHeader) && strpos($authHeader, 'Bearer ') === 0) {
            $userId = intval(substr($authHeader, 7));
        }
    }
    
    if ($userId <= 0) {
        throw new Exception('请先登录');
    }
    
    // 获取数据库连接
    $pdo = DB::getInstance();
    
    // 获取用户信息
    $userStmt = $pdo->prepare("SELECT id, username, name, role_id FROM user WHERE id = :user_id AND status = 1");
    $userStmt->execute([':user_id' => $userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('用户不存在或已被禁用');
    }
    
    // 获取请求数据
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['ticket_id'])) {
        throw new Exception('工单ID不能为空');
    }
    
    $ticketId = intval($data['ticket_id']);
    $remark = isset($data['remark']) ? trim($data['remark']) : '';
    
    if ($ticketId <= 0) {
        throw new Exception('无效的工单ID');
    }
    
    // 检查工单是否存在
    $ticketStmt = $pdo->prepare("
        SELECT id, status, handler_id, title
        FROM ticket
        WHERE id = :ticket_id
    ");
    $ticketStmt->execute([':ticket_id' => $ticketId]);
    $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        throw new Exception('工单不存在');
    }
    
    // 检查工单状态
    if ($ticket['status'] == 2) {
        throw new Exception('工单已关闭，无需重复操作');
    }
    
    // 获取用户角色
    $roleStmt = $pdo->prepare("
        SELECT r.role_name
        FROM user u
        LEFT JOIN role r ON u.role_id = r.id
        WHERE u.id = :user_id
    ");
    $roleStmt->execute([':user_id' => $user['id']]);
    $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
    
    // 权限检查：只有系统管理员或运维工程师可以关闭工单
    // 运维工程师只能关闭自己处理的工单
    $isAdmin = $role && $role['role_name'] === '系统管理员';
    $isOpsEngineer = $role && $role['role_name'] === '运维工程师';
    
    if (!$isAdmin && !$isOpsEngineer) {
        throw new Exception('您没有权限关闭工单');
    }
    
    // 运维工程师只能关闭自己处理的工单
    if ($isOpsEngineer && $ticket['handler_id'] != $user['id']) {
        throw new Exception('您只能关闭自己处理的工单');
    }
    
    // 计算处理时长（分钟）
    $handleDuration = null;
    if ($ticket['handle_time']) {
        $handleStart = new DateTime($ticket['handle_time']);
        $handleEnd = new DateTime();
        $interval = $handleStart->diff($handleEnd);
        $handleDuration = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
    }
    
    // 更新工单状态为已完结
    $updateStmt = $pdo->prepare("
        UPDATE ticket
        SET status = 2,
            finish_time = NOW(),
            remark = :remark,
            handle_duration = :handle_duration
        WHERE id = :ticket_id
    ");
    $updateStmt->execute([
        ':ticket_id' => $ticketId,
        ':remark' => $remark,
        ':handle_duration' => $handleDuration
    ]);
    
    // 添加关闭记录到回复表
    $replyContent = '工单已关闭';
    if ($remark) {
        $replyContent .= '。处理结果：' . $remark;
    }
    
    $replyStmt = $pdo->prepare("
        INSERT INTO ticket_reply (ticket_id, user_id, content)
        VALUES (:ticket_id, :user_id, :content)
    ");
    $replyStmt->execute([
        ':ticket_id' => $ticketId,
        ':user_id' => $user['id'],
        ':content' => $replyContent
    ]);
    
    // 返回结果
    echo json_encode([
        'success' => true,
        'message' => '工单关闭成功',
        'data' => [
            'ticket_id' => $ticketId,
            'status' => 2,
            'finish_time' => date('Y-m-d H:i:s'),
            'handle_duration' => $handleDuration
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
