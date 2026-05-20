<?php
// 删除运维系统接口
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
    
    // 检查是否有关联的工单（如果需要限制删除）
    // 这里可以根据实际业务需求决定是否允许删除已有关联工单的系统
    // 如果需要限制，可以执行以下查询：
    /*
    $ticketCheckSql = "SELECT id FROM ticket WHERE system_id = :system_id LIMIT 1";
    $ticketCheckStmt = $pdo->prepare($ticketCheckSql);
    $ticketCheckStmt->execute(array(':system_id' => $system_id));
    if ($ticketCheckStmt->fetch()) {
        echo json_encode(array(
            'success' => false,
            'message' => '该系统下存在工单，无法删除'
        ));
        exit;
    }
    */
    
    // 执行删除操作
    $deleteSql = "DELETE FROM ops_system WHERE id = :id";
    $deleteStmt = $pdo->prepare($deleteSql);
    $deleteStmt->execute(array(':id' => $system_id));
    
    // 检查删除是否成功
    if ($deleteStmt->rowCount() > 0) {
        echo json_encode(array(
            'success' => true,
            'message' => '删除运维系统成功'
        ));
    } else {
        echo json_encode(array(
            'success' => false,
            'message' => '删除失败，请重试'
        ));
    }
} catch (PDOException $e) {
    // 返回错误响应
    echo json_encode(array(
        'success' => false,
        'message' => '删除运维系统失败: ' . $e->getMessage()
    ));
}