<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Admin');

header('Content-Type: application/json');

try {
    $courseId = intval($_GET['course_id'] ?? 0);
    
    if (!$courseId) {
        echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Get lecturers assigned to this course
    $query = "SELECT DISTINCT l.lecturer_id, l.staff_id, u.first_name, u.last_name
              FROM lecturers l
              JOIN users u ON l.user_id = u.user_id
              JOIN lecturer_course_assignments lca ON l.lecturer_id = lca.lecturer_id
              WHERE lca.course_id = :course_id AND u.is_active = 1
              ORDER BY u.first_name, u.last_name";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_id', $courseId);
    $stmt->execute();
    
    $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'lecturers' => $lecturers
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
