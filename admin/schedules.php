<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Admin');

$pageTitle = "Schedule Management";

// Handle schedule operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setAlert('danger', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if ($action === 'create' || $action === 'update') {
                $scheduleId = $_POST['schedule_id'] ?? null;
                $examId = intval($_POST['exam_id']);
                $venueId = intval($_POST['venue_id']);
                $examDate = $_POST['exam_date'];
                $startTime = $_POST['start_time'];
                $endTime = $_POST['end_time'];
                $capacity = intval($_POST['capacity']);
                
                // Validate times
                if (strtotime($endTime) <= strtotime($startTime)) {
                    setAlert('danger', 'End time must be after start time.');
                } else {
                // Check for conflicts (venue and time overlap)
                $conflictQuery = "SELECT es.*, e.course_id, c.course_code, v.venue_name 
                                 FROM exam_schedules es 
                                 JOIN examinations e ON es.exam_id = e.exam_id
                                 JOIN courses c ON e.course_id = c.course_id
                                 JOIN venues v ON es.venue_id = v.venue_id
                                 WHERE es.venue_id = :venue_id 
                                 AND es.exam_date = :exam_date 
                                 AND ((es.start_time <= :start_time AND es.end_time > :start_time) 
                                      OR (es.start_time < :end_time AND es.end_time >= :end_time)
                                      OR (es.start_time >= :start_time AND es.end_time <= :end_time))";                    if ($action === 'update') {
                    $conflictQuery .= " AND es.schedule_id != :schedule_id";
                }
                    
                    $conflictStmt = $db->prepare($conflictQuery);
                    $conflictStmt->bindParam(':venue_id', $venueId);
                    $conflictStmt->bindParam(':exam_date', $examDate);
                    $conflictStmt->bindParam(':start_time', $startTime);
                    $conflictStmt->bindParam(':end_time', $endTime);
                    
                    if ($action === 'update') {
                        $conflictStmt->bindParam(':schedule_id', $scheduleId);
                    }
                    
                    $conflictStmt->execute();
                    $conflicts = $conflictStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($conflicts)) {
                        $conflictInfo = $conflicts[0];
                        setAlert('warning', "Schedule conflict: {$conflictInfo['venue_name']} is already booked for {$conflictInfo['course_code']} from {$conflictInfo['start_time']} to {$conflictInfo['end_time']} on {$conflictInfo['exam_date']}.");
                    } else {
                        if ($action === 'create') {
                            $query = "INSERT INTO exam_schedules (exam_id, venue_id, exam_date, start_time, end_time, capacity_allocated) 
                                     VALUES (:exam_id, :venue_id, :exam_date, :start_time, :end_time, :capacity)";
                            $stmt = $db->prepare($query);
                        } else {
                            $query = "UPDATE exam_schedules SET exam_id = :exam_id, venue_id = :venue_id, exam_date = :exam_date, 
                                     start_time = :start_time, end_time = :end_time, capacity_allocated = :capacity 
                                     WHERE schedule_id = :schedule_id";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':schedule_id', $scheduleId);
                        }
                        
                        $stmt->bindParam(':exam_id', $examId);
                        $stmt->bindParam(':venue_id', $venueId);
                        $stmt->bindParam(':exam_date', $examDate);
                        $stmt->bindParam(':start_time', $startTime);
                        $stmt->bindParam(':end_time', $endTime);
                        $stmt->bindParam(':capacity', $capacity);
                        
                        $stmt->execute();
                        
                        setAlert('success', $action === 'create' ? 'Schedule created successfully.' : 'Schedule updated successfully.');
                    }
                }
            } elseif ($action === 'delete') {
                $scheduleId = intval($_POST['schedule_id']);
                
                $query = "DELETE FROM exam_schedules WHERE schedule_id = :schedule_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':schedule_id', $scheduleId);
                $stmt->execute();
                
                setAlert('success', 'Schedule deleted successfully.');
            }
        } catch (Exception $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
    }
    
    header("Location: schedules.php");
    exit();
}

