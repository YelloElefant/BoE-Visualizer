<?php
require_once 'db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    $db = DB::getInstance();
    
    // Get list of papers with summary statistics from database
    $papers = $db->getPaperList();
    
    // Transform data for frontend compatibility
    $result = [];
    foreach ($papers as $paper) {
        $result[] = [
            'paper_code' => $paper['paper_code'],
            'paper_name' => $paper['paper_name'],
            'semester' => $paper['semester'],
            'year' => $paper['year'],
            'location' => $paper['location'],
            'total_submissions' => $paper['total_submissions'],
            'average_score' => $paper['average_score'],
            'created_at' => $paper['created_at'],
            'updated_at' => $paper['updated_at']
        ];
    }
    
    // Return the paper list
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'papers' => $result
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to retrieve paper list: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
