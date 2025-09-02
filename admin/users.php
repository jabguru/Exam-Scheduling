<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Admin');

$pageTitle = "User Management";

// Handle user creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setAlert('danger', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if ($action === 'create' || $action === 'update') {
                $userId = $_POST['user_id'] ?? null;
                $username = sanitizeInput($_POST['username']);
                $email = sanitizeInput($_POST['email']);
                $firstName = sanitizeInput($_POST['first_name']);
                $lastName = sanitizeInput($_POST['last_name']);
                $phoneNumber = sanitizeInput($_POST['phone_number']);
                $roleId = intval($_POST['role_id']);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                $password = $_POST['password'] ?? '';
                
                if ($action === 'create') {
                    if (empty($password)) {
                        throw new Exception('Password is required for new users.');
                    }
                    
                    $passwordHash = hashPassword($password);
                    
                    $query = "INSERT INTO users (username, email, password_hash, first_name, last_name, phone_number, role_id, is_active) 
                             VALUES (:username, :email, :password_hash, :first_name, :last_name, :phone_number, :role_id, :is_active)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':password_hash', $passwordHash);
                } else {
                    $query = "UPDATE users SET username = :username, email = :email, first_name = :first_name, 
                             last_name = :last_name, phone_number = :phone_number, role_id = :role_id, is_active = :is_active";
                    
                    if (!empty($password)) {
                        $passwordHash = hashPassword($password);
                        $query .= ", password_hash = :password_hash";
                    }
                    
                    $query .= " WHERE user_id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $userId);
                    
                    if (!empty($password)) {
                        $stmt->bindParam(':password_hash', $passwordHash);
                    }
                }
                
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':first_name', $firstName);
                $stmt->bindParam(':last_name', $lastName);
                $stmt->bindParam(':phone_number', $phoneNumber);
                $stmt->bindParam(':role_id', $roleId);
                $stmt->bindParam(':is_active', $isActive);
                
                $stmt->execute();
                
                // If creating a new user, get the user ID and create additional records if needed
                if ($action === 'create') {
                    $newUserId = $db->lastInsertId();
                    
                    // If user is a student (role_id = 2), create student record
                    if ($roleId == 2) {
                        // Generate matric number (you can customize this logic)
                        $year = date('Y');
                        $randomNumber = str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                        $matricNumber = $year . $randomNumber;
                        
                        // Check if matric number already exists and regenerate if needed
                        do {
                            $checkMatric = $db->prepare("SELECT COUNT(*) FROM students WHERE matric_number = ?");
                            $checkMatric->execute([$matricNumber]);
                            if ($checkMatric->fetchColumn() > 0) {
                                $randomNumber = str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
                                $matricNumber = $year . $randomNumber;
                            } else {
                                break;
                            }
                        } while (true);
                        
                        // Default to first department and 100 level
                        $defaultDeptQuery = "SELECT department_id FROM departments ORDER BY department_id LIMIT 1";
                        $defaultDeptStmt = $db->query($defaultDeptQuery);
                        $defaultDepartmentId = $defaultDeptStmt->fetchColumn();
                        
                        $studentQuery = "INSERT INTO students (user_id, matric_number, department_id, academic_level, current_semester, entry_year) 
                                        VALUES (:user_id, :matric_number, :department_id, '100', 'First', :entry_year)";
                        $studentStmt = $db->prepare($studentQuery);
                        $studentStmt->bindParam(':user_id', $newUserId);
                        $studentStmt->bindParam(':matric_number', $matricNumber);
                        $studentStmt->bindParam(':department_id', $defaultDepartmentId);
                        $studentStmt->bindParam(':entry_year', $year);
                        $studentStmt->execute();
                    }
                    
                    // If user is faculty (role_id = 3), create faculty record
                    elseif ($roleId == 3) {
                        // Generate staff ID
                        $staffId = 'STAFF' . date('Y') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                        
                        // Check if staff ID already exists and regenerate if needed
                        do {
                            $checkStaff = $db->prepare("SELECT COUNT(*) FROM faculty WHERE staff_id = ?");
                            $checkStaff->execute([$staffId]);
                            if ($checkStaff->fetchColumn() > 0) {
                                $staffId = 'STAFF' . date('Y') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                            } else {
                                break;
                            }
                        } while (true);
                        
                        // Default to first department
                        $defaultDeptQuery = "SELECT department_id FROM departments ORDER BY department_id LIMIT 1";
                        $defaultDeptStmt = $db->query($defaultDeptQuery);
                        $defaultDepartmentId = $defaultDeptStmt->fetchColumn();
                        
                        $facultyQuery = "INSERT INTO faculty (user_id, staff_id, department_id, designation) 
                                        VALUES (:user_id, :staff_id, :department_id, 'Lecturer')";
                        $facultyStmt = $db->prepare($facultyQuery);
                        $facultyStmt->bindParam(':user_id', $newUserId);
                        $facultyStmt->bindParam(':staff_id', $staffId);
                        $facultyStmt->bindParam(':department_id', $defaultDepartmentId);
                        $facultyStmt->execute();
                    }
                }
                
                setAlert('success', $action === 'create' ? 'User created successfully.' : 'User updated successfully.');
            } elseif ($action === 'delete') {
                $userId = intval($_POST['user_id']);
                
                $query = "UPDATE users SET is_active = 0 WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $userId);
                $stmt->execute();
                
                setAlert('success', 'User deactivated successfully.');
            }
        } catch (Exception $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }
    
    header("Location: users.php");
    exit();
}

