<?php
/**
 * Database Configuration
 * KIU Automated Tuition Verification & Green Card System
 */

class Database {
    private $host = "localhost";
    private $db_name = "Greencard_system";
    private $username = "root";
    private $password = "";
    private $conn;

    /**
     * Get database connection
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                )
            );
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed");
        }

        return $this->conn;
    }

    /**
     * Close database connection
     */
    public function closeConnection() {
        $this->conn = null;
    }
}
