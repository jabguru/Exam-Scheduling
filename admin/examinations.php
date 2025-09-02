<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Admin');

$pageTitle = "Examination Management";

// Handle examination creation/update/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setAlert('danger', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if ($action === 'create' || $action === 'update') {
                $examId = $_POST['exam_id'] ?? null;
                $courseId = intval($_POST['course_id']);
                $examPeriodId = intval($_POST['exam_period_id']);
                $examType = sanitizeInput($_POST['exam_type']);
                $durationMinutes = intval($_POST['duration_minutes']);
                $totalMarks = intval($_POST['total_marks']);
                $instructions = sanitizeInput($_POST['instructions']);
                $createdBy = $_SESSION['user_id'];
                
                if ($action === 'create') {
                    $query = "INSERT INTO examinations (course_id, exam_period_id, exam_type, duration_minutes, total_marks, instructions, created_by) 
                             VALUES (:course_id, :exam_period_id, :exam_type, :duration_minutes, :total_marks, :instructions, :created_by)";
                    $stmt = $db->prepare($query);
                } else {
                    $query = "UPDATE examinations SET course_id = :course_id, exam_period_id = :exam_period_id, exam_type = :exam_type, 
                             duration_minutes = :duration_minutes, total_marks = :total_marks, instructions = :instructions 
                             WHERE exam_id = :exam_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':exam_id', $examId);
                }
                
                $stmt->bindParam(':course_id', $courseId);
                $stmt->bindParam(':exam_period_id', $examPeriodId);
                $stmt->bindParam(':exam_type', $examType);
                $stmt->bindParam(':duration_minutes', $durationMinutes);
                $stmt->bindParam(':total_marks', $totalMarks);
                $stmt->bindParam(':instructions', $instructions);
                if ($action === 'create') {
                    $stmt->bindParam(':created_by', $createdBy);
                }
                
                $stmt->execute();
                setAlert('success', 'Examination ' . ($action === 'create' ? 'created' : 'updated') . ' successfully!');
                
            } elseif ($action === 'delete') {
                $examId = intval($_POST['exam_id']);
                
                // Check if exam has schedules
                $checkQuery = "SELECT COUNT(*) FROM exam_schedules WHERE exam_id = :exam_id";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':exam_id', $examId);
                $checkStmt->execute();
                
                if ($checkStmt->fetchColumn() > 0) {
                    setAlert('danger', 'Cannot delete examination that has schedules. Delete the schedule first.');
                } else {
                    $query = "DELETE FROM examinations WHERE exam_id = :exam_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':exam_id', $examId);
                    $stmt->execute();
                    setAlert('success', 'Examination deleted successfully!');
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                setAlert('danger', 'Examination already exists for this course and exam period.');
            } else {
                setAlert('danger', 'Error: ' . $e->getMessage());
            }
        }
        
        header('Location: examinations.php');
        exit;
    }
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$recordsPerPage = 20;
$offset = ($page - 1) * $recordsPerPage;

// Search and filter
$search = sanitizeInput($_GET['search'] ?? '');
$courseFilter = intval($_GET['course_filter'] ?? 0);
$examPeriodFilter = intval($_GET['exam_period_filter'] ?? 0);
$examTypeFilter = sanitizeInput($_GET['exam_type_filter'] ?? '');

$whereClause = " WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (c.course_code LIKE :search OR c.course_title LIKE :search OR d.department_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($courseFilter > 0) {
    $whereClause .= " AND e.course_id = :course_filter";
    $params[':course_filter'] = $courseFilter;
}

if ($examPeriodFilter > 0) {
    $whereClause .= " AND e.exam_period_id = :exam_period_filter";
    $params[':exam_period_filter'] = $examPeriodFilter;
}

