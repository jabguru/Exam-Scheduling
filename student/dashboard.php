<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Student');

$pageTitle = "Student Dashboard";

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
    
    // Get exam statistics
    $query = "SELECT COUNT(*) as total FROM exam_registrations er
              JOIN examinations e ON er.exam_id = e.exam_id
              WHERE er.student_id = :student_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $student['student_id']);
    $stmt->execute();
    $totalRegistrations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get upcoming exams
    $query = "SELECT 
                c.course_code,
                c.course_title,
                es.exam_date,
                es.start_time,
                es.end_time,
                v.venue_name
              FROM exam_registrations er
              JOIN examinations e ON er.exam_id = e.exam_id
              JOIN courses c ON e.course_id = c.course_id
              JOIN exam_schedules es ON e.exam_id = es.exam_id
              JOIN venues v ON es.venue_id = v.venue_id
              WHERE er.student_id = :student_id 
              AND es.exam_date >= CURDATE()
              ORDER BY es.exam_date, es.start_time
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $student['student_id']);
    $stmt->execute();
    $upcomingExams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent exam results (completed exams)
    $query = "SELECT 
                c.course_code,
                c.course_title,
                es.exam_date,
                er.status as registration_status
              FROM exam_registrations er
              JOIN examinations e ON er.exam_id = e.exam_id
              JOIN courses c ON e.course_id = c.course_id
              JOIN exam_schedules es ON e.exam_id = es.exam_id
              WHERE er.student_id = :student_id 
              AND es.exam_date < CURDATE()
              ORDER BY es.exam_date DESC
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $student['student_id']);
    $stmt->execute();
    $recentExams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available courses for registration
    $query = "SELECT 
                c.course_id,
                c.course_code,
                c.course_title,
                c.credit_units
              FROM courses c
              JOIN examinations e ON c.course_id = e.course_id
              WHERE c.department_id = :department_id 
              AND NOT EXISTS (
                  SELECT 1 FROM exam_registrations er 
                  WHERE er.exam_id = e.exam_id AND er.student_id = :student_id
              )
              ORDER BY c.course_code
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department_id', $student['department_id']);
    $stmt->bindParam(':student_id', $student['student_id']);
    $stmt->execute();
    $availableCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error loading dashboard data: " . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-tachometer-alt"></i> Student Dashboard</h1>
            <div>
                <span class="text-muted">Welcome back, <?php echo $_SESSION['first_name']; ?>!</span>
            </div>
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

<!-- Student Information Card -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-user"></i> Student Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Matric Number:</strong><br>
                        <span class="text-primary"><?php echo htmlspecialchars($student['matric_number'] ?? ''); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Department:</strong><br>
                        <span><?php echo htmlspecialchars($student['department_name'] ?? ''); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Academic Level:</strong><br>
                        <span><?php echo htmlspecialchars($student['academic_level'] ?? ''); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Current Semester:</strong><br>
                        <span><?php echo htmlspecialchars($student['current_semester'] ?? ''); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-calendar-check fa-2x text-primary mb-2"></i>
                <h2 class="stats-number"><?php echo number_format($totalRegistrations ?? 0); ?></h2>
                <p class="stats-label">Exam Registrations</p>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                <h2 class="stats-number"><?php echo count($upcomingExams ?? []); ?></h2>
                <p class="stats-label">Upcoming Exams</p>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h2 class="stats-number"><?php echo count($recentExams ?? []); ?></h2>
                <p class="stats-label">Completed Exams</p>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-book fa-2x text-info mb-2"></i>
                <h2 class="stats-number"><?php echo count($availableCourses ?? []); ?></h2>
                <p class="stats-label">Available Courses</p>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="schedule.php" class="btn btn-primary w-100">
                            <i class="fas fa-calendar"></i> View Schedule
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="registration.php" class="btn btn-success w-100">
                            <i class="fas fa-edit"></i> Register for Exams
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="#" class="btn btn-info w-100">
                            <i class="fas fa-download"></i> Download Hall Ticket
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="../profile.php" class="btn btn-secondary w-100">
                            <i class="fas fa-user-edit"></i> Update Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Upcoming Exams -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-calendar-alt"></i> Upcoming Exams</h5>
                <a href="schedule.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($upcomingExams)): ?>
                <div class="row">
                    <?php foreach ($upcomingExams as $exam): ?>
                    <div class="col-12 mb-3">
                        <div class="exam-card">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="exam-date">
                                        <?php echo formatDate($exam['exam_date']); ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="mb-1"><strong><?php echo htmlspecialchars($exam['course_code']); ?></strong></h6>
                                    <p class="mb-1"><?php echo htmlspecialchars($exam['course_title']); ?></p>
                                    <small class="exam-time">
                                        <i class="fas fa-clock"></i> <?php echo formatTime($exam['start_time']) . ' - ' . formatTime($exam['end_time']); ?>
                                    </small>
                                </div>
                                <div class="col-md-3 text-end">
                                    <div class="exam-venue">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($exam['venue_name']); ?>
                                    </div>
                                    <?php if ($exam['seat_number']): ?>
                                    <small class="text-muted">Seat: <?php echo htmlspecialchars($exam['seat_number']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No upcoming exams scheduled</p>
                    <a href="registration.php" class="btn btn-primary">Register for Exams</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Available Courses for Registration -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-book"></i> Available Courses</h5>
                <a href="registration.php" class="btn btn-sm btn-outline-success">Register</a>
            </div>
            <div class="card-body">
                <?php if (!empty($availableCourses)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($availableCourses as $course): ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($course['course_code']); ?></h6>
                            <small class="text-muted"><?php echo $course['credit_units']; ?> units</small>
                        </div>
                        <p class="mb-1"><?php echo htmlspecialchars($course['course_title']); ?></p>
                        <small class="text-danger">
                            <i class="fas fa-clock"></i> 
                            Deadline: <?php echo formatDate($course['registration_deadline']); ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No courses available for registration</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Exam History -->
<?php if (!empty($recentExams)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history"></i> Recent Exam History</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Exam Date</th>
                                <th>Status</th>
                                <th>Attendance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentExams as $exam): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($exam['course_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($exam['course_title']); ?></small>
                                </td>
                                <td><?php echo formatDate($exam['exam_date']); ?></td>
                                <td>
                                    <span class="badge status-<?php echo strtolower($exam['status']); ?>">
                                        <?php echo $exam['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge status-<?php echo strtolower($exam['registration_status']); ?>">
                                        <?php echo $exam['registration_status']; ?>
                                    </span>
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

<?php include '../includes/footer.php'; ?>
