<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Student');

$pageTitle = "Exam Registration";

// Get student information
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get student details
    $query = "SELECT s.*, d.department_name, u.first_name, u.last_name 
              FROM students s 
              JOIN departments d ON s.department_id = d.department_id
              JOIN users u ON s.user_id = u.user_id
              WHERE s.user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception('Student profile not found.');
    }
    
} catch (Exception $e) {
    setAlert('danger', 'Error: ' . $e->getMessage());
    header("Location: dashboard.php");
    exit();
}

// Handle exam registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setAlert('danger', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            if ($action === 'register') {
                $examId = intval($_POST['exam_id']);
                
                // Check if already registered
                $checkQuery = "SELECT COUNT(*) as count FROM exam_registrations 
                              WHERE exam_id = :exam_id AND student_id = :student_id";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':exam_id', $examId);
                $checkStmt->bindParam(':student_id', $student['student_id']);
                $checkStmt->execute();
                
                if ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                    setAlert('warning', 'You are already registered for this exam.');
                } else {
                    // Check registration deadline
                    $deadlineQuery = "SELECT e.exam_id, c.course_code, es.exam_date, es.start_time
                                     FROM examinations e
                                     JOIN courses c ON e.course_id = c.course_id
                                     JOIN exam_schedules es ON e.exam_id = es.exam_id
                                     WHERE e.exam_id = :exam_id AND es.exam_date > CURDATE()";
                    $deadlineStmt = $db->prepare($deadlineQuery);
                    $deadlineStmt->bindParam(':exam_id', $examId);
                    $deadlineStmt->execute();
                    $examInfo = $deadlineStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$examInfo) {
                        setAlert('danger', 'Registration deadline has passed or exam not found.');
                    } else {
                        // Register for exam
                        $registerQuery = "INSERT INTO exam_registrations (exam_id, student_id, registration_date, status) 
                                         VALUES (:exam_id, :student_id, NOW(), 'Registered')";
                        $registerStmt = $db->prepare($registerQuery);
                        $registerStmt->bindParam(':exam_id', $examId);
                        $registerStmt->bindParam(':student_id', $student['student_id']);
                        $registerStmt->execute();
                        
                        setAlert('success', "Successfully registered for {$examInfo['course_code']} exam.");
                    }
                }
            } elseif ($action === 'cancel') {
                $registrationId = intval($_POST['registration_id']);
                
                // Check if can cancel (exam must be at least 24 hours away)
                $cancelQuery = "SELECT er.registration_id, c.course_code, es.exam_date, es.start_time
                               FROM exam_registrations er
                               JOIN examinations e ON er.exam_id = e.exam_id
                               JOIN courses c ON e.course_id = c.course_id
                               JOIN exam_schedules es ON e.exam_id = es.exam_id
                               WHERE er.registration_id = :registration_id 
                               AND er.student_id = :student_id
                               AND CONCAT(es.exam_date, ' ', es.start_time) > DATE_ADD(NOW(), INTERVAL 24 HOUR)";
                $cancelStmt = $db->prepare($cancelQuery);
                $cancelStmt->bindParam(':registration_id', $registrationId);
                $cancelStmt->bindParam(':student_id', $student['student_id']);
                $cancelStmt->execute();
                $canCancel = $cancelStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$canCancel) {
                    setAlert('danger', 'Cannot cancel registration. Exam is less than 24 hours away.');
                } else {
                    $deleteQuery = "DELETE FROM exam_registrations WHERE registration_id = :registration_id";
                    $deleteStmt = $db->prepare($deleteQuery);
                    $deleteStmt->bindParam(':registration_id', $registrationId);
                    $deleteStmt->execute();
                    
                    setAlert('success', "Registration cancelled for {$canCancel['course_code']} exam.");
                }
            }
        } catch (Exception $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }
    
    header("Location: registration.php");
    exit();
}

// Get available courses for registration (courses for student's level and department)
$availableQuery = "SELECT 
                    e.exam_id,
                    c.course_code,
                    c.course_title,
                    c.credit_units,
                    c.academic_level,
                    c.semester,
                    e.exam_type,
                    es.exam_date,
                    es.start_time,
                    es.end_time,
                    v.venue_name,
                    es.capacity_allocated,
                    COUNT(er.registration_id) as registered_count
                   FROM examinations e
                   JOIN courses c ON e.course_id = c.course_id
                   JOIN exam_schedules es ON e.exam_id = es.exam_id
                   JOIN venues v ON es.venue_id = v.venue_id
                   LEFT JOIN exam_registrations er ON e.exam_id = er.exam_id
                   WHERE c.department_id = :department_id
                   AND c.academic_level <= :student_level
                   AND es.exam_date > CURDATE()
                   AND e.exam_id NOT IN (
                       SELECT exam_id FROM exam_registrations 
                       WHERE student_id = :student_id
                   )
                   GROUP BY e.exam_id
                   ORDER BY es.exam_date, es.start_time";

$availableStmt = $db->prepare($availableQuery);
$availableStmt->bindParam(':department_id', $student['department_id']);
$availableStmt->bindParam(':student_level', $student['academic_level']);
$availableStmt->bindParam(':student_id', $student['student_id']);
$availableStmt->execute();
$availableExams = $availableStmt->fetchAll(PDO::FETCH_ASSOC);

