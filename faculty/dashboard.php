<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Faculty');

$pageTitle = "Faculty Dashboard";

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
        // User has faculty role but no faculty record - create one
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
        
        // Get first available department
        $defaultDeptQuery = "SELECT department_id FROM departments ORDER BY department_id LIMIT 1";
        $defaultDeptStmt = $db->query($defaultDeptQuery);
        $defaultDepartmentId = $defaultDeptStmt->fetchColumn();
        
        if ($defaultDepartmentId) {
            // Create faculty record
            $createFacultyQuery = "INSERT INTO faculty (user_id, staff_id, department_id, designation) 
                                  VALUES (:user_id, :staff_id, :department_id, 'Lecturer')";
            $createStmt = $db->prepare($createFacultyQuery);
            $createStmt->bindParam(':user_id', $_SESSION['user_id']);
            $createStmt->bindParam(':staff_id', $staffId);
            $createStmt->bindParam(':department_id', $defaultDepartmentId);
            $createStmt->execute();
            
            // Now fetch the faculty record again
            $stmt->execute();
            $faculty = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$faculty) {
            throw new Exception('Unable to create faculty profile. Please contact administrator.');
        }
    }
    
    // Get courses taught by faculty
    $query = "SELECT COUNT(*) as total 
              FROM courses c 
              WHERE c.department_id = :department_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department_id', $faculty['department_id']);
    $stmt->execute();
    $totalCourses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get exams created by faculty
    $query = "SELECT COUNT(*) as total 
              FROM examinations e 
              WHERE e.created_by = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $totalExams = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get students in faculty's department
    $query = "SELECT COUNT(*) as total 
              FROM students s 
              JOIN users u ON s.user_id = u.user_id
              WHERE s.department_id = :department_id AND u.is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department_id', $faculty['department_id']);
    $stmt->execute();
    $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get upcoming exams for faculty's courses
    $query = "SELECT 
                c.course_code,
                c.course_title,
                e.exam_type,
                es.exam_date,
                es.start_time,
                v.venue_name,
                COUNT(er.registration_id) as registered_students
              FROM examinations e
              JOIN courses c ON e.course_id = c.course_id
              LEFT JOIN exam_schedules es ON e.exam_id = es.exam_id
              LEFT JOIN venues v ON es.venue_id = v.venue_id
              LEFT JOIN exam_registrations er ON e.exam_id = er.exam_id
              WHERE c.department_id = :department_id 
              AND (es.exam_date IS NULL OR es.exam_date >= CURDATE())
              GROUP BY e.exam_id, c.course_id, es.schedule_id
              ORDER BY es.exam_date, es.start_time
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department_id', $faculty['department_id']);
    $stmt->execute();
    $upcomingExams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent exam registrations
    $query = "SELECT 
                c.course_code,
                c.course_title,
                COUNT(er.registration_id) as registrations,
                MAX(er.registration_date) as latest_registration
              FROM exam_registrations er
              JOIN examinations e ON er.exam_id = e.exam_id
              JOIN courses c ON e.course_id = c.course_id
              WHERE c.department_id = :department_id
              AND er.registration_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              GROUP BY c.course_id
              ORDER BY latest_registration DESC
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department_id', $faculty['department_id']);
    $stmt->execute();
    $recentRegistrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get courses in faculty's department
    $query = "SELECT 
                c.*,
                COUNT(DISTINCT e.exam_id) as exam_count,
                COUNT(DISTINCT er.registration_id) as student_count
              FROM courses c
              LEFT JOIN examinations e ON c.course_id = e.course_id
              LEFT JOIN exam_registrations er ON e.exam_id = er.exam_id
              WHERE c.department_id = :department_id
              GROUP BY c.course_id
              ORDER BY c.course_code
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':department_id', $faculty['department_id']);
    $stmt->execute();
    $departmentCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error loading dashboard data: " . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-chalkboard-teacher"></i> Faculty Dashboard</h1>
            <div>
                <span class="text-muted">Welcome back, Dr. <?php echo $_SESSION['first_name']; ?>!</span>
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

