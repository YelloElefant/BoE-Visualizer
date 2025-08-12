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


// Scan data directory for CSV files
$dataDir = __DIR__ . '/../../data';
if (!is_dir($dataDir)) {
    echo json_encode([]);
    exit();
}

$files = glob($dataDir . '/*.csv');
$result = [];
foreach ($files as $file) {
    $filename = basename($file);
    // Extract paper code from filename: e.g. COMPX123-22A_(HAM)_2025-08-05_12-01-48.csv
    if (preg_match('/^(.+?)_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.csv$/', $filename, $matches)) {
        $paper_code = str_replace('_', ' ', $matches[1]);
        $paper_code = preg_replace('/\s*\(\s*/', ' (', $paper_code); // fix space before (
        $paper_code = preg_replace('/\s+/', ' ', $paper_code); // collapse spaces
        $result[] = [
            'paper_code' => $paper_code,
            'filename' => $filename
        ];
    }
}
echo json_encode($result);
exit();
