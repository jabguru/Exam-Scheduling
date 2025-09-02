<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Admin');

$pageTitle = "Lecturer Course Assignments";

// Handle course assignment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setAlert('danger', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if ($action === 'assign') {
                $lecturerId = intval($_POST['lecturer_id']);
                $courseId = intval($_POST['course_id']);
                
                // Check if assignment already exists
                $checkQuery = "SELECT COUNT(*) FROM lecturer_course_assignments WHERE lecturer_id = :lecturer_id AND course_id = :course_id";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':lecturer_id', $lecturerId);
                $checkStmt->bindParam(':course_id', $courseId);
                $checkStmt->execute();
                
                if ($checkStmt->fetchColumn() > 0) {
                    setAlert('warning', 'This lecturer is already assigned to this course.');
                } else {
                    $query = "INSERT INTO lecturer_course_assignments (lecturer_id, course_id, assigned_by, assigned_date) 
                             VALUES (:lecturer_id, :course_id, :assigned_by, NOW())";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':lecturer_id', $lecturerId);
                    $stmt->bindParam(':course_id', $courseId);
                    $stmt->bindParam(':assigned_by', $_SESSION['user_id']);
                    $stmt->execute();
                    
                    setAlert('success', 'Course assigned to lecturer successfully!');
                }
                
            } elseif ($action === 'remove') {
                $assignmentId = intval($_POST['assignment_id']);
                
                $query = "DELETE FROM lecturer_course_assignments WHERE assignment_id = :assignment_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':assignment_id', $assignmentId);
                $stmt->execute();
                
                setAlert('success', 'Course assignment removed successfully!');
            }
        } catch (PDOException $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
        
        header('Location: lecturer_courses.php');
        exit;
    }
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$recordsPerPage = 20;
$offset = ($page - 1) * $recordsPerPage;

// Search and filter
$search = sanitizeInput($_GET['search'] ?? '');
$lecturerFilter = intval($_GET['lecturer_filter'] ?? 0);
$departmentFilter = intval($_GET['department_filter'] ?? 0);

$whereClause = " WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (c.course_code LIKE :search OR c.course_title LIKE :search OR 
                           CONCAT(u.first_name, ' ', u.last_name) LIKE :search OR l.staff_id LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($lecturerFilter > 0) {
    $whereClause .= " AND lca.lecturer_id = :lecturer_filter";
    $params[':lecturer_filter'] = $lecturerFilter;
}

