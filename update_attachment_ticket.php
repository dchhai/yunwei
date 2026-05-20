<?php
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
    if (!isset($data['ticket_id'], $data['attachment_ids']) || empty($data['attachment_ids'])) {
        throw new Exception('缺少参数');
    }

    $ticketId = (int)$data['ticket_id'];
    $attachmentIds = array_map('intval', $data['attachment_ids']);

    $pdo = DB::getInstance();
    $stmt = $pdo->prepare("
        UPDATE ticket_attachment 
        SET ticket_id = :ticket_id 
        WHERE id IN (" . implode(',', array_fill(0, count($attachmentIds), '?')) . ")
    ");
    $stmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
    
    foreach ($attachmentIds as $index => $id) {
        $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
    }
    
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        throw new Exception('未找到对应的附件记录');
    }

    echo json_encode(['success' => true, 'message' => '附件关联成功']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}