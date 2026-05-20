<?php
/**
 * 数据库连接工具类（示例配置）
 * 请复制此文件为 db.php 或 common/db.php 并填入真实配置
 */
class DB {
    private static $pdo;

    public static function getInstance() {
        if (!self::$pdo) {
            $host = '127.0.0.1';
            $port = '3306';
            $dbname = 'your_database_name';
            $username = 'your_username';
            $password = 'your_password';
            $charset = 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
            try {
                self::$pdo = new PDO(
                    $dsn,
                    $username,
                    $password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]
                );
            } catch (PDOException $e) {
                throw new Exception('数据库连接失败：' . $e->getMessage());
            }
        }
        return self::$pdo;
    }
}
