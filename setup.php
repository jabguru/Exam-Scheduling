<?php
// Database initialization script
// Run this script once to set up the database

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h2>Exam Scheduling System - Database Setup</h2>";

try {
    // Create database connection
    $host = "localhost";
    $username = "root";
    $password = "";
    
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Read and execute SQL schema (excluding the admin user insert)
    $sql = file_get_contents('config/database_schema.sql');
    
    // Remove the admin user insert statement from the SQL
    $sql = preg_replace('/-- Create default admin user.*?VALUES.*?;/s', '', $sql);
    
    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    // Now create the admin user with proper password hash
    $adminUsername = 'admin';
    $adminEmail = 'admin@unilag.edu.ng';
    $adminPassword = 'admin123';
    $adminPasswordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
    
    // Switch to the exam_scheduling database
    $pdo->exec("USE exam_scheduling");
    
    // Check if admin user already exists
    $checkAdmin = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $checkAdmin->execute([$adminUsername]);
    
    if ($checkAdmin->fetchColumn() == 0) {
        // Insert admin user
        $insertAdmin = $pdo->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, role_id) VALUES (?, ?, ?, 'System', 'Administrator', 1)");
        $insertAdmin->execute([$adminUsername, $adminEmail, $adminPasswordHash]);
        
        echo "<div style='color: green; padding: 10px; background: #e8f5e8; border: 1px solid #4caf50; margin: 10px 0; border-radius: 5px;'>";
        echo "<strong>✓ Success!</strong> Database and tables created successfully. Admin user created.";
        echo "</div>";
    } else {
        // Update existing admin user with correct password
        $updateAdmin = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
        $updateAdmin->execute([$adminPasswordHash, $adminUsername]);
        
        echo "<div style='color: green; padding: 10px; background: #e8f5e8; border: 1px solid #4caf50; margin: 10px 0; border-radius: 5px;'>";
        echo "<strong>✓ Success!</strong> Database setup complete. Admin user password updated.";
        echo "</div>";
    }
    
    echo "<h3>Database Setup Complete</h3>";
    echo "<p><strong>Default Admin Account:</strong></p>";
    echo "<ul>";
    echo "<li>Username: <code>admin</code></li>";
    echo "<li>Password: <code>admin123</code></li>";
    echo "</ul>";
    
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>Note:</strong> The password has been properly hashed and should work correctly now.";
    echo "</div>";
    
    echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
    
} catch (PDOException $e) {
    echo "<div style='color: #721c24; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>✗ Error:</strong> " . $e->getMessage();
    echo "</div>";
    
    echo "<h3>Setup Instructions:</h3>";
    echo "<ol>";
    echo "<li>Make sure XAMPP is running (Apache and MySQL)</li>";
    echo "<li>Ensure MySQL is accessible on localhost:3306</li>";
    echo "<li>Check that the MySQL root user has the correct permissions</li>";
    echo "<li>Try refreshing this page after ensuring the above conditions</li>";
    echo "</ol>";
}
?>
