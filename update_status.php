<?php
/**
 * 更新工单状态接口
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
require_once __DIR__ . '/common/auth.php';

try {
    // 获取请求数据
    $data = json_decode(file_get_contents('php://input'), true);
    
    // 验证参数
    if (!isset($data['id'], $data['status'], $data['handler_id'])) {
        throw new Exception('工单ID、状态和处理人ID不能为空');
    }
    
    $validStatus = ['pending', 'processing', 'resolved', 'closed'];
    if (!in_array($data['status'], $validStatus)) {
        throw new Exception('无效的工单状态');
    }
    
    // 更新工单
    $pdo = DB::getInstance();
    $stmt = $pdo->prepare("
        UPDATE work_orders 
        SET status = :status, handler_id = :handler_id, updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    
    $stmt->execute([
        ':id' => (int)$data['id'],
        ':status' => $data['status'],
        ':handler_id' => (int)$data['handler_id']
    ]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('更新失败，工单不存在');
    }
    
    // 返回结果
    echo json_encode([
        'success' => true,
        'message' => '工单状态更新成功'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}