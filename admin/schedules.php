<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Admin');

$pageTitle = "Schedule Management";

// Helper function to create schedule with multiple venues based on enrollment
function createScheduleWithMultipleVenues($db, $examId, $primaryVenueId, $examDate, $startTime, $endTime, $capacity) {
    // Get total enrolled students for this exam
    $enrollmentQuery = "SELECT COUNT(DISTINCT sce.student_id) as total_students
                       FROM student_course_enrollments sce
                       JOIN examinations e ON sce.course_id = e.course_id AND sce.exam_period_id = e.exam_period_id
                       WHERE e.exam_id = :exam_id AND sce.status = 'Registered'";
    $enrollmentStmt = $db->prepare($enrollmentQuery);
    $enrollmentStmt->bindParam(':exam_id', $examId);
    $enrollmentStmt->execute();
    $totalStudents = $enrollmentStmt->fetch(PDO::FETCH_ASSOC)['total_students'];
    
    // Get primary venue capacity
    $venueQuery = "SELECT capacity FROM venues WHERE venue_id = :venue_id";
    $venueStmt = $db->prepare($venueQuery);
    $venueStmt->bindParam(':venue_id', $primaryVenueId);
    $venueStmt->execute();
    $primaryVenueCapacity = $venueStmt->fetch(PDO::FETCH_ASSOC)['capacity'];
    
    $venues = [$primaryVenueId];
    $remainingStudents = $totalStudents;
    
    // If primary venue can't accommodate all students, find additional venues
    if ($totalStudents > $primaryVenueCapacity) {
        $remainingStudents -= $primaryVenueCapacity;
        
        // Find additional available venues for the same time slot
        $additionalVenuesQuery = "SELECT v.venue_id, v.capacity 
                                 FROM venues v 
                                 WHERE v.venue_id != :primary_venue_id
                                 AND v.venue_id NOT IN (
                                     SELECT es.venue_id 
                                     FROM exam_schedules es 
                                     WHERE es.exam_date = :exam_date 
                                     AND ((es.start_time <= :start_time AND es.end_time > :start_time) 
                                          OR (es.start_time < :end_time AND es.end_time >= :end_time)
                                          OR (es.start_time >= :start_time AND es.end_time <= :end_time))
                                 )
                                 ORDER BY v.capacity DESC";
        $additionalStmt = $db->prepare($additionalVenuesQuery);
        $additionalStmt->bindParam(':primary_venue_id', $primaryVenueId);
        $additionalStmt->bindParam(':exam_date', $examDate);
        $additionalStmt->bindParam(':start_time', $startTime);
        $additionalStmt->bindParam(':end_time', $endTime);
        $additionalStmt->execute();
        $additionalVenues = $additionalStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($additionalVenues as $venue) {
            if ($remainingStudents <= 0) break;
            $venues[] = $venue['venue_id'];
            $remainingStudents -= $venue['capacity'];
        }
        
        if ($remainingStudents > 0) {
            setAlert('warning', "Warning: Not enough venue capacity. {$remainingStudents} students may not have seats assigned.");
        }
    }
    
    // Create schedule entries for all venues
    $venuesCreated = 0;
    foreach ($venues as $index => $venueId) {
        // Calculate capacity for this venue
        $venueCapacityQuery = "SELECT capacity FROM venues WHERE venue_id = :venue_id";
        $venueCapStmt = $db->prepare($venueCapacityQuery);
        $venueCapStmt->bindParam(':venue_id', $venueId);
        $venueCapStmt->execute();
        $venueCapacity = $venueCapStmt->fetch(PDO::FETCH_ASSOC)['capacity'];
        
        $studentsForThisVenue = min($venueCapacity, $totalStudents - ($venuesCreated * $venueCapacity));
        
        $query = "INSERT INTO exam_schedules (exam_id, venue_id, exam_date, start_time, end_time, capacity_allocated, students_assigned) 
                 VALUES (:exam_id, :venue_id, :exam_date, :start_time, :end_time, :capacity, :students_assigned)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':exam_id', $examId);
        $stmt->bindParam(':venue_id', $venueId);
        $stmt->bindParam(':exam_date', $examDate);
        $stmt->bindParam(':start_time', $startTime);
        $stmt->bindParam(':end_time', $endTime);
        $stmt->bindParam(':capacity', min($capacity, $venueCapacity));
        $stmt->bindParam(':students_assigned', $studentsForThisVenue);
        $stmt->execute();
        
        $venuesCreated++;
    }
    
    // Auto-assign students to venues
    assignStudentsToVenues($db, $examId);
    
    $message = count($venues) === 1 ? 
        'Schedule created successfully.' : 
        'Schedule created successfully with ' . count($venues) . ' venues to accommodate all ' . $totalStudents . ' students.';
    setAlert('success', $message);
}

