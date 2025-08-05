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

    // Sanitize paper code for filename (remove special characters)
    $sanitizedPaperCode = preg_replace('/[^a-zA-Z0-9\-_()]/', '_', $paperCode);
    
    // Create data directory if it doesn't exist
    $dataDir = __DIR__ . '/../../data';
    if (!is_dir($dataDir)) {
        if (!mkdir($dataDir, 0755, true)) {
            throw new Exception('Failed to create data directory');
        }
    }

    // Check if a file with the same paper code already exists
    $existingFiles = glob($dataDir . '/' . $sanitizedPaperCode . '_*.csv');
    if (!empty($existingFiles)) {
        // Paper already exists, return success with existing info
        $existingFile = basename($existingFiles[0]);
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Paper already exists',
            'existing_file' => $existingFile,
            'paper_code' => $paperCode,
            'already_exists' => true
        ]);
        exit();
    }

    // Generate filename with timestamp to avoid conflicts
    $timestamp = date('Y-m-d_H-i-s');
    $filename = $sanitizedPaperCode . '_' . $timestamp . '.csv';
    $filepath = $dataDir . '/' . $filename;

    // Save CSV data to file
    if (file_put_contents($filepath, $csvData) === false) {
        throw new Exception('Failed to save CSV file');
    }

    // Log the upload
    $logEntry = [
        'timestamp' => date('c'),
        'paper_code' => $paperCode,
        'filename' => $filename,
        'file_size' => strlen($csvData),
        'line_count' => count(array_filter(explode("\n", $csvData), function($line) {
            return trim($line) !== '';
        }))
    ];

    $logFile = $dataDir . '/upload_log.json';
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
        'message' => 'CSV file uploaded successfully',
        'filename' => $filename,
        'paper_code' => $paperCode,
        'timestamp' => $timestamp,
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
