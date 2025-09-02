<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Faculty');

$pageTitle = "Exam Management";

// Get faculty information
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get faculty details
    $query = "SELECT f.*, d.department_name, u.first_name, u.last_name 
              FROM faculty f 
              JOIN departments d ON f.department_id = d.department_id
              JOIN users u ON f.user_id = u.user_id
              WHERE f.user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$faculty) {
        throw new Exception('Faculty profile not found.');
    }
    
} catch (Exception $e) {
    setAlert('danger', 'Error: ' . $e->getMessage());
    header("Location: dashboard.php");
    exit();
}

// Handle exam operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setAlert('danger', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            if ($action === 'create' || $action === 'update') {
                $examId = $_POST['exam_id'] ?? null;
                $courseId = intval($_POST['course_id']);
                $examType = sanitizeInput($_POST['exam_type']);
                $examInstructions = sanitizeInput($_POST['exam_instructions']);
                $duration = intval($_POST['duration']);
                $totalMarks = intval($_POST['total_marks']);
                
                if ($action === 'create') {
                    $query = "INSERT INTO examinations (course_id, exam_type, exam_instructions, duration_minutes, total_marks, created_by) 
                             VALUES (:course_id, :exam_type, :exam_instructions, :duration_minutes, :total_marks, :created_by)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':created_by', $faculty['faculty_id']);
                } else {
                    $query = "UPDATE examinations SET course_id = :course_id, exam_type = :exam_type, 
                             exam_instructions = :exam_instructions, duration_minutes = :duration_minutes, 
                             total_marks = :total_marks WHERE exam_id = :exam_id AND created_by = :created_by";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':exam_id', $examId);
                    $stmt->bindParam(':created_by', $faculty['faculty_id']);
                }
                
                $stmt->bindParam(':course_id', $courseId);
                $stmt->bindParam(':exam_type', $examType);
                $stmt->bindParam(':exam_instructions', $examInstructions);
                $stmt->bindParam(':duration_minutes', $duration);
                $stmt->bindParam(':total_marks', $totalMarks);
                
                $stmt->execute();
                
                setAlert('success', $action === 'create' ? 'Exam created successfully.' : 'Exam updated successfully.');
            } elseif ($action === 'delete') {
                $examId = intval($_POST['exam_id']);
                
                // Check if exam has schedules
                $checkQuery = "SELECT COUNT(*) as schedule_count FROM exam_schedules WHERE exam_id = :exam_id";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':exam_id', $examId);
                $checkStmt->execute();
                $scheduleCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['schedule_count'];
                
                if ($scheduleCount > 0) {
                    setAlert('warning', 'Cannot delete exam. It has associated schedules.');
                } else {
                    $query = "DELETE FROM examinations WHERE exam_id = :exam_id AND created_by = :created_by";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':exam_id', $examId);
                    $stmt->bindParam(':created_by', $faculty['faculty_id']);
                    $stmt->execute();
                    
                    setAlert('success', 'Exam deleted successfully.');
                }
            }
        } catch (Exception $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }
    
    header("Location: exams.php");
    exit();
}

// Get courses that this faculty can create exams for (their department)
$courseQuery = "SELECT * FROM courses WHERE department_id = :department_id ORDER BY course_code";
$courseStmt = $db->prepare($courseQuery);
$courseStmt->bindParam(':department_id', $faculty['department_id']);
$courseStmt->execute();
$courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);

// Get exams created by this faculty with pagination
$page = intval($_GET['page'] ?? 1);
$search = sanitizeInput($_GET['search'] ?? '');
$typeFilter = sanitizeInput($_GET['type'] ?? '');
$recordsPerPage = 15;

// Build where clause
$whereConditions = ["e.created_by = :faculty_id"];
$params = [':faculty_id' => $faculty['faculty_id']];

if (!empty($search)) {
    $whereConditions[] = "(c.course_code LIKE :search OR c.course_title LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($typeFilter)) {
    $whereConditions[] = "e.exam_type = :exam_type";
    $params[':exam_type'] = $typeFilter;
}

$whereClause = " WHERE " . implode(" AND ", $whereConditions);

// Count total records
$countQuery = "SELECT COUNT(*) as total 
               FROM examinations e 
               JOIN courses c ON e.course_id = c.course_id" . $whereClause;
$countStmt = $db->prepare($countQuery);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate pagination
$pagination = paginate($page, $totalRecords, $recordsPerPage);

    // Get exams
    $query = "SELECT e.*, c.course_code, c.course_title, c.credit_units, c.academic_level,
                     COUNT(DISTINCT es.schedule_id) as schedule_count,
                     COUNT(DISTINCT er.registration_id) as registration_count
              FROM examinations e 
              JOIN courses c ON e.course_id = c.course_id
              LEFT JOIN exam_schedules es ON e.exam_id = es.exam_id
              LEFT JOIN exam_registrations er ON e.exam_id = er.exam_id" . 
              $whereClause . 
              " GROUP BY e.exam_id
              ORDER BY e.created_at DESC 
              LIMIT :limit OFFSET :offset";$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindParam(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();

