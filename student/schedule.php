<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Student');

$pageTitle = "My Exam Schedule";

// Get student information
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get student details
    $query = "SELECT s.*, d.department_name 
              FROM students s 
              JOIN departments d ON s.department_id = d.department_id
              WHERE s.user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception('Student profile not found.');
    }
    
        // Get exam schedule for this student
    $query = "SELECT 
                c.course_code,
                c.course_title,
                e.exam_type,
                es.exam_date,
                es.start_time,
                es.end_time,
                v.venue_name,
                v.building,
                er.registration_date,
                er.status
              FROM exam_registrations er
              JOIN examinations e ON er.exam_id = e.exam_id
              JOIN courses c ON e.course_id = c.course_id
              LEFT JOIN exam_schedules es ON e.exam_id = es.exam_id
              LEFT JOIN venues v ON es.venue_id = v.venue_id
              WHERE er.student_id = :student_id
              ORDER BY es.exam_date, es.start_time";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $student['student_id']);
    $stmt->execute();
    $examSchedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group exams by date
    $examsByDate = [];
    foreach ($examSchedules as $exam) {
        if ($exam['exam_date']) {
            $date = $exam['exam_date'];
            if (!isset($examsByDate[$date])) {
                $examsByDate[$date] = [];
            }
            $examsByDate[$date][] = $exam;
        }
    }
    
} catch (Exception $e) {
    $error = "Error loading schedule: " . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-calendar"></i> My Exam Schedule</h1>
            <div>
                <button onclick="printSchedule()" class="btn btn-outline-primary">
                    <i class="fas fa-print"></i> Print Schedule
                </button>
                <button onclick="exportToCalendar()" class="btn btn-outline-success">
                    <i class="fas fa-download"></i> Export Calendar
                </button>
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

<!-- Student Information -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Student:</strong> <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Matric Number:</strong> <?php echo htmlspecialchars($student['matric_number']); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Department:</strong> <?php echo htmlspecialchars($student['department_name']); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Options -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label for="statusFilter" class="form-label">Filter by Status:</label>
                        <select id="statusFilter" class="form-control" onchange="filterSchedule()">
                            <option value="">All Statuses</option>
                            <option value="Scheduled">Scheduled</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="typeFilter" class="form-label">Filter by Type:</label>
                        <select id="typeFilter" class="form-control" onchange="filterSchedule()">
                            <option value="">All Types</option>
                            <option value="CA">Continuous Assessment</option>
                            <option value="Final">Final Exam</option>
                            <option value="Makeup">Makeup Exam</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="dateFilter" class="form-label">Filter by Date:</label>
                        <select id="dateFilter" class="form-control" onchange="filterSchedule()">
                            <option value="">All Dates</option>
                            <option value="upcoming">Upcoming Only</option>
                            <option value="past">Past Only</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Display -->
<div id="scheduleContent">
    <?php if (!empty($examsByDate)): ?>
    
    <!-- Calendar View Toggle -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-primary active" onclick="showListView()">
                    <i class="fas fa-list"></i> List View
                </button>
                <button type="button" class="btn btn-outline-primary" onclick="showCalendarView()">
                    <i class="fas fa-calendar-alt"></i> Calendar View
                </button>
            </div>
        </div>
    </div>
    
    <!-- List View -->
    <div id="listView">
        <?php foreach ($examsByDate as $date => $exams): ?>
        <div class="row mb-4 schedule-date-group" data-date="<?php echo $date; ?>">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-day"></i> 
                            <?php echo formatDate($date); ?>
                            <span class="badge bg-light text-dark ms-2"><?php echo count($exams); ?> exam(s)</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($exams as $exam): ?>
                            <div class="col-lg-6 mb-3 schedule-item" 
                                 data-status="<?php echo strtolower($exam['status']); ?>"
                                 data-type="<?php echo strtolower($exam['exam_type']); ?>"
                                 data-date="<?php echo $exam['exam_date']; ?>">
                                <div class="exam-card">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1">
                                                <strong><?php echo htmlspecialchars($exam['course_code']); ?></strong>
                                                <span class="badge bg-info ms-2"><?php echo $exam['exam_type']; ?></span>
                                            </h6>
                                            <p class="mb-1"><?php echo htmlspecialchars($exam['course_title']); ?></p>
                                        </div>
                                        <span class="badge status-<?php echo strtolower($exam['status']); ?>">
                                            <?php echo $exam['status']; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="exam-details">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock"></i> Time:<br>
                                                    <strong class="exam-time">
                                                        <?php echo formatTime($exam['start_time']) . ' - ' . formatTime($exam['end_time']); ?>
                                                    </strong>
                                                </small>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">
                                                    <i class="fas fa-hourglass-half"></i> Duration:<br>
                                                    <strong><?php echo $exam['duration_minutes']; ?> minutes</strong>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <div class="row mt-2">
                                            <div class="col-8">
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt"></i> Venue:<br>
                                                    <strong class="exam-venue"><?php echo htmlspecialchars($exam['venue_name']); ?></strong>
                                                    <?php if ($exam['location']): ?>
                                                    <br><span class="text-muted"><?php echo htmlspecialchars($exam['location']); ?></span>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <div class="col-4">
                                                <?php if ($exam['seat_number']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-chair"></i> Seat:<br>
                                                    <strong class="text-primary"><?php echo htmlspecialchars($exam['seat_number']); ?></strong>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 text-end">
                                        <small class="text-muted">
                                            Registered: <?php echo formatDate($exam['registration_date']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Calendar View (Hidden by default) -->
    <div id="calendarView" style="display: none;">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div id="examCalendar"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-calendar-times fa-5x text-muted mb-4"></i>
                    <h4 class="text-muted">No Exams Scheduled</h4>
                    <p class="text-muted">You haven't registered for any exams yet.</p>
                    <a href="registration.php" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Register for Exams
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Exam Details Modal -->
<div class="modal fade" id="examDetailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Exam Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="examDetailsContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printExamTicket()">
                    <i class="fas fa-print"></i> Print Hall Ticket
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Filter functions
function filterSchedule() {
    const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
    const typeFilter = document.getElementById('typeFilter').value.toLowerCase();
    const dateFilter = document.getElementById('dateFilter').value;
    const today = new Date().toISOString().split('T')[0];
    
    const scheduleItems = document.querySelectorAll('.schedule-item');
    const dateGroups = document.querySelectorAll('.schedule-date-group');
    
    scheduleItems.forEach(item => {
        const itemStatus = item.dataset.status;
        const itemType = item.dataset.type;
        const itemDate = item.dataset.date;
        
        let showItem = true;
        
        // Status filter
        if (statusFilter && itemStatus !== statusFilter) {
            showItem = false;
        }
        
        // Type filter
        if (typeFilter && itemType !== typeFilter) {
            showItem = false;
        }
        
        // Date filter
        if (dateFilter === 'upcoming' && itemDate < today) {
            showItem = false;
        } else if (dateFilter === 'past' && itemDate >= today) {
            showItem = false;
        }
        
        item.style.display = showItem ? 'block' : 'none';
    });
    
    // Hide date groups with no visible items
    dateGroups.forEach(group => {
        const visibleItems = group.querySelectorAll('.schedule-item[style="display: block"], .schedule-item:not([style*="display: none"])');
        group.style.display = visibleItems.length > 0 ? 'block' : 'none';
    });
}

// View functions
function showListView() {
    document.getElementById('listView').style.display = 'block';
    document.getElementById('calendarView').style.display = 'none';
    
    // Update button states
    document.querySelectorAll('.btn-group .btn').forEach(btn => btn.classList.remove('active'));
    event.target.closest('.btn').classList.add('active');
}

function showCalendarView() {
    document.getElementById('listView').style.display = 'none';
    document.getElementById('calendarView').style.display = 'block';
    
    // Update button states
    document.querySelectorAll('.btn-group .btn').forEach(btn => btn.classList.remove('active'));
    event.target.closest('.btn').classList.add('active');
    
    // Initialize calendar if not already done
    if (!window.calendarInitialized) {
        initializeCalendar();
    }
}

function initializeCalendar() {
    // This would require FullCalendar.js library
    // For now, we'll show a placeholder
    document.getElementById('examCalendar').innerHTML = 
        '<div class="text-center py-5">' +
        '<i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>' +
        '<p class="text-muted">Calendar view requires FullCalendar.js library</p>' +
        '</div>';
    
    window.calendarInitialized = true;
}

// Print functions
function printSchedule() {
    window.print();
}

function printExamTicket() {
    // This would generate and print individual hall ticket
    alert('Hall ticket printing functionality would be implemented here');
}

function exportToCalendar() {
    // This would generate an ICS file for calendar import
    alert('Calendar export functionality would be implemented here');
}

// Add click handlers for exam cards to show details
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.exam-card').forEach(card => {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function() {
            // Extract exam data and show in modal
            const courseCode = this.querySelector('h6 strong').textContent;
            const courseTitle = this.querySelector('p').textContent;
            
            document.getElementById('examDetailsContent').innerHTML = 
                '<h6>' + courseCode + '</h6>' +
                '<p>' + courseTitle + '</p>' +
                '<p>Click on an exam card to see detailed information in a modal.</p>';
            
            new bootstrap.Modal(document.getElementById('examDetailsModal')).show();
        });
    });
});
</script>

<style>
@media print {
    .btn, .card-header .badge, #examDetailsModal {
        display: none !important;
    }
    
    .exam-card {
        border: 1px solid #000 !important;
        page-break-inside: avoid;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
