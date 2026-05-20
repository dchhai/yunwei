<?php
/**
 * 工单详情接口 - 包含所有要求返回的字段
 */
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只允许GET请求']);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/common/auth.php';

try {
    $ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($ticketId <= 0) {
        throw new Exception('无效的工单ID');
    }

    $pdo = DB::getInstance();
    
    // 首先检查ticket表是否有contact_name和contact_phone字段
    $checkStmt = $pdo->query("SHOW COLUMNS FROM ticket LIKE 'contact_name'");
    $hasContactName = $checkStmt->rowCount() > 0;
    
    $checkStmt = $pdo->query("SHOW COLUMNS FROM ticket LIKE 'contact_phone'");
    $hasContactPhone = $checkStmt->rowCount() > 0;
    
    // 构建SQL查询，根据字段是否存在动态添加
    $contactNameField = $hasContactName ? 't.contact_name' : "'' AS contact_name";
    $contactPhoneField = $hasContactPhone ? 't.contact_phone' : "'' AS contact_phone";
    
    // 关联查询：工单表 + 运维系统表 + 工程师用户表（获取专属运维工程师姓名）
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.title,                  -- 工单标题
            t.status,                 -- 工单状态
            t.problem_type,           -- 问题类型
            t.priority,               -- 优先级（假设ticket表存在该字段）
            t.description,            -- 问题描述
            t.create_time,            -- 工单提交时间
            {$contactNameField},      -- 联系人姓名
            {$contactPhoneField},     -- 联系电话
            os.system_name,           -- 所属运维系统
            os.pm_name,               -- 项目经理姓名
            os.maintain_start,        -- 运维开始时间
            os.maintain_end,          -- 运维结束时间
            COALESCE(u_engineer.name, u_engineer.username) AS engineer_name,  -- 专属运维工程师姓名（优先显示姓名）
            COALESCE(u_creator.name, u_creator.username) AS creator_name,    -- 创建人姓名（优先显示姓名）
            COALESCE(u_handler.name, u_handler.username) AS handler_name     -- 处理人姓名（优先显示姓名）
        FROM ticket t
        LEFT JOIN ops_system os ON t.ops_system_id = os.id
        LEFT JOIN user u_engineer ON os.engineer_id = u_engineer.id  -- 关联专属运维工程师
        LEFT JOIN user u_creator ON t.creator_id = u_creator.id
        LEFT JOIN user u_handler ON t.handler_id = u_handler.id
        WHERE t.id = :id
    ");
    $stmt->execute([':id' => $ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        throw new Exception('工单不存在');
    }

    // 处理运维期时间（开始-结束）
    $maintainStart = $ticket['maintain_start'] ? date('Y-m-d', strtotime($ticket['maintain_start'])) : '无';
    $maintainEnd = $ticket['maintain_end'] ? date('Y-m-d', strtotime($ticket['maintain_end'])) : '无';
    $ticket['maintain_period'] = "{$maintainStart} - {$maintainEnd}";  // 运维期时间

    // 状态文本映射
    $statusMap = [0 => '待分派', 1 => '处理中', 2 => '已完结', 3 => '已转派'];
    $ticket['status_text'] = $statusMap[$ticket['status']] ?? '未知状态';

    // 优先级文本映射
    $priorityMap = [1 => '低', 2 => '中', 3 => '高'];
    $ticket['priority_text'] = $priorityMap[$ticket['priority']] ?? '未知';

    // 问题类型文本映射
    $problemTypeMap = [
        1 => '系统故障', 2 => '网络问题', 3 => '软件安装',
        4 => '硬件维护', 5 => '账号权限', 6 => '其他请求'
    ];
    $ticket['problem_type_text'] = $problemTypeMap[$ticket['problem_type']] ?? "未知({$ticket['problem_type']})";

    echo json_encode([
        'success' => true,
        'data' => $ticket
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
