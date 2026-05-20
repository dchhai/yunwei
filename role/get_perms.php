<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST");
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../common/auth.php';

try {
    checkPermission('role:view');

    $roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;
    if ($roleId <= 0) {
        throw new Exception("角色ID无效");
    }

    $pdo = DB::getInstance();
    $stmt = $pdo->prepare("
        SELECT perm_id FROM role_permission WHERE role_id = :role_id
    ");
    $stmt->execute([':role_id' => $roleId]);
    $perms = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    echo json_encode([
        'success' => true,
        'data' => $perms
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}