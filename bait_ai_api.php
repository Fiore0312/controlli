<?php
/**
 * BAIT AI Chat API Endpoint
 * Handles AJAX requests for file listing, chat operations, and system management
 */

require_once 'OpenRouterClient.php';
require_once 'FileAnalyzer.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'bait_service_real',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

$response = [
    'success' => false,
    'error' => '',
    'data' => null
];

// Helper function to determine CSV file importance
function getCSVImportance($fileName) {
    $fileName = strtolower($fileName);
    if (strpos($fileName, 'timbrature') !== false) return 100;
    if (strpos($fileName, 'teamviewer') !== false) return 90;
    if (strpos($fileName, 'attivita') !== false) return 80;
    if (strpos($fileName, 'auto') !== false) return 70;
    if (strpos($fileName, 'calendario') !== false) return 60;
    if (strpos($fileName, 'permessi') !== false) return 50;
    if (strpos($fileName, 'aziende') !== false) return 40;
    return 30;
}

try {
    $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}", 
                   $config['username'], $config['password'], [
                       PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                       PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                       PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                   ]);

    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_file_list':
            // Get files directly from upload_csv folder for immediate results
            $uploadDir = __DIR__ . '/upload_csv/';
            $files = [];
            $id = 1;
            
            if (is_dir($uploadDir)) {
                $csvFiles = glob($uploadDir . '*.csv');
                foreach ($csvFiles as $filePath) {
                    $fileName = basename($filePath);
                    $fileSize = filesize($filePath);
                    $lastModified = filemtime($filePath);
                    
                    $files[] = [
                        'id' => $id++,
                        'file_name' => $fileName,
                        'file_path' => 'upload_csv/' . $fileName,
                        'file_type' => 'csv',
                        'file_size' => $fileSize,
                        'last_modified' => date('Y-m-d H:i:s', $lastModified),
                        'complexity_score' => getCSVImportance($fileName)
                    ];
                }
                
                // Sort by importance
                usort($files, function($a, $b) {
                    return $b['complexity_score'] - $a['complexity_score'];
                });
            }
            
            $response['success'] = true;
            $response['data'] = [
                'files' => $files,
                'total' => count($files)
            ];
            break;
            
        case 'index_files':
            $fileAnalyzer = new FileAnalyzer($pdo);
            $results = $fileAnalyzer->indexAllFiles();
            
            $response['success'] = true;
            $response['data'] = $results;
            break;
            
        case 'get_file_stats':
            $fileAnalyzer = new FileAnalyzer($pdo);
            $stats = $fileAnalyzer->getFileStatistics();
            
            $response['success'] = true;
            $response['data'] = $stats;
            break;
            
        case 'send_chat_message':
            $apiKey = $_SESSION['openrouter_api_key'] ?? '';
            if (empty($apiKey)) {
                throw new Exception('API Key not configured');
            }
            
            $message = trim($_POST['message'] ?? '');
            $contextType = $_POST['context_type'] ?? 'general';
            $contextId = $_POST['context_id'] ?? null;
            
            if (empty($message)) {
                throw new Exception('Message is required');
            }
            
            $selectedModel = $_SESSION['selected_model'] ?? 'z-ai/glm-4.5-air:free';
            $client = new OpenRouterClient($apiKey, $pdo, $selectedModel);
            $fileAnalyzer = new FileAnalyzer($pdo);
            $startTime = microtime(true);
            
            switch ($contextType) {
                case 'file_analysis':
                    if ($contextId) {
                        // Get CSV files directly from upload folder
                        $uploadDir = __DIR__ . '/upload_csv/';
                        $csvFiles = glob($uploadDir . '*.csv');
                        $selectedFile = null;
                        
                        // Find file by ID (matching the list we generated)
                        $id = 1;
                        foreach ($csvFiles as $filePath) {
                            if ($id == $contextId) {
                                $selectedFile = $filePath;
                                break;
                            }
                            $id++;
                        }
                        
                        if ($selectedFile && file_exists($selectedFile)) {
                            $result = $client->analyzeFile($selectedFile, $message, ['project' => 'BAIT Service Enterprise']);
                        } else {
                            throw new Exception('CSV file not found');
                        }
                    } else {
                        throw new Exception('File ID is required for file analysis');
                    }
                    break;
                    
                case 'database_query':
                    $result = $client->queryDatabase($message, ['database' => 'bait_service_real']);
                    break;
                    
                case 'multi_file':
                    $fileIds = explode(',', $contextId);
                    $files = [];
                    foreach ($fileIds as $fileId) {
                        $file = $fileAnalyzer->getFileForAnalysis(trim($fileId));
                        if ($file) {
                            $files[] = __DIR__ . DIRECTORY_SEPARATOR . $file['file_path'];
                        }
                    }
                    if (!empty($files)) {
                        $result = $client->analyzeMultipleFiles($files, $message, ['project' => 'BAIT Service Enterprise']);
                    } else {
                        throw new Exception('No valid files found');
                    }
                    break;
                    
                default:
                    $systemPrompt = "You are an AI assistant for the BAIT Service Enterprise system. " .
                                  "You help with business analysis, code review, and operational insights. " .
                                  "Provide professional, accurate, and actionable responses in Italian when possible.";
                    $result = $client->chat($message, $systemPrompt);
            }
            
            $responseTime = round((microtime(true) - $startTime) * 1000);
            
            if ($result['success']) {
                // Save to history
                $sessionId = session_id();
                $stmt = $pdo->prepare("
                    INSERT INTO ai_chat_history 
                    (session_id, user_message, ai_response, context_type, context_data, response_time_ms, tokens_used)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $sessionId, $message, $result['content'], $contextType,
                    json_encode(['context_id' => $contextId]), $responseTime,
                    $result['usage']['total_tokens'] ?? 0
                ]);
                
                $response['success'] = true;
                $response['data'] = [
                    'ai_response' => $result['content'],
                    'response_time' => $responseTime,
                    'tokens_used' => $result['usage']['total_tokens'] ?? 0,
                    'model' => $result['model'],
                    'cached' => $result['cached'] ?? false
                ];
            } else {
                throw new Exception($result['error']);
            }
            break;
            
        case 'clear_chat_history':
            $sessionId = session_id();
            $stmt = $pdo->prepare("DELETE FROM ai_chat_history WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            
            $response['success'] = true;
            $response['data'] = ['deleted' => $stmt->rowCount()];
            break;
            
        case 'get_chat_history':
            $sessionId = session_id();
            $limit = (int)($_GET['limit'] ?? 20);
            
            $stmt = $pdo->prepare("
                SELECT user_message, ai_response, context_type, response_time_ms, tokens_used, created_at
                FROM ai_chat_history 
                WHERE session_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$sessionId, $limit]);
            
            $response['success'] = true;
            $response['data'] = ['history' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
            break;
            
        case 'test_api_connection':
            $apiKey = trim($_POST['api_key'] ?? '');
            if (empty($apiKey)) {
                throw new Exception('API Key is required');
            }
            
            $client = new OpenRouterClient($apiKey, $pdo);
            $testResult = $client->testConnection();
            
            if ($testResult['success']) {
                $_SESSION['openrouter_api_key'] = $apiKey;
                $response['success'] = true;
                $response['data'] = [
                    'message' => 'API Key configured successfully',
                    'model_info' => $client->getModelInfo()
                ];
            } else {
                throw new Exception($testResult['error']);
            }
            break;
            
        case 'get_file_content':
            $fileId = (int)($_GET['file_id'] ?? 0);
            if (!$fileId) {
                throw new Exception('File ID is required');
            }
            
            $fileAnalyzer = new FileAnalyzer($pdo);
            $file = $fileAnalyzer->getFileForAnalysis($fileId);
            
            if (!$file) {
                throw new Exception('File not found');
            }
            
            $response['success'] = true;
            $response['data'] = [
                'file_info' => [
                    'id' => $file['id'],
                    'name' => $file['file_name'],
                    'type' => $file['file_type'],
                    'size' => $file['file_size'],
                    'last_modified' => $file['last_modified']
                ],
                'content' => $file['content'] ?? 'Content not available'
            ];
            break;
            
        case 'get_related_files':
            $fileId = (int)($_GET['file_id'] ?? 0);
            if (!$fileId) {
                throw new Exception('File ID is required');
            }
            
            $fileAnalyzer = new FileAnalyzer($pdo);
            $relatedFiles = $fileAnalyzer->getRelatedFiles($fileId);
            
            $response['success'] = true;
            $response['data'] = ['related_files' => $relatedFiles];
            break;
            
        case 'generate_project_summary':
            $apiKey = $_SESSION['openrouter_api_key'] ?? '';
            if (empty($apiKey)) {
                throw new Exception('API Key not configured');
            }
            
            $fileAnalyzer = new FileAnalyzer($pdo);
            $stats = $fileAnalyzer->getFileStatistics();
            
            $client = new OpenRouterClient($apiKey, $pdo);
            $systemPrompt = "You are analyzing the BAIT Service Enterprise project. Based on the file statistics provided, generate a comprehensive project summary including technology stack, complexity analysis, and recommendations.";
            
            $message = "Project Statistics:\n" . json_encode($stats, JSON_PRETTY_PRINT) . "\n\nGenerate a detailed project analysis and summary.";
            
            $result = $client->chat($message, $systemPrompt);
            
            if ($result['success']) {
                $response['success'] = true;
                $response['data'] = [
                    'summary' => $result['content'],
                    'stats' => $stats
                ];
            } else {
                throw new Exception($result['error']);
            }
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    error_log("BAIT AI API Error: " . $e->getMessage());
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>