// Get schedules with pagination and filters
$page = intval($_GET['page'] ?? 1);
$search = sanitizeInput($_GET['search'] ?? '');
$venueFilter = intval($_GET['venue'] ?? 0);
$dateFilter = $_GET['date'] ?? '';
$recordsPerPage = 15;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get venues for filter dropdown
    $venueQuery = "SELECT * FROM venues ORDER BY venue_name";
    $venueStmt = $db->query($venueQuery);
    $venues = $venueStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get examinations for dropdown
    $examQuery = "SELECT e.*, c.course_code, c.course_title, d.department_name 
                  FROM examinations e 
                  JOIN courses c ON e.course_id = c.course_id 
                  JOIN departments d ON c.department_id = d.department_id 
                  ORDER BY d.department_name, c.course_code";
    $examStmt = $db->query($examQuery);
    $examinations = $examStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build where clause
    $whereConditions = [];
    $params = [];
    
    if (!empty($search)) {
        $whereConditions[] = "(c.course_code LIKE :search OR c.course_title LIKE :search OR v.venue_name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if ($venueFilter > 0) {
        $whereConditions[] = "es.venue_id = :venue_id";
        $params[':venue_id'] = $venueFilter;
    }
    
    if (!empty($dateFilter)) {
        $whereConditions[] = "es.exam_date = :exam_date";
        $params[':exam_date'] = $dateFilter;
    }
    
    $whereClause = empty($whereConditions) ? "" : " WHERE " . implode(" AND ", $whereConditions);
    
    // Count total records
    $countQuery = "SELECT COUNT(*) as total 
                   FROM exam_schedules es 
                   JOIN examinations e ON es.exam_id = e.exam_id 
                   JOIN courses c ON e.course_id = c.course_id 
                   JOIN venues v ON es.venue_id = v.venue_id" . $whereClause;
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calculate pagination
    $pagination = paginate($page, $totalRecords, $recordsPerPage);
    
    // Get schedules
    $query = "SELECT es.*, e.exam_id, e.exam_type, c.course_code, c.course_title, c.academic_level,
                     v.venue_name, v.capacity as max_capacity, d.department_name,
                     COUNT(DISTINCT er.registration_id) as registered_students
              FROM exam_schedules es 
              JOIN examinations e ON es.exam_id = e.exam_id
              JOIN courses c ON e.course_id = c.course_id
              JOIN departments d ON c.department_id = d.department_id
              JOIN venues v ON es.venue_id = v.venue_id
              LEFT JOIN exam_registrations er ON e.exam_id = er.exam_id" . 
              $whereClause . 
              " GROUP BY es.schedule_id
              ORDER BY es.exam_date, es.start_time 
              LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();
    
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error loading schedules: " . $e->getMessage();
}

