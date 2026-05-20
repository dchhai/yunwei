<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST");
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../common/auth.php';

try {
    checkPermission('role:delete');

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        throw new Exception("角色ID无效");
    }

    // 检查是否有关联用户
    $pdo = DB::getInstance();
    $stmt = $pdo->prepare("SELECT id FROM `user` WHERE role_id = :id");
    $stmt->execute([':id' => $id]);
    if ($stmt->fetch()) {
        throw new Exception("该角色下有关联用户，无法删除");
    }

    $pdo->beginTransaction();

    // 删除角色（级联删除角色-权限关联）
    $stmt = $pdo->prepare("DELETE FROM role WHERE id = :id");
    $stmt->execute([':id' => $id]);

    // 记录日志
    $pdo->prepare("
        INSERT INTO operation_log (user_id, operate_type, operate_object, operate_detail)
        VALUES (:operator_id, 'delete', 'role:{$id}', '删除角色')
    ")->execute([':operator_id' => $_SESSION['user_id']]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => '角色删除成功']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}