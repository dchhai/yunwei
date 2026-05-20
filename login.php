<?php
/**
 * 登录接口（使用统一数据库配置）
 */
header("Content-Type: application/json; charset=utf-8");
session_start();

// 禁止缓存
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// 引入数据库配置（关键：通过db.php获取连接）
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/common/auth.php';

// 1. 接收前端数据
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    exit(json_encode(['success' => false, 'message' => '参数格式错误']));
}

// 2. 验证码验证
if (empty($data['captcha'])) {
    exit(json_encode(['success' => false, 'message' => '请输入验证码']));
}

$sessionCaptcha = isset($_SESSION['captcha']) ? $_SESSION['captcha'] : '';
$userCaptcha = trim($data['captcha']);

if (empty($sessionCaptcha) || strtolower($userCaptcha) !== strtolower($sessionCaptcha)) {
    unset($_SESSION['captcha']);
    exit(json_encode(['success' => false, 'message' => '验证码错误']));
}

// 3. 验证用户名和密码
$username = trim($data['username'] ?? '');
$password = trim($data['password'] ?? '');

if (empty($username)) {
    exit(json_encode(['success' => false, 'message' => '请输入用户名']));
}
if (empty($password)) {
    exit(json_encode(['success' => false, 'message' => '请输入密码']));
}

// 4. 数据库验证（通过db.php获取连接）
try {
    // 从DB类获取数据库连接（无需重复配置）
    $pdo = DB::getInstance();

    $stmt = $pdo->prepare("SELECT * FROM user WHERE username = :username LIMIT 1");
    $stmt->bindValue(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        exit(json_encode(['success' => false, 'message' => '用户名或密码错误']));
    }

    // 获取角色名称
    $stmt = $pdo->prepare("SELECT role_name FROM role WHERE id = :role_id LIMIT 1");
    $stmt->bindValue(':role_id', $user['role_id'], PDO::PARAM_INT);
    $stmt->execute();
    $role = $stmt->fetch();
    $roleName = $role ? $role['role_name'] : '';

    // 登录成功处理
    unset($_SESSION['captcha']);
    exit(json_encode([
        'success' => true,
        'data' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'name' => $user['name'],
            'role_id' => $user['role_id'],
            'role_name' => $roleName
        ]
    ]));

} catch (PDOException $e) {
    exit(json_encode(['success' => false, 'message' => '数据库错误：' . $e->getMessage()]));
}