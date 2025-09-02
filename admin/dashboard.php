<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Admin');

$pageTitle = "Admin Dashboard";

// Get statistics
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Count total users
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Count total students
    $stmt = $db->query("SELECT COUNT(*) as total FROM students s JOIN users u ON s.user_id = u.user_id WHERE u.is_active = 1");
    $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Count total courses
    $stmt = $db->query("SELECT COUNT(*) as total FROM courses");
    $totalCourses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Count total venues
    $stmt = $db->query("SELECT COUNT(*) as total FROM venues WHERE is_available = 1");
    $totalVenues = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Count scheduled exams
    $stmt = $db->query("SELECT COUNT(*) as total FROM exam_schedules WHERE exam_date >= CURDATE()");
    $scheduledExams = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Count ongoing exams (today's exams)
    $stmt = $db->query("SELECT COUNT(*) as total FROM exam_schedules WHERE exam_date = CURDATE()");
    $ongoingExams = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get recent activities
    $query = "SELECT 
                'User Registration' as activity_type,
                CONCAT(first_name, ' ', last_name) as description,
                created_at as activity_date
              FROM users 
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              ORDER BY created_at DESC 
              LIMIT 5";
    $stmt = $db->query($query);
    $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get upcoming exams
    $query = "SELECT 
                c.course_code,
                c.course_title,
                es.exam_date,
                es.start_time,
                v.venue_name
              FROM exam_schedules es
              JOIN examinations e ON es.exam_id = e.exam_id
              JOIN courses c ON e.course_id = c.course_id
              JOIN venues v ON es.venue_id = v.venue_id
              WHERE es.exam_date >= CURDATE()
              ORDER BY es.exam_date, es.start_time
              LIMIT 10";
    $stmt = $db->query($query);
    $upcomingExams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error loading dashboard data: " . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
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

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                <h2 class="stats-number"><?php echo number_format($totalUsers ?? 0); ?></h2>
                <p class="stats-label">Total Users</p>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-user-graduate fa-2x text-success mb-2"></i>
                <h2 class="stats-number"><?php echo number_format($totalStudents ?? 0); ?></h2>
                <p class="stats-label">Students</p>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-book fa-2x text-info mb-2"></i>
                <h2 class="stats-number"><?php echo number_format($totalCourses ?? 0); ?></h2>
                <p class="stats-label">Courses</p>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-building fa-2x text-warning mb-2"></i>
                <h2 class="stats-number"><?php echo number_format($totalVenues ?? 0); ?></h2>
                <p class="stats-label">Venues</p>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-calendar-check fa-2x text-danger mb-2"></i>
                <h2 class="stats-number"><?php echo number_format($scheduledExams ?? 0); ?></h2>
                <p class="stats-label">Scheduled</p>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x text-secondary mb-2"></i>
                <h2 class="stats-number"><?php echo number_format($ongoingExams ?? 0); ?></h2>
                <p class="stats-label">Ongoing</p>
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
                    <div class="col-md-2 mb-2">
                        <a href="users.php" class="btn btn-primary w-100">
                            <i class="fas fa-user-plus"></i> Manage Users
                        </a>
                    </div>
                    <div class="col-md-2 mb-2">
                        <a href="lecturer_courses.php" class="btn btn-secondary w-100">
                            <i class="fas fa-user-tie"></i> Lecturer Assignments
                        </a>
                    </div>
                    <div class="col-md-2 mb-2">
                        <a href="schedules.php" class="btn btn-success w-100">
                            <i class="fas fa-calendar-plus"></i> Create Schedule
                        </a>
                    </div>
                    <div class="col-md-2 mb-2">
                        <a href="venues.php" class="btn btn-info w-100">
                            <i class="fas fa-building"></i> Manage Venues
                        </a>
                    </div>
                    <div class="col-md-2 mb-2">
                        <a href="courses.php" class="btn btn-warning w-100">
                            <i class="fas fa-book"></i> Manage Courses
                        </a>
                    </div>
                    <div class="col-md-2 mb-2">
                        <a href="examinations.php" class="btn btn-dark w-100">
                            <i class="fas fa-clipboard-list"></i> Examinations
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
                <a href="schedules.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($upcomingExams)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Venue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingExams as $exam): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($exam['course_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($exam['course_title']); ?></small>
                                </td>
                                <td><?php echo formatDate($exam['exam_date']); ?></td>
                                <td><?php echo formatTime($exam['start_time']); ?></td>
                                <td><?php echo htmlspecialchars($exam['venue_name']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No upcoming exams scheduled</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Activities -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history"></i> Recent Activities</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recentActivities)): ?>
                <div class="timeline">
                    <?php foreach ($recentActivities as $activity): ?>
                    <div class="timeline-item mb-3">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-user-plus text-primary"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1"><?php echo htmlspecialchars($activity['activity_type']); ?></h6>
                                <p class="mb-1 text-muted"><?php echo htmlspecialchars($activity['description']); ?></p>
                                <small class="text-muted"><?php echo formatDateTime($activity['activity_date']); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No recent activities</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- System Information -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-info-circle"></i> System Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>PHP Version:</strong><br>
                        <span class="text-muted"><?php echo PHP_VERSION; ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Server Time:</strong><br>
                        <span class="text-muted"><?php echo date('Y-m-d H:i:s'); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Database:</strong><br>
                        <span class="text-muted">MySQL</span>
                    </div>
                    <div class="col-md-3">
                        <strong>System Version:</strong><br>
                        <span class="text-muted">1.0.0</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
