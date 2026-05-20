<?php
// 新增运维系统接口
header('Content-Type: application/json');

// 引入数据库连接
require_once('../db.php');

try {
    // 获取请求参数
    $input = json_decode(file_get_contents('php://input'), true);
    
    // 验证必填字段
    if (!isset($input['system_name']) || empty(trim($input['system_name']))) {
        echo json_encode(array(
            'success' => false,
            'message' => '系统名称不能为空'
        ));
        exit;
    }
    
    // 获取数据库连接
    $pdo = DB::getInstance();
    
    // 检查系统名称是否已存在
    $checkSql = "SELECT id FROM ops_system WHERE system_name = :system_name";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute(array(':system_name' => trim($input['system_name'])));
    if ($checkStmt->fetch()) {
        echo json_encode(array(
            'success' => false,
            'message' => '系统名称已存在'
        ));
        exit;
    }
    
    // 准备插入数据
    $data = array(
        ':system_name' => trim($input['system_name']),
        ':customer_name' => isset($input['customer_name']) ? trim($input['customer_name']) : null,
        ':maintain_start' => isset($input['maintain_start']) && $input['maintain_start'] ? $input['maintain_start'] : null,
        ':maintain_end' => isset($input['maintain_end']) && $input['maintain_end'] ? $input['maintain_end'] : null,
        ':pm_name' => isset($input['pm_name']) ? trim($input['pm_name']) : null,
        ':pm_phone' => isset($input['pm_phone']) ? trim($input['pm_phone']) : null,
        ':engineer_id' => isset($input['engineer_id']) && $input['engineer_id'] ? intval($input['engineer_id']) : null,
        ':status' => isset($input['status']) ? intval($input['status']) : 1
    );
    
    // 构建SQL语句
    $sql = "INSERT INTO ops_system 
            (system_name, customer_name, maintain_start, maintain_end, pm_name, pm_phone, engineer_id, status) 
            VALUES 
            (:system_name, :customer_name, :maintain_start, :maintain_end, :pm_name, :pm_phone, :engineer_id, :status)";
    
    // 执行插入
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    
    // 返回成功响应
    echo json_encode(array(
        'success' => true,
        'message' => '新增运维系统成功',
        'data' => array(
            'system_id' => $pdo->lastInsertId()
        )
    ));
} catch (PDOException $e) {
    // 返回错误响应
    echo json_encode(array(
        'success' => false,
        'message' => '新增运维系统失败: ' . $e->getMessage()
    ));
}