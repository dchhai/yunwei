<?php
header("Content-Type: application/json; charset=utf-8");
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../common/auth.php';

try {
    checkPermission('org:view');

    $pdo = DB::getInstance();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM org WHERE id = :id");
        $stmt->execute([':id' => $id]);
    } else {
        $stmt = $pdo->query("SELECT * FROM org ORDER BY id DESC");
    }

    $orgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode([
        'success' => true,
        'data' => $orgs
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}