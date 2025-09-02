<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Lecturer');

$pageTitle = "My Assigned Courses";

// Get lecturer information
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get lecturer details
    $query = "SELECT l.*, d.department_name 
              FROM lecturers l 
              JOIN departments d ON l.department_id = d.department_id
              WHERE l.user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lecturer) {
        throw new Exception('Lecturer profile not found.');
    }
    
    // Get courses assigned to lecturer by admin
    $coursesQuery = "SELECT 
                        c.*,
                        lca.assigned_date,
                        d.department_name,
                        COUNT(DISTINCT sce.student_id) as enrolled_students,
                        COUNT(DISTINCT e.exam_id) as total_exams
                     FROM lecturer_course_assignments lca
                     JOIN courses c ON lca.course_id = c.course_id
                     JOIN departments d ON c.department_id = d.department_id
                     LEFT JOIN student_course_enrollments sce ON c.course_id = sce.course_id 
                                                              AND sce.status = 'Registered'
                     LEFT JOIN examinations e ON c.course_id = e.course_id
                     WHERE lca.lecturer_id = :lecturer_id
                     GROUP BY c.course_id, lca.assigned_date, d.department_name
                     ORDER BY c.course_code";
    $coursesStmt = $db->prepare($coursesQuery);
    $coursesStmt->bindParam(':lecturer_id', $lecturer['lecturer_id']);
    $coursesStmt->execute();
    $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    setAlert('danger', 'Error: ' . $e->getMessage());
    $lecturer = [];
    $courses = [];
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-book"></i> My Assigned Courses</h1>
        </div>
    </div>
</div>

<!-- Lecturer Info Card -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Lecturer Information</h5>
    </div>
    <div class="card-body">
        <?php if ($lecturer): ?>
        <div class="row">
            <div class="col-md-6">
                <p><strong>Staff ID:</strong> <?php echo htmlspecialchars($lecturer['staff_id']); ?></p>
                <p><strong>Department:</strong> <?php echo htmlspecialchars($lecturer['department_name']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Designation:</strong> <?php echo htmlspecialchars($lecturer['designation']); ?></p>
                <p><strong>Total Courses:</strong> <?php echo count($courses); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Assigned Courses -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-list"></i> Assigned Courses (<?php echo count($courses); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($courses)): ?>
        <div class="text-center py-4">
            <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No Courses Assigned</h5>
            <p class="text-muted">You haven't been assigned any courses yet. Course assignments are managed by the admin.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Course Code</th>
                        <th>Course Title</th>
                        <th>Department</th>
                        <th>Credit Units</th>
                        <th>Enrolled Students</th>
                        <th>Total Exams</th>
                        <th>Assigned Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($course['course_title']); ?></td>
                        <td><?php echo htmlspecialchars($course['department_name']); ?></td>
                        <td>
                            <span class="badge bg-info"><?php echo $course['credit_units']; ?></span>
                        </td>
                        <td>
                            <span class="badge bg-success"><?php echo $course['enrolled_students']; ?></span>
                        </td>
                        <td>
                            <span class="badge bg-primary"><?php echo $course['total_exams']; ?></span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($course['assigned_date'])); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="students.php?course_id=<?php echo $course['course_id']; ?>" 
                                   class="btn btn-outline-primary" title="View Students">
                                    <i class="fas fa-users"></i>
                                </a>
                                <a href="exams.php?course_id=<?php echo $course['course_id']; ?>" 
                                   class="btn btn-outline-success" title="View Exams">
                                    <i class="fas fa-file-alt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-users fa-2x text-primary mb-3"></i>
                <h5 class="card-title">Students</h5>
                <p class="card-text">View students enrolled in your courses.</p>
                <a href="students.php" class="btn btn-primary">View All Students</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-file-alt fa-2x text-success mb-3"></i>
                <h5 class="card-title">Examinations</h5>
                <p class="card-text">View examinations for your courses.</p>
                <a href="exams.php" class="btn btn-success">View All Exams</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
                             