<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST");
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../common/auth.php';

try {
    checkPermission('role:create');

    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['role_name'])) {
        throw new Exception("角色名称不能为空");
    }

    // 校验角色名称唯一性
    $pdo = DB::getInstance();
    $stmt = $pdo->prepare("SELECT id FROM role WHERE role_name = :name");
    $stmt->execute([':name' => $data['role_name']]);
    if ($stmt->fetch()) {
        throw new Exception("角色名称已存在");
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

    // 插入角色
    $stmt = $pdo->prepare("    
        INSERT INTO role (role_name, description)
        VALUES (:role_name, :description)
    ");
    $stmt->execute([
        ':role_name' => $data['role_name'],
        ':description' => $data['description'] ?? ''
    ]);

    // 记录日志
    $roleId = $pdo->lastInsertId();
    $pdo->prepare("    
        INSERT INTO operation_log (user_id, operate_type, operate_object, operate_detail)
        VALUES (:operator_id, 'create', 'role:{$roleId}', '新增角色:{$data['role_name']}')
    ")->execute([':operator_id' => $userId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => '角色创建成功']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}