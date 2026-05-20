<?php
require_once 'db.php';

$pdo = DB::getInstance();

// 检查ticket表结构
$stmt = $pdo->query("DESCRIBE ticket");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Ticket table columns:\n";
foreach ($columns as $column) {
    echo "{$column['Field']} - {$column['Type']} - {$column['Null']}\n";
}

// 检查ticket_attachment_blob表是否存在
$checkTableStmt = $pdo->query("SHOW TABLES LIKE 'ticket_attachment_blob'");
$hasBlobTable = $checkTableStmt->rowCount() > 0;
echo "\nHas ticket_attachment_blob table: " . ($hasBlobTable ? "Yes" : "No");

// 检查最近的工单数据
$ticketStmt = $pdo->query("SELECT id, title, priority, problem_type FROM ticket ORDER BY id DESC LIMIT 5");
$tickets = $ticketStmt->fetchAll(PDO::FETCH_ASSOC);

echo "\n\nRecent tickets:\n";
foreach ($tickets as $ticket) {
    echo "ID: {$ticket['id']}, Title: {$ticket['title']}, Priority: {$ticket['priority']}, Problem Type: {$ticket['problem_type']}\n";
}

// 检查附件数据
if ($hasBlobTable) {
    $attachStmt = $pdo->query("SELECT id, ticket_id, file_name, file_size FROM ticket_attachment_blob ORDER BY id DESC LIMIT 5");
    $attachments = $attachStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n\nRecent attachments:\n";
    if (count($attachments) > 0) {
        foreach ($attachments as $attach) {
            echo "ID: {$attach['id']}, Ticket ID: {$attach['ticket_id']}, File: {$attach['file_name']}, Size: {$attach['file_size']} bytes\n";
        }
    } else {
        echo "No attachments found\n";
    }
}