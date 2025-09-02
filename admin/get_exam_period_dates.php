<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('Admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exam_id'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $examId = intval($_POST['exam_id']);
        
        // Get exam period dates for the selected examination
        $query = "SELECT ep.start_date, ep.end_date, ep.period_name,
                         COUNT(DISTINCT sce.student_id) as enrollment_count
                  FROM examinations e
                  JOIN exam_periods ep ON e.exam_period_id = ep.exam_period_id
                  LEFT JOIN student_course_enrollments sce ON e.course_id = sce.course_id 
                                                            AND sce.exam_period_id = e.exam_period_id 
                                                            AND sce.status = 'Registered'
                  WHERE e.exam_id = :exam_id
                  GROUP BY e.exam_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':exam_id', $examId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'start_date' => $result['start_date'],
                'end_date' => $result['end_date'],
                'period_name' => $result['period_name'],
                'enrollment_count' => $result['enrollment_count']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Examination not found'
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}
?>
