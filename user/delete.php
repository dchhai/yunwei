<?php
header("Content-Type: application/json; charset=utf-8");
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../common/auth.php';

try {
    checkPermission('user:delete');

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        throw new Exception("用户ID无效");
    }

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

    // 删除用户
    $stmt = $pdo->prepare("DELETE FROM `user` WHERE id = :id");
    $stmt->execute([':id' => $id]);

    // 记录日志
    $pdo->prepare("    
        INSERT INTO operation_log (user_id, operate_type, operate_object, operate_detail)
        VALUES (:operator_id, 'delete', 'user:{$id}', '删除用户')
    ")->execute([':operator_id' => $userId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => '用户删除成功']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}