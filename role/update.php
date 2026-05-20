<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST");
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../common/auth.php';

try {
    checkPermission('role:update');

    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['id'])) {
        throw new Exception("角色ID不能为空");
    }
    $id = (int)$data['id'];

    // 校验角色名称唯一性（排除自身）
    if (!empty($data['role_name'])) {
        $pdo = DB::getInstance();
        $stmt = $pdo->prepare("SELECT id FROM role WHERE role_name = :name AND id != :id");
        $stmt->execute([':name' => $data['role_name'], ':id' => $id]);
        if ($stmt->fetch()) {
            throw new Exception("角色名称已存在");
        }
    }

    $pdo->beginTransaction();
    
    // 获取用户ID（兼容多种登录方式）
    $userId = $_SESSION['user_id'] ?? null;
    
    // 检查是否从Authorization头获取
    if ($userId === null && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        if (strpos($authHeader, 'Bearer ') === 0) {
            $userId = substr($authHeader, 7);
        }
    }
    
    // 检查是否从请求参数获取
    if ($userId === null && isset($_GET['user_id'])) {
        $userId = $_GET['user_id'];
    }
    
    // 确保userId有效
    if (empty($userId)) {
        throw new Exception("无法获取用户信息，请重新登录");
    }

    // 更新角色
    $stmt = $pdo->prepare("
        UPDATE role 
        SET role_name = :role_name, description = :description 
        WHERE id = :id
    ");
    $stmt->execute([
        ':role_name' => $data['role_name'],
        ':description' => $data['description'] ?? '',
        ':id' => $id
    ]);

    // 记录日志
    $pdo->prepare("    
        INSERT INTO operation_log (user_id, operate_type, operate_object, operate_detail)
        VALUES (:operator_id, 'update', 'role:{$id}', '编辑角色信息')
    ")->execute([':operator_id' => $userId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => '角色更新成功']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}