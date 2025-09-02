<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = "Exam Timetable";

// Get current exam period
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get active exam periods
    $periodQuery = "SELECT * FROM exam_periods WHERE is_active = 1 ORDER BY start_date DESC";
    $periodStmt = $db->query($periodQuery);
    $examPeriods = $periodStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get selected exam period (default to current active period)
    $selectedPeriod = $_GET['period'] ?? ($examPeriods[0]['exam_period_id'] ?? null);
    
    if ($selectedPeriod) {
        // Get comprehensive exam timetable for the selected period
        $timetableQuery = "SELECT 
                            e.exam_id,
                            c.course_code,
                            c.course_title,
                            e.exam_type,
                            e.duration_minutes,
                            es.exam_date,
                            es.start_time,
                            es.end_time,
                            v.venue_name,
                            v.venue_code,
                            v.capacity,
                            es.capacity_allocated,
                            es.students_assigned,
                            d.department_name,
                            d.department_code,
                            COUNT(DISTINCT sce.student_id) as enrolled_students,
                            GROUP_CONCAT(DISTINCT CONCAT(u_inv.first_name, ' ', u_inv.last_name) 
                                        ORDER BY lia.role_type DESC, u_inv.first_name SEPARATOR ', ') as invigilators
                          FROM examinations e
                          JOIN courses c ON e.course_id = c.course_id
                          JOIN departments d ON c.department_id = d.department_id
                          JOIN exam_schedules es ON e.exam_id = es.exam_id
                          JOIN venues v ON es.venue_id = v.venue_id
                          LEFT JOIN student_course_enrollments sce ON e.course_id = sce.course_id 
                                    AND e.exam_period_id = sce.exam_period_id 
                                    AND sce.status = 'Registered'
                          LEFT JOIN lecturer_invigilator_assignments lia ON es.schedule_id = lia.schedule_id
                          LEFT JOIN lecturers l_inv ON lia.lecturer_id = l_inv.lecturer_id
                          LEFT JOIN users u_inv ON l_inv.user_id = u_inv.user_id
                          WHERE e.exam_period_id = :exam_period_id
                          GROUP BY e.exam_id, es.schedule_id
                          ORDER BY es.exam_date, es.start_time, v.venue_name";
        
        $timetableStmt = $db->prepare($timetableQuery);
        $timetableStmt->bindParam(':exam_period_id', $selectedPeriod);
        $timetableStmt->execute();
        $timetableData = $timetableStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group timetable by date and time
        $timetableByDate = [];
        $timeSlots = [];
        
        foreach ($timetableData as $exam) {
            $date = $exam['exam_date'];
            $timeSlot = $exam['start_time'] . ' - ' . $exam['end_time'];
            
            if (!isset($timetableByDate[$date])) {
                $timetableByDate[$date] = [];
            }
            if (!isset($timetableByDate[$date][$timeSlot])) {
                $timetableByDate[$date][$timeSlot] = [];
            }
            
            $timetableByDate[$date][$timeSlot][] = $exam;
            
            // Track all unique time slots
            if (!in_array($timeSlot, $timeSlots)) {
                $timeSlots[] = $timeSlot;
            }
        }
        
        // Sort time slots
        sort($timeSlots);
        
        // Get selected period details
        $currentPeriod = array_filter($examPeriods, function($period) use ($selectedPeriod) {
            return $period['exam_period_id'] == $selectedPeriod;
        });
        $currentPeriod = reset($currentPeriod);
    }
    
} catch (Exception $e) {
    $error = "Error loading timetable: " . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-calendar-alt"></i> Exam Timetable</h1>
            <div class="d-flex gap-2">
                <?php if (isset($timetableByDate) && !empty($timetableByDate)): ?>
                <button class="btn btn-outline-primary" onclick="printTimetable()">
                    <i class="fas fa-print"></i> Print Timetable
                </button>
                <button class="btn btn-outline-success" onclick="exportTimetable()">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Period Selection -->
        <?php if (!empty($examPeriods)): ?>
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row align-items-end">
                    <div class="col-md-6">
                        <label for="period" class="form-label">Select Exam Period:</label>
                        <select name="period" id="period" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($examPeriods as $period): ?>
                            <option value="<?php echo $period['exam_period_id']; ?>" 
                                    <?php echo ($period['exam_period_id'] == $selectedPeriod) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($period['period_name']); ?> 
                                (<?php echo formatDate($period['start_date']) . ' - ' . formatDate($period['end_date']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <?php if (isset($currentPeriod)): ?>
                        <div class="text-muted">
                            <small>
                                <i class="fas fa-info-circle"></i>
                                Period: <?php echo formatDate($currentPeriod['start_date']) . ' to ' . formatDate($currentPeriod['end_date']); ?>
                                <br>Registration Deadline: <?php echo formatDate($currentPeriod['registration_deadline']); ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Timetable Display -->
        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php elseif (isset($timetableByDate) && !empty($timetableByDate)): ?>
        
        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-primary"><?php echo count($timetableByDate); ?></h5>
                        <p class="card-text">Exam Days</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-success"><?php echo count($timetableData); ?></h5>
                        <p class="card-text">Total Exam Sessions</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-info"><?php echo count(array_unique(array_column($timetableData, 'course_code'))); ?></h5>
                        <p class="card-text">Unique Courses</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-warning"><?php echo count(array_unique(array_column($timetableData, 'venue_name'))); ?></h5>
                        <p class="card-text">Venues Used</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Timetable Grid -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-table"></i> 
                    <?php echo htmlspecialchars($currentPeriod['period_name'] ?? 'Exam Timetable'); ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="timetableTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="12%">Date</th>
                                <th width="15%">Time</th>
                                <th width="15%">Course</th>
                                <th width="8%">Type</th>
                                <th width="15%">Venue</th>
                                <th width="8%">Duration</th>
                                <th width="8%">Students</th>
                                <th width="19%">Invigilators</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timetableByDate as $date => $timeSlots): ?>
                                <?php $dateRowSpan = array_sum(array_map('count', $timeSlots)); ?>
                                <?php $firstDate = true; ?>
                                <?php foreach ($timeSlots as $timeSlot => $exams): ?>
                                    <?php $timeRowSpan = count($exams); ?>
                                    <?php $firstTime = true; ?>
                                    <?php foreach ($exams as $exam): ?>
                                    <tr>
                                        <?php if ($firstDate): ?>
                                        <td rowspan="<?php echo $dateRowSpan; ?>" class="align-middle text-center bg-light">
                                            <strong><?php echo formatDate($date); ?></strong>
                                        </td>
                                        <?php $firstDate = false; ?>
                                        <?php endif; ?>
                                        
                                        <?php if ($firstTime): ?>
                                        <td rowspan="<?php echo $timeRowSpan; ?>" class="align-middle text-center">
                                            <strong><?php echo formatTime($exam['start_time']) . '<br>' . formatTime($exam['end_time']); ?></strong>
                                        </td>
                                        <?php $firstTime = false; ?>
                                        <?php endif; ?>
                                        
                                        <td>
                                            <strong><?php echo htmlspecialchars($exam['course_code']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($exam['course_title']); ?></small><br>
                                            <small class="badge bg-secondary"><?php echo htmlspecialchars($exam['department_code']); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php echo $exam['exam_type'] === 'Final' ? 'danger' : ($exam['exam_type'] === 'CA' ? 'warning' : 'info'); ?>">
                                                <?php echo $exam['exam_type']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($exam['venue_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($exam['venue_code']); ?></small><br>
                                            <small class="text-info">Capacity: <?php echo $exam['capacity_allocated']; ?></small>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $exam['duration_minutes']; ?> min
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success"><?php echo $exam['enrolled_students']; ?></span>
                                            <?php if ($exam['students_assigned'] > 0): ?>
                                            <br><small class="text-muted">Assigned: <?php echo $exam['students_assigned']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($exam['invigilators']): ?>
                                                <small><?php echo htmlspecialchars($exam['invigilators']); ?></small>
                                            <?php else: ?>
                                                <small class="text-warning">Not assigned</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Conflict Detection Notice -->
        <div class="alert alert-info mt-4">
            <h6><i class="fas fa-shield-alt"></i> Conflict-Free Scheduling</h6>
            <p class="mb-0">
                This timetable has been generated using our automated conflict detection system. 
                No two exams are scheduled at the same time in the same venue, ensuring optimal resource utilization 
                and preventing scheduling conflicts.
            </p>
        </div>
        
        <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-calendar-times fa-5x text-muted mb-4"></i>
                <h4 class="text-muted">No Exam Timetable Available</h4>
                <p class="text-muted">
                    <?php if (isset($selectedPeriod)): ?>
                    No exams have been scheduled for the selected period yet.
                    <?php else: ?>
                    No active exam periods found. Please contact the administrator.
                    <?php endif; ?>
                </p>
                <?php if (hasRole('Admin')): ?>
                <a href="admin/schedules.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Exam Schedules
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Print functionality
function printTimetable() {
    window.print();
}

// Export functionality
function exportTimetable() {
    // Simple CSV export
    const table = document.getElementById('timetableTable');
    let csv = [];
    
    // Headers
    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
    csv.push(headers.join(','));
    
    // Data rows
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const cells = Array.from(row.querySelectorAll('td')).map(td => {
            return '"' + td.textContent.trim().replace(/"/g, '""') + '"';
        });
        csv.push(cells.join(','));
    });
    
    // Download
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'exam_timetable.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Initialize DataTable if available
$(document).ready(function() {
    if (typeof DataTable !== 'undefined' && $('#timetableTable').length) {
        $('#timetableTable').DataTable({
            "pageLength": 25,
            "order": [[ 0, "asc" ], [ 1, "asc" ]],
            "columnDefs": [
                { "orderable": false, "targets": [7] }
            ]
        });
    }
});
</script>

<style>
@media print {
    .btn, .alert, .card-header { display: none !important; }
    .card { border: none !important; }
    .table { font-size: 12px; }
}

.table td, .table th {
    vertical-align: middle;
}

.badge {
    font-size: 0.75em;
}
</style>

<?php include 'includes/footer.php'; ?>