// Get users with pagination
$page = intval($_GET['page'] ?? 1);
$search = sanitizeInput($_GET['search'] ?? '');
$recordsPerPage = 10;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get roles for dropdown
    $roleQuery = "SELECT * FROM roles ORDER BY role_name";
    $roleStmt = $db->query($roleQuery);
    $roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count total records
    $countQuery = "SELECT COUNT(*) as total FROM users u 
                   JOIN roles r ON u.role_id = r.role_id";
    $whereClause = "";
    
    if (!empty($search)) {
        $whereClause = " WHERE u.username LIKE :search OR u.email LIKE :search OR 
                         CONCAT(u.first_name, ' ', u.last_name) LIKE :search OR r.role_name LIKE :search";
        $countQuery .= $whereClause;
    }
    
    $countStmt = $db->prepare($countQuery);
    if (!empty($search)) {
        $searchParam = "%$search%";
        $countStmt->bindParam(':search', $searchParam);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calculate pagination
    $pagination = paginate($page, $totalRecords, $recordsPerPage);
    
    // Get users
    $query = "SELECT u.*, r.role_name 
              FROM users u 
              JOIN roles r ON u.role_id = r.role_id" . 
              $whereClause . 
              " ORDER BY u.created_at DESC 
              LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    if (!empty($search)) {
        $stmt->bindParam(':search', $searchParam);
    }
    $stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error loading users: " . $e->getMessage();
}

$csrfToken = generateCSRFToken();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-users"></i> User Management</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openUserModal()">
                <i class="fas fa-user-plus"></i> Add User
            </button>
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

<!-- Search and Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by username, email, name, or role..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="users.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Users (<?php echo number_format($totalRecords); ?> total)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($users)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($user['role_name']); ?></span>
                                </td>
                                <td>
                                    <span class="badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($user['created_at']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete" 
                                            onclick="deleteUser(<?php echo $user['user_id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($pagination['totalPages'] > 1): ?>
                <nav aria-label="Page navigation" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $pagination['page'] <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $pagination['page'] - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                        <li class="page-item <?php echo $i == $pagination['page'] ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $pagination['page'] + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No users found</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="userForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="user_id" id="userId">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="firstName" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="firstName" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lastName" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="lastName" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phoneNumber" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phoneNumber" name="phone_number">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="roleId" class="form-label">Role *</label>
                            <select class="form-control" id="roleId" name="role_id" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['role_id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password <span id="passwordRequired">*</span></label>
                        <input type="password" class="form-control" id="password" name="password">
                        <small class="form-text text-muted" id="passwordHelp">Leave blank to keep current password when editing.</small>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="isActive" name="is_active" checked>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to deactivate this user? This action can be reversed later.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="submit" class="btn btn-danger">Deactivate User</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openUserModal() {
    document.getElementById('userModalTitle').textContent = 'Add User';
    document.getElementById('formAction').value = 'create';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('passwordRequired').style.display = 'inline';
    document.getElementById('passwordHelp').style.display = 'none';
    document.getElementById('password').required = true;
}

function editUser(user) {
    document.getElementById('userModalTitle').textContent = 'Edit User';
    document.getElementById('formAction').value = 'update';
    document.getElementById('userId').value = user.user_id;
    document.getElementById('username').value = user.username;
    document.getElementById('email').value = user.email;
    document.getElementById('firstName').value = user.first_name;
    document.getElementById('lastName').value = user.last_name;
    document.getElementById('phoneNumber').value = user.phone_number || '';
    document.getElementById('roleId').value = user.role_id;
    document.getElementById('isActive').checked = user.is_active == 1;
    document.getElementById('passwordRequired').style.display = 'none';
    document.getElementById('passwordHelp').style.display = 'block';
    document.getElementById('password').required = false;
    document.getElementById('password').value = '';
    
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

function deleteUser(userId) {
    document.getElementById('deleteUserId').value = userId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
