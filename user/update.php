<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST");
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../common/auth.php';

try {
    checkPermission('user:update');

    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['id'])) {
        throw new Exception("用户ID不能为空");
    }
    $id = (int)$data['id'];

    $pdo = DB::getInstance();
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

    // 构建更新字段（密码可选更新）
    $updateFields = [
        'username' => $data['username'],
        'org_id' => $data['org_id'],
        'role_id' => $data['role_id'],
        'phone' => $data['phone'] ?? '',
        'zjd_code' => $data['zjd_code'] ?? ''
    ];
    $sql = "UPDATE `user` SET " . implode(', ', array_map(function($k) { return "{$k} = :{$k}"; }, array_keys($updateFields)));
    
    // 如传入密码则更新
    if (!empty($data['password'])) {
        $updateFields['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        $sql .= ", password = :password";
    }
    $sql .= " WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($updateFields, [':id' => $id]));

    // 记录日志
    $pdo->prepare("    
        INSERT INTO operation_log (user_id, operate_type, operate_object, operate_detail)
        VALUES (:operator_id, 'update', 'user:{$id}', '编辑用户信息')
    ")->execute([':operator_id' => $userId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => '用户更新成功']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}