$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrfToken = generateCSRFToken();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-file-alt"></i> Exam Management</h1>
            <div>
                <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#examModal" onclick="openExamModal()">
                    <i class="fas fa-plus"></i> Create Exam
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Faculty Info -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Employee ID:</strong><br>
                        <span class="text-primary"><?php echo htmlspecialchars($faculty['employee_id']); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Name:</strong><br>
                        <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Department:</strong><br>
                        <?php echo htmlspecialchars($faculty['department_name']); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Position:</strong><br>
                        <?php echo htmlspecialchars($faculty['position']); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by course code or title..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" name="type">
                            <option value="">All Exam Types</option>
                            <option value="Midterm" <?php echo $typeFilter === 'Midterm' ? 'selected' : ''; ?>>Midterm</option>
                            <option value="Final" <?php echo $typeFilter === 'Final' ? 'selected' : ''; ?>>Final</option>
                            <option value="Quiz" <?php echo $typeFilter === 'Quiz' ? 'selected' : ''; ?>>Quiz</option>
                            <option value="Assignment" <?php echo $typeFilter === 'Assignment' ? 'selected' : ''; ?>>Assignment</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="exams.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Exams Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Your Exams (<?php echo number_format($totalRecords); ?> total)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($exams)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Exam Type</th>
                                <th>Duration</th>
                                <th>Total Marks</th>
                                <th>Schedules</th>
                                <th>Registrations</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exams as $exam): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($exam['course_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($exam['course_title']); ?></small>
                                    <br><span class="badge bg-info"><?php echo $exam['academic_level']; ?> Level</span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $exam['exam_type'] === 'Final' ? 'bg-primary' : 'bg-secondary'; ?>">
                                        <?php echo htmlspecialchars($exam['exam_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo $exam['duration_minutes']; ?></strong> minutes
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo $exam['total_marks']; ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $exam['schedule_count'] > 0 ? 'bg-info' : 'bg-warning'; ?>">
                                        <?php echo $exam['schedule_count']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $exam['registration_count']; ?></span>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($exam['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="editExam(<?php echo htmlspecialchars(json_encode($exam)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteExam(<?php echo $exam['exam_id']; ?>)"
                                                <?php echo $exam['schedule_count'] > 0 ? 'disabled title="Cannot delete: has schedules"' : ''; ?>>
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
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
                            <a class="page-link" href="?page=<?php echo $pagination['page'] - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($typeFilter); ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                        <li class="page-item <?php echo $i == $pagination['page'] ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($typeFilter); ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $pagination['page'] + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($typeFilter); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No exams found</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#examModal" onclick="openExamModal()">
                        <i class="fas fa-plus"></i> Create Your First Exam
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Exam Modal -->
<div class="modal fade" id="examModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="examModalTitle">Create Exam</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="examForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="exam_id" id="examId">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="courseId" class="form-label">Course *</label>
                            <select class="form-control" id="courseId" name="course_id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['course_id']; ?>">
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="examType" class="form-label">Exam Type *</label>
                            <select class="form-control" id="examType" name="exam_type" required>
                                <option value="">Select Type</option>
                                <option value="Midterm">Midterm</option>
                                <option value="Final">Final</option>
                                <option value="Quiz">Quiz</option>
                                <option value="Assignment">Assignment</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="duration" class="form-label">Duration (minutes) *</label>
                            <input type="number" class="form-control" id="duration" name="duration" required min="15" max="300">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="totalMarks" class="form-label">Total Marks *</label>
                            <input type="number" class="form-control" id="totalMarks" name="total_marks" required min="1" max="100">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="examInstructions" class="form-label">Exam Instructions</label>
                        <textarea class="form-control" id="examInstructions" name="exam_instructions" rows="4" 
                                  placeholder="Enter specific instructions for students taking this exam..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Exam</button>
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
                <p>Are you sure you want to delete this exam? This action cannot be undone.</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    You can only delete exams that have no schedules.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="exam_id" id="deleteExamId">
                    <button type="submit" class="btn btn-danger">Delete Exam</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openExamModal() {
    document.getElementById('examModalTitle').textContent = 'Create Exam';
    document.getElementById('formAction').value = 'create';
    document.getElementById('examForm').reset();
    document.getElementById('examId').value = '';
}

function editExam(exam) {
    document.getElementById('examModalTitle').textContent = 'Edit Exam';
    document.getElementById('formAction').value = 'update';
    document.getElementById('examId').value = exam.exam_id;
    document.getElementById('courseId').value = exam.course_id;
    document.getElementById('examType').value = exam.exam_type;
    document.getElementById('duration').value = exam.duration_minutes;
    document.getElementById('totalMarks').value = exam.total_marks;
    document.getElementById('examInstructions').value = exam.exam_instructions || '';
    
    new bootstrap.Modal(document.getElementById('examModal')).show();
}

function deleteExam(examId) {
    document.getElementById('deleteExamId').value = examId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
