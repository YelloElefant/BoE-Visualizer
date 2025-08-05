<?php
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

    // Sanitize paper code for filename (same as upload.php)
    $sanitizedPaperCode = preg_replace('/[^a-zA-Z0-9\-_()]/', '_', $paperCode);
    
    // Define data directory
    $dataDir = __DIR__ . '/../../data';
    
    if (!is_dir($dataDir)) {
        throw new Exception('Data directory not found');
    }

    // Look for files with the matching paper code
    $pattern = $dataDir . '/' . $sanitizedPaperCode . '_*.csv';
    $matchingFiles = glob($pattern);

    if (empty($matchingFiles)) {
        throw new Exception('No data found for paper code: ' . $paperCode);
    }

    // Get the most recent file (last in array after sorting)
    sort($matchingFiles);
    $csvFile = end($matchingFiles);
    
    // Read the CSV file
    $csvContent = file_get_contents($csvFile);
    
    if ($csvContent === false) {
        throw new Exception('Failed to read CSV file');
    }

    // Get file info
    $filename = basename($csvFile);
    $fileSize = filesize($csvFile);
    $lastModified = date('c', filemtime($csvFile));

    // Return success response with CSV content
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'paper_code' => $paperCode,
        'filename' => $filename,
        'csv_content' => $csvContent,
        'file_size' => $fileSize,
        'last_modified' => $lastModified,
        'line_count' => count(array_filter(explode("\n", $csvContent), function($line) {
            return trim($line) !== '';
        }))
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
