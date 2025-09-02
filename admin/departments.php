<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Admin');

$pageTitle = "Department Management";

// Handle department creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setAlert('danger', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if ($action === 'create' || $action === 'update') {
                $departmentId = $_POST['department_id'] ?? null;
                $departmentName = sanitizeInput($_POST['department_name']);
                $departmentCode = sanitizeInput($_POST['department_code']);
                $headOfDepartment = sanitizeInput($_POST['head_of_department']);
                
                if ($action === 'create') {
                    $query = "INSERT INTO departments (department_name, department_code, head_of_department) 
                             VALUES (:department_name, :department_code, :head_of_department)";
                    $stmt = $db->prepare($query);
                } else {
                    $query = "UPDATE departments SET department_name = :department_name, department_code = :department_code, 
                             head_of_department = :head_of_department WHERE department_id = :department_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':department_id', $departmentId);
                }
                
                $stmt->bindParam(':department_name', $departmentName);
                $stmt->bindParam(':department_code', $departmentCode);
                $stmt->bindParam(':head_of_department', $headOfDepartment);
                
                $stmt->execute();
                
                setAlert('success', $action === 'create' ? 'Department created successfully.' : 'Department updated successfully.');
            } elseif ($action === 'delete') {
                $departmentId = intval($_POST['department_id']);
                
                // Check if department has courses or students
                $checkQuery = "SELECT 
                                 (SELECT COUNT(*) FROM courses WHERE department_id = :dept_id) as course_count,
                                 (SELECT COUNT(*) FROM students WHERE department_id = :dept_id) as student_count";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':dept_id', $departmentId);
                $checkStmt->execute();
                $counts = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($counts['course_count'] > 0 || $counts['student_count'] > 0) {
                    setAlert('warning', 'Cannot delete department. It has associated courses or students.');
                } else {
                    $query = "DELETE FROM departments WHERE department_id = :department_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':department_id', $departmentId);
                    $stmt->execute();
                    
                    setAlert('success', 'Department deleted successfully.');
                }
            }
        } catch (Exception $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }
    
    header("Location: departments.php");
    exit();
}

// Get departments with statistics
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT 
                d.*,
                COUNT(DISTINCT c.course_id) as course_count,
                COUNT(DISTINCT s.student_id) as student_count,
                COUNT(DISTINCT f.faculty_id) as faculty_count
              FROM departments d
              LEFT JOIN courses c ON d.department_id = c.department_id
              LEFT JOIN students s ON d.department_id = s.department_id
              LEFT JOIN faculty f ON d.department_id = f.department_id
              GROUP BY d.department_id
              ORDER BY d.department_name";
    
    $stmt = $db->query($query);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error loading departments: " . $e->getMessage();
}

$csrfToken = generateCSRFToken();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-university"></i> Department Management</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#departmentModal" onclick="openDepartmentModal()">
                <i class="fas fa-plus"></i> Add Department
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

<!-- Departments Grid -->
<div class="row">
    <?php if (!empty($departments)): ?>
        <?php foreach ($departments as $department): ?>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <strong><?php echo htmlspecialchars($department['department_code']); ?></strong>
                    </h5>
                </div>
                <div class="card-body">
                    <h6 class="card-title"><?php echo htmlspecialchars($department['department_name']); ?></h6>
                    <p class="card-text">
                        <strong>Head of Department:</strong><br>
                        <?php echo htmlspecialchars($department['head_of_department'] ?: 'Not assigned'); ?>
                    </p>
                    
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="bg-light p-2 rounded">
                                <h6 class="mb-0 text-primary"><?php echo $department['course_count']; ?></h6>
                                <small class="text-muted">Courses</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light p-2 rounded">
                                <h6 class="mb-0 text-success"><?php echo $department['student_count']; ?></h6>
                                <small class="text-muted">Students</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light p-2 rounded">
                                <h6 class="mb-0 text-info"><?php echo $department['faculty_count']; ?></h6>
                                <small class="text-muted">Faculty</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="btn-group w-100" role="group">
                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                onclick="editDepartment(<?php echo htmlspecialchars(json_encode($department)); ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" 
                                onclick="deleteDepartment(<?php echo $department['department_id']; ?>)"
                                <?php echo ($department['course_count'] > 0 || $department['student_count'] > 0) ? 'disabled title="Cannot delete: has courses or students"' : ''; ?>>
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-university fa-5x text-muted mb-4"></i>
                <h4 class="text-muted">No Departments Found</h4>
                <p class="text-muted">Start by creating your first department.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#departmentModal" onclick="openDepartmentModal()">
                    <i class="fas fa-plus"></i> Add First Department
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Department Modal -->
<div class="modal fade" id="departmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="departmentModalTitle">Add Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="departmentForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="department_id" id="departmentId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="departmentName" class="form-label">Department Name *</label>
                        <input type="text" class="form-control" id="departmentName" name="department_name" required>
                        <div class="form-text">e.g., Computer Science, Mathematics, Physics</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="departmentCode" class="form-label">Department Code *</label>
                        <input type="text" class="form-control" id="departmentCode" name="department_code" required maxlength="10">
                        <div class="form-text">e.g., CS, MATH, PHY (max 10 characters)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="headOfDepartment" class="form-label">Head of Department</label>
                        <input type="text" class="form-control" id="headOfDepartment" name="head_of_department">
                        <div class="form-text">e.g., Prof. John Smith, Dr. Jane Doe</div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Department</button>
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
                <p>Are you sure you want to delete this department? This action cannot be undone.</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    You can only delete departments that have no courses or students assigned.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="department_id" id="deleteDepartmentId">
                    <button type="submit" class="btn btn-danger">Delete Department</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openDepartmentModal() {
    document.getElementById('departmentModalTitle').textContent = 'Add Department';
    document.getElementById('formAction').value = 'create';
    document.getElementById('departmentForm').reset();
    document.getElementById('departmentId').value = '';
}

function editDepartment(department) {
    document.getElementById('departmentModalTitle').textContent = 'Edit Department';
    document.getElementById('formAction').value = 'update';
    document.getElementById('departmentId').value = department.department_id;
    document.getElementById('departmentName').value = department.department_name;
    document.getElementById('departmentCode').value = department.department_code;
    document.getElementById('headOfDepartment').value = department.head_of_department || '';
    
    new bootstrap.Modal(document.getElementById('departmentModal')).show();
}

function deleteDepartment(departmentId) {
    document.getElementById('deleteDepartmentId').value = departmentId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Auto-generate department code from name
document.getElementById('departmentName').addEventListener('input', function() {
    const name = this.value;
    const codeField = document.getElementById('departmentCode');
    
    if (name && !codeField.value) {
        // Generate code from first letters of words
        const words = name.split(' ');
        let code = '';
        words.forEach(word => {
            if (word.length > 0) {
                code += word[0].toUpperCase();
            }
        });
        codeField.value = code.substring(0, 10); // Limit to 10 characters
    }
});
</script>

<?php include '../includes/footer.php'; ?>
