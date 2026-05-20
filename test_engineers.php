<?php
/**
 * 测试工程师列表接口
 */
// 直接包含engineers.php来测试
ob_start();
require_once 'engineers.php';
$result = ob_get_clean();

// 输出结果和调试信息
echo "原始输出：\n";
var_dump($result);

echo "\n解析后的JSON：\n";
$decoded = json_decode($result, true);
var_dump($decoded);

// 检查数据库连接
if (!class_exists('DB')) {
    echo "\n数据库类不存在！\n";
} else {
    try {
        $pdo = DB::getInstance();
        echo "\n数据库连接成功！\n";
        
        // 测试角色表
        $stmt = $pdo->query("SHOW TABLES LIKE 'role'");
        if ($stmt->rowCount() > 0) {
            echo "角色表存在\n";
            $stmt = $pdo->query("SELECT * FROM role");
            echo "角色列表：\n";
            var_dump($stmt->fetchAll(PDO::FETCH_ASSOC));
        } else {
            echo "角色表不存在\n";
        }
        
        // 测试用户表
        $stmt = $pdo->query("SHOW TABLES LIKE 'user'");
        if ($stmt->rowCount() > 0) {
            echo "用户表存在\n";
            $stmt = $pdo->query("SELECT * FROM user LIMIT 5");
            echo "用户列表（前5条）：\n";
            var_dump($stmt->fetchAll(PDO::FETCH_ASSOC));
        } else {
            echo "用户表不存在\n";
        }
        
    } catch (Exception $e) {
        echo "\n数据库连接失败：" . $e->getMessage() . "\n";
    }
}