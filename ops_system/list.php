<?php
// 运维系统列表查询接口
header('Content-Type: application/json');

// 引入数据库连接
require_once('../db.php');

try {
    // 获取请求参数
    $input = json_decode(file_get_contents('php://input'), true);
    $system_id = isset($input['system_id']) ? intval($input['system_id']) : 0;
    
    // 构建查询条件
    $where = '';
    $params = array();
    
    if ($system_id > 0) {
        $where = "WHERE os.id = :system_id";
        $params[':system_id'] = $system_id;
    }
    
    // 获取数据库连接
    $pdo = DB::getInstance();
    
    // 查询运维系统列表，关联工程师信息
    $sql = "SELECT 
                os.id, 
                os.system_name, 
                os.customer_name, 
                os.maintain_start, 
                os.maintain_end, 
                os.pm_name, 
                os.pm_phone, 
                os.engineer_id,
                u.name AS engineer_name,
                os.status, 
                os.create_time, 
                os.update_time 
            FROM ops_system os 
            LEFT JOIN user u ON os.engineer_id = u.id 
            $where 
            ORDER BY os.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $systems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($systems as &$system) {
        $system['status'] = (int)$system['status'];
        $system['engineer_id'] = $system['engineer_id'] ? (int)$system['engineer_id'] : null;
    }
    unset($system);
    
    echo json_encode(array(
        'success' => true,
        'data' => array(
            'list' => $systems,
            'total' => count($systems)
        )
    ));
} catch (PDOException $e) {
    // 返回错误响应
    echo json_encode(array(
        'success' => false,
        'message' => '查询系统列表失败: ' . $e->getMessage()
    ));
}