if ($departmentFilter > 0) {
    $whereClause .= " AND d.department_id = :department_filter";
    $params[':department_filter'] = $departmentFilter;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Count total records
    $countQuery = "SELECT COUNT(*) FROM lecturer_course_assignments lca
                   JOIN lecturers l ON lca.lecturer_id = l.lecturer_id
                   JOIN users u ON l.user_id = u.user_id
                   JOIN courses c ON lca.course_id = c.course_id
                   JOIN departments d ON c.department_id = d.department_id" . $whereClause;
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    
    // Calculate pagination
    $pagination = paginate($page, $totalRecords, $recordsPerPage);
    
    // Get assignments
    $query = "SELECT lca.*, l.staff_id, u.first_name, u.last_name, 
                     c.course_code, c.course_title, c.credit_units,
                     d.department_name, ld.department_name as lecturer_department
              FROM lecturer_course_assignments lca
              JOIN lecturers l ON lca.lecturer_id = l.lecturer_id
              JOIN users u ON l.user_id = u.user_id
              JOIN departments ld ON l.department_id = ld.department_id
              JOIN courses c ON lca.course_id = c.course_id
              JOIN departments d ON c.department_id = d.department_id" . 
              $whereClause . 
              " ORDER BY lca.assigned_date DESC
              LIMIT $recordsPerPage OFFSET $offset";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get lecturers for dropdown
    $lecturerQuery = "SELECT l.lecturer_id, l.staff_id, u.first_name, u.last_name, d.department_name
                      FROM lecturers l 
                      JOIN users u ON l.user_id = u.user_id
                      JOIN departments d ON l.department_id = d.department_id
                      WHERE u.is_active = 1
                      ORDER BY u.first_name, u.last_name";
    $lecturerStmt = $db->query($lecturerQuery);
    $lecturers = $lecturerStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get courses for dropdown
    $courseQuery = "SELECT c.*, d.department_name 
                    FROM courses c 
                    JOIN departments d ON c.department_id = d.department_id 
                    ORDER BY c.course_code";
    $courseStmt = $db->query($courseQuery);
    $courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get departments for filter
    $deptQuery = "SELECT * FROM departments ORDER BY department_name";
    $deptStmt = $db->query($deptQuery);
    $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    setAlert('danger', 'Error loading assignments: ' . $e->getMessage());
    $assignments = [];
    $lecturers = [];
    $courses = [];
    $departments = [];
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-user-tie"></i> Lecturer Course Assignments</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignmentModal">
                <i class="fas fa-plus"></i> Assign Course
            </button>
        </div>
    </div>
</div>

<!-- Search and Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Search & Filter</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Lecturer name, course code, or title">
            </div>
            <div class="col-md-3">
                <label for="lecturerFilter" class="form-label">Lecturer</label>
                <select class="form-control" id="lecturerFilter" name="lecturer_filter">
                    <option value="">All Lecturers</option>
                    <?php foreach ($lecturers as $lecturer): ?>
                    <option value="<?php echo $lecturer['lecturer_id']; ?>" 
                            <?php echo $lecturerFilter == $lecturer['lecturer_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name'] . ' (' . $lecturer['staff_id'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="departmentFilter" class="form-label">Department</label>
                <select class="form-control" id="departmentFilter" name="department_filter">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $department): ?>
                    <option value="<?php echo $department['department_id']; ?>" 
                            <?php echo $departmentFilter == $department['department_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($department['department_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="lecturer_courses.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Assignments Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Course Assignments (<?php echo $totalRecords; ?> total)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($assignments)): ?>
        <div class="text-center py-4">
            <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
            <p class="text-muted">No course assignments found.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Lecturer</th>
                        <th>Lecturer Department</th>
                        <th>Course</th>
                        <th>Course Department</th>
                        <th>Credit Units</th>
                        <th>Assigned Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $assignment): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?></strong>
                            <br><small class="text-muted"><?php echo htmlspecialchars($assignment['staff_id']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($assignment['lecturer_department']); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($assignment['course_code']); ?></strong>
                            <br><small class="text-muted"><?php echo htmlspecialchars($assignment['course_title']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($assignment['department_name']); ?></td>
                        <td><?php echo $assignment['credit_units']; ?></td>
                        <td><?php echo date('M j, Y', strtotime($assignment['assigned_date'])); ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    onclick="removeAssignment(<?php echo $assignment['assignment_id']; ?>)">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer">
        <?php include '../includes/pagination.php'; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Assignment Modal -->
<div class="modal fade" id="assignmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Course to Lecturer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="assign">
                    
                    <div class="mb-3">
                        <label for="lecturerId" class="form-label">Lecturer *</label>
                        <select class="form-control" id="lecturerId" name="lecturer_id" required>
                            <option value="">Select Lecturer</option>
                            <?php foreach ($lecturers as $lecturer): ?>
                            <option value="<?php echo $lecturer['lecturer_id']; ?>">
                                <?php echo htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name'] . ' (' . $lecturer['staff_id'] . ') - ' . $lecturer['department_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="courseId" class="form-label">Course *</label>
                        <select class="form-control" id="courseId" name="course_id" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['course_id']; ?>">
                                <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_title'] . ' (' . $course['department_name'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Remove Confirmation Modal -->
<div class="modal fade" id="removeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Remove</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove this course assignment? The lecturer will no longer be assigned to this course.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="assignment_id" id="removeAssignmentId">
                    <button type="submit" class="btn btn-danger">Remove Assignment</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function removeAssignment(assignmentId) {
    document.getElementById('removeAssignmentId').value = assignmentId;
    new bootstrap.Modal(document.getElementById('removeModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
