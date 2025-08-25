<?php
/**
 * OpenRouter API Client for BAIT Service Enterprise
 * Integrates z-ai/glm-4.5-air:free model for intelligent file analysis
 * 
 * Features:
 * - Secure API key management
 * - Rate limiting and error handling
 * - Context-aware conversations
 * - File content analysis
 */

class OpenRouterClient {
    private $apiKey;
    private $baseUrl = 'https://openrouter.ai/api/v1';
    private $model = 'z-ai/glm-4.5-air:free';
    private $maxTokens = 4000;
    private $temperature = 0.7;
    private $pdo;
    
    public function __construct($apiKey, $pdo = null) {
        if (empty($apiKey)) {
            throw new Exception('OpenRouter API key is required');
        }
        
        $this->apiKey = $apiKey;
        $this->pdo = $pdo;
    }
    
    /**
     * Send a chat completion request to OpenRouter
     */
    public function chat($messages, $systemPrompt = null) {
        try {
            // Prepare messages array
            $formattedMessages = [];
            
            if ($systemPrompt) {
                $formattedMessages[] = [
                    'role' => 'system',
                    'content' => $systemPrompt
                ];
            }
            
            // Add user messages
            if (is_string($messages)) {
                $formattedMessages[] = [
                    'role' => 'user', 
                    'content' => $messages
                ];
            } elseif (is_array($messages)) {
                $formattedMessages = array_merge($formattedMessages, $messages);
            }
            
            $payload = [
                'model' => $this->model,
                'messages' => $formattedMessages,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'stream' => false
            ];
            
            $response = $this->makeRequest('/chat/completions', $payload);
            
            if (isset($response['choices'][0]['message']['content'])) {
                return [
                    'success' => true,
                    'content' => $response['choices'][0]['message']['content'],
                    'usage' => $response['usage'] ?? null,
                    'model' => $this->model
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Invalid response format',
                'response' => $response
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Analyze file content with context
     */
    public function analyzeFile($filePath, $question, $context = []) {
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'error' => 'File not found: ' . $filePath
            ];
        }
        
        $fileContent = $this->readFileContent($filePath);
        $fileName = basename($filePath);
        $fileType = pathinfo($filePath, PATHINFO_EXTENSION);
        
        $systemPrompt = $this->buildFileAnalysisPrompt($fileName, $fileType, $context);
        
        $userMessage = "File: {$fileName}\n\nContent:\n{$fileContent}\n\nQuestion: {$question}";
        
        return $this->chat($userMessage, $systemPrompt);
    }
    
    /**
     * Query database with natural language
     */
    public function queryDatabase($question, $context = []) {
        if (!$this->pdo) {
            return [
                'success' => false,
                'error' => 'Database connection not available'
            ];
        }
        
        $systemPrompt = $this->buildDatabaseQueryPrompt($context);
        
        $dbSchema = $this->getDatabaseSchema();
        $userMessage = "Database Schema:\n{$dbSchema}\n\nQuestion: {$question}";
        
        return $this->chat($userMessage, $systemPrompt);
    }
    
    /**
     * Multi-file analysis with cross-references
     */
    public function analyzeMultipleFiles($files, $question, $context = []) {
        $filesContent = '';
        
        foreach ($files as $filePath) {
            if (file_exists($filePath)) {
                $fileName = basename($filePath);
                $content = $this->readFileContent($filePath);
                $filesContent .= "\n=== {$fileName} ===\n{$content}\n";
            }
        }
        
        $systemPrompt = $this->buildMultiFileAnalysisPrompt($context);
        $userMessage = "Files Content:\n{$filesContent}\n\nQuestion: {$question}";
        
        return $this->chat($userMessage, $systemPrompt);
    }
    
    /**
     * Build system prompt for file analysis
     */
    private function buildFileAnalysisPrompt($fileName, $fileType, $context) {
        $basePrompt = "You are an expert BAIT Service Enterprise system analyst. ";
        $basePrompt .= "You're analyzing a {$fileType} file named {$fileName}. ";
        $basePrompt .= "Provide precise, actionable insights focusing on business value and technical accuracy. ";
        
        if (!empty($context)) {
            $basePrompt .= "Context: " . implode(', ', $context) . ". ";
        }
        
        switch ($fileType) {
            case 'php':
                $basePrompt .= "Focus on code logic, security issues, performance bottlenecks, and integration points. ";
                break;
            case 'sql':
                $basePrompt .= "Analyze database queries, performance, data integrity, and optimization opportunities. ";
                break;
            case 'csv':
                $basePrompt .= "Examine data patterns, anomalies, missing values, and business insights. ";
                break;
            case 'html':
            case 'css':
            case 'js':
                $basePrompt .= "Review UI/UX implementation, accessibility, and user experience optimization. ";
                break;
            default:
                $basePrompt .= "Provide comprehensive analysis based on file content and business context. ";
        }
        
        $basePrompt .= "Always provide specific, actionable recommendations.";
        
        return $basePrompt;
    }
    
    /**
     * Build system prompt for database queries
     */
    private function buildDatabaseQueryPrompt($context) {
        $prompt = "You are a BAIT Service Enterprise database analyst. ";
        $prompt .= "You understand MySQL databases and the BAIT business domain (technician activities, alerts, timbratures, clients). ";
        $prompt .= "When asked about data, provide SQL queries and explain insights in business terms. ";
        $prompt .= "Focus on: technician productivity, alert patterns, time tracking accuracy, client service quality. ";
        
        if (!empty($context)) {
            $prompt .= "Additional context: " . implode(', ', $context) . ". ";
        }
        
        return $prompt;
    }
    
    /**
     * Build system prompt for multi-file analysis
     */
    private function buildMultiFileAnalysisPrompt($context) {
        $prompt = "You are analyzing multiple files from the BAIT Service Enterprise system. ";
        $prompt .= "Look for patterns, inconsistencies, integration issues, and optimization opportunities across files. ";
        $prompt .= "Provide holistic insights that consider how these files work together. ";
        
        if (!empty($context)) {
            $prompt .= "Focus context: " . implode(', ', $context) . ". ";
        }
        
        return $prompt;
    }
    
    /**
     * Read file content with size limits and encoding handling
     */
    private function readFileContent($filePath) {
        $maxFileSize = 100000; // 100KB limit
        
        if (filesize($filePath) > $maxFileSize) {
            return "File too large. Showing first " . ($maxFileSize / 1000) . "KB:\n" . 
                   substr(file_get_contents($filePath), 0, $maxFileSize);
        }
        
        $content = file_get_contents($filePath);
        
        // Handle encoding
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'auto');
        }
        
        return $content;
    }
    
