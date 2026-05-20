<?php
// 获取工程师列表接口
header('Content-Type: application/json');

// 引入数据库连接
require_once('../db.php');

try {
    // 获取数据库连接
    $pdo = DB::getInstance();
    
    // 查询用户表中的工程师用户（假设有role_id标识角色，1为工程师角色，根据实际情况调整）
    $sql = "SELECT u.id, u.username, u.name 
            FROM user u 
            LEFT JOIN role r ON u.role_id = r.id 
            WHERE r.role_name LIKE '%工程师%' OR r.id = 2 
            ORDER BY u.username ASC";
    
    $stmt = $pdo->query($sql);
    $engineers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 返回成功响应
    echo json_encode(array(
        'success' => true,
        'data' => $engineers
    ));
} catch (PDOException $e) {
    // 返回错误响应
    echo json_encode(array(
        'success' => false,
        'message' => '获取工程师列表失败: ' . $e->getMessage()
    ));
}