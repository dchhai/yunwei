<?php
/**
 * 工单列表查询接口（适配最新表结构）
 * 支持分页、条件筛选，关联运维系统表和用户表获取完整字段
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
    echo json_encode(['success' => false, 'message' => '仅支持POST请求']);
    exit;
}

// 引入数据库连接
require_once __DIR__ . '/db.php';

try {
    // 获取请求参数
    $requestData = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('请求数据格式错误');
    }

    // 分页参数处理（默认第1页，每页10条）
    $page = isset($requestData['page']) ? (int)$requestData['page'] : 1;
    $limit = isset($requestData['limit']) ? (int)$requestData['limit'] : 10;
    $offset = ($page - 1) * $limit;

    // 验证分页参数合法性
    if ($page < 1 || $limit < 1 || $limit > 100) {
        throw new Exception('分页参数不合法（页码≥1，条数1-100）');
    }

    // 构建查询条件
    $whereConditions = [];
    $params = [];

    // 1. 创建人筛选（普通用户只能看自己的工单）
    if (!empty($requestData['creator_id'])) {
        $whereConditions[] = 't.creator_id = :creator_id';
        $params[':creator_id'] = (int)$requestData['creator_id'];
    }

    // 2. 状态筛选（0=待分派，1=处理中，2=已完结，3=已转派）
    if (isset($requestData['status']) && $requestData['status'] !== '') {
        $status = (int)$requestData['status'];
        if (in_array($status, [0, 1, 2, 3])) {
            $whereConditions[] = 't.status = :status';
            $params[':status'] = $status;
        } else {
            throw new Exception('状态参数不合法（0=待分派，1=处理中，2=已完结，3=已转派）');
        }
    }

    // 3. 问题类型筛选（1-6的字符串类型）
    if (!empty($requestData['problem_type'])) {
        $problemType = (string)$requestData['problem_type'];
        if (in_array($problemType, ['1', '2', '3', '4', '5', '6'])) {
            $whereConditions[] = 't.problem_type = :problem_type';
            $params[':problem_type'] = $problemType;
        } else {
            throw new Exception('问题类型参数不合法（1-6）');
        }
    }

    // 4. 所属运维系统筛选（通过ops_system_id）
    if (!empty($requestData['ops_system_id'])) {
        $whereConditions[] = 't.ops_system_id = :ops_system_id';
        $params[':ops_system_id'] = (int)$requestData['ops_system_id'];
    }

    // 5. 处理人（工程师）筛选
    if (!empty($requestData['handler_id'])) {
        $whereConditions[] = 't.handler_id = :handler_id';
        $params[':handler_id'] = (int)$requestData['handler_id'];
        // 调试输出
        error_log('添加handler_id过滤条件: ' . $requestData['handler_id']);
    }

    // 6. 关键词搜索（标题/工单编号）
    if (!empty($requestData['keyword'])) {
        $keyword = "%{$requestData['keyword']}%";
        $whereConditions[] = '(t.title LIKE :keyword OR t.ticket_no LIKE :keyword)';
        $params[':keyword'] = $keyword;
    }

    // 组装WHERE子句
    $whereStr = '';
    if (!empty($whereConditions)) {
        $whereStr = 'WHERE ' . implode(' AND ', $whereConditions);
    }
    
    // 调试输出
    error_log('请求参数: ' . print_r($requestData, true));
    error_log('WHERE条件: ' . $whereStr);
    error_log('查询参数: ' . print_r($params, true));

    // 数据库连接
    $pdo = DB::getInstance();

    // 1. 查询工单列表数据（核心：关联运维系统表和用户表）
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.ticket_no AS ticket_id,               -- 工单编号
            t.title,                                -- 工单标题
            t.problem_type,                         -- 问题类型（1-6字符串）
            os.system_name AS system_type,          -- 所属运维系统（来自ops_system表）
            os.pm_name AS manager_name,             -- 项目经理姓名（来自ops_system表）
            h.name AS engineer_name,            -- 运维工程师姓名（处理人，来自user表）
            t.status,                               -- 工单状态（0-3）
            t.create_time AS created_at,            -- 提交时间
            t.handler_id,                           -- 处理人ID（用于权限判断）
            t.ops_system_id                         -- 系统ID（用于扩展查询）
        FROM ticket t
        LEFT JOIN ops_system os ON t.ops_system_id = os.id  -- 关联运维系统表获取系统名称和项目经理
        LEFT JOIN user c ON t.creator_id = c.id             -- 关联创建人（冗余，可用于扩展）
        LEFT JOIN user h ON t.handler_id = h.id             -- 关联处理人（工程师）
        {$whereStr}
        ORDER BY t.create_time DESC
        LIMIT :offset, :limit
    ");

    // 绑定分页参数（需单独绑定，确保整数类型）
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

    // 绑定其他查询参数
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }

    $stmt->execute();
    $ticketList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. 查询总记录数（用于分页计算）
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) AS total 
        FROM ticket t
        LEFT JOIN ops_system os ON t.ops_system_id = os.id
        LEFT JOIN user c ON t.creator_id = c.id
        LEFT JOIN user h ON t.handler_id = h.id
        {$whereStr}
    ");

    // 绑定计数查询的参数
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $countStmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $countStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }

    $countStmt->execute();
    $total = $countStmt->fetchColumn();

    // 构造返回数据
    echo json_encode([
        'success' => true,
        'data' => [
            'list' => $ticketList,
            'pagination' => [
                'total' => (int)$total,       // 总记录数
                'page' => $page,              // 当前页码
                'limit' => $limit,            // 每页条数
                'pages' => (int)ceil($total / $limit)  // 总页数
            ]
        ]
    ]);

} catch (Exception $e) {
    // 记录错误日志
    file_put_contents(__DIR__ . '/list_errors.log', 
        date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . "\n", 
        FILE_APPEND
    );

    // 返回错误信息
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}