    /**
     * Get database schema for context
     */
    private function getDatabaseSchema() {
        if (!$this->pdo) {
            return "Database connection not available";
        }
        
        try {
            $tables = ['tecnici', 'alert_dettagliati', 'audit_alerts', 'aziende_reali'];
            $schema = "";
            
            foreach ($tables as $table) {
                $stmt = $this->pdo->query("DESCRIBE {$table}");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $schema .= "\nTable: {$table}\n";
                foreach ($columns as $column) {
                    $schema .= "- {$column['Field']}: {$column['Type']}\n";
                }
            }
            
            return $schema;
        } catch (Exception $e) {
            return "Schema unavailable: " . $e->getMessage();
        }
    }
    
    /**
     * Make HTTP request to OpenRouter API
     */
    private function makeRequest($endpoint, $data) {
        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'HTTP-Referer: https://bait-service-enterprise.local',
            'X-Title: BAIT Service Enterprise'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'BAIT-Service-Enterprise/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('HTTP Error ' . $httpCode . ': ' . $response);
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }
        
        return $decoded;
    }
    
    /**
     * Test API connection
     */
    public function testConnection() {
        try {
            $response = $this->chat("Hello, this is a connection test.", "You are a helpful assistant.");
            return $response;
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get model information
     */
    public function getModelInfo() {
        return [
            'model' => $this->model,
            'provider' => 'OpenRouter',
            'cost' => 'Free',
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature
        ];
    }
}
?>