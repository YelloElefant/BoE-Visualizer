<?php
require_once 'db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Get the data from POST request
    $csvData = $_POST['csvData'] ?? '';
    $paperCode = $_POST['paperCode'] ?? '';

    // Validate input
    if (empty($csvData)) {
        throw new Exception('CSV data is required');
    }

    if (empty($paperCode)) {
        throw new Exception('Paper code is required');
    }

    $db = DB::getInstance();
    
    // Check if paper exists
    if (!$db->paperExists($paperCode)) {
        throw new Exception('Paper not found: ' . $paperCode . '. Please upload the paper first.');
    }
    
    // Update CSV data in database (this will create backups automatically)
    $result = $db->updatePaperData($paperCode, $csvData);
    
    // Count lines for response
    $lines = array_filter(explode("\n", trim($csvData)), function($line) {
        return trim($line) !== '';
    });

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Paper data updated successfully',
        'paper_code' => $paperCode,
        'timestamp' => date('Y-m-d_H-i-s'),
        'line_count' => count($lines),
        'upload_id' => $result['upload_id'],
        'records_imported' => $result['records_imported'],
        'records_updated' => $result['records_updated'],
        'total_processed' => $result['total_processed']
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
