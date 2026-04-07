<?php
/**
 * Input Validation Class
 */

class Validator {
    private $errors = [];
    
    /**
     * Validate required field
     */
    public function required($field, $value, $label = null) {
        $label = $label ?? ucfirst($field);
        if (empty($value)) {
            $this->errors[$field] = "$label is required";
            return false;
        }
        return true;
    }
    
    /**
     * Validate email
     */
    public function email($field, $value, $label = null) {
        $label = $label ?? ucfirst($field);
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "$label is not a valid email address";
            return false;
        }
        return true;
    }
    
    /**
     * Validate minimum length
     */
    public function minLength($field, $value, $min, $label = null) {
        $label = $label ?? ucfirst($field);
        if (strlen($value) < $min) {
            $this->errors[$field] = "$label must be at least $min characters";
            return false;
        }
        return true;
    }
    
    /**
     * Validate maximum length
     */
    public function maxLength($field, $value, $max, $label = null) {
        $label = $label ?? ucfirst($field);
        if (strlen($value) > $max) {
            $this->errors[$field] = "$label must not exceed $max characters";
            return false;
        }
        return true;
    }
    
    /**
     * Validate numeric
     */
    public function numeric($field, $value, $label = null) {
        $label = $label ?? ucfirst($field);
        if (!is_numeric($value)) {
            $this->errors[$field] = "$label must be a number";
            return false;
        }
        return true;
    }
    
    /**
     * Validate phone number
     */
    public function phone($field, $value, $label = null) {
        $label = $label ?? ucfirst($field);
        $pattern = '/^(\+256|0)[0-9]{9}$/';
        if (!preg_match($pattern, $value)) {
            $this->errors[$field] = "$label is not a valid phone number";
            return false;
        }
        return true;
    }
    
    /**
     * Validate date
     */
    public function date($field, $value, $label = null) {
        $label = $label ?? ucfirst($field);
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            $this->errors[$field] = "$label is not a valid date";
            return false;
        }
        return true;
    }
    
    /**
     * Validate password strength
     */
    public function password($field, $value, $label = null) {
        $label = $label ?? ucfirst($field);
        
        if (strlen($value) < PASSWORD_MIN_LENGTH) {
            $this->errors[$field] = "$label must be at least " . PASSWORD_MIN_LENGTH . " characters";
            return false;
        }
        
        if (!preg_match('/[A-Z]/', $value)) {
            $this->errors[$field] = "$label must contain at least one uppercase letter";
            return false;
        }
        
        if (!preg_match('/[a-z]/', $value)) {
            $this->errors[$field] = "$label must contain at least one lowercase letter";
            return false;
        }
        
        if (!preg_match('/[0-9]/', $value)) {
            $this->errors[$field] = "$label must contain at least one number";
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate match (for password confirmation)
     */
    public function match($field, $value, $match_value, $label = null, $match_label = null) {
        $label = $label ?? ucfirst($field);
        $match_label = $match_label ?? 'confirmation';
        
        if ($value !== $match_value) {
            $this->errors[$field] = "$label and $match_label do not match";
            return false;
        }
        return true;
    }
    
    /**
     * Validate admission number format
     */
    public function admissionNumber($field, $value, $label = null) {
        $label = $label ?? ucfirst($field);
        $pattern = '/^[A-Z0-9\/\-]+$/';
        if (!preg_match($pattern, $value)) {
            $this->errors[$field] = "$label format is invalid";
            return false;
        }
        return true;
    }
    
    /**
     * Validate amount (decimal)
     */
    public function amount($field, $value, $label = null) {
        $label = $label ?? ucfirst($field);
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $value) || $value <= 0) {
            $this->errors[$field] = "$label must be a valid positive amount";
            return false;
        }
        return true;
    }
    
    /**
     * Check if validation has errors
     */
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    /**
     * Get all errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get first error message
     */
    public function getFirstError() {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
    
    /**
     * Clear errors
     */
    public function clearErrors() {
        $this->errors = [];
    }
}
