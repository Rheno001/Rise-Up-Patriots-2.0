<?php
/**
 * Database Setup and Installation Script
 * 
 * This script initializes the database and creates necessary tables
 * Run this once to set up the database for the Rise-Up Patriots application
 */

require_once __DIR__ . '/../config/database.php';

// Set content type
header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rise-Up Patriots - Database Setup</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f7fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2d5a3d;
            text-align: center;
            margin-bottom: 30px;
        }
        .status {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid;
        }
        .success {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .info {
            background-color: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
        .btn {
            background-color: #2d5a3d;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
        }
        .btn:hover {
            background-color: #1a4028;
        }
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Rise-Up Patriots Database Setup</h1>
        
        <?php
        if (isset($_POST['install'])) {
            echo '<div class="info">Starting database installation...</div>';
            
            try {
                $database = new Database();
                
                // Initialize database
                if ($database->initializeDatabase()) {
                    echo '<div class="success">✓ Database and tables created successfully!</div>';
                    
                    // Test connection
                    $conn = $database->getConnection();
                    if ($conn) {
                        echo '<div class="success">✓ Database connection test passed!</div>';
                        
                        // Show created tables
                        $tables_query = "SHOW TABLES";
                        $stmt = $conn->prepare($tables_query);
                        $stmt->execute();
                        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        echo '<div class="info">Created tables:</div>';
                        echo '<pre>';
                        foreach ($tables as $table) {
                            echo "- $table\n";
                        }
                        echo '</pre>';
                        
                        echo '<div class="success">✓ Installation completed successfully!</div>';
                        echo '<div class="info">You can now access the admin panel with the default credentials:<br>';
                        echo '<strong>Username:</strong> admin<br>';
                        echo '<strong>Password:</strong> admin123</div>';
                        echo '<p><a href="../api/health.php" class="btn">Test API Health</a></p>';
                        echo '<p><a href="../../admin-login.html" class="btn">Admin Login</a></p>';
                        echo '<p><a href="../../registration.html" class="btn">Go to Registration Form</a></p>';
                        
                    } else {
                        echo '<div class="error">✗ Database connection test failed!</div>';
                    }
                } else {
                    echo '<div class="error">✗ Failed to initialize database!</div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="error">✗ Installation failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            ?>
            <div class="info">
                <h3>Database Installation</h3>
                <p>This will create the necessary database and tables for the Rise-Up Patriots registration system.</p>
                <p><strong>Database Configuration:</strong></p>
                <ul>
                    <li>Host: localhost</li>
                    <li>Database: rise_up_patriots</li>
                    <li>Username: root</li>
                    <li>Password: (empty - default WAMP setup)</li>
                </ul>
                <p><strong>Tables to be created:</strong></p>
                <ul>
                    <li><code>registrations</code> - Store user registration data</li>
                    <li><code>admin_logs</code> - Track system activities</li>
                    <li><code>admin_users</code> - Store admin user accounts for authentication</li>
                </ul>
                
                <div class="info" style="margin-top: 20px;">
                    <h4>Default Admin Account</h4>
                    <p>A default admin account will be created automatically:</p>
                    <ul>
                        <li><strong>Username:</strong> admin</li>
                        <li><strong>Email:</strong> admin@riseuppatriots.com</li>
                        <li><strong>Password:</strong> admin123</li>
                        <li><strong>Role:</strong> Super Admin</li>
                    </ul>
                    <p><strong>⚠️ Important:</strong> Please change the default password after your first login for security reasons.</p>
                </div>
            </div>
            
            <form method="post">
                <button type="submit" name="install" class="btn">Install Database</button>
            </form>
            
            <div class="info">
                <h3>Prerequisites:</h3>
                <ul>
                    <li>WAMP/XAMPP server running</li>
                    <li>MySQL service started</li>
                    <li>PHP with PDO MySQL extension enabled</li>
                </ul>
            </div>
            <?php
        }
        ?>
    </div>
</body>
</html>