<?php
/**
 * Audit Log Helper for Infinity Builders
 * Records all significant actions for compliance and debugging
 */

/**
 * Log an audit event
 * 
 * @param string $action_type create|update|delete|login|logout|export|view
 * @param string $entity_type projects|vendors|users|payments|settings
 * @param int|null $entity_id
 * @param string|null $entity_name
 * @param array|null $changes ['old' => [...], 'new' => [...]] or null
 * @param int|null $user_id Override user_id (optional)
 * @return bool Success
 */
function audit_log(string $action_type, string $entity_type, ?int $entity_id = null, ?string $entity_name = null, ?array $changes = null, ?int $user_id = null): bool {
    static $pdo = null;
    
    try {
        if ($pdo === null) {
            require_once __DIR__ . '/../config/config.php';
            global $db_host, $db_name, $db_user, $db_pass;
            $pdo = new PDO(
                'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4',
                $db_user,
                $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }
        
        $username = $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'system';
        $uid = $user_id ?? ($_SESSION['user_id'] ?? null);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_log 
            (user_id, username, action_type, entity_type, entity_id, entity_name, changes, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $uid,
            $username,
            $action_type,
            $entity_type,
            $entity_id,
            $entity_name,
            $changes ? json_encode($changes) : null,
            $ip,
            substr($ua ?? '', 0, 500)
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log('Audit log failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get audit logs with optional filters
 * 
 * @param array $filters [entity_type, entity_id, user_id, action_type, from_date, to_date, limit, offset]
 * @return array
 */
function get_audit_logs(array $filters = []): array {
    static $pdo = null;
    
    try {
        if ($pdo === null) {
            require_once __DIR__ . '/../config/config.php';
            global $db_host, $db_name, $db_user, $db_pass;
            $pdo = new PDO(
                'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4',
                $db_user,
                $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }
        
        $where = [];
        $params = [];
        
        if (!empty($filters['entity_type'])) {
            $where[] = "entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['entity_id'])) {
            $where[] = "entity_id = ?";
            $params[] = (int)$filters['entity_id'];
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = (int)$filters['user_id'];
        }
        
        if (!empty($filters['action_type'])) {
            $where[] = "action_type = ?";
            $params[] = $filters['action_type'];
        }
        
        if (!empty($filters['from_date'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['from_date'];
        }
        
        if (!empty($filters['to_date'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['to_date'];
        }
        
        $sql = "SELECT * FROM audit_log";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY created_at DESC";
        
        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;
        $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Get audit logs failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get recent activity summary
 * 
 * @param int $hours Past hours to look back
 * @return array
 */
function get_recent_activity(int $hours = 24): array {
    static $pdo = null;
    
    try {
        if ($pdo === null) {
            require_once __DIR__ . '/../config/config.php';
            global $db_host, $db_name, $db_user, $db_pass;
            $pdo = new PDO(
                'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4',
                $db_user,
                $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                action_type,
                entity_type,
                COUNT(*) as count
            FROM audit_log 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            GROUP BY action_type, entity_type
            ORDER BY count DESC
        ");
        $stmt->execute([$hours]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}
