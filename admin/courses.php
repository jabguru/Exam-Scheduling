<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Admin');

$pageTitle = "Course Management";

// Handle course creation/update/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setAlert('danger', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if ($action === 'create' || $action === 'update') {
                $courseId = $_POST['course_id'] ?? null;
                $courseCode = sanitizeInput($_POST['course_code']);
                $courseTitle = sanitizeInput($_POST['course_title']);
                $creditUnits = intval($_POST['credit_units']);
                $departmentId = intval($_POST['department_id']);
                $semester = sanitizeInput($_POST['semester']);
                $academicLevel = sanitizeInput($_POST['academic_level']);
                $courseType = sanitizeInput($_POST['course_type']);
                
                if ($action === 'create') {
                    $query = "INSERT INTO courses (course_code, course_title, credit_units, department_id, semester, academic_level, course_type) 
                             VALUES (:course_code, :course_title, :credit_units, :department_id, :semester, :academic_level, :course_type)";
                    $stmt = $db->prepare($query);
                } else {
                    $query = "UPDATE courses SET course_code = :course_code, course_title = :course_title, credit_units = :credit_units, 
                             department_id = :department_id, semester = :semester, academic_level = :academic_level, course_type = :course_type 
                             WHERE course_id = :course_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':course_id', $courseId);
                }
                
                $stmt->bindParam(':course_code', $courseCode);
                $stmt->bindParam(':course_title', $courseTitle);
                $stmt->bindParam(':credit_units', $creditUnits);
                $stmt->bindParam(':department_id', $departmentId);
                $stmt->bindParam(':semester', $semester);
                $stmt->bindParam(':academic_level', $academicLevel);
                $stmt->bindParam(':course_type', $courseType);
                
                $stmt->execute();
                
                setAlert('success', $action === 'create' ? 'Course created successfully.' : 'Course updated successfully.');
            } elseif ($action === 'delete') {
                $courseId = intval($_POST['course_id']);
                
                // Check if course has examinations
                $checkQuery = "SELECT COUNT(*) as exam_count FROM examinations WHERE course_id = :course_id";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':course_id', $courseId);
                $checkStmt->execute();
                $examCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['exam_count'];
                
                if ($examCount > 0) {
                    setAlert('warning', 'Cannot delete course. It has associated examinations.');
                } else {
                    $query = "DELETE FROM courses WHERE course_id = :course_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':course_id', $courseId);
                    $stmt->execute();
                    
                    setAlert('success', 'Course deleted successfully.');
                }
            }
        } catch (Exception $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }
    
    header("Location: courses.php");
    exit();
}

// Get courses with pagination and filters
$page = intval($_GET['page'] ?? 1);
$search = sanitizeInput($_GET['search'] ?? '');
$departmentFilter = intval($_GET['department'] ?? 0);
$levelFilter = sanitizeInput($_GET['level'] ?? '');
$recordsPerPage = 15;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get departments for filter dropdown
    $deptQuery = "SELECT * FROM departments ORDER BY department_name";
    $deptStmt = $db->query($deptQuery);
    $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build where clause
    $whereConditions = [];
    $params = [];
    
    if (!empty($search)) {
        $whereConditions[] = "(c.course_code LIKE :search OR c.course_title LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if ($departmentFilter > 0) {
        $whereConditions[] = "c.department_id = :dept_id";
        $params[':dept_id'] = $departmentFilter;
    }
    
    if (!empty($levelFilter)) {
        $whereConditions[] = "c.academic_level = :level";
        $params[':level'] = $levelFilter;
    }
    
    $whereClause = empty($whereConditions) ? "" : " WHERE " . implode(" AND ", $whereConditions);
    
    // Count total records
    $countQuery = "SELECT COUNT(*) as total FROM courses c" . $whereClause;
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calculate pagination
    $pagination = paginate($page, $totalRecords, $recordsPerPage);
    
    // Get courses
    $query = "SELECT c.*, d.department_name, d.department_code,
                     COUNT(DISTINCT e.exam_id) as exam_count,
                     COUNT(DISTINCT er.registration_id) as student_count
              FROM courses c 
              JOIN departments d ON c.department_id = d.department_id
              LEFT JOIN examinations e ON c.course_id = e.course_id
              LEFT JOIN exam_registrations er ON e.exam_id = er.exam_id" . 
              $whereClause . 
              " GROUP BY c.course_id
              ORDER BY d.department_name, c.course_code 
              LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();
    
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error loading courses: " . $e->getMessage();
}