$csrfToken = generateCSRFToken();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-calendar-alt"></i> Schedule Management</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal" onclick="openScheduleModal()">
                <i class="fas fa-plus"></i> Add Schedule
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
                               placeholder="Search by course or venue..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" name="venue">
                            <option value="">All Venues</option>
                            <?php foreach ($venues as $venue): ?>
                            <option value="<?php echo $venue['venue_id']; ?>" 
                                    <?php echo $venueFilter == $venue['venue_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($venue['venue_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="date" 
                               value="<?php echo htmlspecialchars($dateFilter); ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="schedules.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Schedules Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Exam Schedules (<?php echo number_format($totalRecords); ?> total)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($schedules)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Exam Type</th>
                                <th>Venue</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Capacity</th>
                                <th>Registered</th>
                                <th>Utilization</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $schedule): ?>
                            <?php 
                                $utilization = $schedule['capacity_allocated'] > 0 ? ($schedule['registered_students'] / $schedule['capacity_allocated']) * 100 : 0;
                                $utilizationClass = $utilization > 90 ? 'bg-danger' : ($utilization > 70 ? 'bg-warning' : 'bg-success');
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($schedule['course_code']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($schedule['course_title']); ?></small>
                                    <br><span class="badge bg-info"><?php echo $schedule['academic_level']; ?> Level</span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $schedule['exam_type'] === 'Final' ? 'bg-primary' : 'bg-secondary'; ?>">
                                        <?php echo htmlspecialchars($schedule['exam_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($schedule['venue_name']); ?></strong>
                                    <br><small class="text-muted">Max: <?php echo $schedule['max_capacity']; ?></small>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($schedule['exam_date'])); ?>
                                    <br><small class="text-muted"><?php echo date('l', strtotime($schedule['exam_date'])); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo date('g:i A', strtotime($schedule['start_time'])); ?></strong>
                                    <br><small class="text-muted">to <?php echo date('g:i A', strtotime($schedule['end_time'])); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $schedule['capacity_allocated']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $schedule['registered_students']; ?></span>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar <?php echo $utilizationClass; ?>" 
                                             style="width: <?php echo min(100, $utilization); ?>%">
                                            <?php echo round($utilization, 1); ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editSchedule(<?php echo htmlspecialchars(json_encode($schedule)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteSchedule(<?php echo $schedule['schedule_id']; ?>)">
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
                            <a class="page-link" href="?page=<?php echo $pagination['page'] - 1; ?>&search=<?php echo urlencode($search); ?>&venue=<?php echo $venueFilter; ?>&date=<?php echo urlencode($dateFilter); ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                        <li class="page-item <?php echo $i == $pagination['page'] ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&venue=<?php echo $venueFilter; ?>&date=<?php echo urlencode($dateFilter); ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $pagination['page'] + 1; ?>&search=<?php echo urlencode($search); ?>&venue=<?php echo $venueFilter; ?>&date=<?php echo urlencode($dateFilter); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No schedules found</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleModalTitle">Add Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="scheduleForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="schedule_id" id="scheduleId">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="examId" class="form-label">Examination *</label>
                            <select class="form-control" id="examId" name="exam_id" required>
                                <option value="">Select Examination</option>
                                <?php foreach ($examinations as $exam): ?>
                                <option value="<?php echo $exam['exam_id']; ?>">
                                    <?php echo htmlspecialchars($exam['course_code'] . ' - ' . $exam['exam_type'] . ' (' . $exam['department_name'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="venueId" class="form-label">Venue *</label>
                            <select class="form-control" id="venueId" name="venue_id" required onchange="updateCapacity()">
                                <option value="">Select Venue</option>
                                <?php foreach ($venues as $venue): ?>
                                <option value="<?php echo $venue['venue_id']; ?>" data-capacity="<?php echo $venue['capacity']; ?>">
                                    <?php echo htmlspecialchars($venue['venue_name'] . ' (Max: ' . $venue['capacity'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="examDate" class="form-label">Exam Date *</label>
                            <input type="date" class="form-control" id="examDate" name="exam_date" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="startTime" class="form-label">Start Time *</label>
                            <input type="time" class="form-control" id="startTime" name="start_time" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="endTime" class="form-label">End Time *</label>
                            <input type="time" class="form-control" id="endTime" name="end_time" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="capacity" class="form-label">Exam Capacity *</label>
                        <input type="number" class="form-control" id="capacity" name="capacity" required min="1">
                        <div class="form-text">Maximum number of students for this exam (cannot exceed venue capacity)</div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Schedule</button>
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
                <p>Are you sure you want to delete this schedule? This action cannot be undone.</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    This will affect students who have registered for this exam.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="schedule_id" id="deleteScheduleId">
                    <button type="submit" class="btn btn-danger">Delete Schedule</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openScheduleModal() {
    document.getElementById('scheduleModalTitle').textContent = 'Add Schedule';
    document.getElementById('formAction').value = 'create';
    document.getElementById('scheduleForm').reset();
    document.getElementById('scheduleId').value = '';
}

function editSchedule(schedule) {
    document.getElementById('scheduleModalTitle').textContent = 'Edit Schedule';
    document.getElementById('formAction').value = 'update';
    document.getElementById('scheduleId').value = schedule.schedule_id;
    document.getElementById('examId').value = schedule.exam_id;
    document.getElementById('venueId').value = schedule.venue_id;
    document.getElementById('examDate').value = schedule.exam_date;
    document.getElementById('startTime').value = schedule.start_time;
    document.getElementById('endTime').value = schedule.end_time;
    document.getElementById('capacity').value = schedule.capacity;
    
    updateCapacity(); // Update max capacity based on selected venue
    
    new bootstrap.Modal(document.getElementById('scheduleModal')).show();
}

function deleteSchedule(scheduleId) {
    document.getElementById('deleteScheduleId').value = scheduleId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function updateCapacity() {
    const venueSelect = document.getElementById('venueId');
    const capacityInput = document.getElementById('capacity');
    const selectedOption = venueSelect.options[venueSelect.selectedIndex];
    
    if (selectedOption && selectedOption.dataset.capacity) {
        const maxCapacity = parseInt(selectedOption.dataset.capacity);
        capacityInput.max = maxCapacity;
        
        // If current value exceeds max, reset it
        if (capacityInput.value && parseInt(capacityInput.value) > maxCapacity) {
            capacityInput.value = maxCapacity;
        }
    }
}
</script>

<?php include '../includes/footer.php'; ?>
