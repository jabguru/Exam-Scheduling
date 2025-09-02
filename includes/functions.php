<?php
session_start();

// Security functions
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /Exam-Scheduling/login.php");
        exit();
    }
}

function hasRole($requiredRole) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $requiredRole;
}

function requireRole($requiredRole) {
    requireLogin();
    if (!hasRole($requiredRole)) {
        header("Location: /Exam-Scheduling/unauthorized.php");
        exit();
    }
}

function logout() {
    session_unset();
    session_destroy();
    header("Location: /Exam-Scheduling/login.php");
    exit();
}

// Pagination helper
function paginate($page, $totalRecords, $recordsPerPage = 10) {
    $totalPages = ceil($totalRecords / $recordsPerPage);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $recordsPerPage;
    
    return [
        'page' => $page,
        'totalPages' => $totalPages,
        'offset' => $offset,
        'limit' => $recordsPerPage
    ];
}

// Date and time helpers
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('M d, Y g:i A', strtotime($datetime));
}

function formatTime($time) {
    return date('g:i A', strtotime($time));
}

// Alert messages
function setAlert($type, $message) {
    $_SESSION['alert'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

// JSON response helper
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Auto-assign students to venues when exam schedules are created
function assignStudentsToVenues($db, $examId) {
    try {
        // Get all students enrolled in this exam's course
        $studentsQuery = "SELECT DISTINCT sce.student_id
                         FROM student_course_enrollments sce
                         JOIN examinations e ON sce.course_id = e.course_id AND sce.exam_period_id = e.exam_period_id
                         WHERE e.exam_id = :exam_id AND sce.status = 'Registered'
                         ORDER BY sce.enrollment_date";
        $studentsStmt = $db->prepare($studentsQuery);
        $studentsStmt->bindParam(':exam_id', $examId);
        $studentsStmt->execute();
        $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all schedules (venues) for this exam
        $venuesQuery = "SELECT schedule_id, venue_id, capacity_allocated
                       FROM exam_schedules 
                       WHERE exam_id = :exam_id
                       ORDER BY schedule_id";
        $venuesStmt = $db->prepare($venuesQuery);
        $venuesStmt->bindParam(':exam_id', $examId);
        $venuesStmt->execute();
        $venues = $venuesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($venues)) {
            return false;
        }
        
        // Assign students to venues in round-robin fashion
        $venueIndex = 0;
        $venueAssignments = array_fill_keys(array_column($venues, 'schedule_id'), 0);
        
        foreach ($students as $student) {
            $currentVenue = $venues[$venueIndex];
            
            // Check if current venue has capacity
            if ($venueAssignments[$currentVenue['schedule_id']] < $currentVenue['capacity_allocated']) {
                // Assign student to this venue
                $assignQuery = "INSERT INTO student_venue_assignments (student_id, schedule_id, seat_number) 
                               VALUES (:student_id, :schedule_id, :seat_number)
                               ON DUPLICATE KEY UPDATE schedule_id = :schedule_id";
                $assignStmt = $db->prepare($assignQuery);
                $assignStmt->bindParam(':student_id', $student['student_id']);
                $assignStmt->bindParam(':schedule_id', $currentVenue['schedule_id']);
                $seatNumber = 'S' . str_pad($venueAssignments[$currentVenue['schedule_id']] + 1, 3, '0', STR_PAD_LEFT);
                $assignStmt->bindParam(':seat_number', $seatNumber);
                $assignStmt->execute();
                
                $venueAssignments[$currentVenue['schedule_id']]++;
            }
            
            // Move to next venue (round-robin)
            $venueIndex = ($venueIndex + 1) % count($venues);
        }
        
        // Update students_assigned count in exam_schedules
        foreach ($venues as $venue) {
            $updateQuery = "UPDATE exam_schedules 
                           SET students_assigned = :students_assigned 
                           WHERE schedule_id = :schedule_id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':students_assigned', $venueAssignments[$venue['schedule_id']]);
            $updateStmt->bindParam(':schedule_id', $venue['schedule_id']);
            $updateStmt->execute();
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error assigning students to venues: " . $e->getMessage());
        return false;
    }
}
?>