<!-- Faculty Information Card -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-user-tie"></i> Faculty Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Staff ID:</strong><br>
                        <span class="text-primary"><?php echo htmlspecialchars($faculty['staff_id'] ?? ''); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Department:</strong><br>
                        <span><?php echo htmlspecialchars($faculty['department_name'] ?? ''); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Designation:</strong><br>
                        <span><?php echo htmlspecialchars($faculty['designation'] ?? 'Faculty Member'); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Specialization:</strong><br>
                        <span><?php echo htmlspecialchars($faculty['specialization'] ?? 'General'); ?></span>
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
                <i class="fas fa-book fa-2x text-primary mb-2"></i>
                <h2 class="stats-number"><?php echo number_format($totalCourses ?? 0); ?></h2>
                <p class="stats-label">Department Courses</p>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-file-alt fa-2x text-success mb-2"></i>
                <h2 class="stats-number"><?php echo number_format($totalExams ?? 0); ?></h2>
                <p class="stats-label">Exams Created</p>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-user-graduate fa-2x text-info mb-2"></i>
                <h2 class="stats-number"><?php echo number_format($totalStudents ?? 0); ?></h2>
                <p class="stats-label">Department Students</p>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-calendar-check fa-2x text-warning mb-2"></i>
                <h2 class="stats-number"><?php echo count($upcomingExams ?? []); ?></h2>
                <p class="stats-label">Upcoming Exams</p>
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
                        <a href="exams.php" class="btn btn-primary w-100">
                            <i class="fas fa-plus"></i> Create Exam
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="exams.php" class="btn btn-success w-100">
                            <i class="fas fa-file-alt"></i> Manage Exams
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="students.php" class="btn btn-info w-100">
                            <i class="fas fa-users"></i> View Students
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="#" class="btn btn-warning w-100">
                            <i class="fas fa-chart-bar"></i> Generate Reports
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
                <a href="exams.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($upcomingExams)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Type</th>
                                <th>Date & Time</th>
                                <th>Venue</th>
                                <th>Students</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingExams as $exam): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($exam['course_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($exam['course_title']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $exam['exam_type']; ?></span>
                                </td>
                                <td>
                                    <?php if ($exam['exam_date']): ?>
                                        <?php echo formatDate($exam['exam_date']); ?><br>
                                        <small class="text-muted"><?php echo formatTime($exam['start_time']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Not scheduled</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($exam['venue_name'] ?? 'TBA'); ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $exam['registered_students']; ?></span>
                                </td>
                                <td>
                                    <span class="badge status-<?php echo strtolower($exam['status'] ?? 'pending'); ?>">
                                        <?php echo $exam['status'] ?? 'Pending'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No upcoming exams found</p>
                    <a href="exams.php" class="btn btn-primary">Create New Exam</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Registrations -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-user-plus"></i> Recent Registrations</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recentRegistrations)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentRegistrations as $registration): ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($registration['course_code']); ?></h6>
                            <small class="text-primary">+<?php echo $registration['registrations']; ?></small>
                        </div>
                        <p class="mb-1"><?php echo htmlspecialchars($registration['course_title']); ?></p>
                        <small class="text-muted">
                            Latest: <?php echo formatDate($registration['latest_registration']); ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No recent registrations</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Department Courses -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-book"></i> Department Courses</h5>
                <a href="../admin/courses.php" class="btn btn-sm btn-outline-primary">Manage Courses</a>
            </div>
            <div class="card-body">
                <?php if (!empty($departmentCourses)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th>Credits</th>
                                <th>Level</th>
                                <th>Exams</th>
                                <th>Students</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departmentCourses as $course): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['course_title']); ?></td>
                                <td><?php echo $course['credit_units']; ?></td>
                                <td><?php echo htmlspecialchars($course['academic_level']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $course['exam_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo $course['student_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $course['course_type'] === 'Core' ? 'bg-primary' : 'bg-secondary'; ?>">
                                        <?php echo $course['course_type']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No courses found in your department</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
