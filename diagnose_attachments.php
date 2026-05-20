<?php
header("Content-Type: text/plain; charset=utf-8");

require_once 'db.php';

echo "=== 附件系统诊断 ===\n\n";

$pdo = DB::getInstance();

// 1. 检查ticket_attachment_blob表
echo "1. 检查ticket_attachment_blob表...\n";
$checkTableStmt = $pdo->query("SHOW TABLES LIKE 'ticket_attachment_blob'");
$hasBlobTable = $checkTableStmt->rowCount() > 0;
echo "   表存在: " . ($hasBlobTable ? "是" : "否") . "\n";

if (!$hasBlobTable) {
    echo "\n   错误: ticket_attachment_blob表不存在！\n";
    echo "   需要创建该表才能存储附件。\n";
    exit;
}

// 2. 检查表结构
echo "\n2. 检查表结构...\n";
$columnsStmt = $pdo->query("DESCRIBE ticket_attachment_blob");
$columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $column) {
    echo "   - {$column['Field']}: {$column['Type']}\n";
}

// 3. 检查附件数据
echo "\n3. 检查附件数据...\n";
$attachStmt = $pdo->query("SELECT COUNT(*) as count FROM ticket_attachment_blob");
$count = $attachStmt->fetch(PDO::FETCH_ASSOC);
echo "   总附件数: {$count['count']}\n";

if ($count['count'] > 0) {
    $recentStmt = $pdo->query("SELECT id, ticket_id, file_name, file_size, create_time FROM ticket_attachment_blob ORDER BY id DESC LIMIT 5");
    $attachments = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\n   最近的附件:\n";
    foreach ($attachments as $attach) {
        echo "   - ID: {$attach['id']}, 工单ID: {$attach['ticket_id']}, 文件: {$attach['file_name']}, 大小: {$attach['file_size']}字节\n";
    }
}

// 4. 检查工单数据
echo "\n4. 检查工单数据...\n";
$ticketStmt = $pdo->query("SELECT id, title, create_time FROM ticket ORDER BY id DESC LIMIT 5");
$tickets = $ticketStmt->fetchAll(PDO::FETCH_ASSOC);
echo "   总工单数: " . count($tickets) . "\n";
echo "\n   最近的工单:\n";
foreach ($tickets as $ticket) {
    // 检查每个工单的附件数量
    $attachCountStmt = $pdo->prepare("SELECT COUNT(*) as count FROM ticket_attachment_blob WHERE ticket_id = ?");
    $attachCountStmt->execute([$ticket['id']]);
    $attachCount = $attachCountStmt->fetch(PDO::FETCH_ASSOC);
    echo "   - ID: {$ticket['id']}, 标题: {$ticket['title']}, 附件数: {$attachCount['count']}\n";
}

// 5. 测试get_attachments.php接口
echo "\n5. 测试get_attachments.php接口...\n";
if (count($tickets) > 0) {
    $testTicketId = $tickets[0]['id'];
    echo "   测试工单ID: $testTicketId\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            id,
            file_name,
            file_type,
            file_size,
            ticket_id,
            reply_id,
            create_time AS upload_time
        FROM ticket_attachment_blob
        WHERE ticket_id = :ticket_id OR reply_id IN (
            SELECT id FROM ticket_reply WHERE ticket_id = :ticket_id
        )
        ORDER BY create_time DESC
    ");
    $stmt->execute([':ticket_id' => $testTicketId]);
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   查询结果: " . count($attachments) . " 个附件\n";
    
    // 格式化文件大小
    foreach ($attachments as &$attach) {
        if (isset($attach['file_size'])) {
            $attach['file_size'] = number_format($attach['file_size'] / 1024, 2);
        }
    }
    
    // 模拟JSON响应
    echo "\n   模拟JSON响应:\n";
    $response = [
        'success' => true,
        'data' => $attachments
    ];
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo "   没有工单可供测试\n";
}

echo "\n\n=== 诊断完成 ===\n";