<?php
// Simple test to upload CSV data

$csvData = "Student ID,First Name,Last Name,Email,Grade,Score,Max Score
12345,John,Doe,john.doe@example.com,A+,95,100
23456,Jane,Smith,jane.smith@example.com,B,82,100
34567,Bob,Johnson,bob.johnson@example.com,A,90,100";

$paperCode = "TEST123-25A (TEST)";

$postData = http_build_query([
    'csvData' => $csvData,
    'paperCode' => $paperCode
]);

$options = [
    'http' => [
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'method' => 'POST',
        'content' => $postData
    ]
];

$context = stream_context_create($options);
$result = file_get_contents('http://localhost:374/api/upload.php', false, $context);

echo "Upload Response:\n";
echo $result . "\n";

// Also check what's in the database
echo "\nChecking database...\n";

try {
    require_once 'backend/db.php';
    $db = DB::getInstance();
    
    // Check papers
    $papers = $db->query("SELECT * FROM papers");
    echo "Papers in database: " . count($papers) . "\n";
    foreach ($papers as $paper) {
        echo "  - {$paper['paper_code']}: {$paper['paper_name']}\n";
    }
    
    // Check students
    $students = $db->query("SELECT * FROM students");
    echo "\nStudents in database: " . count($students) . "\n";
    foreach ($students as $student) {
        echo "  - {$student['student_id']}: {$student['first_name']} {$student['last_name']}\n";
    }
    
    // Check submissions
    $submissions = $db->query("SELECT * FROM submissions");
    echo "\nSubmissions in database: " . count($submissions) . "\n";
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
