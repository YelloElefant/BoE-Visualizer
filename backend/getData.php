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
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get the paper code from GET request
    // Support both formats: ?paperCode=VALUE and ?VALUE (direct parameter)
    $paperCode = '';
    
    if (isset($_GET['paperCode']) && !empty($_GET['paperCode'])) {
        // Standard format: ?paperCode=COMPX123-22A%20(HAM)
        $paperCode = $_GET['paperCode'];
    } else {
        // Direct format: ?COMPX123-22A%20(HAM) - get the first parameter key
        $queryParams = $_GET;
        if (!empty($queryParams)) {
            $paperCode = array_keys($queryParams)[0];
        }
    }

    // Validate input
    if (empty($paperCode)) {
        throw new Exception('Paper code is required. Use ?paperCode=VALUE or ?VALUE format');
    }

    $db = DB::getInstance();
    
    // Get paper data from database
    $result = $db->getPaperData($paperCode);
    
    if (!$result) {
        throw new Exception('No data found for paper code: ' . $paperCode);
    }

    // Count lines in CSV content
    $lines = array_filter(explode("\n", trim($result['csv_content'])), function($line) {
        return trim($line) !== '';
    });

    // Return success response with CSV content and statistics
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'paper_code' => $paperCode,
        'paper_name' => $result['paper']['paper_name'],
        'semester' => $result['paper']['semester'],
        'year' => $result['paper']['year'],
        'location' => $result['paper']['location'],
        'csv_content' => $result['csv_content'],
        'line_count' => count($lines),
        'last_modified' => $result['paper']['updated_at'],
        'statistics' => [
            'total_students' => $result['statistics']['total_students'] ?? 0,
            'average_score' => $result['statistics']['average_score'] ?? null,
            'highest_score' => $result['statistics']['highest_score'] ?? null,
            'lowest_score' => $result['statistics']['lowest_score'] ?? null,
            'pass_rate' => $result['statistics']['pass_rate'] ?? null,
            'grade_distribution' => $result['statistics']['grade_distribution'] ?? []
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