// Get current registrations
$registeredQuery = "SELECT 
                     er.registration_id,
                     er.registration_date,
                     er.status,
                     c.course_code,
                     c.course_title,
                     e.exam_type,
                     es.exam_date,
                     es.start_time,
                     es.end_time,
                     v.venue_name,
                     CASE 
                         WHEN CONCAT(es.exam_date, ' ', es.start_time) > DATE_ADD(NOW(), INTERVAL 24 HOUR) THEN 1
                         ELSE 0
                     END as can_cancel
                    FROM exam_registrations er
                    JOIN examinations e ON er.exam_id = e.exam_id
                    JOIN courses c ON e.course_id = c.course_id
                    JOIN exam_schedules es ON e.exam_id = es.exam_id
                    JOIN venues v ON es.venue_id = v.venue_id
                    WHERE er.student_id = :student_id
                    ORDER BY es.exam_date, es.start_time";

$registeredStmt = $db->prepare($registeredQuery);
$registeredStmt->bindParam(':student_id', $student['student_id']);
$registeredStmt->execute();
$registeredExams = $registeredStmt->fetchAll(PDO::FETCH_ASSOC);

$csrfToken = generateCSRFToken();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-clipboard-list"></i> Exam Registration</h1>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Student Info -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Matric Number:</strong><br>
                        <span class="text-primary"><?php echo htmlspecialchars($student['matric_number']); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Name:</strong><br>
                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Department:</strong><br>
                        <?php echo htmlspecialchars($student['department_name']); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Level:</strong><br>
                        <?php echo htmlspecialchars($student['academic_level']); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Current Registrations -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list-check"></i> Your Registered Exams (<?php echo count($registeredExams); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($registeredExams)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Exam Type</th>
                                <th>Date & Time</th>
                                <th>Venue</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registeredExams as $exam): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($exam['course_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($exam['course_title']); ?></small>
                                </td>
                                <td>
                                    <span class="badge <?php echo $exam['exam_type'] === 'Final' ? 'bg-primary' : 'bg-secondary'; ?>">
                                        <?php echo htmlspecialchars($exam['exam_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($exam['exam_date'])); ?><br>
                                    <small class="text-muted">
                                        <?php echo date('g:i A', strtotime($exam['start_time'])); ?> - 
                                        <?php echo date('g:i A', strtotime($exam['end_time'])); ?>
                                    </small>
                                </td>
                                <td><?php echo htmlspecialchars($exam['venue_name']); ?></td>
                                <td>
                                    <span class="badge bg-success"><?php echo htmlspecialchars($exam['status']); ?></span>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo date('M j, Y', strtotime($exam['registration_date'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($exam['can_cancel']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="cancelRegistration(<?php echo $exam['registration_id']; ?>)">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                    <?php else: ?>
                                    <span class="text-muted small">Cannot cancel</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <p class="text-muted">You have not registered for any exams yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Available Exams for Registration -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-plus-circle"></i> Available Exams for Registration (<?php echo count($availableExams); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($availableExams)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Level</th>
                                <th>Exam Type</th>
                                <th>Date & Time</th>
                                <th>Venue</th>
                                <th>Capacity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($availableExams as $exam): ?>
                            <?php 
                                $spacesLeft = $exam['capacity_allocated'] - $exam['registered_count'];
                                $canRegister = $spacesLeft > 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($exam['course_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($exam['course_title']); ?></small>
                                    <br><span class="badge bg-info"><?php echo $exam['credit_units']; ?> Units</span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $exam['academic_level']; ?></span><br>
                                    <small class="text-muted"><?php echo $exam['semester']; ?> Semester</small>
                                </td>
                                <td>
                                    <span class="badge <?php echo $exam['exam_type'] === 'Final' ? 'bg-primary' : 'bg-secondary'; ?>">
                                        <?php echo htmlspecialchars($exam['exam_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($exam['exam_date'])); ?><br>
                                    <small class="text-muted">
                                        <?php echo date('g:i A', strtotime($exam['start_time'])); ?> - 
                                        <?php echo date('g:i A', strtotime($exam['end_time'])); ?>
                                    </small>
                                </td>
                                <td><?php echo htmlspecialchars($exam['venue_name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $canRegister ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $spacesLeft; ?> / <?php echo $exam['capacity_allocated']; ?>
                                    </span><br>
                                    <small class="text-muted"><?php echo $exam['registered_count']; ?> registered</small>
                                </td>
                                <td>
                                    <?php if ($canRegister): ?>
                                    <button type="button" class="btn btn-sm btn-success" 
                                            onclick="registerForExam(<?php echo $exam['exam_id']; ?>)">
                                        <i class="fas fa-plus"></i> Register
                                    </button>
                                    <?php else: ?>
                                    <span class="text-danger small">Full</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No exams available for registration at this time.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Hidden forms for actions -->
<form id="actionForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
    <input type="hidden" name="action" id="actionType">
    <input type="hidden" name="exam_id" id="actionExamId">
    <input type="hidden" name="registration_id" id="actionRegistrationId">
</form>

<script>
function registerForExam(examId) {
    if (confirm('Are you sure you want to register for this exam?')) {
        document.getElementById('actionType').value = 'register';
        document.getElementById('actionExamId').value = examId;
        document.getElementById('actionForm').submit();
    }
}

function cancelRegistration(registrationId) {
    if (confirm('Are you sure you want to cancel this registration? This action cannot be undone.')) {
        document.getElementById('actionType').value = 'cancel';
        document.getElementById('actionRegistrationId').value = registrationId;
        document.getElementById('actionForm').submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>
