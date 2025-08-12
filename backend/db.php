<?php
// DB stuff for the grade visualizer

class DB {
    private static $instance = null;
    private $connection = null;
    
    private $host;
    private $port;
    private $dbname;
    private $username;
    private $password;
    
    private function __construct() {
        $this->host = $_ENV['DB_HOST'] ?? 'postgres';
        $this->port = $_ENV['DB_PORT'] ?? '5432';
        $this->dbname = $_ENV['DB_NAME'] ?? 'boe_visualizer';
        $this->username = $_ENV['DB_USER'] ?? 'boe_user';
        $this->password = $_ENV['DB_PASSWORD'] ?? 'boe_password';
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        if ($this->connection === null) {
            try {
                $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->dbname}";
                $this->connection = new PDO($dsn, $this->username, $this->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                throw new Exception("Database connection failed: " . $e->getMessage());
            }
        }
        return $this->connection;
    }
    
    // basic db functions
    public function query($sql, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }
    
    public function queryOne($sql, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }
    
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Execute failed: " . $e->getMessage());
        }
    }
    
    // transaction stuff
    public function beginTransaction() {
        return $this->getConnection()->beginTransaction();
    }
    
    public function commit() {
        return $this->getConnection()->commit();
    }
    
    public function rollback() {
        return $this->getConnection()->rollBack();
    }
    
    public function getOrCreatePaper($paperCode) {
        // check if paper exists first
        $paper = $this->queryOne(
            "SELECT * FROM papers WHERE paper_code = ?",
            [$paperCode]
        );
        
        if ($paper) {
            return $paper;
        }
        
        // Parse paper code to extract information
        $parsed = $this->queryOne(
            "SELECT * FROM parse_paper_code(?)",
            [$paperCode]
        );
        
        // make new paper
        $sql = "INSERT INTO papers (paper_code, paper_name, semester, year, location) 
                VALUES (?, ?, ?, ?, ?) RETURNING *";
        
        return $this->queryOne($sql, [
            $paperCode,
            $parsed['name'] ?? $parsed['code'],
            $parsed['semester'],
            $parsed['year'],
            $parsed['location']
        ]);
    }
    
    public function getOrCreateStudent($studentId, $firstName = null, $lastName = null, $email = null) {
        // check if student exists
        $student = $this->queryOne(
            "SELECT * FROM students WHERE student_id = ?",
            [$studentId]
        );
        
        if ($student) {
            // update student info if we have new data
            if ($firstName || $lastName || $email) {
                $this->execute(
                    "UPDATE students SET 
                     first_name = COALESCE(?, first_name),
                     last_name = COALESCE(?, last_name),
                     email = COALESCE(?, email),
                     updated_at = CURRENT_TIMESTAMP
                     WHERE student_id = ?",
                    [$firstName, $lastName, $email, $studentId]
                );
                
                // get updated student data
                $student = $this->queryOne(
                    "SELECT * FROM students WHERE student_id = ?",
                    [$studentId]
                );
            }
            return $student;
        }
        
        // make new student
        $sql = "INSERT INTO students (student_id, first_name, last_name, email) 
                VALUES (?, ?, ?, ?) RETURNING *";
        
        return $this->queryOne($sql, [$studentId, $firstName, $lastName, $email]);
    }
    
    // process CSV and stick it in the db
    public function processCsvData($paperCode, $csvContent, $filename) {
        $this->beginTransaction();
        
        try {
            // get or make paper
            $paper = $this->getOrCreatePaper($paperCode);
            
            // split up the CSV
            $lines = array_filter(explode("\n", trim($csvContent)), function($line) {
                return trim($line) !== '';
            });
            
            if (empty($lines)) {
                throw new Exception("CSV content is empty");
            }
            
            // get headers
            $headers = str_getcsv($lines[0]);
            $headers = array_map('trim', $headers);
            
            // figure out which columns are which
            $stdColumns = $this->identifyStandardColumns($headers);
            
            // track the upload
            $uploadSql = "INSERT INTO csv_uploads (paper_id, filename, original_headers, records_imported, records_updated) 
                         VALUES (?, ?, ?, 0, 0) RETURNING id";
            $upload = $this->queryOne($uploadSql, [
                $paper['id'],
                $filename,
                '{' . implode(',', $headers) . '}'
            ]);
            $uploadId = $upload['id'];
            
            $imported = 0;
            $updated = 0;
            
            // go through each row and process it
            for ($i = 1; $i < count($lines); $i++) {
                $rowData = str_getcsv($lines[$i]);
                
                // make sure row has enough columns
                while (count($rowData) < count($headers)) {
                    $rowData[] = '';
                }
                
                // grab the important stuff
                $studentId = $this->extractValue($rowData, $headers, $stdColumns['student_id']);
                $firstName = $this->extractValue($rowData, $headers, $stdColumns['first_name']);
                $lastName = $this->extractValue($rowData, $headers, $stdColumns['last_name']);
                $email = $this->extractValue($rowData, $headers, $stdColumns['email']);
                $grade = $this->extractValue($rowData, $headers, $stdColumns['grade']);
                
                // convert numbers to actual numbers
                $scoreRaw = $this->extractValue($rowData, $headers, $stdColumns['score']);
                $maxScoreRaw = $this->extractValue($rowData, $headers, $stdColumns['max_score']);
                
                $score = $scoreRaw !== null ? floatval($scoreRaw) : null;
                $maxScore = $maxScoreRaw !== null ? floatval($maxScoreRaw) : null;
                
                // 0 values are basically null anyway
                if ($score === 0.0) $score = null;
                if ($maxScore === 0.0) $maxScore = null;
                
                // skip if no student ID
                if (empty($studentId)) {
                    continue;
                }
                
                // get or make student
                $student = $this->getOrCreateStudent($studentId, $firstName, $lastName, $email);
                
                // work out percentage
                $percentage = null;
                if ($score !== null && $maxScore !== null && $maxScore > 0) {
                    $percentage = ($score / $maxScore) * 100;
                } elseif ($score !== null && $maxScore === null) {
                    $percentage = $score; // Assume score is already a percentage
                }
                
                // If no grade provided but we have a percentage, calculate letter grade
                if (empty($grade) && $percentage !== null) {
                    $grade = $this->calculateLetterGrade($percentage);
                }
                
                // Create or update submission
                $submissionSql = "INSERT INTO submissions (paper_id, student_id, grade, score, max_score, percentage) 
                                 VALUES (?, ?, ?, ?, ?, ?)
                                 ON CONFLICT (paper_id, student_id) 
                                 DO UPDATE SET 
                                    grade = EXCLUDED.grade,
                                    score = EXCLUDED.score,
                                    max_score = EXCLUDED.max_score,
                                    percentage = EXCLUDED.percentage,
                                    updated_at = CURRENT_TIMESTAMP
                                 RETURNING id, (xmax = 0) as was_inserted";
                
                $submission = $this->queryOne($submissionSql, [
                    $paper['id'],
                    $student['id'],
                    $grade,
                    $score,
                    $maxScore,
                    $percentage
                ]);
                
                if ($submission['was_inserted']) {
                    $imported++;
                } else {
                    $updated++;
                }
                
                // Store additional fields
                $this->storeAdditionalFields($submission['id'], $rowData, $headers, $stdColumns);
            }
            
            // Update upload record
            $this->execute(
                "UPDATE csv_uploads SET records_imported = ?, records_updated = ? WHERE id = ?",
                [$imported, $updated, $uploadId]
            );
            
            $this->commit();
            
            return [
                'upload_id' => $uploadId,
                'paper_id' => $paper['id'],
                'records_imported' => $imported,
                'records_updated' => $updated,
                'total_processed' => $imported + $updated
            ];
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    
    // figure out columns
    private function identifyStandardColumns($headers) {
        $mapping = [
            'student_id' => null,
            'first_name' => null,
            'last_name' => null,
            'email' => null,
            'grade' => null,
            'score' => null,
            'max_score' => null
        ];
        
        foreach ($headers as $index => $header) {
            $lower = strtolower(trim($header));
            
            // Student ID patterns - more flexible matching
            if (preg_match('/(student.?id|^id$|student.?number|studentno|id.?number|student.?no)/i', $lower)) {
                $mapping['student_id'] = $index;
            }
            // Name patterns
            elseif (preg_match('/(first.?name|fname|given.?name)/i', $lower)) {
                $mapping['first_name'] = $index;
            }
            elseif (preg_match('/(last.?name|lname|surname|family.?name)/i', $lower)) {
                $mapping['last_name'] = $index;
            }
            elseif (preg_match('/^(name|full.?name)$/i', $lower) && !$mapping['first_name'] && !$mapping['last_name']) {
                $mapping['first_name'] = $index; // Use as combined name field
            }
            // Email patterns
            elseif (preg_match('/(email|e.?mail|mail|email.?address)/i', $lower)) {
                $mapping['email'] = $index;
            }
            // Grade patterns
            elseif (preg_match('/(grade|letter.?grade|final.?grade)/i', $lower)) {
                $mapping['grade'] = $index;
            }
            // Score patterns - check for paper total first, then other patterns
            elseif (preg_match('/(paper.?total)/i', $lower)) {
                $mapping['score'] = $index;
            }
            elseif (preg_match('/(^score$|^points?$|^marks?$|^total$|^percentage$|^result$)/i', $lower) && !$mapping['score']) {
                $mapping['score'] = $index;
            }
            elseif (preg_match('/(max.?score|max.?points?|max.?marks?|maximum)/i', $lower)) {
                $mapping['max_score'] = $index;
            }
        }
        
        return $mapping;
    }
    
    
    // get value from csv row
    private function extractValue($rowData, $headers, $columnIndex) {
        if ($columnIndex === null || !isset($rowData[$columnIndex])) {
            return null;
        }
        
        $value = trim($rowData[$columnIndex]);
        return $value === '' ? null : $value;
    }
    
    
    // save extra csv columns that we dont use
    private function storeAdditionalFields($submissionId, $rowData, $headers, $stdColumns) {
        $usedColumns = array_filter($stdColumns);
        
        foreach ($headers as $index => $header) {
            // Skip if this column is mapped to a standard field
            if (in_array($index, $usedColumns)) {
                continue;
            }
            
            $value = $this->extractValue($rowData, $headers, $index);
            if ($value !== null) {
                $this->execute(
                    "INSERT INTO submission_fields (submission_id, field_name, field_value) 
                     VALUES (?, ?, ?) 
                     ON CONFLICT (submission_id, field_name) 
                     DO UPDATE SET field_value = EXCLUDED.field_value",
                    [$submissionId, trim($header), $value]
                );
            }
        }
    }
    
    
    // get list of papers
    public function getPaperList() {
        return $this->query(
            "SELECT 
                paper_code,
                paper_name,
                semester,
                year,
                location,
                total_submissions,
                ROUND(average_score, 2) as average_score,
                created_at,
                updated_at
             FROM paper_summary 
             ORDER BY year DESC, semester DESC, paper_code"
        );
    }
    
    
    // export paper data
    public function getPaperData($paperCode) {
        $paper = $this->queryOne(
            "SELECT * FROM papers WHERE paper_code = ?",
            [$paperCode]
        );
        
        if (!$paper) {
            throw new Exception("Paper not found: " . $paperCode);
        }
        
        // Use the database function to export CSV
        $csvContent = $this->queryOne(
            "SELECT export_paper_csv(?) as csv_content",
            [$paper['id']]
        );
        
        if (!$csvContent || !$csvContent['csv_content']) {
            throw new Exception("No data found for paper: " . $paperCode);
        }
        
        // Get paper statistics
        $stats = $this->queryOne(
            "SELECT * FROM get_paper_stats(?)",
            [$paper['id']]
        );
        
        return [
            'paper' => $paper,
            'csv_content' => $csvContent['csv_content'],
            'statistics' => $stats
        ];
    }
    
    
    // update paper with new csv
    public function updatePaperData($paperCode, $csvContent) {
        // Generate a filename for the update
        $timestamp = date('Y-m-d_H-i-s');
        $filename = preg_replace('/[^a-zA-Z0-9\-_()]/', '_', $paperCode) . '_updated_' . $timestamp . '.csv';
        
        // Process the CSV data (this will update existing records)
        return $this->processCsvData($paperCode, $csvContent, $filename);
    }
    
    
    // get paper stats  
    public function getPaperStatistics($paperCode) {
        $paper = $this->queryOne(
            "SELECT id FROM papers WHERE paper_code = ?",
            [$paperCode]
        );
        
        if (!$paper) {
            throw new Exception("Paper not found: " . $paperCode);
        }
        
        return $this->queryOne(
            "SELECT * FROM get_paper_stats(?)",
            [$paper['id']]
        );
    }
    
    
    // check if paper already exists
    public function paperExists($paperCode) {
        $result = $this->queryOne(
            "SELECT COUNT(*) as count FROM papers WHERE paper_code = ?",
            [$paperCode]
        );
        
        return $result['count'] > 0;
    }
    
    
    // convert percentage to letter grade
    private function calculateLetterGrade($percentage) {
        if ($percentage >= 90) return 'A+';
        if ($percentage >= 85) return 'A';
        if ($percentage >= 80) return 'A-';
        if ($percentage >= 75) return 'B+';
        if ($percentage >= 70) return 'B';
        if ($percentage >= 65) return 'B-';
        if ($percentage >= 60) return 'C+';
        if ($percentage >= 55) return 'C';
        if ($percentage >= 50) return 'C-';
        if ($percentage >= 45) return 'D+';
        if ($percentage >= 40) return 'D';
        if ($percentage >= 35) return 'D-';
        return 'F';
    }
}
?>
