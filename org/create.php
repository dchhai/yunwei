<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Methods: POST");
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../common/auth.php';

try {
    checkPermission('org:create');

    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['org_name'])) {
        throw new Exception("组织名称不能为空");
    }

    // 校验组织名称唯一性
    $pdo = DB::getInstance();
    $stmt = $pdo->prepare("SELECT id FROM org WHERE org_name = :name");
    $stmt->execute([':name' => $data['org_name']]);
    if ($stmt->fetch()) {
        throw new Exception("组织名称已存在");
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
    
    // 插入组织
    $stmt = $pdo->prepare("        INSERT INTO org (org_name, org_level, manager, phone, dept, status, create_time)
        VALUES (:org_name, :org_level, :manager, :phone, :dept, :status, NOW())
    ");
    $stmt->execute([
        ':org_name' => $data['org_name'],
        ':org_level' => $data['org_level'] ?? '',
        ':manager' => $data['manager'] ?? '',
        ':phone' => $data['phone'] ?? '',
        ':dept' => $data['dept'] ?? '',
        ':status' => $data['status'] ?? 1
    ]);

    // 记录日志
    $orgId = $pdo->lastInsertId();
    
    $pdo->prepare("    
        INSERT INTO operation_log (user_id, operate_type, operate_object, operate_detail)
        VALUES (:operator_id, 'create', 'org:{$orgId}', '新增组织:{$data['org_name']}')
    ")->execute([':operator_id' => $userId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => '组织创建成功']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}