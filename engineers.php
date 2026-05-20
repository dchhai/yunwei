<?php
/**
 * 获取工程师列表接口（增强版）
 */
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 尝试连接数据库
$engineers = [];
$error = null;
$debugInfo = [];

// 根据用户提供的信息，role_id=2的用户即为工程师
try {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/common/auth.php';
    $pdo = DB::getInstance();
    
    // 先查询角色表，获取角色名称为"运维工程师"的role_id
    try {
        $stmt = $pdo->prepare("SELECT id FROM role WHERE role_name = ?");
        $stmt->execute(["运维工程师"]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($role) {
            $engineerRoleId = $role['id'];
            $debugInfo[] = "成功查询到运维工程师角色，role_id: " . $engineerRoleId;
            
            // 使用查询到的role_id查询工程师用户
            $stmt = $pdo->prepare("SELECT id, name FROM user WHERE role_id = ? AND status = 1 ORDER BY name ASC");
            $stmt->execute([$engineerRoleId]);
            $engineers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($engineers)) {
                $debugInfo[] = "成功查询到运维工程师用户列表";
            } else {
                $debugInfo[] = "未找到运维工程师用户";
                $error = "未找到工程师数据";
            }
        } else {
            $debugInfo[] = "未找到角色名称为'运维工程师'的角色";
            $error = "未找到工程师角色";
        }
    } catch (Exception $e) {
        $debugInfo[] = "查询工程师数据失败: " . $e->getMessage();
        $error = "查询工程师数据失败";
    }
    
    // 确保返回的数据格式正确
    foreach ($engineers as &$engineer) {
        // 为了兼容前端显示，添加realname字段，使用username值
        $engineer['realname'] = $engineer['name'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $engineers,
        'debug' => $error,
        'debugInfo' => $debugInfo
    ]);
} catch (Exception $e) {
    // 如果数据库连接失败，返回空数据
    echo json_encode([
        'success' => true,
        'data' => [],
        'debug' => "数据库连接失败: " . $e->getMessage(),
        'simulated' => false
    ]);
}