// Helper function to update a single schedule
function updateSingleSchedule($db, $scheduleId, $examId, $venueId, $examDate, $startTime, $endTime, $capacity) {
    // Check for conflicts
    $conflictQuery = "SELECT es.*, e.course_id, c.course_code, v.venue_name 
                     FROM exam_schedules es 
                     JOIN examinations e ON es.exam_id = e.exam_id
                     JOIN courses c ON e.course_id = c.course_id
                     JOIN venues v ON es.venue_id = v.venue_id
                     WHERE es.venue_id = :venue_id 
                     AND es.exam_date = :exam_date 
                     AND es.schedule_id != :schedule_id
                     AND ((es.start_time <= :start_time AND es.end_time > :start_time) 
                          OR (es.start_time < :end_time AND es.end_time >= :end_time)
                          OR (es.start_time >= :start_time AND es.end_time <= :end_time))";
    
    $conflictStmt = $db->prepare($conflictQuery);
    $conflictStmt->bindParam(':venue_id', $venueId);
    $conflictStmt->bindParam(':exam_date', $examDate);
    $conflictStmt->bindParam(':start_time', $startTime);
    $conflictStmt->bindParam(':end_time', $endTime);
    $conflictStmt->bindParam(':schedule_id', $scheduleId);
    $conflictStmt->execute();
    $conflicts = $conflictStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($conflicts)) {
        $conflictInfo = $conflicts[0];
        setAlert('warning', "Schedule conflict: {$conflictInfo['venue_name']} is already booked for {$conflictInfo['course_code']} from {$conflictInfo['start_time']} to {$conflictInfo['end_time']} on {$conflictInfo['exam_date']}.");
    } else {
        $query = "UPDATE exam_schedules SET exam_id = :exam_id, venue_id = :venue_id, exam_date = :exam_date, 
                 start_time = :start_time, end_time = :end_time, capacity_allocated = :capacity 
                 WHERE schedule_id = :schedule_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':schedule_id', $scheduleId);
        $stmt->bindParam(':exam_id', $examId);
        $stmt->bindParam(':venue_id', $venueId);
        $stmt->bindParam(':exam_date', $examDate);
        $stmt->bindParam(':start_time', $startTime);
        $stmt->bindParam(':end_time', $endTime);
        $stmt->bindParam(':capacity', $capacity);
        $stmt->execute();
        
        setAlert('success', 'Schedule updated successfully.');
    }
}

