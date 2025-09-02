<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Student');

$pageTitle = "Course Enrollment";

// Get current student details
$database = new Database();
$db = $database->getConnection();

$studentQuery = "SELECT s.*, d.department_name FROM students s 
                 JOIN departments d ON s.department_id = d.department_id 
                 WHERE s.user_id = :user_id";
$studentStmt = $db->prepare($studentQuery);
$studentStmt->bindParam(':user_id', $_SESSION['user_id']);
$studentStmt->execute();
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

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
        $studentStmt->execute();
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
        
        setAlert('success', 'Student profile created automatically. Matric Number: ' . $matricNumber);
    }
    
    if (!$student) {
        setAlert('danger', 'Unable to create student record. Please contact administrator.');
        header('Location: dashboard.php');
        exit;
    }
}

// Handle enrollment/withdrawal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setAlert('danger', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        $courseId = intval($_POST['course_id']);
        $examPeriodId = intval($_POST['exam_period_id']);
        
        try {
            if ($action === 'enroll') {
                // Check if already enrolled
                $checkQuery = "SELECT COUNT(*) FROM student_course_enrollments 
                              WHERE student_id = :student_id AND course_id = :course_id AND exam_period_id = :exam_period_id";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':student_id', $student['student_id']);
                $checkStmt->bindParam(':course_id', $courseId);
                $checkStmt->bindParam(':exam_period_id', $examPeriodId);
                $checkStmt->execute();
                
                if ($checkStmt->fetchColumn() > 0) {
                    setAlert('warning', 'You are already enrolled in this course for this exam period.');
                } else {
                    $enrollQuery = "INSERT INTO student_course_enrollments (student_id, course_id, exam_period_id, enrollment_date) 
                                   VALUES (:student_id, :course_id, :exam_period_id, CURDATE())";
                    $enrollStmt = $db->prepare($enrollQuery);
                    $enrollStmt->bindParam(':student_id', $student['student_id']);
                    $enrollStmt->bindParam(':course_id', $courseId);
                    $enrollStmt->bindParam(':exam_period_id', $examPeriodId);
                    $enrollStmt->execute();
                    setAlert('success', 'Successfully enrolled in the course!');
                }
                
            } elseif ($action === 'withdraw') {
                $withdrawQuery = "UPDATE student_course_enrollments SET status = 'Withdrawn' 
                                 WHERE student_id = :student_id AND course_id = :course_id AND exam_period_id = :exam_period_id";
                $withdrawStmt = $db->prepare($withdrawQuery);
                $withdrawStmt->bindParam(':student_id', $student['student_id']);
                $withdrawStmt->bindParam(':course_id', $courseId);
                $withdrawStmt->bindParam(':exam_period_id', $examPeriodId);
                $withdrawStmt->execute();
                setAlert('success', 'Successfully withdrawn from the course!');
            }
        } catch (PDOException $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
        
        header('Location: enrollment.php');
        exit;
    }
}

// Get current exam period
$currentPeriodQuery = "SELECT * FROM exam_periods WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1";
$currentPeriodStmt = $db->query($currentPeriodQuery);
$currentPeriod = $currentPeriodStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentPeriod) {
    $error = "No active exam period found. Please contact administrator.";
} else {
    // Get available courses for the student's level and department
    $availableCoursesQuery = "SELECT c.*, d.department_name,
                             CASE WHEN sce.enrollment_id IS NOT NULL THEN sce.status ELSE 'Not Enrolled' END as enrollment_status
                             FROM courses c 
                             JOIN departments d ON c.department_id = d.department_id
                             LEFT JOIN student_course_enrollments sce ON c.course_id = sce.course_id 
                                 AND sce.student_id = :student_id AND sce.exam_period_id = :exam_period_id
                             WHERE (c.department_id = :department_id OR c.course_type = 'Elective')
                             AND c.academic_level = :academic_level
                             ORDER BY c.course_code";
    $availableStmt = $db->prepare($availableCoursesQuery);
    $availableStmt->bindParam(':student_id', $student['student_id']);
    $availableStmt->bindParam(':exam_period_id', $currentPeriod['exam_period_id']);
    $availableStmt->bindParam(':department_id', $student['department_id']);
    $availableStmt->bindParam(':academic_level', $student['academic_level']);
    $availableStmt->execute();
    $availableCourses = $availableStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get enrolled courses
    $enrolledCoursesQuery = "SELECT c.*, d.department_name, sce.enrollment_date, sce.status
                            FROM student_course_enrollments sce
                            JOIN courses c ON sce.course_id = c.course_id
                            JOIN departments d ON c.department_id = d.department_id
                            WHERE sce.student_id = :student_id AND sce.exam_period_id = :exam_period_id
                            AND sce.status = 'Registered'
                            ORDER BY c.course_code";
    $enrolledStmt = $db->prepare($enrolledCoursesQuery);
    $enrolledStmt->bindParam(':student_id', $student['student_id']);
    $enrolledStmt->bindParam(':exam_period_id', $currentPeriod['exam_period_id']);
    $enrolledStmt->execute();
    $enrolledCourses = $enrolledStmt->fetchAll(PDO::FETCH_ASSOC);
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <main class="col-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Course Enrollment</h1>
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
                                    <strong>Enrolled Courses:</strong> <?php echo count($enrolledCourses); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Enrolled Courses -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">My Enrolled Courses (<?php echo count($enrolledCourses); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($enrolledCourses)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                <p class="text-muted">You haven't enrolled in any courses yet.</p>
                            </div>
                            <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($enrolledCourses as $course): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="me-auto">
                                        <div class="fw-bold"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($course['course_title']); ?></small>
                                        <br><small class="text-muted">
                                            <?php echo $course['credit_units']; ?> Units • 
                                            Enrolled: <?php echo date('M j, Y', strtotime($course['enrollment_date'])); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <?php if (strtotime($currentPeriod['registration_deadline']) > time()): ?>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to withdraw from this course?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="withdraw">
                                            <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                            <input type="hidden" name="exam_period_id" value="<?php echo $currentPeriod['exam_period_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-times"></i> Withdraw
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Locked</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Available Courses -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Available Courses</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($availableCourses)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No courses available for your level.</p>
                            </div>
                            <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($availableCourses as $course): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="me-auto">
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars($course['course_code']); ?>
                                            <span class="badge bg-<?php echo $course['course_type'] === 'Core' ? 'primary' : 'info'; ?>">
                                                <?php echo $course['course_type']; ?>
                                            </span>
                                        </div>
                                        <small class="text-muted"><?php echo htmlspecialchars($course['course_title']); ?></small>
                                        <br><small class="text-muted">
                                            <?php echo $course['credit_units']; ?> Units • 
                                            <?php echo htmlspecialchars($course['department_name']); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <?php if ($course['enrollment_status'] === 'Not Enrolled'): ?>
                                            <?php if (strtotime($currentPeriod['registration_deadline']) > time()): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="enroll">
                                                <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                                <input type="hidden" name="exam_period_id" value="<?php echo $currentPeriod['exam_period_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="fas fa-plus"></i> Enroll
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">Registration Closed</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                        <span class="badge bg-<?php echo $course['enrollment_status'] === 'Registered' ? 'success' : 'warning'; ?>">
                                            <?php echo $course['enrollment_status']; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
