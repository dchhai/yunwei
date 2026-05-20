<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST");
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../common/auth.php';

try {
    // 校验权限
    checkPermission('user:create');

    $data = json_decode(file_get_contents('php://input'), true);
    $required = ['username', 'password', 'org_id', 'role_id'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("{$field}不能为空");
        }
    }

    // 校验用户名唯一性
    $pdo = DB::getInstance();
    $stmt = $pdo->prepare("SELECT id FROM `user` WHERE username = :username");
    $stmt->execute([':username' => $data['username']]);
    if ($stmt->fetch()) {
        throw new Exception("用户名已存在");
    }

    // 密码加密（bcrypt）
    $encryptedPwd = password_hash($data['password'], PASSWORD_BCRYPT);

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

    // 插入用户
    $stmt = $pdo->prepare("    
        INSERT INTO `user` (username, password, org_id, role_id, phone, zjd_code)
        VALUES (:username, :password, :org_id, :role_id, :phone, :zjd_code)
    ");
    $stmt->execute([
        ':username' => $data['username'],
        ':password' => $encryptedPwd,
        ':org_id' => $data['org_id'],
        ':role_id' => $data['role_id'],
        ':phone' => $data['phone'] ?? '',
        ':zjd_code' => $data['zjd_code'] ?? ''
    ]);

    // 记录操作日志
    $newUserId = $pdo->lastInsertId();
    $pdo->prepare("    
        INSERT INTO operation_log (user_id, operate_type, operate_object, operate_detail)
        VALUES (:operator_id, 'create', 'user:{$newUserId}', '新增用户:{$data['username']}')
    ")->execute([':operator_id' => $userId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => '用户创建成功']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}