<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Lecturer');

$pageTitle = "Student Management";

// Get lecturer information
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get lecturer details
    $query = "SELECT l.*, d.department_name, u.first_name, u.last_name 
              FROM lecturers l 
              JOIN departments d ON l.department_id = d.department_id
              JOIN users u ON l.user_id = u.user_id
              WHERE l.user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lecturer) {
        throw new Exception('Lecturer profile not found.');
    }
    
} catch (Exception $e) {
    setAlert('danger', 'Error: ' . $e->getMessage());
    header("Location: dashboard.php");
    exit();
}

// Get students with pagination and filters
$page = intval($_GET['page'] ?? 1);
$search = sanitizeInput($_GET['search'] ?? '');
$levelFilter = sanitizeInput($_GET['level'] ?? '');
$examFilter = intval($_GET['exam'] ?? 0);
$recordsPerPage = 20;

// Get exams created by this lecturer for filter dropdown
$examQuery = "SELECT e.exam_id, c.course_code, c.course_title, e.exam_type
              FROM examinations e
              JOIN courses c ON e.course_id = c.course_id
              WHERE e.created_by = :lecturer_id
              ORDER BY c.course_code, e.exam_type";
$examStmt = $db->prepare($examQuery);
$examStmt->bindParam(':lecturer_id', $lecturer['lecturer_id']);
$examStmt->execute();
$lecturerExams = $examStmt->fetchAll(PDO::FETCH_ASSOC);

// Build where clause for students
$whereConditions = ["s.department_id = :department_id"];
$params = [':department_id' => $lecturer['department_id']];

if (!empty($search)) {
    $whereConditions[] = "(s.matric_number LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($levelFilter)) {
    $whereConditions[] = "s.academic_level = :level";
    $params[':level'] = $levelFilter;
}

$whereClause = " WHERE " . implode(" AND ", $whereConditions);

// Count total records
$countQuery = "SELECT COUNT(*) as total 
               FROM students s 
               JOIN users u ON s.user_id = u.user_id" . $whereClause;
$countStmt = $db->prepare($countQuery);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate pagination
$pagination = paginate($page, $totalRecords, $recordsPerPage);

