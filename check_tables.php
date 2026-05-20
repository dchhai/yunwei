<?php
/**
 * 检查表结构的临时脚本
 */
header("Content-Type: text/html; charset=utf-8");

try {
    require_once __DIR__ . '/db.php';
    $pdo = DB::getInstance();
    
    echo "<h3>数据库表结构检查</h3>\n";
    echo "<h4>user表结构：</h4>\n";
    echo "<pre>\n";
    $stmt = $pdo->query("DESCRIBE user");
    $userTable = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($userTable);
    echo "</pre>\n";
    
    echo "<h4>role表结构：</h4>\n";
    echo "<pre>\n";
    $stmt = $pdo->query("DESCRIBE role");
    $roleTable = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($roleTable);
    echo "</pre>\n";
    
    echo "<h4>role表中的角色数据：</h4>\n";
    echo "<pre>\n";
    $stmt = $pdo->query("SELECT * FROM role");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($roles);
    echo "</pre>\n";
    
    echo "<h4>用户表中所有用户及其角色：</h4>\n";
    echo "<pre>\n";
    $stmt = $pdo->query("SELECT u.id, u.username, u.realname, u.role_id, r.role_name 
                         FROM user u 
                         LEFT JOIN role r ON u.role_id = r.id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($users);
    echo "</pre>\n";
    
    echo "<h4>符合工程师条件的用户（测试查询）：</h4>\n";
    echo "<pre>\n";
    try {
        // 测试推荐的查询方式
        $stmt = $pdo->prepare("SELECT u.id, u.username, u.realname 
                              FROM user u
                              JOIN role r ON u.role_id = r.id
                              WHERE r.role_name = 'engineer'
                              ORDER BY u.username ASC");
        $stmt->execute();
        $engineers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "推荐查询结果：\n";
        print_r($engineers);
    } catch (Exception $e) {
        echo "推荐查询失败：" . $e->getMessage() . "\n";
    }
    
    try {
        // 测试备选查询方式1
        $stmt = $pdo->prepare("SELECT id, username, realname FROM user WHERE role_id = (SELECT id FROM role WHERE role_name = 'engineer') ORDER BY username ASC");
        $stmt->execute();
        $engineers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "\n备选查询1结果：\n";
        print_r($engineers);
    } catch (Exception $e) {
        echo "\n备选查询1失败：" . $e->getMessage() . "\n";
    }
    
    try {
        // 测试备选查询方式2
        $stmt = $pdo->prepare("SELECT id, username, realname FROM user WHERE role = 'engineer' OR role_name = 'engineer' ORDER BY username ASC");
        $stmt->execute();
        $engineers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "\n备选查询2结果：\n";
        print_r($engineers);
    } catch (Exception $e) {
        echo "\n备选查询2失败：" . $e->getMessage() . "\n";
    }
    
    echo "</pre>\n";
    
} catch (Exception $e) {
    echo "错误：" . $e->getMessage();
}