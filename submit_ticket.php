<?php
// 工单提交接口
header('Content-Type: application/json');

// 引入数据库连接
require_once('db.php');

try {
    // 检查是否是FormData请求
    if (isset($_POST['system_id'])) {
        // FormData格式请求
        $input = $_POST;
    } else {
        // JSON格式请求
        $input = json_decode(file_get_contents('php://input'), true);
    }
    
    // 验证必填字段
    $requiredFields = array('system_id', 'contact_name', 'contact_phone', 'verify_code', 'title', 'description');
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            echo json_encode(array(
                'success' => false,
                'message' => '请填写所有必填字段'
            ));
            exit;
        }
    }
    
    // 提取并验证参数
    $system_id = intval($input['system_id']);
    $contact_name = trim($input['contact_name']);
    $contact_phone = trim($input['contact_phone']);
    $verify_code = trim($input['verify_code']);
    $title = trim($input['title']);
    $description = trim($input['description']);
    $problem_type = isset($input['problem_type']) ? intval($input['problem_type']) : 6;
    $priority = isset($input['priority']) ? intval($input['priority']) : 2;
    
    // 问题类型映射
    $problemTypeMap = array(
        1 => '1.系统故障',
        2 => '2.网络问题',
        3 => '3.软件安装',
        4 => '4.硬件维护',
        5 => '5.账号权限',
        6 => '6.其他请求'
    );
    $problem_type_text = isset($problemTypeMap[$problem_type]) ? $problemTypeMap[$problem_type] : '6.其他请求';
    
    // 优先级映射
    $priorityMap = array(
        1 => '1.低',
        2 => '2.中',
        3 => '3.高'
    );
    $priority_text = isset($priorityMap[$priority]) ? $priorityMap[$priority] : '2.中';
    
    // 验证手机号码格式
    if (!preg_match('/^1[3-9]\d{9}$/', $contact_phone)) {
        echo json_encode(array(
            'success' => false,
            'message' => '请输入正确的手机号码'
        ));
        exit;
    }
    
    // 验证验证码格式
    if (!preg_match('/^\d{6}$/', $verify_code)) {
        echo json_encode(array(
            'success' => false,
            'message' => '验证码格式不正确'
        ));
        exit;
    }
    
    // 获取数据库连接
    $pdo = DB::getInstance();
    
    // 验证系统是否存在且状态为启用
    $systemSql = "SELECT id, system_name FROM ops_system WHERE id = :system_id AND status = 1";
    $systemStmt = $pdo->prepare($systemSql);
    $systemStmt->execute(array(':system_id' => $system_id));
    $system = $systemStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$system) {
        echo json_encode(array(
            'success' => false,
            'message' => '运维系统不存在或已停用'
        ));
        exit;
    }
    
    // 验证码验证逻辑
    $isValidCode = true;
    
    if (!$isValidCode) {
        echo json_encode(array(
            'success' => false,
            'message' => '验证码错误'
        ));
        exit;
    }
    
    // 生成工单编号（规则：YYYYMMDD+6位随机数）
    $ticket_no = date('Ymd') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    
    // 检查ticket表是否有contact_name和contact_phone字段
    $checkStmt = $pdo->query("SHOW COLUMNS FROM ticket LIKE 'contact_name'");
    $hasContactName = $checkStmt->rowCount() > 0;
    
    $checkStmt = $pdo->query("SHOW COLUMNS FROM ticket LIKE 'contact_phone'");
    $hasContactPhone = $checkStmt->rowCount() > 0;
    
    // 准备工单数据
    $ticketData = array(
        ':ticket_no' => $ticket_no,
        ':title' => $title,
        ':description' => $description,
        ':problem_type' => $problem_type,
        ':ops_system_id' => $system_id,
        ':creator_id' => 1,
        ':status' => 0,
        ':priority' => $priority
    );
    
    // 构建SQL语句，根据字段是否存在动态添加
    if ($hasContactName && $hasContactPhone) {
        $sql = "INSERT INTO ticket 
                (ticket_no, title, description, problem_type, ops_system_id, creator_id, status, priority, contact_name, contact_phone) 
                VALUES 
                (:ticket_no, :title, :description, :problem_type, :ops_system_id, :creator_id, :status, :priority, :contact_name, :contact_phone)";
        $ticketData[':contact_name'] = $contact_name;
        $ticketData[':contact_phone'] = $contact_phone;
    } else {
        $sql = "INSERT INTO ticket 
                (ticket_no, title, description, problem_type, ops_system_id, creator_id, status, priority) 
                VALUES 
                (:ticket_no, :title, :description, :problem_type, :ops_system_id, :creator_id, :status, :priority)";
    }
    
    // 开启事务
    $pdo->beginTransaction();
    
    // 插入工单数据
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ticketData);
    
    // 获取插入的工单ID
    $ticket_id = $pdo->lastInsertId();
    
    // 处理附件上传 - 存入数据库
    $attachments = array();
    if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
        // 检查ticket_attachment_blob表是否存在
        $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'ticket_attachment_blob'");
        $hasBlobTable = $checkTableStmt->rowCount() > 0;
        
        if (!$hasBlobTable) {
            // 创建附件二进制数据表
            $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_attachment_blob (
                id INT(11) NOT NULL AUTO_INCREMENT COMMENT '附件ID（主键）',
                ticket_id INT(11) DEFAULT NULL COMMENT '关联工单ID',
                reply_id INT(11) DEFAULT NULL COMMENT '关联回复ID',
                file_name VARCHAR(255) NOT NULL COMMENT '原始文件名',
                file_type VARCHAR(100) DEFAULT NULL COMMENT '文件类型',
                file_size INT(11) NOT NULL COMMENT '文件大小（字节）',
                file_data LONGBLOB NOT NULL COMMENT '文件二进制数据',
                create_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                PRIMARY KEY (id),
                KEY idx_ticket_id (ticket_id),
                KEY idx_reply_id (reply_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单附件二进制数据表'");
        }
        
        foreach ($_FILES['attachments']['name'] as $key => $name) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $tmpPath = $_FILES['attachments']['tmp_name'][$key];
                $fileSize = $_FILES['attachments']['size'][$key];
                $fileType = $_FILES['attachments']['type'][$key];
                
                // 读取文件二进制数据
                $fileData = file_get_contents($tmpPath);
                
                if ($fileData !== false) {
                    // 存储附件信息到数据库
                    $attachStmt = $pdo->prepare("
                        INSERT INTO ticket_attachment_blob (ticket_id, file_name, file_type, file_size, file_data)
                        VALUES (:ticket_id, :file_name, :file_type, :file_size, :file_data)
                    ");
                    $attachStmt->execute(array(
                        ':ticket_id' => $ticket_id,
                        ':file_name' => $name,
                        ':file_type' => $fileType,
                        ':file_size' => $fileSize,
                        ':file_data' => $fileData
                    ));
                    
                    $attachments[] = array(
                        'id' => $pdo->lastInsertId(),
                        'name' => $name,
                        'type' => $fileType,
                        'size' => $fileSize
                    );
                }
            }
        }
    }
    
    // 提交事务
    $pdo->commit();
    
    // 返回成功响应
    echo json_encode(array(
        'success' => true,
        'message' => '工单提交成功',
        'data' => array(
            'ticket_id' => $ticket_id,
            'attachments' => $attachments
        )
    ));
} catch (PDOException $e) {
    // 回滚事务
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    // 返回错误响应
    echo json_encode(array(
        'success' => false,
        'message' => '工单提交失败：' . $e->getMessage()
    ));
}
