<?php
/**
 * Encryption/Decryption Class
 */

class Encryption {
    private $key;
    private $cipher = 'aes-256-cbc';
    
    public function __construct($key = null) {
        $this->key = $key ?? ENCRYPTION_KEY;
    }
    
    /**
     * Encrypt data
     */
    public function encrypt($data) {
        $iv_length = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        $encrypted = openssl_encrypt(
            $data,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($encrypted === false) {
            return false;
        }
        
        // Combine IV and encrypted data
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt data
     */
    public function decrypt($data) {
        $data = base64_decode($data);
        
        $iv_length = openssl_cipher_iv_length($this->cipher);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        return $decrypted;
    }
    
    /**
     * Encrypt file
     */
    public function encryptFile($source_file, $destination_file = null) {
        if (!file_exists($source_file)) {
            return false;
        }
        
        $destination_file = $destination_file ?? $source_file . '.enc';
        
        $data = file_get_contents($source_file);
        $encrypted = $this->encrypt($data);
        
        if ($encrypted === false) {
            return false;
        }
        
        return file_put_contents($destination_file, $encrypted) !== false;
    }
    
    /**
     * Decrypt file
     */
    public function decryptFile($source_file, $destination_file = null) {
        if (!file_exists($source_file)) {
            return false;
        }
        
        $destination_file = $destination_file ?? str_replace('.enc', '', $source_file);
        
        $encrypted = file_get_contents($source_file);
        $decrypted = $this->decrypt($encrypted);
        
        if ($decrypted === false) {
            return false;
        }
        
        return file_put_contents($destination_file, $decrypted) !== false;
    }
    
    /**
     * Generate hash
     */
    public function hash($data) {
        return hash('sha256', $data);
    }
    
    /**
     * Verify hash
     */
    public function verifyHash($data, $hash) {
        return hash_equals($hash, $this->hash($data));
    }
    
    /**
     * Generate secure token
     */
    public function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
}
