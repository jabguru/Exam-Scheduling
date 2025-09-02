<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Admin');

$pageTitle = "Academic Sessions & Exam Periods";

// Handle session/period creation/update/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setAlert('danger', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            if ($action === 'create_session' || $action === 'update_session') {
                $sessionId = $_POST['session_id'] ?? null;
                $sessionName = sanitizeInput($_POST['session_name']);
                $startDate = $_POST['start_date'];
                $endDate = $_POST['end_date'];
                $isCurrent = isset($_POST['is_current']) ? 1 : 0;
                $status = sanitizeInput($_POST['status']);
                
                if ($action === 'create_session') {
                    // If this is set as current, make all others non-current
                    if ($isCurrent) {
                        $db->exec("UPDATE academic_sessions SET is_current = 0");
                    }
                    
                    $query = "INSERT INTO academic_sessions (session_name, start_date, end_date, is_current, status) 
                             VALUES (:session_name, :start_date, :end_date, :is_current, :status)";
                    $stmt = $db->prepare($query);
                } else {
                    // If this is set as current, make all others non-current
                    if ($isCurrent) {
                        $db->exec("UPDATE academic_sessions SET is_current = 0");
                    }
                    
                    $query = "UPDATE academic_sessions SET session_name = :session_name, start_date = :start_date, 
                             end_date = :end_date, is_current = :is_current, status = :status 
                             WHERE session_id = :session_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':session_id', $sessionId);
                }
                
                $stmt->bindParam(':session_name', $sessionName);
                $stmt->bindParam(':start_date', $startDate);
                $stmt->bindParam(':end_date', $endDate);
                $stmt->bindParam(':is_current', $isCurrent);
                $stmt->bindParam(':status', $status);
                $stmt->execute();
                
                setAlert('success', 'Academic session ' . ($action === 'create_session' ? 'created' : 'updated') . ' successfully!');
                
            } elseif ($action === 'create_period' || $action === 'update_period') {
                $periodId = $_POST['period_id'] ?? null;
                $sessionId = intval($_POST['session_id']);
                $periodName = sanitizeInput($_POST['period_name']);
                $startDate = $_POST['start_date'];
                $endDate = $_POST['end_date'];
                $registrationDeadline = $_POST['registration_deadline'];
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                if ($action === 'create_period') {
                    // If this is set as active, make all others inactive
                    if ($isActive) {
                        $db->exec("UPDATE exam_periods SET is_active = 0");
                    }
                    
                    $query = "INSERT INTO exam_periods (session_id, period_name, start_date, end_date, registration_deadline, is_active) 
                             VALUES (:session_id, :period_name, :start_date, :end_date, :registration_deadline, :is_active)";
                    $stmt = $db->prepare($query);
                } else {
                    // If this is set as active, make all others inactive
                    if ($isActive) {
                        $db->exec("UPDATE exam_periods SET is_active = 0");
                    }
                    
                    $query = "UPDATE exam_periods SET session_id = :session_id, period_name = :period_name, 
                             start_date = :start_date, end_date = :end_date, registration_deadline = :registration_deadline, 
                             is_active = :is_active WHERE exam_period_id = :period_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':period_id', $periodId);
                }
                
                $stmt->bindParam(':session_id', $sessionId);
                $stmt->bindParam(':period_name', $periodName);
                $stmt->bindParam(':start_date', $startDate);
                $stmt->bindParam(':end_date', $endDate);
                $stmt->bindParam(':registration_deadline', $registrationDeadline);
                $stmt->bindParam(':is_active', $isActive);
                $stmt->execute();
                
                setAlert('success', 'Exam period ' . ($action === 'create_period' ? 'created' : 'updated') . ' successfully!');
                
            } elseif ($action === 'delete_session') {
                $sessionId = intval($_POST['session_id']);
                
                // Check if session has exam periods
                $checkQuery = "SELECT COUNT(*) FROM exam_periods WHERE session_id = :session_id";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':session_id', $sessionId);
                $checkStmt->execute();
                
                if ($checkStmt->fetchColumn() > 0) {
                    setAlert('danger', 'Cannot delete session that has exam periods. Delete the periods first.');
                } else {
                    $query = "DELETE FROM academic_sessions WHERE session_id = :session_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':session_id', $sessionId);
                    $stmt->execute();
                    setAlert('success', 'Academic session deleted successfully!');
                }
                
            } elseif ($action === 'delete_period') {
                $periodId = intval($_POST['period_id']);
                
                // Check if period has examinations
                $checkQuery = "SELECT COUNT(*) FROM examinations WHERE exam_period_id = :period_id";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->bindParam(':period_id', $periodId);
                $checkStmt->execute();
                
                if ($checkStmt->fetchColumn() > 0) {
                    setAlert('danger', 'Cannot delete exam period that has examinations. Delete the examinations first.');
                } else {
                    $query = "DELETE FROM exam_periods WHERE exam_period_id = :period_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':period_id', $periodId);
                    $stmt->execute();
                    setAlert('success', 'Exam period deleted successfully!');
                }
            }
        } catch (PDOException $e) {
            setAlert('danger', 'Error: ' . $e->getMessage());
        }
        
        header('Location: periods.php');
        exit;
    }
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get academic sessions
    $sessionQuery = "SELECT * FROM academic_sessions ORDER BY start_date DESC";
    $sessionStmt = $db->query($sessionQuery);
    $sessions = $sessionStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get exam periods with session info
    $periodQuery = "SELECT ep.*, asa.session_name 
                    FROM exam_periods ep 
                    JOIN academic_sessions asa ON ep.session_id = asa.session_id 
                    ORDER BY ep.start_date DESC";
    $periodStmt = $db->query($periodQuery);
    $periods = $periodStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    setAlert('danger', 'Error loading data: ' . $e->getMessage());
    $sessions = [];
    $periods = [];
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-calendar-alt"></i> Academic Sessions & Exam Periods</h1>
            <div class="btn-group">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sessionModal">
                    <i class="fas fa-plus"></i> Add Session
                </button>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#periodModal">
                    <i class="fas fa-plus"></i> Add Exam Period
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Academic Sessions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Academic Sessions (<?php echo count($sessions); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($sessions)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No academic sessions found.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Session Name</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Current</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($session['session_name']); ?></strong>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($session['start_date'])); ?> - 
                                    <?php echo date('M j, Y', strtotime($session['end_date'])); ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $session['status'] === 'Active' ? 'success' : ($session['status'] === 'Inactive' ? 'warning' : 'secondary'); ?>">
                                        <?php echo $session['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($session['is_current']): ?>
                                    <span class="badge bg-primary">Current</span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($session['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editSession(<?php echo htmlspecialchars(json_encode($session)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteSession(<?php echo $session['session_id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
    </div>
</div>

<!-- Exam Periods -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Exam Periods (<?php echo count($periods); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($periods)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No exam periods found.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Period Name</th>
                                <th>Academic Session</th>
                                <th>Exam Duration</th>
                                <th>Registration Deadline</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($periods as $period): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($period['period_name']); ?></strong>
                                    <?php if ($period['is_active']): ?>
                                    <br><span class="badge bg-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($period['session_name']); ?></td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($period['start_date'])); ?> - 
                                    <?php echo date('M j, Y', strtotime($period['end_date'])); ?>
                                </td>
                                <td>
                                    <span class="text-<?php echo strtotime($period['registration_deadline']) > time() ? 'success' : 'danger'; ?>">
                                        <?php echo date('M j, Y', strtotime($period['registration_deadline'])); ?>
                                    </span>
                                    <?php if (strtotime($period['registration_deadline']) > time()): ?>
                                    <br><small class="text-success">Open</small>
                                    <?php else: ?>
                                    <br><small class="text-danger">Closed</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $now = time();
                                    $start = strtotime($period['start_date']);
                                    $end = strtotime($period['end_date']);
                                    
                                    if ($now < $start) {
                                        echo '<span class="badge bg-info">Upcoming</span>';
                                    } elseif ($now >= $start && $now <= $end) {
                                        echo '<span class="badge bg-warning">Ongoing</span>';
                                    } else {
                                        echo '<span class="badge bg-secondary">Completed</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editPeriod(<?php echo htmlspecialchars(json_encode($period)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deletePeriod(<?php echo $period['exam_period_id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
    </div>
</div>

<!-- Session Modal -->
<div class="modal fade" id="sessionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sessionModalTitle">Add Academic Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="sessionForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="sessionAction" value="create_session">
                    <input type="hidden" name="session_id" id="sessionId">
                    
                    <div class="mb-3">
                        <label for="sessionName" class="form-label">Session Name *</label>
                        <input type="text" class="form-control" id="sessionName" name="session_name" 
                               placeholder="e.g., 2024/2025" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="sessionStartDate" class="form-label">Start Date *</label>
                            <input type="date" class="form-control" id="sessionStartDate" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="sessionEndDate" class="form-label">End Date *</label>
                            <input type="date" class="form-control" id="sessionEndDate" name="end_date" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sessionStatus" class="form-label">Status *</label>
                        <select class="form-control" id="sessionStatus" name="status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Archived">Archived</option>
                        </select>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="isCurrent" name="is_current">
                        <label class="form-check-label" for="isCurrent">
                            Set as current session
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="sessionSubmitBtn">Create Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Period Modal -->
<div class="modal fade" id="periodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="periodModalTitle">Add Exam Period</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="periodForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="periodAction" value="create_period">
                    <input type="hidden" name="period_id" id="periodId">
                    
                    <div class="mb-3">
                        <label for="periodSessionId" class="form-label">Academic Session *</label>
                        <select class="form-control" id="periodSessionId" name="session_id" required>
                            <option value="">Select Session</option>
                            <?php foreach ($sessions as $session): ?>
                            <option value="<?php echo $session['session_id']; ?>">
                                <?php echo htmlspecialchars($session['session_name']); ?>
                                (<?php echo date('Y', strtotime($session['start_date'])); ?>-<?php echo date('Y', strtotime($session['end_date'])); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="periodName" class="form-label">Period Name *</label>
                        <input type="text" class="form-control" id="periodName" name="period_name" 
                               placeholder="e.g., First Semester, Second Semester" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="periodStartDate" class="form-label">Exam Start Date *</label>
                            <input type="date" class="form-control" id="periodStartDate" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="periodEndDate" class="form-label">Exam End Date *</label>
                            <input type="date" class="form-control" id="periodEndDate" name="end_date" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="registrationDeadline" class="form-label">Registration Deadline *</label>
                        <input type="date" class="form-control" id="registrationDeadline" name="registration_deadline" required>
                        <div class="form-text">Students must register for exams before this date</div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="isActive" name="is_active">
                        <label class="form-check-label" for="isActive">
                            Set as active period
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="periodSubmitBtn">Create Period</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modals -->
<div class="modal fade" id="deleteSessionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this academic session? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete_session">
                    <input type="hidden" name="session_id" id="deleteSessionId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deletePeriodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this exam period? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete_period">
                    <input type="hidden" name="period_id" id="deletePeriodId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editSession(session) {
    document.getElementById('sessionModalTitle').textContent = 'Edit Academic Session';
    document.getElementById('sessionAction').value = 'update_session';
    document.getElementById('sessionId').value = session.session_id;
    document.getElementById('sessionName').value = session.session_name;
    document.getElementById('sessionStartDate').value = session.start_date;
    document.getElementById('sessionEndDate').value = session.end_date;
    document.getElementById('sessionStatus').value = session.status;
    document.getElementById('isCurrent').checked = session.is_current == 1;
    document.getElementById('sessionSubmitBtn').textContent = 'Update Session';
    
    new bootstrap.Modal(document.getElementById('sessionModal')).show();
}

function editPeriod(period) {
    document.getElementById('periodModalTitle').textContent = 'Edit Exam Period';
    document.getElementById('periodAction').value = 'update_period';
    document.getElementById('periodId').value = period.exam_period_id;
    document.getElementById('periodSessionId').value = period.session_id;
    document.getElementById('periodName').value = period.period_name;
    document.getElementById('periodStartDate').value = period.start_date;
    document.getElementById('periodEndDate').value = period.end_date;
    document.getElementById('registrationDeadline').value = period.registration_deadline;
    document.getElementById('isActive').checked = period.is_active == 1;
    document.getElementById('periodSubmitBtn').textContent = 'Update Period';
    
    new bootstrap.Modal(document.getElementById('periodModal')).show();
}

function deleteSession(sessionId) {
    document.getElementById('deleteSessionId').value = sessionId;
    new bootstrap.Modal(document.getElementById('deleteSessionModal')).show();
}

function deletePeriod(periodId) {
    document.getElementById('deletePeriodId').value = periodId;
    new bootstrap.Modal(document.getElementById('deletePeriodModal')).show();
}

// Reset modals when closed
document.getElementById('sessionModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('sessionForm').reset();
    document.getElementById('sessionModalTitle').textContent = 'Add Academic Session';
    document.getElementById('sessionAction').value = 'create_session';
    document.getElementById('sessionId').value = '';
    document.getElementById('sessionSubmitBtn').textContent = 'Create Session';
});

document.getElementById('periodModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('periodForm').reset();
    document.getElementById('periodModalTitle').textContent = 'Add Exam Period';
    document.getElementById('periodAction').value = 'create_period';
    document.getElementById('periodId').value = '';
    document.getElementById('periodSubmitBtn').textContent = 'Create Period';
});
</script>

<?php include '../includes/footer.php'; ?>
