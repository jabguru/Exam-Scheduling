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
?>