// Helper function to create schedule with user-selected multiple venues
function createScheduleWithMultipleVenuesFromForm($db, $examId, $venueIds, $examDate, $startTime, $endTime, $capacity, $capacityMode = 'auto') {
    try {
        // Get total enrolled students for this exam
        $enrollmentQuery = "SELECT COUNT(DISTINCT sce.student_id) as total_students
                           FROM student_course_enrollments sce
                           JOIN examinations e ON sce.course_id = e.course_id AND sce.exam_period_id = e.exam_period_id
                           WHERE e.exam_id = :exam_id AND sce.status = 'Registered'";
        $enrollmentStmt = $db->prepare($enrollmentQuery);
        $enrollmentStmt->bindParam(':exam_id', $examId);
        $enrollmentStmt->execute();
        $totalStudents = $enrollmentStmt->fetch(PDO::FETCH_ASSOC)['total_students'];
        
        $venuesCreated = 0;
        $totalCapacityAllocated = 0;
        
        // Check for conflicts and create schedules for selected venues
        foreach ($venueIds as $venueId) {
            $venueId = intval($venueId);
            
            // Check for venue conflicts
            $conflictQuery = "SELECT es.*, e.course_id, c.course_code, v.venue_name 
                             FROM exam_schedules es 
                             JOIN examinations e ON es.exam_id = e.exam_id
                             JOIN courses c ON e.course_id = c.course_id
                             JOIN venues v ON es.venue_id = v.venue_id
                             WHERE es.venue_id = :venue_id 
                             AND es.exam_date = :exam_date 
                             AND ((es.start_time <= :start_time AND es.end_time > :start_time) 
                                  OR (es.start_time < :end_time AND es.end_time >= :end_time)
                                  OR (es.start_time >= :start_time AND es.end_time <= :end_time))";
            
            $conflictStmt = $db->prepare($conflictQuery);
            $conflictStmt->bindParam(':venue_id', $venueId);
            $conflictStmt->bindParam(':exam_date', $examDate);
            $conflictStmt->bindParam(':start_time', $startTime);
            $conflictStmt->bindParam(':end_time', $endTime);
            $conflictStmt->execute();
            $conflicts = $conflictStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($conflicts)) {
                $conflictInfo = $conflicts[0];
                setAlert('warning', "Conflict: {$conflictInfo['venue_name']} is already booked for {$conflictInfo['course_code']} at the same time. Skipping this venue.");
                continue;
            }
            
            // Get venue capacity
            $venueCapacityQuery = "SELECT capacity FROM venues WHERE venue_id = :venue_id";
            $venueCapStmt = $db->prepare($venueCapacityQuery);
            $venueCapStmt->bindParam(':venue_id', $venueId);
            $venueCapStmt->execute();
            $venueCapacity = $venueCapStmt->fetch(PDO::FETCH_ASSOC)['capacity'];
            
            // Determine capacity allocation based on mode
            if ($capacityMode === 'auto') {
                // Use full venue capacity
                $allocatedCapacity = $venueCapacity;
            } else {
                // Use manual capacity, but don't exceed venue maximum
                $allocatedCapacity = min($capacity, $venueCapacity);
            }
            $query = "INSERT INTO exam_schedules (exam_id, venue_id, exam_date, start_time, end_time, capacity_allocated, students_assigned) 
                     VALUES (:exam_id, :venue_id, :exam_date, :start_time, :end_time, :capacity, 0)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':exam_id', $examId);
            $stmt->bindParam(':venue_id', $venueId);
            $stmt->bindParam(':exam_date', $examDate);
            $stmt->bindParam(':start_time', $startTime);
            $stmt->bindParam(':end_time', $endTime);
            $stmt->bindParam(':capacity', $allocatedCapacity);
            $stmt->execute();
            
            $venuesCreated++;
            $totalCapacityAllocated += $allocatedCapacity;
        }
        
        if ($venuesCreated === 0) {
            setAlert('danger', 'No venues could be scheduled due to conflicts.');
            return;
        }
        
        // Auto-assign students to venues
        assignStudentsToVenues($db, $examId);
        
        $modeText = ($capacityMode === 'auto') ? 'full capacity' : $capacity . ' students per venue';
        $message = "Schedule created successfully with {$venuesCreated} venue(s) using {$modeText} for {$totalStudents} students.";
        if ($totalStudents > $totalCapacityAllocated) {
            $message .= " Note: {$totalCapacityAllocated} seats allocated. Consider adding more venues if needed.";
        }
        
        setAlert('success', $message);
        
    } catch (Exception $e) {
        setAlert('danger', 'Error creating schedule: ' . $e->getMessage());
    }
}

