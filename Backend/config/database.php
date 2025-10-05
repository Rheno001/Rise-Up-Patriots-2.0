<?php
/**
 * Database Configuration
 * 
 * This file contains database connection settings for the Rise-Up Patriots application
 */

class Database {
    private $host = "localhost";
    private $db_name = "rise_up_patriots";
    private $username = "root";
    private $password = "";
    private $conn;

    /**
     * Get database connection
     * 
     * @return PDO|null Database connection object or null on failure
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            
            // Set PDO attributes
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("set names utf8");
            
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            return null;
        }

        return $this->conn;
    }

    /**
     * Create database and tables if they don't exist
     */
    public function initializeDatabase() {
        try {
            // First, connect without specifying database
            $temp_conn = new PDO(
                "mysql:host=" . $this->host,
                $this->username,
                $this->password
            );
            $temp_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Create database if it doesn't exist
            $temp_conn->exec("CREATE DATABASE IF NOT EXISTS `{$this->db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Now connect to the specific database
            $this->conn = $this->getConnection();
            
            if ($this->conn) {
                $this->createTables();
                return true;
            }
            
        } catch(PDOException $exception) {
            error_log("Database initialization error: " . $exception->getMessage());
            return false;
        }
        
        return false;
    }

    /**
     * Create necessary tables
     */
    private function createTables() {
        // Create registrations table
        $sql = "CREATE TABLE IF NOT EXISTS registrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(10) NOT NULL,
            gender VARCHAR(20) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            age_range VARCHAR(20) NOT NULL,
            attendance_type VARCHAR(20) NOT NULL,
            country_code VARCHAR(5) NOT NULL,
            country_name VARCHAR(100) NOT NULL,
            state_of_origin VARCHAR(100) NOT NULL,
            how_did_you_hear VARCHAR(50) NOT NULL,
            registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('active', 'cancelled') DEFAULT 'active',
            INDEX idx_email (email),
            INDEX idx_registration_date (registration_date),
            INDEX idx_country (country_code),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->exec($sql);

        // Create admin_logs table for tracking actions
        $sql_logs = "CREATE TABLE IF NOT EXISTS admin_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->exec($sql_logs);

        // Create admin_users table for admin authentication
        $sql_admin = "CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            role ENUM('admin', 'super_admin') DEFAULT 'admin',
            is_active BOOLEAN DEFAULT TRUE,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_email (email),
            INDEX idx_role (role),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->exec($sql_admin);

        // Create default admin user if none exists
        $this->createDefaultAdmin();
    }

    /**
     * Create default admin user if none exists
     */
    private function createDefaultAdmin() {
        try {
            // Check if any admin users exist
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM admin_users");
            $stmt->execute();
            $count = $stmt->fetchColumn();

            if ($count == 0) {
                // Create default admin user
                $default_password = 'admin123'; // This should be changed after first login
                $password_hash = password_hash($default_password, PASSWORD_DEFAULT);
                
                $stmt = $this->conn->prepare("
                    INSERT INTO admin_users (username, email, password_hash, full_name, role) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    'admin',
                    'admin@riseuppatriots.com',
                    $password_hash,
                    'System Administrator',
                    'super_admin'
                ]);

                error_log("Default admin user created - Username: admin, Password: admin123");
            }
        } catch(PDOException $exception) {
            error_log("Error creating default admin: " . $exception->getMessage());
        }
    }
}
?>