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
    private $model = 'meta-llama/llama-3.2-1b-instruct:free';
    private $maxTokens = 2000; // Reduced for faster responses
    private $temperature = 0.3; // Lower temperature for faster, more focused responses
    private $pdo;
    
    public function __construct($apiKey, $pdo = null, $model = null) {
        if (empty($apiKey)) {
            throw new Exception('OpenRouter API key is required');
        }
        
        $this->apiKey = $apiKey;
        $this->pdo = $pdo;
        if ($model) {
            $this->model = $model;
        }
    }
    
    /**
     * Send a chat completion request to OpenRouter with caching
     */
    public function chat($messages, $systemPrompt = null) {
        try {
            // Check cache first for identical requests
            $cacheKey = md5(serialize([$messages, $systemPrompt, $this->model]));
            $cachedResponse = $this->getCachedResponse($cacheKey);
            
            if ($cachedResponse) {
                return [
                    'success' => true,
                    'content' => $cachedResponse['content'],
                    'usage' => $cachedResponse['usage'],
                    'model' => $this->model,
                    'cached' => true
                ];
            }
            
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
            
            // Calculate input length and adjust max_tokens if needed
            $inputLength = strlen(json_encode($formattedMessages));
            $adjustedMaxTokens = $this->maxTokens;
            
            // Reduce max_tokens for very long inputs to avoid timeouts
            if ($inputLength > 50000) {
                $adjustedMaxTokens = 2000;
            } elseif ($inputLength > 20000) {
                $adjustedMaxTokens = 3000;
            }
            
            $payload = [
                'model' => $this->model,
                'messages' => $formattedMessages,
                'max_tokens' => $adjustedMaxTokens,
                'temperature' => $this->temperature,
                'stream' => false
            ];
            
            $response = $this->makeRequest('/chat/completions', $payload);
            
            if (isset($response['choices'][0]['message']['content'])) {
                $content = $response['choices'][0]['message']['content'];
                $usage = $response['usage'] ?? null;
                
                // Cache the response for future use
                $this->cacheResponse($cacheKey, ['content' => $content, 'usage' => $usage]);
                
                return [
                    'success' => true,
                    'content' => $content,
                    'usage' => $usage,
                    'model' => $this->model,
                    'cached' => false
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
     * Build system prompt for file analysis (optimized for speed)
     */
    private function buildFileAnalysisPrompt($fileName, $fileType, $context) {
        $basePrompt = "You are a BAIT Service analyst. Analyze {$fileName} ({$fileType}). ";
        
        switch ($fileType) {
            case 'php':
                $basePrompt .= "Focus: code logic, security, performance. ";
                break;
            case 'sql':
                $basePrompt .= "Focus: query performance, data integrity. ";
                break;
            case 'csv':
                $basePrompt .= "Focus: data patterns, anomalies, business insights. ";
                break;
            default:
                $basePrompt .= "Focus: key issues and optimization opportunities. ";
        }
        
        $basePrompt .= "Be concise and actionable.";
        
        return $basePrompt;
    }
    
    /**
     * Build system prompt for database queries (optimized for speed)
     */
    private function buildDatabaseQueryPrompt($context) {
        $prompt = "You are a BAIT Service database analyst. ";
        $prompt .= "Focus: technician productivity, alerts, timbratures, clients. ";
        $prompt .= "Provide SQL queries and business insights. Be concise.";
        
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
     * Make HTTP request to OpenRouter API with retry logic
     */
    private function makeRequest($endpoint, $data, $retries = 2) {
        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'HTTP-Referer: https://bait-service-enterprise.local',
            'X-Title: BAIT Service Enterprise'
        ];
        
        $lastError = null;
        
        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'BAIT-Service-Enterprise/1.0',
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            // Success case
            if (!$error && $httpCode === 200) {
                $decoded = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
                $lastError = 'Invalid JSON response: ' . json_last_error_msg();
            }
            // Error cases
            else if ($error) {
                $lastError = 'cURL Error: ' . $error;
            } else {
                $lastError = 'HTTP Error ' . $httpCode . ': ' . $response;
            }
            
            // Wait before retry (exponential backoff)
            if ($attempt < $retries) {
                sleep(pow(2, $attempt)); // 1s, 2s, 4s delays
            }
        }
        
        // All retries failed
        throw new Exception($lastError . ' (after ' . ($retries + 1) . ' attempts)');
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
    
    /**
     * Get cached response if available
     */
    private function getCachedResponse($cacheKey) {
        $cacheFile = sys_get_temp_dir() . '/bait_llm_cache_' . $cacheKey;
        
        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true);
            
            // Check if cache is still valid (15 minutes)
            if (isset($cacheData['timestamp']) && (time() - $cacheData['timestamp']) < 900) {
                return $cacheData['response'];
            } else {
                // Clean expired cache
                unlink($cacheFile);
            }
        }
        
        return null;
    }
    
    /**
     * Cache response for future use
     */
    private function cacheResponse($cacheKey, $response) {
        $cacheFile = sys_get_temp_dir() . '/bait_llm_cache_' . $cacheKey;
        $cacheData = [
            'timestamp' => time(),
            'response' => $response
        ];
        
        file_put_contents($cacheFile, json_encode($cacheData));
    }
}
?>