// Helper function to validate exam date is within academic period
function validateExamDateWithinPeriod($db, $examId, $examDate) {
    try {
        // Get the exam period for this examination
        $periodQuery = "SELECT ep.start_date, ep.end_date, ep.period_name
                       FROM examinations e
                       JOIN exam_periods ep ON e.exam_period_id = ep.exam_period_id
                       WHERE e.exam_id = :exam_id";
        $periodStmt = $db->prepare($periodQuery);
        $periodStmt->bindParam(':exam_id', $examId);
        $periodStmt->execute();
        $period = $periodStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$period) {
            return [
                'valid' => false,
                'message' => 'Could not find academic period for this examination.'
            ];
        }
        
        $examDateTime = strtotime($examDate);
        $startDateTime = strtotime($period['start_date']);
        $endDateTime = strtotime($period['end_date']);
        
        if ($examDateTime < $startDateTime || $examDateTime > $endDateTime) {
            return [
                'valid' => false,
                'message' => "Exam date must be within the academic period '{$period['period_name']}' (" . 
                           date('M j, Y', $startDateTime) . " - " . date('M j, Y', $endDateTime) . ")."
            ];
        }
        
        return ['valid' => true, 'message' => ''];
        
    } catch (Exception $e) {
        return [
            'valid' => false,
            'message' => 'Error validating exam date: ' . $e->getMessage()
        ];
    }
}

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
                $venueIds = $_POST['venue_ids'] ?? [];
                $primaryVenueId = $_POST['venue_id'] ?? null; // For backward compatibility
                $examDate = $_POST['exam_date'];
                $startTime = $_POST['start_time'];
                $endTime = $_POST['end_time'];
                $capacityMode = $_POST['capacity_mode'] ?? 'auto';
                $capacity = ($capacityMode === 'manual') ? intval($_POST['capacity']) : null;
                
                // If venue_ids array is provided, use it; otherwise fall back to single venue_id
                if (empty($venueIds) && $primaryVenueId) {
                    $venueIds = [$primaryVenueId];
                }
                
                // Validate that at least one venue is selected
                if (empty($venueIds)) {
                    setAlert('danger', 'Please select at least one venue.');
                } elseif (strtotime($endTime) <= strtotime($startTime)) {
                    setAlert('danger', 'End time must be after start time.');
                } elseif ($capacityMode === 'manual' && (!$capacity || $capacity < 1)) {
                    setAlert('danger', 'Please enter a valid capacity per venue for manual mode.');
                } else {
                    // Validate exam date is within active academic period
                    $periodValidation = validateExamDateWithinPeriod($db, $examId, $examDate);
                    if (!$periodValidation['valid']) {
                        setAlert('danger', $periodValidation['message']);
                    } else {
                    if ($action === 'create') {
                        // For new schedules with multiple venues
                        createScheduleWithMultipleVenuesFromForm($db, $examId, $venueIds, $examDate, $startTime, $endTime, $capacity, $capacityMode);
                    } else {
                        // For updates, use the simple update logic (single venue for now)
                        $venueId = $venueIds[0]; // Use first venue for updates
                        $updateCapacity = $capacity ?? 50; // Default capacity for updates
                        updateSingleSchedule($db, $scheduleId, $examId, $venueId, $examDate, $startTime, $endTime, $updateCapacity);
                    }
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
    
    // Get schedules with enrollment counts
    $query = "SELECT es.*, e.exam_id, e.exam_type, c.course_code, c.course_title, c.academic_level,
                     v.venue_name, v.capacity as max_capacity, d.department_name,
                     COUNT(DISTINCT sce.student_id) as enrolled_students,
                     GROUP_CONCAT(DISTINCT CONCAT(v.venue_name, ' (', es.capacity_allocated, '/', v.capacity, ')') SEPARATOR ', ') as all_venues
              FROM exam_schedules es 
              JOIN examinations e ON es.exam_id = e.exam_id
              JOIN courses c ON e.course_id = c.course_id
              JOIN departments d ON c.department_id = d.department_id
              JOIN venues v ON es.venue_id = v.venue_id
              LEFT JOIN student_course_enrollments sce ON c.course_id = sce.course_id AND sce.status = 'Registered'" . 
              $whereClause . 
              " GROUP BY e.exam_id
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
                                <th>Venues</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Total Capacity</th>
                                <th>Enrolled</th>
                                <th>Utilization</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $schedule): ?>
                            <?php 
                                // Calculate total capacity for all venues of this exam
                                $totalCapacityQuery = "SELECT SUM(capacity_allocated) as total_capacity FROM exam_schedules WHERE exam_id = ?";
                                $totalCapStmt = $db->prepare($totalCapacityQuery);
                                $totalCapStmt->execute([$schedule['exam_id']]);
                                $totalCapacity = $totalCapStmt->fetch(PDO::FETCH_ASSOC)['total_capacity'] ?? 0;
                                
                                $utilization = $totalCapacity > 0 ? ($schedule['enrolled_students'] / $totalCapacity) * 100 : 0;
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
                                    <small class="text-muted"><?php echo htmlspecialchars($schedule['all_venues']); ?></small>
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
                                    <span class="badge bg-secondary"><?php echo $totalCapacity; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $schedule['enrolled_students']; ?></span>
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
                            <label for="venueIds" class="form-label">Venues *</label>
                            <div id="venue-selection" class="border rounded p-3">
                                <div class="mb-2">
                                    <small class="text-muted">Select primary venue first, then additional venues if needed</small>
                                </div>
                                <div id="selected-venues" class="mb-3">
                                    <!-- Selected venues will appear here -->
                                </div>
                                <select class="form-control" id="venueSelector" onchange="addVenue()">
                                    <option value="">Add Venue</option>
                                    <?php foreach ($venues as $venue): ?>
                                    <option value="<?php echo $venue['venue_id']; ?>" 
                                            data-capacity="<?php echo $venue['capacity']; ?>"
                                            data-name="<?php echo htmlspecialchars($venue['venue_name']); ?>">
                                        <?php echo htmlspecialchars($venue['venue_name'] . ' (Capacity: ' . $venue['capacity'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="mt-2">
                                    <small id="total-capacity" class="text-info">Total Capacity: 0</small>
                                </div>
                            </div>
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
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Capacity Management</label>
                            <div class="card border-light">
                                <div class="card-body">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="capacity_mode" id="auto_capacity" value="auto" checked onchange="toggleCapacityMode()">
                                        <label class="form-check-label" for="auto_capacity">
                                            <strong>Automatic</strong> - Use full venue capacity (Recommended)
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="capacity_mode" id="manual_capacity" value="manual" onchange="toggleCapacityMode()">
                                        <label class="form-check-label" for="manual_capacity">
                                            <strong>Manual</strong> - Set specific capacity per venue
                                        </label>
                                    </div>
                                    
                                    <div id="manual_capacity_input" class="mt-3" style="display: none;">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label for="capacity" class="form-label">Students Per Venue</label>
                                                <input type="number" class="form-control" id="capacity" name="capacity" min="1" placeholder="e.g., 50">
                                                <div class="form-text">How many students to assign to EACH selected venue</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Example</label>
                                                <div class="form-control-plaintext">
                                                    <small class="text-muted">If you select 2 venues and set 50 students per venue, total capacity = 100 students</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div id="auto_capacity_info" class="mt-3 alert alert-info">
                                        <i class="fas fa-magic"></i> <strong>Automatic Mode:</strong> System will use the full capacity of each selected venue and distribute students optimally.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Enrollment Info</label>
                            <div id="enrollment-info" class="form-control-plaintext">
                                <small class="text-muted">Select an examination to see enrollment count</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Smart Venue Assignment:</strong> The system will automatically assign additional venues if enrollment exceeds the selected venue capacity.
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
let selectedVenues = [];

function openScheduleModal() {
    document.getElementById('scheduleModalTitle').textContent = 'Add Schedule';
    document.getElementById('formAction').value = 'create';
    document.getElementById('scheduleForm').reset();
    document.getElementById('scheduleId').value = '';
    selectedVenues = [];
    updateVenueDisplay();
}

function editSchedule(schedule) {
    document.getElementById('scheduleModalTitle').textContent = 'Edit Schedule';
    document.getElementById('formAction').value = 'update';
    document.getElementById('scheduleId').value = schedule.schedule_id;
    document.getElementById('examId').value = schedule.exam_id;
    document.getElementById('examDate').value = schedule.exam_date;
    document.getElementById('startTime').value = schedule.start_time;
    document.getElementById('endTime').value = schedule.end_time;
    document.getElementById('capacity').value = schedule.capacity;
    
    // For editing, add the current venue to selected venues
    selectedVenues = [schedule.venue_id.toString()];
    updateVenueDisplay();
    
    new bootstrap.Modal(document.getElementById('scheduleModal')).show();
}

function addVenue() {
    const venueSelector = document.getElementById('venueSelector');
    const selectedOption = venueSelector.options[venueSelector.selectedIndex];
    
    if (selectedOption.value && !selectedVenues.includes(selectedOption.value)) {
        selectedVenues.push(selectedOption.value);
        updateVenueDisplay();
        venueSelector.value = ''; // Reset selector
    }
}

function removeVenue(venueId) {
    selectedVenues = selectedVenues.filter(id => id !== venueId);
    updateVenueDisplay();
}

function updateVenueDisplay() {
    const container = document.getElementById('selected-venues');
    const venueSelector = document.getElementById('venueSelector');
    const totalCapacitySpan = document.getElementById('total-capacity');
    
    container.innerHTML = '';
    let totalCapacity = 0;
    
    selectedVenues.forEach((venueId, index) => {
        const option = venueSelector.querySelector(`option[value="${venueId}"]`);
        if (option) {
            const capacity = parseInt(option.dataset.capacity);
            totalCapacity += capacity;
            
            const venueTag = document.createElement('div');
            venueTag.className = 'badge bg-primary me-2 mb-2 p-2 d-inline-flex align-items-center';
            venueTag.innerHTML = `
                <span>${option.dataset.name} (${capacity})</span>
                <button type="button" class="btn-close btn-close-white ms-2" 
                        onclick="removeVenue('${venueId}')" style="font-size: 0.6em;"></button>
                <input type="hidden" name="venue_ids[]" value="${venueId}">
            `;
            container.appendChild(venueTag);
        }
    });
    
    // Add primary venue input for backward compatibility
    if (selectedVenues.length > 0) {
        const primaryInput = document.createElement('input');
        primaryInput.type = 'hidden';
        primaryInput.name = 'venue_id';
        primaryInput.value = selectedVenues[0];
        container.appendChild(primaryInput);
    }
    
    totalCapacitySpan.textContent = `Total Capacity: ${totalCapacity}`;
    
    // Update venue selector options to hide already selected venues
    Array.from(venueSelector.options).forEach(option => {
        if (option.value) {
            option.style.display = selectedVenues.includes(option.value) ? 'none' : 'block';
        }
    });
    
    // Update enrollment info if exam is selected
    updateEnrollmentInfo();
}

function updateEnrollmentInfo() {
    const examSelect = document.getElementById('examId');
    const enrollmentInfo = document.getElementById('enrollment-info');
    const examDateInput = document.getElementById('examDate');
    
    if (examSelect.value) {
        // Calculate total capacity from selected venues
        const totalCapacity = selectedVenues.reduce((sum, venueId) => {
            const option = document.getElementById('venueSelector').querySelector(`option[value="${venueId}"]`);
            return sum + (option ? parseInt(option.dataset.capacity) : 0);
        }, 0);
        
        // Get exam period dates and update date input constraints
        fetch('get_exam_period_dates.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `exam_id=${examSelect.value}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Set date picker constraints
                examDateInput.min = data.start_date;
                examDateInput.max = data.end_date;
                
                // Show enrollment info with period constraints
                enrollmentInfo.innerHTML = `
                    <div class="row">
                        <div class="col-3">
                            <strong>Selected Venues:</strong> <span class="badge bg-info">${selectedVenues.length}</span>
                        </div>
                        <div class="col-3">
                            <strong>Total Capacity:</strong> <span class="badge bg-primary">${totalCapacity}</span>
                        </div>
                        <div class="col-3">
                            <strong>Mode:</strong> <span class="badge bg-success" id="capacity-mode-badge">Automatic</span>
                        </div>
                        <div class="col-3">
                            <strong>Period:</strong> <span class="badge bg-secondary">${data.period_name}</span>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-info">
                            <i class="fas fa-calendar"></i> Valid dates: ${data.start_date} to ${data.end_date}
                        </small>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error fetching exam period:', error);
            enrollmentInfo.innerHTML = `
                <div class="row">
                    <div class="col-4">
                        <strong>Selected Venues:</strong> <span class="badge bg-info">${selectedVenues.length}</span>
                    </div>
                    <div class="col-4">
                        <strong>Total Capacity:</strong> <span class="badge bg-primary">${totalCapacity}</span>
                    </div>
                    <div class="col-4">
                        <strong>Mode:</strong> <span class="badge bg-success" id="capacity-mode-badge">Automatic</span>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">System will auto-assign additional venues if needed based on enrollment</small>
                </div>
            `;
        });
    } else {
        enrollmentInfo.innerHTML = '<small class="text-muted">Select an examination to see venue info</small>';
        examDateInput.removeAttribute('min');
        examDateInput.removeAttribute('max');
    }
}

function toggleCapacityMode() {
    const autoMode = document.getElementById('auto_capacity').checked;
    const manualInput = document.getElementById('manual_capacity_input');
    const autoInfo = document.getElementById('auto_capacity_info');
    const capacityInput = document.getElementById('capacity');
    const capacityModeBadge = document.getElementById('capacity-mode-badge');
    
    if (autoMode) {
        manualInput.style.display = 'none';
        autoInfo.style.display = 'block';
        capacityInput.removeAttribute('required');
        capacityInput.value = ''; // Clear manual value
        if (capacityModeBadge) capacityModeBadge.textContent = 'Automatic';
    } else {
        manualInput.style.display = 'block';
        autoInfo.style.display = 'none';
        capacityInput.setAttribute('required', 'required');
        if (capacityModeBadge) capacityModeBadge.textContent = 'Manual';
    }
}

function deleteSchedule(scheduleId) {
    document.getElementById('deleteScheduleId').value = scheduleId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    const examSelect = document.getElementById('examId');
    if (examSelect) {
        examSelect.addEventListener('change', updateEnrollmentInfo);
    }
});

// Form submission validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('scheduleForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (selectedVenues.length === 0) {
                e.preventDefault();
                alert('Please select at least one venue.');
                return false;
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
