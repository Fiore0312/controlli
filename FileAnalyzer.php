<?php
/**
 * File Analyzer for BAIT Service Enterprise
 * Indexes and analyzes project files for LLM integration
 * 
 * Features:
 * - File type detection and classification
 * - Content extraction and indexing
 * - Metadata generation
 * - Search and filtering capabilities
 */

require_once 'OpenRouterClient.php';

class FileAnalyzer {
    private $pdo;
    private $projectRoot;
    private $supportedTypes = ['php', 'sql', 'csv', 'html', 'css', 'js', 'json', 'md', 'txt'];
    private $excludePatterns = ['vendor/', 'node_modules/', '.git/', 'backup_', 'logs/'];
    
    public function __construct($pdo, $projectRoot = null) {
        $this->pdo = $pdo;
        $this->projectRoot = $projectRoot ?: __DIR__;
        $this->initializeDatabase();
    }
    
    /**
     * Initialize database tables for file indexing
     */
    private function initializeDatabase() {
        $sql = "
            CREATE TABLE IF NOT EXISTS file_index (
                id INT AUTO_INCREMENT PRIMARY KEY,
                file_path VARCHAR(500) NOT NULL UNIQUE,
                file_name VARCHAR(255) NOT NULL,
                file_type VARCHAR(50) NOT NULL,
                file_size BIGINT NOT NULL,
                content_hash VARCHAR(64) NOT NULL,
                last_modified TIMESTAMP NOT NULL,
                indexed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                -- Metadata fields
                line_count INT DEFAULT 0,
                function_count INT DEFAULT 0,
                class_count INT DEFAULT 0,
                complexity_score INT DEFAULT 0,
                
                -- Content analysis
                keywords TEXT,
                description TEXT,
                tags TEXT,
                
                INDEX idx_file_type (file_type),
                INDEX idx_last_modified (last_modified),
                INDEX idx_complexity (complexity_score)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $this->pdo->exec($sql);
    }
    
    /**
     * Scan and index all project files
     */
    public function indexAllFiles() {
        $results = [
            'scanned' => 0,
            'indexed' => 0,
            'updated' => 0,
            'errors' => 0,
            'skipped' => 0
        ];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->projectRoot),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            $results['scanned']++;
            
            if ($file->isDir() || $file->getFilename() === '.' || $file->getFilename() === '..') {
                continue;
            }
            
            $filePath = $file->getRealPath();
            $relativePath = str_replace($this->projectRoot . DIRECTORY_SEPARATOR, '', $filePath);
            
            // Skip excluded patterns
            if ($this->shouldExcludeFile($relativePath)) {
                $results['skipped']++;
                continue;
            }
            
            // Check if supported file type
            $extension = strtolower($file->getExtension());
            if (!in_array($extension, $this->supportedTypes)) {
                $results['skipped']++;
                continue;
            }
            
            try {
                $result = $this->indexFile($filePath, $relativePath);
                if ($result['action'] === 'indexed') {
                    $results['indexed']++;
                } elseif ($result['action'] === 'updated') {
                    $results['updated']++;
                }
            } catch (Exception $e) {
                $results['errors']++;
                error_log("File indexing error for {$relativePath}: " . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Index a single file
     */
    public function indexFile($filePath, $relativePath = null) {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: {$filePath}");
        }
        
        $relativePath = $relativePath ?: str_replace($this->projectRoot . DIRECTORY_SEPARATOR, '', $filePath);
        $fileName = basename($filePath);
        $fileType = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $fileSize = filesize($filePath);
        $lastModified = date('Y-m-d H:i:s', filemtime($filePath));
        $contentHash = hash_file('sha256', $filePath);
        
        // Check if file already exists and needs update
        $stmt = $this->pdo->prepare("SELECT id, content_hash, last_modified FROM file_index WHERE file_path = ?");
        $stmt->execute([$relativePath]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $needsUpdate = !$existing || 
                      $existing['content_hash'] !== $contentHash || 
                      $existing['last_modified'] !== $lastModified;
        
        if (!$needsUpdate) {
            return ['action' => 'skipped', 'reason' => 'no_changes'];
        }
        
        // Analyze file content
        $analysis = $this->analyzeFileContent($filePath, $fileType);
        
        if ($existing) {
            // Update existing record
            $stmt = $this->pdo->prepare("
                UPDATE file_index SET 
                    file_name = ?, file_type = ?, file_size = ?, 
                    content_hash = ?, last_modified = ?,
                    line_count = ?, function_count = ?, class_count = ?, complexity_score = ?,
                    keywords = ?, description = ?, tags = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $fileName, $fileType, $fileSize, $contentHash, $lastModified,
                $analysis['line_count'], $analysis['function_count'], 
                $analysis['class_count'], $analysis['complexity_score'],
                json_encode($analysis['keywords']), $analysis['description'],
                json_encode($analysis['tags']), $existing['id']
            ]);
            
            return ['action' => 'updated', 'id' => $existing['id']];
        } else {
            // Insert new record
            $stmt = $this->pdo->prepare("
                INSERT INTO file_index 
                (file_path, file_name, file_type, file_size, content_hash, last_modified,
                 line_count, function_count, class_count, complexity_score,
                 keywords, description, tags)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $relativePath, $fileName, $fileType, $fileSize, $contentHash, $lastModified,
                $analysis['line_count'], $analysis['function_count'], 
                $analysis['class_count'], $analysis['complexity_score'],
                json_encode($analysis['keywords']), $analysis['description'],
                json_encode($analysis['tags'])
            ]);
            
            return ['action' => 'indexed', 'id' => $this->pdo->lastInsertId()];
        }
    }
    
    /**
     * Analyze file content based on type
     */
    private function analyzeFileContent($filePath, $fileType) {
        $content = file_get_contents($filePath);
        $analysis = [
            'line_count' => substr_count($content, "\n") + 1,
            'function_count' => 0,
            'class_count' => 0,
            'complexity_score' => 0,
            'keywords' => [],
            'description' => '',
            'tags' => []
        ];
        
        switch ($fileType) {
            case 'php':
                $analysis = $this->analyzePHPFile($content, $analysis);
                break;
            case 'sql':
                $analysis = $this->analyzeSQLFile($content, $analysis);
                break;
            case 'csv':
                $analysis = $this->analyzeCSVFile($filePath, $analysis);
                break;
            case 'js':
                $analysis = $this->analyzeJSFile($content, $analysis);
                break;
            case 'html':
                $analysis = $this->analyzeHTMLFile($content, $analysis);
                break;
            default:
                $analysis = $this->analyzeGenericFile($content, $analysis);
        }
        
        return $analysis;
    }
    
    /**
     * Analyze PHP file
     */
    private function analyzePHPFile($content, $analysis) {
        // Count functions
        $analysis['function_count'] = preg_match_all('/function\s+\w+\s*\(/', $content);
        
        // Count classes
        $analysis['class_count'] = preg_match_all('/class\s+\w+/', $content);
        
        // Extract keywords
        $keywords = [];
        if (strpos($content, 'MySQL') !== false || strpos($content, 'PDO') !== false) {
            $keywords[] = 'database';
        }
        if (strpos($content, 'curl_') !== false) {
            $keywords[] = 'api';
        }
        if (strpos($content, 'audit') !== false) {
            $keywords[] = 'audit';
        }
        if (strpos($content, 'alert') !== false) {
            $keywords[] = 'alerts';
        }
        if (strpos($content, 'tecnico') !== false) {
            $keywords[] = 'technician';
        }
        
        // Calculate complexity (basic)
        $complexity = 0;
        $complexity += substr_count($content, 'if');
        $complexity += substr_count($content, 'for');
        $complexity += substr_count($content, 'while');
        $complexity += substr_count($content, 'switch');
        
        $analysis['complexity_score'] = min($complexity, 100);
        $analysis['keywords'] = $keywords;
        
        // Generate description
        if (preg_match('/\/\*\*(.*?)\*\//s', $content, $matches)) {
            $comment = trim(str_replace(['*', '/'], '', $matches[1]));
            $analysis['description'] = substr($comment, 0, 500);
        }
        
        // Tags
        $tags = ['php'];
        if ($analysis['class_count'] > 0) $tags[] = 'oop';
        if (strpos($content, 'database') !== false) $tags[] = 'database';
        $analysis['tags'] = $tags;
        
        return $analysis;
    }
    
    /**
     * Analyze SQL file
     */
    private function analyzeSQLFile($content, $analysis) {
        $analysis['keywords'] = [];
        $tags = ['sql'];
        
        // Count different SQL operations
        $createCount = substr_count(strtoupper($content), 'CREATE TABLE');
        $insertCount = substr_count(strtoupper($content), 'INSERT INTO');
        $selectCount = substr_count(strtoupper($content), 'SELECT');
        $updateCount = substr_count(strtoupper($content), 'UPDATE');
        
        $analysis['complexity_score'] = ($createCount * 3) + ($insertCount * 2) + $selectCount + ($updateCount * 2);
        
        if ($createCount > 0) {
            $analysis['keywords'][] = 'schema';
            $tags[] = 'ddl';
        }
        if ($insertCount > 0) {
            $analysis['keywords'][] = 'data';
            $tags[] = 'dml';
        }
        if ($selectCount > 0) {
            $analysis['keywords'][] = 'query';
            $tags[] = 'select';
        }
        
        $analysis['tags'] = $tags;
        
        return $analysis;
    }
    
    /**
     * Analyze CSV file
     */
    private function analyzeCSVFile($filePath, $analysis) {
        $handle = fopen($filePath, 'r');
        if ($handle) {
            $headers = fgetcsv($handle);
            $rowCount = 0;
            while (fgetcsv($handle)) {
                $rowCount++;
            }
            fclose($handle);
            
            $analysis['keywords'] = $headers ? array_slice($headers, 0, 10) : [];
            $analysis['description'] = "CSV file with " . count($headers) . " columns and {$rowCount} rows";
            $analysis['tags'] = ['csv', 'data'];
            $analysis['complexity_score'] = min($rowCount / 100, 50);
        }
        
        return $analysis;
    }
    
    /**
     * Analyze JavaScript file
     */
    private function analyzeJSFile($content, $analysis) {
        $analysis['function_count'] = preg_match_all('/function\s+\w+\s*\(|=>\s*{|:\s*function/', $content);
        
        $keywords = [];
        if (strpos($content, 'fetch') !== false || strpos($content, 'ajax') !== false) {
            $keywords[] = 'api';
        }
        if (strpos($content, 'chart') !== false || strpos($content, 'graph') !== false) {
            $keywords[] = 'visualization';
        }
        
        $analysis['keywords'] = $keywords;
        $analysis['tags'] = ['javascript'];
        
        return $analysis;
    }
    
    /**
     * Analyze HTML file
     */
    private function analyzeHTMLFile($content, $analysis) {
        $analysis['keywords'] = [];
        $analysis['tags'] = ['html'];
        
        if (strpos($content, 'bootstrap') !== false) {
            $analysis['keywords'][] = 'bootstrap';
        }
        if (strpos($content, 'dashboard') !== false) {
            $analysis['keywords'][] = 'dashboard';
        }
        
        return $analysis;
    }
    
    /**
     * Analyze generic file
     */
    private function analyzeGenericFile($content, $analysis) {
        $analysis['keywords'] = [];
        $analysis['tags'] = ['text'];
        
        return $analysis;
    }
    
    /**
     * Check if file should be excluded
     */
    private function shouldExcludeFile($relativePath) {
        foreach ($this->excludePatterns as $pattern) {
            if (strpos($relativePath, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Search files by criteria
     */
    public function searchFiles($query = '', $fileType = null, $limit = 50) {
        $sql = "SELECT * FROM file_index WHERE 1=1";
        $params = [];
        
        if (!empty($query)) {
            $sql .= " AND (file_name LIKE ? OR description LIKE ? OR keywords LIKE ?)";
            $searchTerm = "%{$query}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($fileType) {
            $sql .= " AND file_type = ?";
            $params[] = $fileType;
        }
        
        $sql .= " ORDER BY complexity_score DESC, last_modified DESC LIMIT " . (int)$limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get file statistics
     */
    public function getFileStatistics() {
        $stats = [];
        
        // Files by type
        $stmt = $this->pdo->query("
            SELECT file_type, COUNT(*) as count, 
                   AVG(complexity_score) as avg_complexity,
                   SUM(file_size) as total_size
            FROM file_index 
            GROUP BY file_type 
            ORDER BY count DESC
        ");
        $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Total stats
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as total_files,
                   SUM(file_size) as total_size,
                   AVG(complexity_score) as avg_complexity,
                   MAX(last_modified) as latest_update
            FROM file_index
        ");
        $stats['totals'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $stats;
    }
    
    /**
     * Get file content for AI analysis
     */
    public function getFileForAnalysis($fileId) {
        $stmt = $this->pdo->prepare("SELECT * FROM file_index WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            return null;
        }
        
        $fullPath = $this->projectRoot . DIRECTORY_SEPARATOR . $file['file_path'];
        if (file_exists($fullPath)) {
            $file['content'] = file_get_contents($fullPath);
        }
        
        return $file;
    }
    
    /**
     * Get related files based on keywords and type
     */
    public function getRelatedFiles($fileId, $limit = 5) {
        $stmt = $this->pdo->prepare("SELECT keywords, file_type FROM file_index WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            return [];
        }
        
        $keywords = json_decode($file['keywords'], true) ?: [];
        if (empty($keywords)) {
            return [];
        }
        
        $keywordConditions = [];
        $params = [];
        foreach ($keywords as $keyword) {
            $keywordConditions[] = "keywords LIKE ?";
            $params[] = "%{$keyword}%";
        }
        
        $sql = "SELECT id, file_name, file_type, description, complexity_score 
                FROM file_index 
                WHERE id != ? AND (" . implode(' OR ', $keywordConditions) . ")
                ORDER BY complexity_score DESC 
                LIMIT ?";
        
        $params = array_merge([$fileId], $params, [$limit]);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>