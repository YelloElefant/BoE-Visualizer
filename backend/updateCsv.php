<?php
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

    // Sanitize paper code for filename (same as upload.php)
    $sanitizedPaperCode = preg_replace('/[^a-zA-Z0-9\-_()]/', '_', $paperCode);
    
    // Define data directory
    $dataDir = __DIR__ . '/../../data';
    
    if (!is_dir($dataDir)) {
        throw new Exception('Data directory not found');
    }

    // Look for existing files with the matching paper code
    $pattern = $dataDir . '/' . $sanitizedPaperCode . '_*.csv';
    $existingFiles = glob($pattern);

    if (empty($existingFiles)) {
        throw new Exception('No existing file found for paper code: ' . $paperCode);
    }

    // Get the most recent file to update
    sort($existingFiles);
    $existingFile = end($existingFiles);
    
    

    // Log the update
    $logEntry = [
        'timestamp' => date('c'),
        'action' => 'update',
        'paper_code' => $paperCode,
        'filename' => basename($existingFile),
        'file_size' => strlen($csvData),
        'line_count' => count(array_filter(explode("\n", $csvData), function($line) {
            return trim($line) !== '';
        }))
    ];

    $logFile = $dataDir . '/update_log.json';
    $existingLog = [];
    if (file_exists($logFile)) {
        $existingLog = json_decode(file_get_contents($logFile), true) ?: [];
    }
    $existingLog[] = $logEntry;
    file_put_contents($logFile, json_encode($existingLog, JSON_PRETTY_PRINT));

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'CSV file updated successfully',
        'filename' => basename($existingFile),
        'paper_code' => $paperCode,
        'timestamp' => date('Y-m-d_H-i-s'),
        'line_count' => $logEntry['line_count']
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
