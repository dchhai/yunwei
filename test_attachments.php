<?php
require_once 'db.php';

$pdo = DB::getInstance();

// 获取最新的工单ID
$ticketStmt = $pdo->query("SELECT id FROM ticket ORDER BY id DESC LIMIT 1");
$ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    echo "No tickets found\n";
    exit;
}

$ticketId = $ticket['id'];
echo "Testing with ticket ID: $ticketId\n\n";

// 检查ticket_attachment_blob表是否存在
$checkTableStmt = $pdo->query("SHOW TABLES LIKE 'ticket_attachment_blob'");
$hasBlobTable = $checkTableStmt->rowCount() > 0;
echo "Has ticket_attachment_blob table: " . ($hasBlobTable ? "Yes" : "No") . "\n";

if ($hasBlobTable) {
    // 查询该工单的所有附件
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
    $stmt->execute([':ticket_id' => $ticketId]);
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\nAttachments found: " . count($attachments) . "\n";
    foreach ($attachments as $attach) {
        echo "- ID: {$attach['id']}, File: {$attach['file_name']}, Type: {$attach['file_type']}, Size: {$attach['file_size']}\n";
    }
    
    // 模拟JSON响应
    echo "\nSimulated JSON response:\n";
    $response = [
        'success' => true,
        'data' => $attachments
    ];
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo "\nCannot test attachments - table does not exist\n";
}