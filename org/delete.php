<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// 先包含必要的文件
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../common/auth.php';

// 启动session
session_start();

try {
    checkPermission('org:delete');

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        throw new Exception("组织ID无效");
    }

    // 检查是否有关联用户
    $pdo = DB::getInstance();
    $stmt = $pdo->prepare("SELECT id FROM `user` WHERE org_id = :id");
    $stmt->execute([':id' => $id]);
    if ($stmt->fetch()) {
        throw new Exception("该组织下有关联用户，无法删除");
    }

    $pdo->beginTransaction();
    
    // 获取用户ID（兼容多种登录方式）
    $userId = $_SESSION['user_id'] ?? null;
    
    // 检查是否从Authorization头获取
    if ($userId === null) {
        // 尝试从$_SERVER获取
        if (isset($_SERVER['HTTP_AUTHORIZATION']) && strpos($_SERVER['HTTP_AUTHORIZATION'], 'Bearer ') === 0) {
            $userId = substr($_SERVER['HTTP_AUTHORIZATION'], 7);
        }
        // 尝试从getallheaders()获取（兼容不同服务器环境）
        else if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization' && strpos($value, 'Bearer ') === 0) {
                    $userId = substr($value, 7);
                    break;
                }
            }
        }
    }
    
    // 检查是否从请求参数获取
    if ($userId === null && isset($_POST['user_id'])) {
        $userId = $_POST['user_id'];
    }
    
    // 确保userId有效（允许空值，后续日志记录时再处理）
    if (empty($userId)) {
        // 不抛出异常，而是使用默认值，避免因用户ID获取失败而阻止操作
        $userId = 0; // 使用0表示未知用户
    }

    // 删除组织
    $stmt = $pdo->prepare("DELETE FROM org WHERE id = :id");
    $stmt->execute([':id' => $id]);

    // 记录日志
    $pdo->prepare("    INSERT INTO operation_log (user_id, operate_type, operate_object, operate_detail)
        VALUES (:operator_id, 'delete', 'org:{$id}', '删除组织')
    ")->execute([':operator_id' => $userId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => '组织删除成功']);
} catch (Exception $e) {
    // 确保只有在事务处于活动状态时才回滚
    if (isset($pdo)) {
        try {
            $pdo->rollBack();
        } catch (Exception $rollBackEx) {
            // 忽略rollBack失败的异常
        }
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}