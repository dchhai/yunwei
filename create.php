<?php
/**
 * 工单创建接口 - 修复事务回滚逻辑并增加优先级字段存储
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

// 生成工单编号（YYYYMMDD+6位随机数）
function generateTicketNo() {
    $date = date('Ymd');
    $random = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    return $date . $random;
}

try {
    // 初始化PDO实例
    $pdo = DB::getInstance();
    if (!$pdo) {
        throw new Exception("数据库连接失败");
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    // 验证JSON解析
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("请求数据格式错误（JSON解析失败）");
    }
    
    // 验证必填字段 - 增加priority字段验证
    $required = ['title', 'content', 'creator_id', 'problem_type', 'ops_system_id', 'priority'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            throw new Exception("{$field}不能为空");
        }
    }
    // 验证优先级值是否合法
    if (!in_array($data['priority'], ['1', '2', '3'])) {
        throw new Exception("优先级值无效，必须是1、2或3");
    }
    if (strlen(trim($data['content'])) < 10) {
        throw new Exception("问题描述至少10个字符");
    }

    // 标记事务状态
    $transactionActive = false;
    
    try {
        $pdo->beginTransaction();
        $transactionActive = true; // 事务已开启

        // 生成唯一工单编号
        do {
            $ticketNo = generateTicketNo();
            $stmt = $pdo->prepare("SELECT id FROM ticket WHERE ticket_no = :no");
            $stmt->execute([':no' => $ticketNo]);
        } while ($stmt->fetch());

        // 插入工单记录 - 增加priority字段
        $stmt = $pdo->prepare("
            INSERT INTO ticket (
                ticket_no, title, description, problem_type, 
                ops_system_id, creator_id, status, create_time, priority
            ) VALUES (
                :ticket_no, :title, :description, :problem_type,
                :ops_system_id, :creator_id, 0, NOW(), :priority
            )
        ");
        $stmt->execute([
            ':ticket_no' => $ticketNo,
            ':title' => trim($data['title']),
            ':description' => trim($data['content']),
            ':problem_type' => $data['problem_type'],
            ':ops_system_id' => (int)$data['ops_system_id'],
            ':creator_id' => (int)$data['creator_id'],
            ':priority' => (int)$data['priority'] // 新增优先级参数
        ]);
        $ticketId = $pdo->lastInsertId();

        // 记录操作日志（如果表不存在请注释此行及以下3行）
        $pdo->prepare("
            INSERT INTO operation_log (user_id, operate_type, operate_object, operate_detail)
            VALUES (:uid, 'create', 'ticket:{$ticketId}', '创建工单:{$ticketNo}')
        ")->execute([':uid' => (int)$data['creator_id']]);

        $pdo->commit();
        $transactionActive = false; // 事务已提交

        echo json_encode([
            'success' => true,
            'data' => [
                'ticket_id' => $ticketId,
                'ticket_no' => $ticketNo
            ]
        ]);
    } catch (Exception $e) {
        // 仅在事务活跃时回滚
        if ($transactionActive) {
            $pdo->rollBack();
        }
        throw $e; // 传递错误到外层捕获
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}