<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST");
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../common/auth.php';

try {
    checkPermission('role:assign_perm');

    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['role_id']) || !is_array($data['perm_ids'])) {
        throw new Exception("角色ID和权限ID不能为空");
    }
    $roleId = (int)$data['role_id'];
    $permIds = array_map('intval', $data['perm_ids']);

    $pdo = DB::getInstance();
    $pdo->beginTransaction();

    // 先删除旧权限关联
    $stmt = $pdo->prepare("DELETE FROM role_permission WHERE role_id = :role_id");
    $stmt->execute([':role_id' => $roleId]);

    // 插入新权限关联
    if (!empty($permIds)) {
        $values = [];
        foreach ($permIds as $permId) {
            $values[] = "({$roleId}, {$permId})";
        }
        $stmt = $pdo->prepare("
            INSERT INTO role_permission (role_id, perm_id)
            VALUES " . implode(',', $values)
        );
        $stmt->execute();
    }

    // 记录日志
    $pdo->prepare("
        INSERT INTO operation_log (user_id, operate_type, operate_object, operate_detail)
        VALUES (:operator_id, 'assign_perm', 'role:{$roleId}', '更新角色权限')
    ")->execute([':operator_id' => $_SESSION['user_id']]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => '权限分配成功']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}