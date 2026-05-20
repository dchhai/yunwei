<?php
header("Content-Type: application/json; charset=utf-8");    
header("Access-Control-Allow-Methods: POST");
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../common/auth.php';

try {
    checkPermission('user:status');

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;
    if ($id <= 0) {
        throw new Exception("用户ID无效");
    }

    // 切换状态（0→1，1→0）
    $newStatus = $status == 1 ? 0 : 1;
    $pdo = DB::getInstance();
    $stmt = $pdo->prepare("UPDATE `user` SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $newStatus, ':id' => $id]);
    
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

    // 记录日志
    $action = $newStatus == 1 ? '启用' : '禁用';
    $pdo->prepare("    
        INSERT INTO operation_log (user_id, operate_type, operate_object, operate_detail)
        VALUES (:operator_id, 'status', 'user:{$id}', '{$action}用户')
    ")->execute([':operator_id' => $userId]);

    echo json_encode(['success' => true, 'message' => "用户已{$action}"]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}