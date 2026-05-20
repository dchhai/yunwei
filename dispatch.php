<?php
// 工单分派接口
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// 直接创建PDO连接
$host = '127.0.0.1';
$dbname = 'yunweixitong';
$username = 'root';
$password = 'root';
$port = '3306';

// 记录开始请求
error_log('dispatch.php: 收到分派请求');

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (Exception $e) {
    // 数据库连接失败
    echo json_encode([
        'success' => false,
        'message' => '数据库连接失败',
        'error' => $e->getMessage()
    ]);
    exit;
}

// 获取POST请求参数
try {
    // 检查请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'success' => false,
            'message' => '只支持POST请求方法',
            'method' => $_SERVER['REQUEST_METHOD']
        ]);
        exit;
    }
    
    // 获取JSON格式的POST数据
    $rawData = file_get_contents('php://input');
    error_log('dispatch.php: 原始请求数据长度: ' . strlen($rawData));
    error_log('dispatch.php: 原始请求数据: ' . $rawData);
    
    // 解析JSON数据
    $data = json_decode($rawData, true);
    $jsonError = json_last_error();
    
    // 如果JSON解析失败或没有数据，尝试从常规POST参数获取
    if ($jsonError !== JSON_ERROR_NONE || !isset($data) || !is_array($data)) {
        error_log('dispatch.php: JSON解析失败或数据无效，错误码: ' . $jsonError);
        $data = $_POST;
        error_log('dispatch.php: 使用POST数组数据: ' . print_r($data, true));
    }
    
    // 验证必要参数
    if (!isset($data['ticket_id']) || !isset($data['handler_id'])) {
        error_log('dispatch.php: 缺少必要参数，收到的数据: ' . print_r($data, true));
        echo json_encode([
            'success' => false,
            'message' => '缺少必要参数（工单ID或工程师ID）',
            'received_data' => $data,
            'missing_ticket_id' => !isset($data['ticket_id']),
            'missing_handler_id' => !isset($data['handler_id'])
        ]);
        exit;
    }
    
    // 获取工单ID和工程师ID
    $ticketId = intval($data['ticket_id']);
    $engineerId = intval($data['handler_id']);
    $remark = isset($data['remark']) ? trim($data['remark']) : '';
    
    error_log('dispatch.php: 解析到的参数 - ticket_id: ' . $ticketId . ', handler_id: ' . $engineerId . ', remark: ' . $remark);
    
    // 验证参数有效性
    if ($ticketId <= 0 || $engineerId <= 0) {
        error_log('dispatch.php: 无效的参数 - ticket_id: ' . $ticketId . ', handler_id: ' . $engineerId);
        echo json_encode([
            'success' => false,
            'message' => '无效的工单ID或工程师ID',
            'ticket_id' => $ticketId,
            'handler_id' => $engineerId,
            'ticket_id_valid' => $ticketId > 0,
            'handler_id_valid' => $engineerId > 0
        ]);
        exit;
    }
    
    // 简化验证：只检查用户是否存在，不检查角色（因为前端已经从engineers.php获取了有效的工程师列表）
    error_log('dispatch.php: 验证工程师ID是否存在: ' . $engineerId);
    $stmt = $pdo->prepare("SELECT id FROM user WHERE id = ?");
    $stmt->execute([$engineerId]);
    if ($stmt->rowCount() === 0) {
        error_log('dispatch.php: 工程师ID不存在: ' . $engineerId);
        echo json_encode([
            'success' => false,
            'message' => '指定的工程师不存在'
        ]);
        exit;
    }
    
    // 验证工单是否存在
    error_log('dispatch.php: 验证工单ID是否存在: ' . $ticketId);
    $stmt = $pdo->prepare("SELECT id FROM ticket WHERE id = ?");
    $stmt->execute([$ticketId]);
    if ($stmt->rowCount() === 0) {
        error_log('dispatch.php: 工单ID不存在: ' . $ticketId);
        echo json_encode([
            'success' => false,
            'message' => '指定的工单不存在'
        ]);
        exit;
    }
    
    // 简化实现：移除事务，直接更新工单
    // 更新工单分派信息
    try {
        // 先检查表结构（使用正确的表名ticket）
        error_log('dispatch.php: 查询ticket表结构');
        $columnInfo = [];
        $stmt = $pdo->query("DESCRIBE ticket");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columnInfo[] = $row['Field'];
        }
        error_log('dispatch.php: ticket表列信息: ' . implode(', ', $columnInfo));
        
        // 构建更新SQL，根据实际列名动态调整
        $updateFields = [];
        $updateParams = [];
        
        // 基础字段 - 始终设置handler_id（从list.php看出正确字段名是handler_id）
        if (in_array('handler_id', $columnInfo)) {
            $updateFields[] = 'handler_id = ?';
            $updateParams[] = $engineerId;
            error_log('dispatch.php: 添加handler_id字段更新');
        }
        
        // 始终设置status字段
        if (in_array('status', $columnInfo)) {
            $updateFields[] = 'status = 1'; // 使用数字1，不使用字符串
            error_log('dispatch.php: 添加status字段更新为1');
        }
        
        // 设置remark字段（即使为空也更新）
        if (in_array('remark', $columnInfo)) {
            $updateFields[] = 'remark = ?';
            $updateParams[] = $remark;
            error_log('dispatch.php: 添加remark字段更新');
        }
        
        // 设置update_time字段（根据list.php使用的字段名）
        if (in_array('update_time', $columnInfo)) {
            $updateFields[] = 'update_time = NOW()';
            error_log('dispatch.php: 添加update_time字段更新');
        } elseif (in_array('updated_at', $columnInfo)) {
            $updateFields[] = 'updated_at = NOW()';
            error_log('dispatch.php: 添加updated_at字段更新');
        }
        
        // 构建完整SQL（使用正确的表名ticket）
        if (!empty($updateFields)) {
            $updateSql = "UPDATE ticket SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $updateParams[] = $ticketId;
            
            error_log('dispatch.php: 执行更新SQL: ' . $updateSql);
            error_log('dispatch.php: 更新参数: ' . print_r($updateParams, true));
            
            // 预处理并执行SQL
            $stmt = $pdo->prepare($updateSql);
            $success = $stmt->execute($updateParams);
            
            // 获取执行结果
            $affectedRows = $stmt->rowCount();
            error_log('dispatch.php: SQL执行结果: ' . ($success ? '成功' : '失败'));
            error_log('dispatch.php: 影响行数: ' . $affectedRows);
            
            // 即使影响行数为0，也尝试检查工单是否已被更新
            if (!$success || $affectedRows === 0) {
                // 查询工单当前状态（使用正确的表名和字段名）
                $checkStmt = $pdo->prepare("SELECT handler_id, status FROM ticket WHERE id = ?");
                $checkStmt->execute([$ticketId]);
                $currentStatus = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                error_log('dispatch.php: 工单当前状态: ' . print_r($currentStatus, true));
                
                // 检查是否已经是相同的分配状态
                if ($currentStatus && $currentStatus['handler_id'] == $engineerId && $currentStatus['status'] == 1) {
                    error_log('dispatch.php: 工单已处于相同的分派状态');
                    echo json_encode([
                        'success' => true,
                        'message' => '工单已分派给指定工程师',
                        'ticket_id' => $ticketId,
                        'handler_id' => $engineerId,
                        'status' => 'already_assigned'
                    ]);
                    exit;
                } else {
                    error_log('dispatch.php: 更新失败或无变化，检查SQL和参数');
                    echo json_encode([
                        'success' => false,
                        'message' => '工单分派失败',
                        'error' => '更新失败或无变化',
                        'affected_rows' => $affectedRows,
                        'update_success' => $success,
                        'available_columns' => $columnInfo
                    ]);
                    exit;
                }
            } else {
                // 查询更新后的工单状态
                error_log('dispatch.php: 更新成功，查询工单最新状态');
                $checkStmt = $pdo->prepare("SELECT handler_id, status FROM ticket WHERE id = ?");
                $checkStmt->execute([$ticketId]);
                $updatedStatus = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                error_log('dispatch.php: 工单更新后状态: ' . print_r($updatedStatus, true));
                
                // 发送成功响应
                error_log('dispatch.php: 发送成功响应');
                echo json_encode([
                    'success' => true,
                    'message' => '工单分派成功',
                    'ticket_id' => $ticketId,
                    'handler_id' => $engineerId,
                    'status' => 'successfully_assigned',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                exit;
            }
        } else {
            error_log('dispatch.php: 没有可更新的字段');
            echo json_encode([
                'success' => false,
                'message' => '工单分派失败',
                'error' => '没有可更新的字段',
                'available_columns' => $columnInfo
            ]);
            exit;
        }
    } catch (Exception $e) {
        error_log('dispatch.php: 工单更新异常: ' . $e->getMessage());
        error_log('dispatch.php: 异常堆栈: ' . $e->getTraceAsString());
        echo json_encode([
            'success' => false,
            'message' => '工单更新失败',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        exit;
    }
    
    // 返回成功响应
    error_log('dispatch.php: 发送成功响应');
    echo json_encode([
        'success' => true,
        'message' => '工单分派成功',
        'ticket_id' => $ticketId,
        'handler_id' => $engineerId,
        'remark' => $remark,
        'status' => 'successfully_assigned',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // 返回详细的错误响应
    $errorInfo = [
        'success' => false,
        'message' => '工单分派失败：' . $e->getMessage(),
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
        'trace' => $e->getTraceAsString()
    ];
    
    echo json_encode($errorInfo);
    
    // 记录详细错误日志
    error_log('工单分派接口错误: ' . $e->getMessage() . ' | 错误代码: ' . $e->getCode());
    error_log('错误堆栈: ' . $e->getTraceAsString());
}