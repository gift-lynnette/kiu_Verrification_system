<?php
/**
 * Audit Log Class
 */

class AuditLog {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Log activity
     */
    public function log($action, $table_name = null, $record_id = null, $old_value = null, $new_value = null, $changes_summary = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (
                    user_id, action, table_name, record_id, 
                    old_value, new_value, changes_summary,
                    ip_address, user_agent, request_url, request_method, session_id
                ) VALUES (
                    :user_id, :action, :table_name, :record_id,
                    :old_value, :new_value, :changes_summary,
                    :ip_address, :user_agent, :request_url, :request_method, :session_id
                )
            ");
            
            $stmt->execute([
                'user_id' => $_SESSION['user_id'] ?? null,
                'action' => $action,
                'table_name' => $table_name,
                'record_id' => $record_id,
                'old_value' => $old_value ? json_encode($old_value) : null,
                'new_value' => $new_value ? json_encode($new_value) : null,
                'changes_summary' => $changes_summary,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'request_url' => $_SERVER['REQUEST_URI'] ?? null,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'session_id' => session_id()
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get logs for user
     */
    public function getUserLogs($user_id, $limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM audit_logs 
                WHERE user_id = :user_id 
                ORDER BY timestamp DESC 
                LIMIT :limit
            ");
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get user logs error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get logs for table record
     */
    public function getRecordLogs($table_name, $record_id, $limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT al.*, u.email, u.admission_number
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.user_id
                WHERE al.table_name = :table_name AND al.record_id = :record_id 
                ORDER BY al.timestamp DESC 
                LIMIT :limit
            ");
            $stmt->bindValue(':table_name', $table_name, PDO::PARAM_STR);
            $stmt->bindValue(':record_id', $record_id, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get record logs error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent logs
     */
    public function getRecentLogs($limit = 100) {
        try {
            $stmt = $this->db->prepare("
                SELECT al.*, u.email, u.admission_number, u.role
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.user_id
                ORDER BY al.timestamp DESC 
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get recent logs error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get logs by action
     */
    public function getLogsByAction($action, $limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT al.*, u.email, u.admission_number
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.user_id
                WHERE al.action = :action 
                ORDER BY al.timestamp DESC 
                LIMIT :limit
            ");
            $stmt->bindValue(':action', $action, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get logs by action error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Search logs
     */
    public function searchLogs($search_term, $limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT al.*, u.email, u.admission_number
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.user_id
                WHERE al.action LIKE :search 
                   OR al.changes_summary LIKE :search 
                   OR u.email LIKE :search
                ORDER BY al.timestamp DESC 
                LIMIT :limit
            ");
            $search = '%' . $search_term . '%';
            $stmt->bindValue(':search', $search, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Search logs error: " . $e->getMessage());
            return [];
        }
    }
}
