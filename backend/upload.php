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
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get the data from POST request or file upload
    $csvData = '';
    $paperCode = $_POST['paperCode'] ?? '';
    
    // Check if data was uploaded as a file
    if (isset($_FILES['csvFile']) && $_FILES['csvFile']['error'] === UPLOAD_ERR_OK) {
        $csvData = file_get_contents($_FILES['csvFile']['tmp_name']);
    }
    // Otherwise check for direct CSV data
    elseif (!empty($_POST['csvData'])) {
        $csvData = $_POST['csvData'];
    }

    // Validate input
    if (empty($csvData)) {
        throw new Exception('CSV data is required (either as csvData parameter or csvFile upload)');
    }

    if (empty($paperCode)) {
        throw new Exception('Paper code is required');
    }

    $db = DB::getInstance();
    
    // Check if paper already exists
    if ($db->paperExists($paperCode)) {
        // Paper already exists, return info about existing paper
        $paperData = $db->getPaperData($paperCode);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Paper already exists',
            'paper_code' => $paperCode,
            'paper_name' => $paperData['paper']['paper_name'],
            'already_exists' => true,
            'total_submissions' => $paperData['statistics']['total_students'] ?? 0,
            'last_updated' => $paperData['paper']['updated_at']
        ]);
        exit();
    }

    // Generate filename with timestamp
    $sanitizedPaperCode = preg_replace('/[^a-zA-Z0-9\-_()]/', '_', $paperCode);
    $timestamp = date('Y-m-d_H-i-s');
    $filename = $sanitizedPaperCode . '_' . $timestamp . '.csv';

    // Process and store CSV data in normalized database
    $result = $db->processCsvData($paperCode, $csvData, $filename);

    // Count lines for response
    $lines = array_filter(explode("\n", trim($csvData)), function($line) {
        return trim($line) !== '';
    });

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'CSV data processed and stored successfully',
        'filename' => $filename,
        'paper_code' => $paperCode,
        'timestamp' => $timestamp,
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
