<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = "User Profile";

// Get user information based on role
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user details
    $query = "SELECT u.*, r.role_name FROM users u 
              JOIN roles r ON u.role_id = r.role_id 
              WHERE u.user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found.');
    }
    
    // Get role-specific information
    $roleSpecificInfo = null;
    if ($user['role_name'] === 'Student') {
        $query = "SELECT s.*, d.department_name FROM students s 
                  JOIN departments d ON s.department_id = d.department_id 
                  WHERE s.user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        $roleSpecificInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($user['role_name'] === 'Faculty') {
        $query = "SELECT f.*, d.department_name FROM faculty f 
                  JOIN departments d ON f.department_id = d.department_id 
                  WHERE f.user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        $roleSpecificInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $error = "Error loading profile: " . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setAlert('danger', 'Invalid request. Please try again.');
    } else {
        try {
            $firstName = sanitizeInput($_POST['first_name']);
            $lastName = sanitizeInput($_POST['last_name']);
            $email = sanitizeInput($_POST['email']);
            $phoneNumber = sanitizeInput($_POST['phone_number']);
            
            // Update user table
            $query = "UPDATE users SET first_name = :first_name, last_name = :last_name, 
                     email = :email, phone_number = :phone_number WHERE user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':first_name', $firstName);
            $stmt->bindParam(':last_name', $lastName);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone_number', $phoneNumber);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            // Update session
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name'] = $lastName;
            
            setAlert('success', 'Profile updated successfully.');
            
        } catch (Exception $e) {
            setAlert('danger', 'Error updating profile: ' . $e->getMessage());
        }
    }
    
    header("Location: profile.php");
    exit();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setAlert('danger', 'Invalid request. Please try again.');
    } else {
        try {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            // Verify current password
            $query = "SELECT password_hash FROM users WHERE user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            $currentHash = $stmt->fetch(PDO::FETCH_ASSOC)['password_hash'];
            
            if (!password_verify($currentPassword, $currentHash)) {
                setAlert('danger', 'Current password is incorrect.');
            } elseif ($newPassword !== $confirmPassword) {
                setAlert('danger', 'New passwords do not match.');
            } elseif (strlen($newPassword) < 6) {
                setAlert('danger', 'New password must be at least 6 characters long.');
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $query = "UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':password_hash', $newHash);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                
                setAlert('success', 'Password changed successfully.');
            }
            
        } catch (Exception $e) {
            setAlert('danger', 'Error changing password: ' . $e->getMessage());
        }
    }
    
    header("Location: profile.php");
    exit();
}

$csrfToken = generateCSRFToken();

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-user"></i> User Profile</h1>
            <a href="<?php echo strtolower($user['role_name']); ?>/dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <!-- Profile Information -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-user-edit"></i> Profile Information</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="firstName" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="firstName" name="first_name" 
                                   value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lastName" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="lastName" name="last_name" 
                                   value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phoneNumber" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phoneNumber" name="phone_number" 
                                   value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['role_name']); ?>" readonly>
                    </div>
                    
                    <?php if ($roleSpecificInfo): ?>
                    <div class="row">
                        <?php if ($user['role_name'] === 'Student'): ?>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Matric Number</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($roleSpecificInfo['matric_number']); ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($roleSpecificInfo['department_name']); ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Academic Level</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($roleSpecificInfo['academic_level']); ?>" readonly>
                        </div>
                        <?php elseif ($user['role_name'] === 'Faculty'): ?>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Employee ID</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($roleSpecificInfo['employee_id']); ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($roleSpecificInfo['department_name']); ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Position</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($roleSpecificInfo['position']); ?>" readonly>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Change Password -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-key"></i> Change Password</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="mb-3">
                        <label for="currentPassword" class="form-label">Current Password *</label>
                        <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">New Password *</label>
                        <input type="password" class="form-control" id="newPassword" name="new_password" required minlength="6">
                        <div class="form-text">Minimum 6 characters</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirmPassword" class="form-label">Confirm New Password *</label>
                        <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Account Information -->
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-info-circle"></i> Account Information</h5>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <strong>Account Created:</strong><br>
                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></small>
                </div>
                <div class="mb-2">
                    <strong>Last Updated:</strong><br>
                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($user['updated_at'])); ?></small>
                </div>
                <div class="mb-2">
                    <strong>Account Status:</strong><br>
                    <span class="badge <?php echo $user['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirmPassword').addEventListener('input', function() {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