$csrfToken = generateCSRFToken();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-book"></i> Course Management</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#courseModal" onclick="openCourseModal()">
                <i class="fas fa-plus"></i> Add Course
            </button>
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

<!-- Search and Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by course code or title..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>" 
                                    <?php echo $departmentFilter == $dept['department_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
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
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="courses.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Courses Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Courses (<?php echo number_format($totalRecords); ?> total)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($courses)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th>Department</th>
                                <th>Level</th>
                                <th>Units</th>
                                <th>Type</th>
                                <th>Semester</th>
                                <th>Exams</th>
                                <th>Students</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['course_title']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($course['department_code']); ?></span>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($course['department_name']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($course['academic_level']); ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $course['credit_units']; ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $course['course_type'] === 'Core' ? 'bg-primary' : 'bg-warning'; ?>">
                                        <?php echo htmlspecialchars($course['course_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($course['semester']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $course['exam_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo $course['student_count']; ?></span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editCourse(<?php echo htmlspecialchars(json_encode($course)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete" 
                                            onclick="deleteCourse(<?php echo $course['course_id']; ?>)"
                                            <?php echo $course['exam_count'] > 0 ? 'disabled title="Cannot delete: has examinations"' : ''; ?>>
                                        <i class="fas fa-trash"></i>
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
                            <a class="page-link" href="?page=<?php echo $pagination['page'] - 1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo $departmentFilter; ?>&level=<?php echo urlencode($levelFilter); ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                        <li class="page-item <?php echo $i == $pagination['page'] ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo $departmentFilter; ?>&level=<?php echo urlencode($levelFilter); ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $pagination['page'] + 1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo $departmentFilter; ?>&level=<?php echo urlencode($levelFilter); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No courses found</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Course Modal -->
<div class="modal fade" id="courseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="courseModalTitle">Add Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="courseForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="course_id" id="courseId">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="courseCode" class="form-label">Course Code *</label>
                            <input type="text" class="form-control" id="courseCode" name="course_code" required>
                            <div class="form-text">e.g., CS101, MATH201</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="creditUnits" class="form-label">Credit Units *</label>
                            <input type="number" class="form-control" id="creditUnits" name="credit_units" required min="1" max="6">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="courseTitle" class="form-label">Course Title *</label>
                        <input type="text" class="form-control" id="courseTitle" name="course_title" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="departmentId" class="form-label">Department *</label>
                            <select class="form-control" id="departmentId" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="academicLevel" class="form-label">Academic Level *</label>
                            <select class="form-control" id="academicLevel" name="academic_level" required>
                                <option value="">Select Level</option>
                                <option value="100">100 Level</option>
                                <option value="200">200 Level</option>
                                <option value="300">300 Level</option>
                                <option value="400">400 Level</option>
                                <option value="500">500 Level</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="semester" class="form-label">Semester *</label>
                            <select class="form-control" id="semester" name="semester" required>
                                <option value="">Select Semester</option>
                                <option value="First">First Semester</option>
                                <option value="Second">Second Semester</option>
                                <option value="Both">Both Semesters</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="courseType" class="form-label">Course Type *</label>
                            <select class="form-control" id="courseType" name="course_type" required>
                                <option value="">Select Type</option>
                                <option value="Core">Core</option>
                                <option value="Elective">Elective</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Course</button>
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
                <p>Are you sure you want to delete this course? This action cannot be undone.</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    You can only delete courses that have no examinations.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="course_id" id="deleteCourseId">
                    <button type="submit" class="btn btn-danger">Delete Course</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openCourseModal() {
    document.getElementById('courseModalTitle').textContent = 'Add Course';
    document.getElementById('formAction').value = 'create';
    document.getElementById('courseForm').reset();
    document.getElementById('courseId').value = '';
}

function editCourse(course) {
    document.getElementById('courseModalTitle').textContent = 'Edit Course';
    document.getElementById('formAction').value = 'update';
    document.getElementById('courseId').value = course.course_id;
    document.getElementById('courseCode').value = course.course_code;
    document.getElementById('courseTitle').value = course.course_title;
    document.getElementById('creditUnits').value = course.credit_units;
    document.getElementById('departmentId').value = course.department_id;
    document.getElementById('academicLevel').value = course.academic_level;
    document.getElementById('semester').value = course.semester;
    document.getElementById('courseType').value = course.course_type;
    
    new bootstrap.Modal(document.getElementById('courseModal')).show();
}

function deleteCourse(courseId) {
    document.getElementById('deleteCourseId').value = courseId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>