if (!empty($examTypeFilter)) {
    $whereClause .= " AND e.exam_type = :exam_type_filter";
    $params[':exam_type_filter'] = $examTypeFilter;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Count total records
    $countQuery = "SELECT COUNT(*) FROM examinations e 
                   JOIN courses c ON e.course_id = c.course_id
                   JOIN departments d ON c.department_id = d.department_id
                   JOIN exam_periods ep ON e.exam_period_id = ep.exam_period_id" . $whereClause;
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    
    // Calculate pagination
    $pagination = paginate($page, $totalRecords, $recordsPerPage);
    
    // Get examinations
    $query = "SELECT e.*, c.course_code, c.course_title, c.credit_units, d.department_name,
                     ep.period_name, ep.start_date as period_start, ep.end_date as period_end,
                     u.first_name, u.last_name,
                     CASE WHEN es.schedule_id IS NOT NULL THEN 'Scheduled' ELSE 'Not Scheduled' END as schedule_status
              FROM examinations e 
              JOIN courses c ON e.course_id = c.course_id
              JOIN departments d ON c.department_id = d.department_id
              JOIN exam_periods ep ON e.exam_period_id = ep.exam_period_id
              JOIN users u ON e.created_by = u.user_id
              LEFT JOIN exam_schedules es ON e.exam_id = es.exam_id" . 
              $whereClause . 
              " ORDER BY e.created_at DESC
              LIMIT $recordsPerPage OFFSET $offset";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $examinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get courses for filter dropdown
    $courseQuery = "SELECT c.*, d.department_name FROM courses c 
                    JOIN departments d ON c.department_id = d.department_id 
                    ORDER BY c.course_code";
    $courseStmt = $db->query($courseQuery);
    $courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get exam periods for filter dropdown
    $periodQuery = "SELECT * FROM exam_periods ORDER BY start_date DESC";
    $periodStmt = $db->query($periodQuery);
    $examPeriods = $periodStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    setAlert('danger', 'Error loading examinations: ' . $e->getMessage());
    $examinations = [];
    $courses = [];
    $examPeriods = [];
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-clipboard-list"></i> Examination Management</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#examinationModal">
                <i class="fas fa-plus"></i> Add Examination
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
                                   placeholder="Course code, title, or department">
                        </div>
                        <div class="col-md-3">
                            <label for="courseFilter" class="form-label">Course</label>
                            <select class="form-control" id="courseFilter" name="course_filter">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['course_id']; ?>" 
                                        <?php echo $courseFilter == $course['course_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="examPeriodFilter" class="form-label">Exam Period</label>
                            <select class="form-control" id="examPeriodFilter" name="exam_period_filter">
                                <option value="">All Periods</option>
                                <?php foreach ($examPeriods as $period): ?>
                                <option value="<?php echo $period['exam_period_id']; ?>" 
                                        <?php echo $examPeriodFilter == $period['exam_period_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($period['period_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="examTypeFilter" class="form-label">Exam Type</label>
                            <select class="form-control" id="examTypeFilter" name="exam_type_filter">
                                <option value="">All Types</option>
                                <option value="CA" <?php echo $examTypeFilter === 'CA' ? 'selected' : ''; ?>>CA</option>
                                <option value="Final" <?php echo $examTypeFilter === 'Final' ? 'selected' : ''; ?>>Final</option>
                                <option value="Makeup" <?php echo $examTypeFilter === 'Makeup' ? 'selected' : ''; ?>>Makeup</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="examinations.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Examinations Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Examinations (<?php echo $totalRecords; ?> total)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($examinations)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No examinations found.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Course</th>
                                    <th>Department</th>
                                    <th>Exam Period</th>
                                    <th>Type</th>
                                    <th>Duration</th>
                                    <th>Total Marks</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($examinations as $examination): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($examination['course_code']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($examination['course_title']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($examination['department_name']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($examination['period_name']); ?>
                                        <br><small class="text-muted">
                                            <?php echo date('M j, Y', strtotime($examination['period_start'])); ?> - 
                                            <?php echo date('M j, Y', strtotime($examination['period_end'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $examination['exam_type'] === 'Final' ? 'primary' : ($examination['exam_type'] === 'CA' ? 'info' : 'warning'); ?>">
                                            <?php echo htmlspecialchars($examination['exam_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $examination['duration_minutes']; ?> mins</td>
                                    <td><?php echo $examination['total_marks']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $examination['schedule_status'] === 'Scheduled' ? 'success' : 'warning'; ?>">
                                            <?php echo $examination['schedule_status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($examination['first_name'] . ' ' . $examination['last_name']); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick="editExamination(<?php echo htmlspecialchars(json_encode($examination)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($examination['schedule_status'] === 'Not Scheduled'): ?>
                                            <button type="button" class="btn btn-outline-success" 
                                                    onclick="scheduleExam(<?php echo $examination['exam_id']; ?>)">
                                                <i class="fas fa-calendar-plus"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deleteExamination(<?php echo $examination['exam_id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
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
        </main>
    </div>
</div>

<!-- Examination Modal -->
<div class="modal fade" id="examinationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="examinationModalTitle">Add Examination</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="examinationForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="exam_id" id="examId">
                    
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
                            <label for="examPeriodId" class="form-label">Exam Period *</label>
                            <select class="form-control" id="examPeriodId" name="exam_period_id" required>
                                <option value="">Select Exam Period</option>
                                <?php foreach ($examPeriods as $period): ?>
                                <option value="<?php echo $period['exam_period_id']; ?>">
                                    <?php echo htmlspecialchars($period['period_name']); ?>
                                    (<?php echo date('M j, Y', strtotime($period['start_date'])); ?> - 
                                     <?php echo date('M j, Y', strtotime($period['end_date'])); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="examType" class="form-label">Exam Type *</label>
                            <select class="form-control" id="examType" name="exam_type" required>
                                <option value="">Select Type</option>
                                <option value="CA">Continuous Assessment</option>
                                <option value="Final">Final Exam</option>
                                <option value="Makeup">Makeup Exam</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="durationMinutes" class="form-label">Duration (minutes) *</label>
                            <input type="number" class="form-control" id="durationMinutes" name="duration_minutes" 
                                   min="30" max="300" step="15" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="totalMarks" class="form-label">Total Marks *</label>
                            <input type="number" class="form-control" id="totalMarks" name="total_marks" 
                                   min="1" max="200" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="instructions" class="form-label">Exam Instructions</label>
                        <textarea class="form-control" id="instructions" name="instructions" rows="4" 
                                  placeholder="Special instructions for this exam..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Create Examination</button>
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
                <p>Are you sure you want to delete this examination? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="exam_id" id="deleteExamId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editExamination(examination) {
    document.getElementById('examinationModalTitle').textContent = 'Edit Examination';
    document.getElementById('formAction').value = 'update';
    document.getElementById('examId').value = examination.exam_id;
    document.getElementById('courseId').value = examination.course_id;
    document.getElementById('examPeriodId').value = examination.exam_period_id;
    document.getElementById('examType').value = examination.exam_type;
    document.getElementById('durationMinutes').value = examination.duration_minutes;
    document.getElementById('totalMarks').value = examination.total_marks;
    document.getElementById('instructions').value = examination.instructions || '';
    document.getElementById('submitBtn').textContent = 'Update Examination';
    
    new bootstrap.Modal(document.getElementById('examinationModal')).show();
}

function deleteExamination(examId) {
    document.getElementById('deleteExamId').value = examId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function scheduleExam(examId) {
    window.location.href = 'schedules.php?exam_id=' + examId;
}

// Reset modal when closed
document.getElementById('examinationModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('examinationForm').reset();
    document.getElementById('examinationModalTitle').textContent = 'Add Examination';
    document.getElementById('formAction').value = 'create';
    document.getElementById('examId').value = '';
    document.getElementById('submitBtn').textContent = 'Create Examination';
});
</script>

<?php include '../includes/footer.php'; ?>
