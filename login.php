<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();
                
                $query = "SELECT u.*, r.role_name 
                         FROM users u 
                         JOIN roles r ON u.role_id = r.role_id 
                         WHERE u.username = :username AND u.is_active = 1";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (verifyPassword($password, $user['password_hash'])) {
                        // Set session variables
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['first_name'] = $user['first_name'];
                        $_SESSION['last_name'] = $user['last_name'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['role'] = $user['role_name'];
                        $_SESSION['role_id'] = $user['role_id'];
                        
                        // Redirect based on role
                        switch ($user['role_name']) {
                            case 'Admin':
                                header("Location: /Exam-Scheduling/admin/dashboard.php");
                                break;
                            case 'Student':
                                header("Location: /Exam-Scheduling/student/dashboard.php");
                                break;
                            case 'Faculty':
                                header("Location: /Exam-Scheduling/faculty/dashboard.php");
                                break;
                            default:
                                header("Location: /Exam-Scheduling/");
                        }
                        exit();
                    } else {
                        $error = 'Invalid username or password.';
                    }
                } else {
                    $error = 'Invalid username or password.';
                }
            } catch (Exception $e) {
                $error = 'Login failed. Please try again later.';
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Exam Scheduling System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/Exam-Scheduling/assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="fas fa-calendar-alt fa-3x text-primary mb-3"></i>
                <h2>Exam Scheduling System</h2>
                <p>Please sign in to your account</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="mb-3">
                    <label for="username" class="form-label">
                        <i class="fas fa-user"></i> Username
                    </label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                           required autofocus>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="rememberMe">
                    <label class="form-check-label" for="rememberMe">
                        Remember me
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
            
            <div class="text-center">
                <a href="/Exam-Scheduling/" class="text-decoration-none">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
            
            <!-- Demo Credentials -->
            <div class="mt-4 p-3 bg-light rounded">
                <small class="text-muted">
                    <strong>Demo Credentials:</strong><br>
                    Admin: username: <code>admin</code>, password: <code>admin123</code>
                </small>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Auto-focus on username field
        document.getElementById('username').focus();
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please enter both username and password.');
                return false;
            }
        });
    </script>
</body>
</html>
