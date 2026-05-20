<?php
/**
 * 添加工单回复接口
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

// 定义工单回复表结构（如果不存在）
try {
    $pdo = DB::getInstance();
    
    // 检查工单回复表是否存在
    $checkTable = $pdo->query("SHOW TABLES LIKE 'ticket_reply'");
    if (!$checkTable->fetch()) {
        // 创建工单回复表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `ticket_reply` (
                `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '回复ID（主键）',
                `ticket_id` int(11) NOT NULL COMMENT '工单ID（外键）',
                `user_id` int(11) NOT NULL COMMENT '回复人ID（外键）',
                `content` text NOT NULL COMMENT '回复内容',
                `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '回复时间',
                PRIMARY KEY (`id`),
                KEY `idx_ticket` (`ticket_id`),
                KEY `idx_user` (`user_id`),
                CONSTRAINT `fk_reply_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `ticket` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_reply_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单回复表（关联工单表和用户表，级联删除）'
        ");
    }
    
    // 检查附件表是否存在
    $checkAttachmentTable = $pdo->query("SHOW TABLES LIKE 'ticket_attachment'");
    if (!$checkAttachmentTable->fetch()) {
        // 创建附件表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `ticket_attachment` (
                `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '附件ID（主键）',
                `ticket_id` int(11) DEFAULT NULL COMMENT '工单ID（外键，用于工单创建时的附件）',
                `reply_id` int(11) DEFAULT NULL COMMENT '回复ID（外键，用于回复时的附件）',
                `file_name` varchar(100) NOT NULL COMMENT '文件名',
                `file_path` varchar(255) NOT NULL COMMENT '文件存储路径',
                `file_size` decimal(10,2) NOT NULL COMMENT '文件大小（KB）',
                `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',
                PRIMARY KEY (`id`),
                KEY `idx_ticket` (`ticket_id`),
                KEY `idx_reply` (`reply_id`),
                CONSTRAINT `fk_attachment_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `ticket` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_attachment_reply` FOREIGN KEY (`reply_id`) REFERENCES `ticket_reply` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单附件表（关联工单表和回复表，级联删除）'
        ");
    }
} catch (Exception $e) {
    // 表创建失败不影响后续操作
}

try {
    // 获取请求数据
    if (isset($_POST['ticket_id']) && isset($_POST['user_id']) && isset($_POST['content'])) {
        // 从FormData获取数据
        $data = [
            'ticket_id' => $_POST['ticket_id'],
            'user_id' => $_POST['user_id'],
            'content' => $_POST['content']
        ];
    } else {
        // 尝试从JSON获取数据（兼容旧格式）
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['ticket_id'], $data['user_id'], $data['content'])) {
            throw new Exception('工单ID、用户ID和回复内容不能为空');
        }
    }
    
    // 验证参数
    if (!isset($data['ticket_id'], $data['user_id'], $data['content'])) {
        throw new Exception('工单ID、用户ID和回复内容不能为空');
    }
    
    // 开启事务
    $pdo = DB::getInstance();
    $pdo->beginTransaction();
    
    // 添加回复
    $stmt = $pdo->prepare("
        INSERT INTO ticket_reply (ticket_id, user_id, content)
        VALUES (:ticket_id, :user_id, :content)
    ");
    
    $stmt->execute([
        ':ticket_id' => (int)$data['ticket_id'],
        ':user_id' => (int)$data['user_id'],
        ':content' => $data['content']
    ]);
    
    $replyId = $pdo->lastInsertId();
    
    // 如果是处理人回复，自动更新工单状态为处理中
    $userStmt = $pdo->prepare("
        SELECT role_id FROM user WHERE id = :user_id
    ");
    $userStmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT);
    $userStmt->execute();
    $user = $userStmt->fetch();
    
    if ($user) {
        // 获取角色名称
        $roleStmt = $pdo->prepare("SELECT role_name FROM role WHERE id = :role_id");
        $roleStmt->bindParam(':role_id', $user['role_id'], PDO::PARAM_INT);
        $roleStmt->execute();
        $role = $roleStmt->fetch();
        
        // 如果是运维工程师或系统管理员回复，更新工单状态为处理中
        if ($role && ($role['role_name'] === '运维工程师' || $role['role_name'] === '系统管理员')) {
            $orderStmt = $pdo->prepare("
                UPDATE ticket 
                SET status = 1
                WHERE id = :ticket_id
            ");
            $orderStmt->bindParam(':ticket_id', $data['ticket_id'], PDO::PARAM_INT);
            $orderStmt->execute();
        }
    }
    
    // 处理附件上传（如果有）- 存入数据库
    $attachments = [];
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
                        INSERT INTO ticket_attachment_blob (reply_id, file_name, file_type, file_size, file_data)
                        VALUES (:reply_id, :file_name, :file_type, :file_size, :file_data)
                    ");
                    $attachStmt->execute([
                        ':reply_id' => $replyId,
                        ':file_name' => $name,
                        ':file_type' => $fileType,
                        ':file_size' => $fileSize,
                        ':file_data' => $fileData
                    ]);
                    
                    $attachments[] = [
                        'id' => $pdo->lastInsertId(),
                        'name' => $name,
                        'type' => $fileType,
                        'size' => $fileSize
                    ];
                }
            }
        }
    }
    
    $pdo->commit();
    
    // 返回结果
    echo json_encode([
        'success' => true,
        'message' => '回复添加成功',
        'data' => [
            'reply_id' => $replyId,
            'attachments' => $attachments
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}