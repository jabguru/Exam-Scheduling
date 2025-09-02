<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Lecturer');

$pageTitle = "Invigilation Duties";

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
    
    // Get invigilation assignments
    $assignmentsQuery = "SELECT 
                            lia.assignment_id,
                            lia.role_type,
                            lia.assigned_at,
                            c.course_code,
                            c.course_title,
                            e.exam_type,
                            es.exam_date,
                            es.start_time,
                            es.end_time,
                            v.venue_name,
                            v.location,
                            es.students_assigned,
                            v.capacity,
                            CASE 
                                WHEN es.exam_date < CURDATE() THEN 'Completed'
                                WHEN es.exam_date = CURDATE() THEN 'Today'
                                ELSE 'Upcoming'
                            END as status
                         FROM lecturer_invigilator_assignments lia
                         JOIN exam_schedules es ON lia.schedule_id = es.schedule_id
                         JOIN examinations e ON es.exam_id = e.exam_id
                         JOIN courses c ON e.course_id = c.course_id
                         JOIN venues v ON es.venue_id = v.venue_id
                         WHERE lia.lecturer_id = :lecturer_id
                         ORDER BY es.exam_date DESC, es.start_time";
    $assignmentsStmt = $db->prepare($assignmentsQuery);
    $assignmentsStmt->bindParam(':lecturer_id', $lecturer['lecturer_id']);
    $assignmentsStmt->execute();
    $assignments = $assignmentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get upcoming assignments (next 30 days)
    $upcomingQuery = "SELECT 
                        lia.assignment_id,
                        lia.role_type,
                        c.course_code,
                        c.course_title,
                        e.exam_type,
                        es.exam_date,
                        es.start_time,
                        es.end_time,
                        v.venue_name,
                        v.location,
                        es.students_assigned
                      FROM lecturer_invigilator_assignments lia
                      JOIN exam_schedules es ON lia.schedule_id = es.schedule_id
                      JOIN examinations e ON es.exam_id = e.exam_id
                      JOIN courses c ON e.course_id = c.course_id
                      JOIN venues v ON es.venue_id = v.venue_id
                      WHERE lia.lecturer_id = :lecturer_id
                      AND es.exam_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                      ORDER BY es.exam_date, es.start_time";
    $upcomingStmt = $db->prepare($upcomingQuery);
    $upcomingStmt->bindParam(':lecturer_id', $lecturer['lecturer_id']);
    $upcomingStmt->execute();
    $upcomingAssignments = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error loading invigilation duties: " . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <main class="col-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-eye"></i> Invigilation Duties</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <span class="badge bg-info">Total: <?php echo count($assignments); ?></span>
                        <span class="badge bg-warning">Upcoming: <?php echo count($upcomingAssignments); ?></span>
                    </div>
                </div>
            </div>

            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <!-- Lecturer Information -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Lecturer:</strong> <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Staff ID:</strong> <?php echo htmlspecialchars($lecturer['staff_id']); ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Department:</strong> <?php echo htmlspecialchars($lecturer['department_name']); ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Invigilation Count:</strong> <?php echo count($assignments); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Duties Alert -->
            <?php if (!empty($upcomingAssignments)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-info">
                        <h5><i class="fas fa-calendar-alt"></i> Upcoming Invigilation Duties</h5>
                        <div class="row">
                            <?php foreach (array_slice($upcomingAssignments, 0, 3) as $upcoming): ?>
                            <div class="col-md-4">
                                <div class="card border-primary">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($upcoming['course_code']); ?></h6>
                                        <p class="card-text">
                                            <strong>Date:</strong> <?php echo date('M j, Y', strtotime($upcoming['exam_date'])); ?><br>
                                            <strong>Time:</strong> <?php echo date('g:i A', strtotime($upcoming['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($upcoming['end_time'])); ?><br>
                                            <strong>Venue:</strong> <?php echo htmlspecialchars($upcoming['venue_name']); ?><br>
                                            <strong>Role:</strong> <span class="badge bg-<?php echo $upcoming['role_type'] === 'Chief' ? 'primary' : 'secondary'; ?>">
                                                <?php echo $upcoming['role_type']; ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- All Invigilation Assignments -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-list"></i> All Invigilation Assignments
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($assignments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-eye fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No invigilation duties assigned yet.</p>
                                <p class="text-muted">Invigilation assignments are made by administrators when scheduling exams.</p>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Course</th>
                                            <th>Exam Type</th>
                                            <th>Date & Time</th>
                                            <th>Venue</th>
                                            <th>Role</th>
                                            <th>Students</th>
                                            <th>Status</th>
                                            <th>Assigned Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assignments as $assignment): ?>
                                        <tr class="<?php echo $assignment['status'] === 'Today' ? 'table-warning' : ''; ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($assignment['course_code']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($assignment['course_title']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $assignment['exam_type'] === 'Final' ? 'primary' : 'info'; ?>">
                                                    <?php echo htmlspecialchars($assignment['exam_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo date('M j, Y', strtotime($assignment['exam_date'])); ?></strong>
                                                <br><small class="text-muted">
                                                    <?php echo date('g:i A', strtotime($assignment['start_time'])); ?> - 
                                                    <?php echo date('g:i A', strtotime($assignment['end_time'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($assignment['venue_name']); ?></strong>
                                                <?php if ($assignment['location']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($assignment['location']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $assignment['role_type'] === 'Chief' ? 'primary' : 'secondary'; ?>">
                                                    <?php echo $assignment['role_type']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $assignment['students_assigned']; ?> / <?php echo $assignment['capacity']; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $statusColors = [
                                                    'Upcoming' => 'primary',
                                                    'Today' => 'warning',
                                                    'Completed' => 'success'
                                                ];
                                                $statusColor = $statusColors[$assignment['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $statusColor; ?>">
                                                    <?php echo $assignment['status']; ?>
                                                </span>
                                                <?php if ($assignment['status'] === 'Today'): ?>
                                                <br><small class="text-warning"><strong>Today!</strong></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($assignment['assigned_at'])); ?>
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
            <?php if (!empty($assignments)): ?>
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="text-primary"><?php echo count(array_filter($assignments, function($a) { return $a['status'] === 'Upcoming'; })); ?></h5>
                            <small class="text-muted">Upcoming</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="text-warning"><?php echo count(array_filter($assignments, function($a) { return $a['status'] === 'Today'; })); ?></h5>
                            <small class="text-muted">Today</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="text-success"><?php echo count(array_filter($assignments, function($a) { return $a['status'] === 'Completed'; })); ?></h5>
                            <small class="text-muted">Completed</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="text-info"><?php echo count(array_filter($assignments, function($a) { return $a['role_type'] === 'Chief'; })); ?></h5>
                            <small class="text-muted">Chief Invigilator</small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
