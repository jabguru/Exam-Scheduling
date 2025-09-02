<?php
// Password reset script for admin user
// Use this if you need to reset the admin password

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h2>Admin Password Reset</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($newPassword) || empty($confirmPassword)) {
        echo "<div style='color: #721c24; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; border-radius: 5px;'>";
        echo "<strong>Error:</strong> Please enter both password fields.";
        echo "</div>";
    } elseif ($newPassword !== $confirmPassword) {
        echo "<div style='color: #721c24; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; border-radius: 5px;'>";
        echo "<strong>Error:</strong> Passwords do not match.";
        echo "</div>";
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $query = "UPDATE users SET password_hash = :password_hash WHERE username = 'admin'";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':password_hash', $passwordHash);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                echo "<div style='color: green; padding: 10px; background: #e8f5e8; border: 1px solid #4caf50; margin: 10px 0; border-radius: 5px;'>";
                echo "<strong>✓ Success!</strong> Admin password has been reset successfully.";
                echo "</div>";
                
                echo "<p><strong>New Admin Credentials:</strong></p>";
                echo "<ul>";
                echo "<li>Username: <code>admin</code></li>";
                echo "<li>Password: <code>" . htmlspecialchars($newPassword) . "</code></li>";
                echo "</ul>";
                
                echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
            } else {
                echo "<div style='color: #721c24; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; border-radius: 5px;'>";
                echo "<strong>Error:</strong> Admin user not found in database.";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div style='color: #721c24; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; border-radius: 5px;'>";
            echo "<strong>Error:</strong> " . $e->getMessage();
            echo "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Password Reset</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="password"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="warning">
        <strong>Warning:</strong> This script should only be used if you've lost access to the admin account. 
        Delete this file after use for security reasons.
    </div>
    
    <form method="POST">
        <div class="form-group">
            <label for="new_password">New Password:</label>
            <input type="password" id="new_password" name="new_password" required minlength="6">
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
        </div>
        
        <button type="submit">Reset Admin Password</button>
    </form>
    
    <p><a href="index.php">← Back to Home</a></p>
</body>
</html>
