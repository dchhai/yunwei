<?php
/**
 * 工单分派接口
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只允许POST请求']);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/common/auth.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // 验证参数
    if (!isset($data['ticket_id'], $data['engineer_id'], $data['operator_id'])) {
        throw new Exception('参数不完整');
    }
    
    $pdo = DB::getInstance();
    $pdo->beginTransaction();
    
    // 更新工单信息
    $stmt = $pdo->prepare("
        UPDATE ticket 
        SET engineer_id = :engineer_id, 
            status = 1,  // 改为处理中状态
            assign_time = NOW(),
            assign_by = :operator_id
        WHERE id = :ticket_id
    ");
    $stmt->execute([
        ':ticket_id' => $data['ticket_id'],
        ':engineer_id' => $data['engineer_id'],
        ':operator_id' => $data['operator_id']
    ]);
    
    // 记录操作日志
    $stmt = $pdo->prepare("
        INSERT INTO ticket_log (ticket_id, operator_id, action, remark, create_time)
        VALUES (:ticket_id, :operator_id, 'assign', :remark, NOW())
    ");
    $stmt->execute([
        ':ticket_id' => $data['ticket_id'],
        ':operator_id' => $data['operator_id'],
        ':remark' => $data['remark'] ?? ''
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '分派成功'
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}