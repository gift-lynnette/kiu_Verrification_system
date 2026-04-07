<?php
/**
 * Authentication Class
 */

class Auth {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Register a new user
     */
    public function register($admission_number, $email, $password) {
        try {
            // Check if user already exists
            $stmt = $this->db->prepare("
                SELECT user_id FROM users 
                WHERE admission_number = :admission_number OR email = :email
            ");
            $stmt->execute([
                'admission_number' => $admission_number,
                'email' => $email
            ]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'User already exists'];
            }
            
            // Hash password
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            
            // Insert new user
            $stmt = $this->db->prepare("
                INSERT INTO users (admission_number, email, password_hash, role)
                VALUES (:admission_number, :email, :password_hash, 'student')
            ");
            
            $stmt->execute([
                'admission_number' => $admission_number,
                'email' => $email,
                'password_hash' => $password_hash
            ]);
            
            $user_id = $this->db->lastInsertId();
            
            // Log activity
            $this->logActivity($user_id, 'REGISTER', 'User registered: ' . $email);
            
            return ['success' => true, 'message' => 'Registration successful', 'user_id' => $user_id];
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }
    
    /**
     * Login user
     */
    public function login($identifier, $password) {
        try {
                $rawIdentifier = trim((string)$identifier);
                $normalizedAdmissionNumber = strtoupper($rawIdentifier);

                // Get user by admission/staff number OR email (case-insensitive)
                $stmt = $this->db->prepare("
                    SELECT * FROM users 
                    WHERE (UPPER(admission_number) = :admission_number OR LOWER(email) = :email)
                      AND is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([
                    'admission_number' => $normalizedAdmissionNumber,
                    'email' => strtolower($rawIdentifier)
                ]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Check if account is locked
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                return ['success' => false, 'message' => 'Account is locked. Try again later.'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                // Increment failed login attempts
                $this->incrementLoginAttempts($user['user_id']);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Reset login attempts
            $this->resetLoginAttempts($user['user_id']);
            
            // Update last login
            $stmt = $this->db->prepare("
                UPDATE users SET last_login_at = NOW() WHERE user_id = :user_id
            ");
            $stmt->execute(['user_id' => $user['user_id']]);
            
            // Set session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['admission_number'] = $user['admission_number'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Log activity
            $this->logActivity($user['user_id'], 'LOGIN', 'User logged in');
            
            return ['success' => true, 'message' => 'Login successful', 'user' => $user];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed'];
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'LOGOUT', 'User logged out');
        }
        
        session_unset();
        session_destroy();
        
        return ['success' => true, 'message' => 'Logout successful'];
    }
    
    /**
     * Change password
     */
    public function changePassword($user_id, $old_password, $new_password) {
        try {
            // Get current password
            $stmt = $this->db->prepare("
                SELECT password_hash FROM users WHERE user_id = :user_id
            ");
            $stmt->execute(['user_id' => $user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            // Verify old password
            if (!password_verify($old_password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
            
            // Update password
            $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("
                UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id
            ");
            $stmt->execute([
                'password_hash' => $new_hash,
                'user_id' => $user_id
            ]);
            
            $this->logActivity($user_id, 'PASSWORD_CHANGE', 'Password changed');
            
            return ['success' => true, 'message' => 'Password changed successfully'];
            
        } catch (Exception $e) {
            error_log("Password change error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Password change failed'];
        }
    }
    
    /**
     * Increment failed login attempts
     */
    private function incrementLoginAttempts($user_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET login_attempts = login_attempts + 1,
                    locked_until = CASE 
                        WHEN login_attempts + 1 >= :max_attempts 
                        THEN DATE_ADD(NOW(), INTERVAL :lockout SECOND)
                        ELSE NULL 
                    END
                WHERE user_id = :user_id
            ");
            $stmt->execute([
                'max_attempts' => MAX_LOGIN_ATTEMPTS,
                'lockout' => LOCKOUT_DURATION,
                'user_id' => $user_id
            ]);
        } catch (Exception $e) {
            error_log("Failed to increment login attempts: " . $e->getMessage());
        }
    }
    
    /**
     * Reset login attempts
     */
    private function resetLoginAttempts($user_id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET login_attempts = 0, locked_until = NULL
                WHERE user_id = :user_id
            ");
            $stmt->execute(['user_id' => $user_id]);
        } catch (Exception $e) {
            error_log("Failed to reset login attempts: " . $e->getMessage());
        }
    }
    
    /**
     * Log activity
     */
    private function logActivity($user_id, $action, $details) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action, changes_summary, ip_address, user_agent)
                VALUES (:user_id, :action, :details, :ip, :user_agent)
            ");
            $stmt->execute([
                'user_id' => $user_id,
                'action' => $action,
                'details' => $details,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}
