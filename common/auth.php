<?php
/**
 * 权限校验函数
 * @param string $permCode 权限标识（如user:view）
 * @throws Exception 无权限时抛出异常
 */
function checkPermission($permCode) {
    // 1. 检查用户是否登录 - 兼容session和前端localStorage登录方式
    // 注意：session_start()应该由调用方在调用此函数前执行
    
    // 尝试从session获取用户ID
    $userId = 0;
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
        $userId = (int)$_SESSION['user_id'];
    } else {
        // 如果session中没有，尝试从请求头获取token或用户信息
        // 这是为了兼容前端使用localStorage的情况
        $authHeader = '';
        
        // 尝试从$_SERVER获取Authorization头
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }
        // 尝试使用getallheaders()获取（兼容不同的服务器环境）
        else if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    $authHeader = $value;
                    break;
                }
            }
        }
        
        // 处理Authorization头
        if (!empty($authHeader) && strpos($authHeader, 'Bearer ') === 0) {
            $userId = intval(substr($authHeader, 7));
        }
        
        // 如果仍然没有用户ID，允许通过检查请求中的user_id参数（用于调试）
        if ($userId <= 0) {
            // 检查GET、POST和JSON请求中的user_id参数
            if (isset($_REQUEST['user_id']) && $_REQUEST['user_id'] > 0) {
                $userId = (int)$_REQUEST['user_id'];
            } else if (isset($_POST['user_id']) && $_POST['user_id'] > 0) {
                $userId = (int)$_POST['user_id'];
            } else if (isset($_GET['user_id']) && $_GET['user_id'] > 0) {
                $userId = (int)$_GET['user_id'];
            }
        }
    }
    
    // 如果仍然没有有效的用户ID，抛出未登录异常
    if ($userId <= 0) {
        throw new Exception("请先登录系统");
    }

    // 2. 获取用户角色ID
    $pdo = DB::getInstance();
    $stmt = $pdo->prepare("SELECT role_id FROM `user` WHERE id = :uid AND status = 1");
    $stmt->execute([':uid' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new Exception("用户不存在或已被禁用");
    }
    $roleId = $user['role_id'];

    // 3. 校验角色是否拥有目标权限
    $stmt = $pdo->prepare("
        SELECT rp.id 
        FROM role_permission rp
        INNER JOIN permission p ON rp.perm_id = p.id
        WHERE rp.role_id = :role_id AND p.perm_code = :perm_code
    ");
    $stmt->execute([':role_id' => $roleId, ':perm_code' => $permCode]);
    if (!$stmt->fetch()) {
        throw new Exception("您没有【{$permCode}】权限，请联系管理员");
    }
}