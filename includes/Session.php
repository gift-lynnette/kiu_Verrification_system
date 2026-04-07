<?php
/**
 * Session Management Class
 */

class Session {
    
    /**
     * Initialize session
     */
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
    }
    
    /**
     * Set session variable
     */
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get session variable
     */
    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Check if session variable exists
     */
    public static function has($key) {
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session variable
     */
    public static function remove($key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * Destroy session
     */
    public static function destroy() {
        session_unset();
        session_destroy();
    }
    
    /**
     * Check if session is expired
     */
    public static function isExpired() {
        if (self::has('login_time')) {
            $elapsed = time() - self::get('login_time');
            return $elapsed > SESSION_TIMEOUT;
        }
        return true;
    }
    
    /**
     * Update session activity time
     */
    public static function updateActivity() {
        self::set('login_time', time());
    }
    
    /**
     * Set flash message
     */
    public static function setFlash($type, $message) {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }
    
    /**
     * Get and clear flash message
     */
    public static function getFlash() {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return self::has('user_id') && !self::isExpired();
    }
    
    /**
     * Get current user ID
     */
    public static function getUserId() {
        return self::get('user_id');
    }
    
    /**
     * Get current user role
     */
    public static function getUserRole() {
        return self::get('role');
    }
    
    /**
     * Check if user has role
     */
    public static function hasRole($role) {
        if (is_array($role)) {
            return in_array(self::getUserRole(), $role);
        }
        return self::getUserRole() === $role;
    }
}
