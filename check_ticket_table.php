<?php
require_once 'db.php';

try {
    $pdo = DB::getInstance();
    
    // 查询ticket表的结构
    $stmt = $pdo->query("DESCRIBE ticket");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Ticket table structure:\n";
    foreach ($columns as $column) {
        echo "Column: {$column['Field']}, Type: {$column['Type']}, Null: {$column['Null']}, Key: {$column['Key']}, Default: {$column['Default']}\n";
    }
    
    // 检查是否存在联系人相关字段
    $hasContactFields = false;
    foreach ($columns as $column) {
        if (in_array($column['Field'], ['contact_name', 'contact_phone'])) {
            $hasContactFields = true;
            break;
        }
    }
    
    if (!$hasContactFields) {
        echo "\nContact fields (contact_name, contact_phone) not found. Need to add them.\n";
    } else {
        echo "\nContact fields already exist.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
