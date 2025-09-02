<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Student');

$pageTitle = "My Examinations";

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
        // User has student role but no student record - create one
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
        
        // Get first available department
        $defaultDeptQuery = "SELECT department_id FROM departments ORDER BY department_id LIMIT 1";
        $defaultDeptStmt = $db->query($defaultDeptQuery);
        $defaultDepartmentId = $defaultDeptStmt->fetchColumn();
        
        if ($defaultDepartmentId) {
            // Create student record
            $createStudentQuery = "INSERT INTO students (user_id, matric_number, department_id, academic_level, current_semester, entry_year) 
                                  VALUES (:user_id, :matric_number, :department_id, '100', 'First', :entry_year)";
            $createStmt = $db->prepare($createStudentQuery);
            $createStmt->bindParam(':user_id', $_SESSION['user_id']);
            $createStmt->bindParam(':matric_number', $matricNumber);
            $createStmt->bindParam(':department_id', $defaultDepartmentId);
            $createStmt->bindParam(':entry_year', $year);
            $createStmt->execute();
            
            // Now fetch the student record again
            $stmt->execute();
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            setAlert('success', 'Student profile created automatically. Matric Number: ' . $matricNumber);
        }
        
        if (!$student) {
            throw new Exception('Unable to create student profile. Please contact administrator.');
        }
    }
    
    // Get current exam period
    $currentPeriodQuery = "SELECT * FROM exam_periods WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1";
    $currentPeriodStmt = $db->query($currentPeriodQuery);
    $currentPeriod = $currentPeriodStmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentPeriod) {
        $error = "No active exam period found. Please contact administrator.";
    } else {
        // Get examinations for courses student is enrolled in
        $examsQuery = "SELECT 
                        c.course_code,
                        c.course_title,
                        c.credit_units,
                        e.exam_id,
                        e.exam_type,
                        e.duration_minutes,
                        e.total_marks,
                        e.instructions,
                        es.exam_date,
                        es.start_time,
                        es.end_time,
                        v.venue_name,
                        v.location,
                        sce.enrollment_date,
                        CASE 
                            WHEN es.exam_date IS NULL THEN 'Not Scheduled'
                            WHEN es.exam_date < CURDATE() THEN 'Completed'
                            WHEN es.exam_date = CURDATE() THEN 'Today'
                            ELSE 'Upcoming'
                        END as exam_status
                       FROM student_course_enrollments sce
                       JOIN courses c ON sce.course_id = c.course_id
                       LEFT JOIN examinations e ON c.course_id = e.course_id AND sce.exam_period_id = e.exam_period_id
                       LEFT JOIN exam_schedules es ON e.exam_id = es.exam_id
                       LEFT JOIN venues v ON es.venue_id = v.venue_id
                       WHERE sce.student_id = :student_id 
                       AND sce.status = 'Registered'
                       AND sce.exam_period_id = :exam_period_id
                       ORDER BY es.exam_date, es.start_time, c.course_code";
        $examsStmt = $db->prepare($examsQuery);
        $examsStmt->bindParam(':student_id', $student['student_id']);
        $examsStmt->bindParam(':exam_period_id', $currentPeriod['exam_period_id']);
        $examsStmt->execute();
        $examinations = $examsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    setAlert('danger', 'Error: ' . $e->getMessage());
    $examinations = [];
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <main class="col-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">My Examinations</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <span class="badge bg-info"><?php echo htmlspecialchars($student['matric_number']); ?></span>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($student['academic_level']); ?> Level</span>
                    </div>
                </div>
            </div>

            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
            <?php else: ?>
            
            <!-- Exam Period Info -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Current Exam Period: <?php echo htmlspecialchars($currentPeriod['period_name']); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Period:</strong> <?php echo date('M j, Y', strtotime($currentPeriod['start_date'])); ?> - 
                                    <?php echo date('M j, Y', strtotime($currentPeriod['end_date'])); ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Registration Deadline:</strong> 
                                    <span class="text-<?php echo strtotime($currentPeriod['registration_deadline']) > time() ? 'success' : 'danger'; ?>">
                                        <?php echo date('M j, Y', strtotime($currentPeriod['registration_deadline'])); ?>
                                    </span>
                                </div>
                                <div class="col-md-4">
                                    <strong>Total Examinations:</strong> <?php echo count(array_filter($examinations, function($exam) { return !is_null($exam['exam_id']); })); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Examinations Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-clipboard-list"></i> Examinations Based on Course Enrollment
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($examinations)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                <p class="text-muted">You are not enrolled in any courses for this exam period.</p>
                                <a href="enrollment.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Enroll in Courses
                                </a>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Note:</strong> You are automatically scheduled for examinations of all courses you are enrolled in. 
                                No manual registration required.
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Course</th>
                                            <th>Exam Type</th>
                                            <th>Date & Time</th>
                                            <th>Duration</th>
                                            <th>Venue</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($examinations as $exam): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($exam['course_code']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($exam['course_title']); ?></small>
                                                <br><small class="text-info"><?php echo $exam['credit_units']; ?> Units</small>
                                            </td>
                                            <td>
                                                <?php if ($exam['exam_type']): ?>
                                                <span class="badge bg-<?php echo $exam['exam_type'] === 'Final' ? 'primary' : ($exam['exam_type'] === 'CA' ? 'info' : 'warning'); ?>">
                                                    <?php echo htmlspecialchars($exam['exam_type']); ?>
                                                </span>
                                                <br><small class="text-muted"><?php echo $exam['total_marks']; ?> marks</small>
                                                <?php else: ?>
                                                <span class="text-muted">Not created yet</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($exam['exam_date']): ?>
                                                <strong><?php echo date('M j, Y', strtotime($exam['exam_date'])); ?></strong>
                                                <br><small class="text-muted">
                                                    <?php echo date('g:i A', strtotime($exam['start_time'])); ?> - 
                                                    <?php echo date('g:i A', strtotime($exam['end_time'])); ?>
                                                </small>
                                                <?php else: ?>
                                                <span class="text-warning">Not scheduled</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($exam['duration_minutes']): ?>
                                                <?php echo $exam['duration_minutes']; ?> minutes
                                                <?php else: ?>
                                                <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($exam['venue_name']): ?>
                                                <strong><?php echo htmlspecialchars($exam['venue_name']); ?></strong>
                                                <?php if ($exam['location']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($exam['location']); ?></small>
                                                <?php endif; ?>
                                                <?php else: ?>
                                                <span class="text-warning">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $statusColors = [
                                                    'Not Scheduled' => 'warning',
                                                    'Upcoming' => 'primary',
                                                    'Today' => 'success',
                                                    'Completed' => 'secondary'
                                                ];
                                                $statusColor = $statusColors[$exam['exam_status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $statusColor; ?>">
                                                    <?php echo $exam['exam_status']; ?>
                                                </span>
                                                <?php if ($exam['exam_status'] === 'Today'): ?>
                                                <br><small class="text-success"><strong>Exam Day!</strong></small>
                                                <?php elseif ($exam['exam_status'] === 'Upcoming' && $exam['exam_date']): ?>
                                                <br><small class="text-muted">
                                                    <?php 
                                                    $days = floor((strtotime($exam['exam_date']) - time()) / (60*60*24));
                                                    echo $days . ' day' . ($days != 1 ? 's' : '') . ' left';
                                                    ?>
                                                </small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistics -->
            <?php if (!empty($examinations)): ?>
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="text-primary"><?php echo count(array_filter($examinations, function($e) { return $e['exam_status'] === 'Upcoming'; })); ?></h5>
                            <small class="text-muted">Upcoming Exams</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="text-warning"><?php echo count(array_filter($examinations, function($e) { return $e['exam_status'] === 'Not Scheduled'; })); ?></h5>
                            <small class="text-muted">Not Scheduled</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="text-success"><?php echo count(array_filter($examinations, function($e) { return $e['exam_status'] === 'Today'; })); ?></h5>
                            <small class="text-muted">Today</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="text-secondary"><?php echo count(array_filter($examinations, function($e) { return $e['exam_status'] === 'Completed'; })); ?></h5>
                            <small class="text-muted">Completed</small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
