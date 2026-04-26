<?php
/**
 * Database Connection with Prepared Statements
 * Infinity Builders
 */

class Database {
    private static ?mysqli $connection = null;
    
    public static function getConnection(): mysqli {
        if (self::$connection === null) {
            require_once __DIR__ . '/../config/config.php';
            
            self::$connection = new mysqli(
                DB_HOST,
                DB_USER,
                DB_PASS,
                DB_NAME
            );
            
            if (self::$connection->connect_error) {
                error_log('Database connection failed: ' . self::$connection->connect_error);
                die('Database connection error');
            }
            
            self::$connection->set_charset('utf8mb4');
        }
        
        return self::$connection;
    }
    
    /**
     * Execute a prepared statement
     */
    public static function query(string $sql, array $params = []): mysqli_stmt|false {
        $conn = self::getConnection();
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log('Prepare failed: ' . $conn->error);
            return false;
        }
        
        if (!empty($params)) {
            $types = '';
            $values = [];
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
                $values[] = $param;
            }
            $stmt->bind_param($types, ...$values);
        }
        
        $stmt->execute();
        return $stmt;
    }
    
    /**
     * Fetch single row
     */
    public static function fetch(string $sql, array $params = []): ?array {
        $stmt = self::query($sql, $params);
        if (!$stmt) return null;
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row ?: null;
    }
    
    /**
     * Fetch all rows
     */
    public static function fetchAll(string $sql, array $params = []): array {
        $stmt = self::query($sql, $params);
        if (!$stmt) return [];
        
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $rows;
    }
    
    /**
     * Insert and return last insert ID
     */
    public static function insert(string $sql, array $params = []): int|false {
        $stmt = self::query($sql, $params);
        if (!$stmt) return false;
        
        $id = $stmt->insert_id;
        $stmt->close();
        
        return $id;
    }
    
    /**
     * Update/Delete and return affected rows
     */
    public static function execute(string $sql, array $params = []): int|false {
        $stmt = self::query($sql, $params);
        if (!$stmt) return false;
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected;
    }
}
