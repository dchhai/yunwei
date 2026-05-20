<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../common/auth.php';

try {
    // 权限列表仅对有权限管理的用户开放
    checkPermission('role:assign_perm');

    $pdo = DB::getInstance();
    $stmt = $pdo->query("SELECT * FROM permission ORDER BY id ASC");
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $permissions
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}