<?php
/**
 * Database Setup Script
 * Run this once to create the database schema and initial data
 */

require_once 'database.php';

try {
    // Create database connection
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>Setting up Exam Scheduling Database...</h2>\n";
    
    // Read and execute SQL schema
    $sqlFile = __DIR__ . '/database_schema.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL schema file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $db->exec($statement);
                echo "✓ Executed: " . substr($statement, 0, 50) . "...\n<br>";
            } catch (Exception $e) {
                // Some statements might fail if tables already exist - that's okay
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "⚠ Warning: " . $e->getMessage() . "\n<br>";
                }
            }
        }
    }
    
    echo "<h3>✅ Database setup completed successfully!</h3>\n";
    echo "<p><a href='../login.php'>Go to Login Page</a></p>\n";
    echo "<p><strong>Default Admin Login:</strong><br>";
    echo "Email: admin@unilag.edu.ng<br>";
    echo "Password: admin123</p>\n";
    
} catch (Exception $e) {
    echo "<h3>❌ Error setting up database:</h3>\n";
    echo "<p>" . $e->getMessage() . "</p>\n";
    echo "<p>Please check your database configuration in config/database.php</p>\n";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - UNILAG Exam Scheduling</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 { color: #2c3e50; }
        h3 { color: #27ae60; }
        .error { color: #e74c3c; }
        .warning { color: #f39c12; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎓 UNILAG Exam Scheduling System</h1>
        <div id="output">
            <!-- PHP output will appear here -->
        </div>
    </div>
</body>
</html>
