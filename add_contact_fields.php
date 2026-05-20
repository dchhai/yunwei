<?php
require_once 'db.php';

try {
    $pdo = DB::getInstance();
    
    // 开始事务
    $pdo->beginTransaction();
    
    // 检查并添加contact_name字段
    try {
        $pdo->exec("ALTER TABLE ticket ADD COLUMN contact_name VARCHAR(50) DEFAULT NULL COMMENT '联系人姓名'");
        echo "Added contact_name column.\n";
    } catch (PDOException $e) {
        // 如果字段已经存在，忽略错误
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "contact_name column already exists.\n";
        } else {
            throw $e;
        }
    }
    
    // 检查并添加contact_phone字段
    try {
        $pdo->exec("ALTER TABLE ticket ADD COLUMN contact_phone VARCHAR(20) DEFAULT NULL COMMENT '联系电话'");
        echo "Added contact_phone column.\n";
    } catch (PDOException $e) {
        // 如果字段已经存在，忽略错误
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "contact_phone column already exists.\n";
        } else {
            throw $e;
        }
    }
    
    // 提交事务
    $pdo->commit();
    
    echo "\nContact fields added successfully.\n";
    
} catch (Exception $e) {
    // 回滚事务
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
