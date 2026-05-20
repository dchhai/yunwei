<?php
header("Content-Type: application/json; charset=utf-8");
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../common/auth.php';

try {
    // 校验权限
    checkPermission('user:view');

    $pdo = DB::getInstance();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    // 单条查询或列表查询
    if ($id > 0) {
        $stmt = $pdo->prepare("
            SELECT u.*, o.org_name, r.role_name 
            FROM `user` u
            LEFT JOIN org o ON u.org_id = o.id
            LEFT JOIN role r ON u.role_id = r.id
            WHERE u.id = :id
        ");
        $stmt->execute([':id' => $id]);
    } else {
        // 分页参数
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        $stmt = $pdo->prepare("
            SELECT u.*, o.org_name, r.role_name 
            FROM `user` u
            LEFT JOIN org o ON u.org_id = o.id
            LEFT JOIN role r ON u.role_id = r.id
            LIMIT :offset, :limit
        ");
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    }

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = $id > 0 ? count($users) : $pdo->query("SELECT COUNT(*) FROM `user`")->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => $users,
        'total' => $total
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}