<?php
// 更新运维系统接口
header('Content-Type: application/json');

// 引入数据库连接
require_once('../db.php');

try {
    // 获取请求参数
    $input = json_decode(file_get_contents('php://input'), true);
    
    // 验证系统ID
    if (!isset($input['id']) || intval($input['id']) <= 0) {
        echo json_encode(array(
            'success' => false,
            'message' => '无效的系统ID'
        ));
        exit;
    }
    
    $system_id = intval($input['id']);
    
    // 获取数据库连接
    $pdo = DB::getInstance();
    
    // 检查系统是否存在
    $checkSql = "SELECT id FROM ops_system WHERE id = :id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute(array(':id' => $system_id));
    if (!$checkStmt->fetch()) {
        echo json_encode(array(
            'success' => false,
            'message' => '运维系统不存在'
        ));
        exit;
    }
    
    // 准备更新字段和参数
    $updateFields = array();
    $params = array(':id' => $system_id);
    
    // 系统名称更新（需要检查唯一性）
    if (isset($input['system_name'])) {
        $system_name = trim($input['system_name']);
        if (empty($system_name)) {
            echo json_encode(array(
                'success' => false,
                'message' => '系统名称不能为空'
            ));
            exit;
        }
        
        // 检查名称是否被其他系统使用
        $nameCheckSql = "SELECT id FROM ops_system WHERE system_name = :system_name AND id != :id";
        $nameCheckStmt = $pdo->prepare($nameCheckSql);
        $nameCheckStmt->execute(array(
            ':system_name' => $system_name,
            ':id' => $system_id
        ));
        if ($nameCheckStmt->fetch()) {
            echo json_encode(array(
                'success' => false,
                'message' => '系统名称已被使用'
            ));
            exit;
        }
        
        $updateFields[] = "system_name = :system_name";
        $params[':system_name'] = $system_name;
    }
    
    // 其他可更新字段
    $fieldsToUpdate = array(
        'customer_name', 'maintain_start', 'maintain_end', 
        'pm_name', 'pm_phone', 'engineer_id', 'status'
    );
    
    foreach ($fieldsToUpdate as $field) {
        if (isset($input[$field])) {
            if ($field === 'engineer_id') {
                // engineer_id 特殊处理，可以为null
                $updateFields[] = "$field = :$field";
                $params[":$field"] = $input[$field] ? intval($input[$field]) : null;
            } else if ($field === 'status') {
                // 状态字段确保是整数
                $updateFields[] = "$field = :$field";
                $params[":$field"] = intval($input[$field]);
            } else {
                // 字符串字段去除首尾空格
                $updateFields[] = "$field = :$field";
                $params[":$field"] = trim($input[$field]) ?: null;
            }
        }
    }
    
    // 如果没有要更新的字段
    if (empty($updateFields)) {
        echo json_encode(array(
            'success' => false,
            'message' => '没有需要更新的字段'
        ));
        exit;
    }
    
    // 添加更新时间
    $updateFields[] = "update_time = NOW()";
    
    // 构建SQL语句
    $sql = "UPDATE ops_system SET " . implode(", ", $updateFields) . " WHERE id = :id";
    
    // 执行更新
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // 返回成功响应
    echo json_encode(array(
        'success' => true,
        'message' => '更新运维系统成功'
    ));
} catch (PDOException $e) {
    // 返回错误响应
    echo json_encode(array(
        'success' => false,
        'message' => '更新运维系统失败: ' . $e->getMessage()
    ));
}