// Get students
$query = "SELECT s.*, u.first_name, u.last_name, u.email, u.is_active,
                 COUNT(DISTINCT er.registration_id) as total_registrations,
                 COUNT(DISTINCT CASE WHEN e.created_by = :lecturer_id THEN er.registration_id END) as lecturer_exam_registrations
          FROM students s 
          JOIN users u ON s.user_id = u.user_id
          LEFT JOIN exam_registrations er ON s.student_id = er.student_id
          LEFT JOIN examinations e ON er.exam_id = e.exam_id" . 
          $whereClause . 
          " GROUP BY s.student_id
          ORDER BY s.academic_level, s.matric_number 
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindParam(':lecturer_id', $lecturer['lecturer_id'], PDO::PARAM_INT);
$stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindParam(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If filtering by specific exam, get detailed registration info
$examRegistrations = [];
if ($examFilter > 0) {
    $examRegQuery = "SELECT er.*, s.matric_number, u.first_name, u.last_name,
                            es.exam_date, es.start_time, v.venue_name
                     FROM exam_registrations er
                     JOIN students s ON er.student_id = s.student_id
                     JOIN users u ON s.user_id = u.user_id
                     LEFT JOIN exam_schedules es ON er.exam_id = es.exam_id
                     LEFT JOIN venues v ON es.venue_id = v.venue_id
                     WHERE er.exam_id = :exam_id
                     ORDER BY s.matric_number";
    $examRegStmt = $db->prepare($examRegQuery);
    $examRegStmt->bindParam(':exam_id', $examFilter);
    $examRegStmt->execute();
    $examRegistrations = $examRegStmt->fetchAll(PDO::FETCH_ASSOC);
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-user-graduate"></i> Student Management</h1>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Lecturer Info -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Employee ID:</strong><br>
                        <span class="text-primary"><?php echo htmlspecialchars($lecturer['employee_id']); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Name:</strong><br>
                        <?php echo htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Department:</strong><br>
                        <?php echo htmlspecialchars($lecturer['department_name']); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Position:</strong><br>
                        <?php echo htmlspecialchars($lecturer['position']); ?>
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
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by matric number or name..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-control" name="level">
                            <option value="">All Levels</option>
                            <option value="100" <?php echo $levelFilter === '100' ? 'selected' : ''; ?>>100 Level</option>
                            <option value="200" <?php echo $levelFilter === '200' ? 'selected' : ''; ?>>200 Level</option>
                            <option value="300" <?php echo $levelFilter === '300' ? 'selected' : ''; ?>>300 Level</option>
                            <option value="400" <?php echo $levelFilter === '400' ? 'selected' : ''; ?>>400 Level</option>
                            <option value="500" <?php echo $levelFilter === '500' ? 'selected' : ''; ?>>500 Level</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" name="exam">
                            <option value="">All Students</option>
                            <?php foreach ($lecturerExams as $exam): ?>
                            <option value="<?php echo $exam['exam_id']; ?>" 
                                    <?php echo $examFilter == $exam['exam_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam['course_code'] . ' - ' . $exam['exam_type']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="students.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($examFilter > 0 && !empty($examRegistrations)): ?>
<!-- Exam Registration Details -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-clipboard-list"></i> 
                    Exam Registrations: 
                    <?php 
                        $selectedExam = array_filter($lecturerExams, function($e) use ($examFilter) {
                            return $e['exam_id'] == $examFilter;
                        });
                        $selectedExam = reset($selectedExam);
                        echo htmlspecialchars($selectedExam['course_code'] . ' - ' . $selectedExam['exam_type']);
                    ?>
                    (<?php echo count($examRegistrations); ?> students)
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Matric Number</th>
                                <th>Student Name</th>
                                <th>Registration Date</th>
                                <th>Status</th>
                                <th>Exam Date</th>
                                <th>Venue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($examRegistrations as $registration): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($registration['matric_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($registration['first_name'] . ' ' . $registration['last_name']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($registration['registration_date'])); ?></td>
                                <td>
                                    <span class="badge bg-success"><?php echo htmlspecialchars($registration['status']); ?></span>
                                </td>
                                <td>
                                    <?php if ($registration['exam_date']): ?>
                                        <?php echo date('M j, Y', strtotime($registration['exam_date'])); ?><br>
                                        <small class="text-muted"><?php echo date('g:i A', strtotime($registration['start_time'])); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Not scheduled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $registration['venue_name'] ? htmlspecialchars($registration['venue_name']) : '<span class="text-muted">Not assigned</span>'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Students Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> 
                    <?php echo $examFilter > 0 ? 'All Department Students' : 'Department Students'; ?> 
                    (<?php echo number_format($totalRecords); ?> total)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($students)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Matric Number</th>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Level</th>
                                <th>Status</th>
                                <th>Total Registrations</th>
                                <th>Your Exams</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($student['matric_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $student['academic_level']; ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $student['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $student['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $student['total_registrations']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $student['lecturer_exam_registrations']; ?></span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                            onclick="viewStudentDetails('<?php echo $student['matric_number']; ?>')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
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
                            <a class="page-link" href="?page=<?php echo $pagination['page'] - 1; ?>&search=<?php echo urlencode($search); ?>&level=<?php echo urlencode($levelFilter); ?>&exam=<?php echo $examFilter; ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                        <li class="page-item <?php echo $i == $pagination['page'] ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&level=<?php echo urlencode($levelFilter); ?>&exam=<?php echo $examFilter; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $pagination['page'] + 1; ?>&search=<?php echo urlencode($search); ?>&level=<?php echo urlencode($levelFilter); ?>&exam=<?php echo $examFilter; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No students found matching your criteria</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Summary -->
<div class="row mt-4">
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                <h3 class="stats-number"><?php echo number_format($totalRecords); ?></h3>
                <p class="stats-label">Total Students</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-file-alt fa-2x text-success mb-2"></i>
                <h3 class="stats-number"><?php echo count($lecturerExams); ?></h3>
                <p class="stats-label">Your Exams</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-clipboard-list fa-2x text-info mb-2"></i>
                <h3 class="stats-number">
                    <?php 
                        $totalLecturerRegistrations = array_sum(array_column($students, 'lecturer_exam_registrations'));
                        echo number_format($totalLecturerRegistrations);
                    ?>
                </h3>
                <p class="stats-label">Exam Registrations</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-percentage fa-2x text-warning mb-2"></i>
                <h3 class="stats-number">
                    <?php 
                        $registrationRate = $totalRecords > 0 ? round(($totalLecturerRegistrations / $totalRecords), 1) : 0;
                        echo $registrationRate;
                    ?>
                </h3>
                <p class="stats-label">Avg Registrations</p>
            </div>
        </div>
    </div>
</div>

<script>
function viewStudentDetails(matricNumber) {
    // This could open a modal with detailed student information
    // For now, we'll just show an alert
    alert('Student details for: ' + matricNumber + '\n\nThis feature will show detailed exam history, performance, and other relevant information.');
}
</script>

<?php include '../includes/footer.php'; ?>
