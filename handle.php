<?php
/**
 * 工单处理接口 - 适配ticket表结构
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
    $data = json_decode(file_get_contents('php://input'), true);
    $required = ['ticket_id', 'handler_id', 'status'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            throw new Exception("{$field}不能为空");
        }
    }
    // 验证状态值有效性
    if (!in_array($data['status'], [0, 1, 2, 3])) {
        throw new Exception("无效的状态值（0-3）");
    }

    $pdo = DB::getInstance();
    $pdo->beginTransaction();

    // 获取当前工单信息
    $stmt = $pdo->prepare("SELECT status, handle_time FROM ticket WHERE id = :id");
    $stmt->execute([':id' => $data['ticket_id']]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        throw new Exception('工单不存在');
    }

    // 构建更新字段
    $update = [
        'handler_id' => $data['handler_id'],
        'status' => $data['status']
    ];
    $timeFields = [];
    
    switch ($data['status']) {
        case 1: // 处理中（首次处理）
            if ($ticket['status'] != 1) { // 避免重复更新
                $timeFields['handle_time'] = 'NOW()';
            }
            break;
        case 2: // 已完结
            if (empty($ticket['handle_time'])) {
                throw new Exception('请先标记为处理中');
            }
            $timeFields['finish_time'] = 'NOW()';
            $timeFields['handle_duration'] = "TIMESTAMPDIFF(MINUTE, handle_time, NOW())";
            // 验证处理结果（完结时必填）
            if (empty($data['remark'])) {
                throw new Exception('完结工单需填写处理结果');
            }
            $update['remark'] = $data['remark'];
            break;
        case 3: // 已转派
            $timeFields['handle_time'] = 'NOW()'; // 转派视为开始处理
            break;
    }

    // 生成SQL
    $setClause = [];
    foreach ($update as $k => $v) {
        $setClause[] = "{$k} = :{$k}";
    }
    foreach ($timeFields as $k => $v) {
        $setClause[] = "{$k} = {$v}";
    }

    $stmt = $pdo->prepare("
        UPDATE ticket 
        SET " . implode(', ', $setClause) . " 
        WHERE id = :ticket_id
    ");
    $stmt->execute(array_merge($update, [':ticket_id' => $data['ticket_id']]));

    // 记录操作日志
    $statusTextMap = [0=>'待分派',1=>'处理中',2=>'已完结',3=>'已转派'];
    $pdo->prepare("
        INSERT INTO operation_log (user_id, operate_type, operate_object, operate_detail)
        VALUES (:uid, 'handle', 'ticket:{$data['ticket_id']}', '更新状态为:{$statusTextMap[$data['status']]}')
    ")->execute([':uid' => $data['handler_id']]);

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'message' => '操作成功'
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}