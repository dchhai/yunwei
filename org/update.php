<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// 先包含必要的文件
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../common/auth.php';

// 启动session
session_start();

try {
    checkPermission('org:update');

    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['id'])) {
        throw new Exception("组织ID不能为空");
    }
    $id = (int)$data['id'];

    // 获取数据库连接
    $pdo = DB::getInstance();
    
    // 校验组织名称唯一性（排除自身）
    if (!empty($data['org_name'])) {
        $stmt = $pdo->prepare("SELECT id FROM org WHERE org_name = :name AND id != :id");
        $stmt->execute([':name' => $data['org_name'], ':id' => $id]);
        if ($stmt->fetch()) {
            throw new Exception("组织名称已存在");
        }
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
    
    // 检查是否从JSON数据中获取
    if ($userId === null && isset($data['user_id'])) {
        $userId = $data['user_id'];
    }
    
    // 确保userId有效（允许空值，后续日志记录时再处理）
    if (empty($userId)) {
        // 不抛出异常，而是使用默认值，避免因用户ID获取失败而阻止操作
        $userId = 0; // 使用0表示未知用户
    }

    // 更新组织 - 只更新提供的字段
    $updateFields = [];
    $params = [];
    
    // 检查哪些字段需要更新
    if (isset($data['status'])) {
        $updateFields[] = 'status = :status';
        $params[':status'] = $data['status'];
    }
    if (isset($data['org_name'])) {
        $updateFields[] = 'org_name = :org_name';
        $params[':org_name'] = $data['org_name'];
    }
    if (isset($data['org_level'])) {
        $updateFields[] = 'org_level = :org_level';
        $params[':org_level'] = $data['org_level'] ?? '';
    }
    if (isset($data['manager'])) {
        $updateFields[] = 'manager = :manager';
        $params[':manager'] = $data['manager'] ?? '';
    }
    if (isset($data['phone'])) {
        $updateFields[] = 'phone = :phone';
        $params[':phone'] = $data['phone'] ?? '';
    }
    if (isset($data['dept'])) {
        $updateFields[] = 'dept = :dept';
        $params[':dept'] = $data['dept'] ?? '';
    }
    
    // 总是更新update_time
    $updateFields[] = 'update_time = NOW()';
    $params[':id'] = $id;
    
    // 构建SQL语句
    $sql = "UPDATE org SET " . implode(', ', $updateFields) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // 记录日志
    $pdo->prepare("    
        INSERT INTO operation_log (user_id, operate_type, operate_object, operate_detail)
        VALUES (:operator_id, 'update', 'org:{$id}', '编辑组织信息')
    ")->execute([':operator_id' => $userId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => '组织更新成功']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}