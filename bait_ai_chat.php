<?php
/**
 * BAIT AI Chat Interface - Intelligent File Analysis System
 * Professional chat interface for interacting with project files using OpenRouter LLM
 */

require_once 'OpenRouterClient.php';
require_once 'FileAnalyzer.php';
require_once 'includes/bait_navigation.php';

session_start();
header('Content-Type: text/html; charset=utf-8');

$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'bait_service_real',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

// Initialize variables
$error = null;
$apiKeyConfigured = false;
$chatResponse = null;
$fileAnalyzer = null;

try {
    $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}", 
                   $config['username'], $config['password'], [
                       PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                       PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                       PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                   ]);
    
    // Initialize chat history table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ai_chat_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) NOT NULL,
            user_message TEXT NOT NULL,
            ai_response TEXT NOT NULL,
            context_type VARCHAR(50),
            context_data JSON,
            response_time_ms INT DEFAULT 0,
            tokens_used INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_session (session_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    $fileAnalyzer = new FileAnalyzer($pdo);
    
    // Check if API key is configured
    $apiKey = $_SESSION['openrouter_api_key'] ?? '';
    $apiKeyConfigured = !empty($apiKey);
    
    // Handle API key configuration
    if (($_POST['action'] ?? '') === 'configure_api' && !empty($_POST['api_key'])) {
        $apiKey = trim($_POST['api_key']);
        $selectedModel = $_POST['selected_model'] ?? 'z-ai/glm-4.5-air:free';
        
        // Save selected model in session
        $_SESSION['selected_model'] = $selectedModel;
        
        // Test the API key
        try {
            $client = new OpenRouterClient($apiKey, $pdo);
            $testResult = $client->testConnection();
            
            if ($testResult['success']) {
                $_SESSION['openrouter_api_key'] = $apiKey;
                $apiKeyConfigured = true;
                $chatResponse = [
                    'type' => 'success',
                    'message' => 'API Key configurata con successo! Connessione a OpenRouter stabilita.',
                    'model_info' => $client->getModelInfo()
                ];
            } else {
                $error = 'Test API Key fallito: ' . $testResult['error'];
            }
        } catch (Exception $e) {
            $error = 'Errore configurazione API Key: ' . $e->getMessage();
        }
    }
    
    // Handle chat requests
    if (($_POST['action'] ?? '') === 'send_message' && $apiKeyConfigured && !empty($_POST['message'])) {
        $userMessage = trim($_POST['message']);
        $contextType = $_POST['context_type'] ?? 'general';
        $contextId = $_POST['context_id'] ?? null;
        
        try {
            $selectedModel = $_SESSION['selected_model'] ?? 'z-ai/glm-4.5-air:free';
            $client = new OpenRouterClient($apiKey, $pdo, $selectedModel);
            $startTime = microtime(true);
            
            switch ($contextType) {
                case 'file_analysis':
                    if ($contextId) {
                        $file = $fileAnalyzer->getFileForAnalysis($contextId);
                        if ($file) {
                            $filePath = __DIR__ . DIRECTORY_SEPARATOR . $file['file_path'];
                            $result = $client->analyzeFile($filePath, $userMessage, ['project' => 'BAIT Service Enterprise']);
                        } else {
                            $result = ['success' => false, 'error' => 'File not found'];
                        }
                    }
                    break;
                    
                case 'database_query':
                    $result = $client->queryDatabase($userMessage, ['database' => 'bait_service_real']);
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
                        $result = $client->analyzeMultipleFiles($files, $userMessage, ['project' => 'BAIT Service Enterprise']);
                    }
                    break;
                    
                default:
                    $systemPrompt = "You are an AI assistant for the BAIT Service Enterprise system. " .
                                  "You help with business analysis, code review, and operational insights. " .
                                  "Provide professional, accurate, and actionable responses.";
                    $result = $client->chat($userMessage, $systemPrompt);
            }
            
            $responseTime = round((microtime(true) - $startTime) * 1000);
            
            if ($result['success']) {
                $chatResponse = [
                    'type' => 'chat',
                    'user_message' => $userMessage,
                    'ai_response' => $result['content'],
                    'response_time' => $responseTime,
                    'tokens_used' => $result['usage']['total_tokens'] ?? 0,
                    'model' => $result['model']
                ];
                
                // Save to history
                $sessionId = session_id();
                $stmt = $pdo->prepare("
                    INSERT INTO ai_chat_history 
                    (session_id, user_message, ai_response, context_type, context_data, response_time_ms, tokens_used)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $sessionId, $userMessage, $result['content'], $contextType,
                    json_encode(['context_id' => $contextId]), $responseTime,
                    $result['usage']['total_tokens'] ?? 0
                ]);
                
            } else {
                $error = 'Errore AI: ' . $result['error'];
            }
            
        } catch (Exception $e) {
            $error = 'Errore invio messaggio: ' . $e->getMessage();
        }
    }
    
    // Get file statistics for dashboard
    $fileStats = $fileAnalyzer->getFileStatistics();
    
    // Get recent chat history
    $sessionId = session_id();
    $stmt = $pdo->prepare("
        SELECT user_message, ai_response, context_type, response_time_ms, tokens_used, created_at
        FROM ai_chat_history 
        WHERE session_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$sessionId]);
    $chatHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BAIT AI Chat - Intelligent File Analysis</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --bait-blue: #2563eb;
            --bait-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --bait-gradient-light: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
        }
        
        .header-gradient {
            background: var(--bait-gradient);
            color: white;
            padding: 1.5rem 0;
        }
        
        .chat-container {
            height: 70vh;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .chat-header {
            background: var(--bait-gradient);
            color: white;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: between;
        }
        
        .chat-messages {
            height: calc(100% - 140px);
            overflow-y: auto;
            padding: 1rem;
            background: white;
        }
        
        .message {
            margin-bottom: 1rem;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message-user {
            text-align: right;
        }
        
        .message-user .message-bubble {
            background: var(--bait-gradient);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 18px 18px 4px 18px;
            display: inline-block;
            max-width: 80%;
            word-wrap: break-word;
        }
        
        .message-ai {
            text-align: left;
        }
        
        .message-ai .message-bubble {
            background: #f1f3f4;
            color: #333;
            padding: 0.75rem 1rem;
            border-radius: 18px 18px 18px 4px;
            display: inline-block;
            max-width: 80%;
            word-wrap: break-word;
            border-left: 4px solid var(--bait-blue);
        }
        
        .message-meta {
            font-size: 0.75rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .chat-input {
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            padding: 1rem;
        }
        
        .input-group {
            position: relative;
        }
        
        .form-control {
            border-radius: 25px;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--bait-blue);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }
        
        .btn-send {
            background: var(--bait-gradient);
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .btn-send:hover {
            transform: translateY(-50%) scale(1.05);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .context-selector {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .file-stats {
            background: var(--bait-gradient-light);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 0.5rem;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--bait-blue);
        }
        
        .api-config {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .api-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .typing-indicator {
            display: none;
            text-align: left;
            margin-bottom: 1rem;
        }
        
        .typing-dots {
            background: #f1f3f4;
            padding: 0.75rem 1rem;
            border-radius: 18px;
            display: inline-block;
            border-left: 4px solid var(--bait-blue);
        }
        
        .typing-dots span {
            height: 8px;
            width: 8px;
            background: #666;
            border-radius: 50%;
            display: inline-block;
            margin: 0 2px;
            animation: typing 1.4s infinite ease-in-out;
        }
        
        .typing-dots span:nth-child(1) { animation-delay: -0.32s; }
        .typing-dots span:nth-child(2) { animation-delay: -0.16s; }
        
        @keyframes typing {
            0%, 80%, 100% { 
                transform: scale(0);
                opacity: 0.5; 
            }
            40% { 
                transform: scale(1);
                opacity: 1; 
            }
        }
        
        .card-modern {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
        }
        
        .btn-context {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            margin: 0.25rem;
            transition: all 0.3s ease;
        }
        
        .btn-context.active {
            background: var(--bait-gradient);
            color: white;
            border-color: transparent;
        }
        
        .btn-context:hover {
            border-color: var(--bait-blue);
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <div class="header-gradient">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-0">
                        <i class="bi bi-robot me-3"></i>
                        BAIT AI Chat
                    </h1>
                    <p class="mb-0 opacity-75">Intelligent File Analysis & Business Intelligence</p>
                </div>
                <div>
                    <a href="/controlli/laravel_bait/public/index_standalone.php" class="btn btn-light">
                        <i class="bi bi-arrow-left me-2"></i>
                        Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container-fluid py-4">
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Errore:</strong> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (!$apiKeyConfigured): ?>
        <!-- API Configuration Panel -->
        <div class="row justify-content-center mb-4">
            <div class="col-md-8">
                <div class="api-config">
                    <div class="text-center mb-3">
                        <i class="bi bi-key-fill" style="font-size: 2rem; color: var(--bait-blue);"></i>
                        <h4 class="mt-2">Configura OpenRouter API</h4>
                        <p class="mb-0">Inserisci la tua API key per attivare l'assistente AI</p>
                    </div>
                    
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="configure_api">
                        <div class="col-md-8">
                            <label for="api_key" class="form-label">
                                <strong>OpenRouter API Key</strong>
                            </label>
                            <input type="password" class="form-control" id="api_key" name="api_key" 
                                   placeholder="sk-or-v1-..." required>
                        </div>
                        <div class="col-md-4">
                            <label for="selected_model" class="form-label">
                                <strong>Modello AI</strong>
                            </label>
                            <select class="form-select" id="selected_model" name="selected_model">
                                <?php $currentModel = $_SESSION['selected_model'] ?? 'z-ai/glm-4.5-air:free'; ?>
                                <option value="z-ai/glm-4.5-air:free" <?= $currentModel === 'z-ai/glm-4.5-air:free' ? 'selected' : '' ?>>GLM-4.5-Air (Gratuito)</option>
                                <option value="google/gemini-flash-1.5" <?= $currentModel === 'google/gemini-flash-1.5' ? 'selected' : '' ?>>Gemini Flash 1.5</option>
                                <option value="anthropic/claude-3.5-sonnet" <?= $currentModel === 'anthropic/claude-3.5-sonnet' ? 'selected' : '' ?>>Claude 3.5 Sonnet</option>
                                <option value="openai/gpt-4o" <?= $currentModel === 'openai/gpt-4o' ? 'selected' : '' ?>>GPT-4o</option>
                                <option value="meta-llama/llama-3.1-70b-instruct" <?= $currentModel === 'meta-llama/llama-3.1-70b-instruct' ? 'selected' : '' ?>>Llama 3.1 70B</option>
                                <option value="mistralai/mistral-large" <?= $currentModel === 'mistralai/mistral-large' ? 'selected' : '' ?>>Mistral Large</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                Ottieni la tua API key gratuita su 
                                <a href="https://openrouter.ai" target="_blank">openrouter.ai</a>
                            </div>
                        </div>
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle me-2"></i>
                                Configura & Testa Connessione
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php else: ?>
        
        <?php if ($chatResponse && $chatResponse['type'] === 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show api-success">
            <i class="bi bi-check-circle me-2"></i>
            <strong>Successo!</strong> <?= htmlspecialchars($chatResponse['message']) ?>
            <br><small>Modello: <?= htmlspecialchars($chatResponse['model_info']['model']) ?> | Provider: <?= htmlspecialchars($chatResponse['model_info']['provider']) ?></small>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Chat Interface -->
            <div class="col-lg-8">
                <div class="chat-container bg-white">
                    <div class="chat-header">
                        <div>
                            <h5 class="mb-0">
                                <i class="bi bi-chat-dots me-2"></i>
                                Assistente AI BAIT
                            </h5>
                            <small class="opacity-75">Powered by <?= $_SESSION['selected_model'] ?? 'z-ai/glm-4.5-air:free' ?> via OpenRouter</small>
                        </div>
                        <div class="text-end">
                            <button class="btn btn-sm btn-outline-light" onclick="clearChat()">
                                <i class="bi bi-trash me-1"></i>Clear
                            </button>
                        </div>
                    </div>
                    
                    <div class="chat-messages" id="chatMessages">
                        <?php if (empty($chatHistory)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-chat-quote" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">Benvenuto nell'assistente AI BAIT!</h5>
                            <p>Inizia una conversazione per analizzare file, interrogare il database o ottenere insights sul progetto.</p>
                            
                            <div class="row mt-4">
                                <div class="col-md-4 mb-3">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body text-center py-3">
                                            <i class="bi bi-filetype-csv text-primary mb-2" style="font-size: 1.5rem;"></i>
                                            <h6>Analisi CSV</h6>
                                            <small>Specializzato in file CSV: timbrature, TeamViewer, attività, auto</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body text-center py-3">
                                            <i class="bi bi-database text-success mb-2" style="font-size: 1.5rem;"></i>
                                            <h6>Query Database</h6>
                                            <small>Interroga i dati con linguaggio naturale</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body text-center py-3">
                                            <i class="bi bi-graph-up text-warning mb-2" style="font-size: 1.5rem;"></i>
                                            <h6>Business Intelligence</h6>
                                            <small>Insights e analisi strategiche</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                            <?php foreach (array_reverse($chatHistory) as $chat): ?>
                            <div class="message message-user">
                                <div class="message-bubble">
                                    <?= nl2br(htmlspecialchars($chat['user_message'])) ?>
                                </div>
                                <div class="message-meta">
                                    <i class="bi bi-person-circle me-1"></i>
                                    Tu • <?= date('H:i', strtotime($chat['created_at'])) ?>
                                </div>
                            </div>
                            
                            <div class="message message-ai">
                                <div class="message-bubble">
                                    <?= nl2br(htmlspecialchars($chat['ai_response'])) ?>
                                </div>
                                <div class="message-meta">
                                    <i class="bi bi-robot me-1"></i>
                                    AI • <?= $chat['response_time_ms'] ?>ms
                                    <?php if ($chat['tokens_used']): ?>
                                    • <?= $chat['tokens_used'] ?> tokens
                                    <?php endif; ?>
                                    • <?= ucfirst($chat['context_type']) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if ($chatResponse && $chatResponse['type'] === 'chat'): ?>
                        <div class="message message-user">
                            <div class="message-bubble">
                                <?= nl2br(htmlspecialchars($chatResponse['user_message'])) ?>
                            </div>
                            <div class="message-meta">
                                <i class="bi bi-person-circle me-1"></i>
                                Tu • Adesso
                            </div>
                        </div>
                        
                        <div class="message message-ai">
                            <div class="message-bubble">
                                <?= nl2br(htmlspecialchars($chatResponse['ai_response'])) ?>
                            </div>
                            <div class="message-meta">
                                <i class="bi bi-robot me-1"></i>
                                <?= $chatResponse['model'] ?> • <?= $chatResponse['response_time'] ?>ms • <?= $chatResponse['tokens_used'] ?> tokens
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="typing-indicator" id="typingIndicator">
                            <div class="typing-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chat-input">
                        <form method="post" id="chatForm">
                            <input type="hidden" name="action" value="send_message">
                            <input type="hidden" name="context_type" id="contextType" value="general">
                            <input type="hidden" name="context_id" id="contextId" value="">
                            
                            <div class="input-group">
                                <input type="text" class="form-control" name="message" id="messageInput" 
                                       placeholder="Scrivi il tuo messaggio..." required>
                                <button type="submit" class="btn-send text-white">
                                    <i class="bi bi-send-fill"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Context & Controls -->
            <div class="col-lg-4">
                <!-- Context Selector -->
                <div class="context-selector">
                    <h6 class="mb-3">
                        <i class="bi bi-gear me-2"></i>
                        Modalità Conversazione
                    </h6>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-context active" data-context="general" onclick="setContext('general')">
                            <i class="bi bi-chat-text me-2"></i>
                            Conversazione Generale
                        </button>
                        <button class="btn btn-context" data-context="database_query" onclick="setContext('database_query')">
                            <i class="bi bi-database me-2"></i>
                            Query Database
                        </button>
                        <button class="btn btn-context" data-context="file_analysis" onclick="setContext('file_analysis')">
                            <i class="bi bi-filetype-csv me-2"></i>
                            Analisi File CSV
                        </button>
                    </div>
                    
                    <div id="fileSelector" style="display: none;" class="mt-3">
                        <div class="alert alert-info py-2 mb-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Focus CSV:</strong> I file CSV di dati sono prioritari per analisi di timbrature, TeamViewer, attività e utilizzi auto.
                        </div>
                        <label class="form-label">Seleziona File:</label>
                        <select class="form-select" id="fileSelect">
                            <option value="">Seleziona un file...</option>
                        </select>
                    </div>
                </div>
                
                <!-- File Statistics -->
                <?php if ($fileStats['totals']): ?>
                <div class="file-stats">
                    <h6 class="mb-3">
                        <i class="bi bi-bar-chart me-2"></i>
                        Statistiche File Indicizzati
                    </h6>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="stat-item">
                                <div class="stat-number"><?= $fileStats['totals']['total_files'] ?></div>
                                <div class="text-muted small">File Totali</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-item">
                                <div class="stat-number"><?= round($fileStats['totals']['avg_complexity']) ?></div>
                                <div class="text-muted small">Complessità Media</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="bi bi-clock me-1"></i>
                            Ultimo aggiornamento: <?= date('d/m/Y H:i', strtotime($fileStats['totals']['latest_update'])) ?>
                        </small>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Quick Actions -->
                <div class="card card-modern">
                    <div class="card-header bg-primary text-white">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-lightning me-2"></i>
                            Azioni Rapide
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="quickQuery('Mostra gli alert critici degli ultimi 7 giorni')">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Alert Critici
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="quickQuery('Analizza le performance dei tecnici questo mese')">
                                <i class="bi bi-person-gear me-1"></i>
                                Performance Tecnici
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="quickQuery('Analizza il file CSV delle timbrature e trova anomalie')">
                                <i class="bi bi-clock-history me-1"></i>
                                Analisi CSV Timbrature
                            </button>
                            <button class="btn btn-outline-warning btn-sm" onclick="quickQuery('Analizza i file CSV di TeamViewer per trovare pattern di utilizzo')">
                                <i class="bi bi-display me-1"></i>
                                Analisi CSV TeamViewer
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="quickQuery('Confronta i dati tra diversi file CSV per trovare incongruenze')">
                                <i class="bi bi-list-check me-1"></i>
                                Cross-Check CSV
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- System Info -->
                <div class="card card-modern mt-3">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            Informazioni Sistema
                        </h6>
                    </div>
                    <div class="card-body">
                        <small>
                            <div class="mb-2">
                                <strong>Modello:</strong> <?= $_SESSION['selected_model'] ?? 'z-ai/glm-4.5-air:free' ?><br>
                                <strong>Provider:</strong> OpenRouter<br>
                                <strong>Costo:</strong> Gratuito
                            </div>
                            <div class="text-muted">
                                <i class="bi bi-shield-check me-1"></i>
                                API Key sicura • Sessione crittografata
                            </div>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Context management
        let currentContext = 'general';
        
        function setContext(context) {
            currentContext = context;
            document.getElementById('contextType').value = context;
            
            // Update UI
            document.querySelectorAll('.btn-context').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-context="${context}"]`).classList.add('active');
            
            // Show/hide file selector
            const fileSelector = document.getElementById('fileSelector');
            if (context === 'file_analysis') {
                fileSelector.style.display = 'block';
                loadFileList();
            } else {
                fileSelector.style.display = 'none';
            }
        }
        
        // Load file list for selection
        function loadFileList() {
            const fileSelect = document.getElementById('fileSelect');
            fileSelect.innerHTML = '<option value="">Caricando file...</option>';
            
            // AJAX call to get real files from database
            fetch('bait_ai_api.php?action=get_file_list&limit=100')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let options = '<option value="">Seleziona un file...</option>';
                        
                        // Separate CSV files and other important files
                        const csvFiles = data.data.files.filter(file => file.file_type === 'csv');
                        const otherImportantFiles = data.data.files.filter(file => 
                            file.file_type !== 'csv' && (
                                file.file_name.includes('upload') ||
                                file.file_name.includes('audit') ||
                                file.file_name.includes('bait') ||
                                file.file_name.includes('timbrature') ||
                                file.file_name.includes('teamviewer') ||
                                file.complexity_score > 50
                            )
                        );
                        
                        // Sort CSV files by importance
                        csvFiles.sort((a, b) => {
                            const importanceA = a.file_name.includes('timbrature') ? 4 :
                                              a.file_name.includes('teamviewer') ? 3 :
                                              a.file_name.includes('attivita') ? 2 :
                                              a.file_name.includes('auto') ? 1 : 0;
                            const importanceB = b.file_name.includes('timbrature') ? 4 :
                                              b.file_name.includes('teamviewer') ? 3 :
                                              b.file_name.includes('attivita') ? 2 :
                                              b.file_name.includes('auto') ? 1 : 0;
                            return importanceB - importanceA;
                        });
                        
                        // Add CSV files first with category
                        if (csvFiles.length > 0) {
                            options += '<optgroup label="📊 File Dati CSV (Raccomandati)">';
                            csvFiles.forEach(file => {
                                const fileDesc = file.file_name.replace('.csv', '');
                                const filePathInfo = file.file_path.includes('upload_csv') ? ' [Upload]' : 
                                                   file.file_path.includes('data') ? ' [Data]' : '';
                                const importance = file.file_name.includes('timbrature') ? ' ⭐ Critico' :
                                                 file.file_name.includes('teamviewer') ? ' 🔧 Importante' :
                                                 file.file_name.includes('attivita') ? ' 📋 Attività' : '';
                                options += `<option value="${file.id}">${fileDesc}${filePathInfo}${importance}</option>`;
                            });
                            options += '</optgroup>';
                        }
                        
                        // Add other important files
                        if (otherImportantFiles.length > 0) {
                            otherImportantFiles.sort((a, b) => b.complexity_score - a.complexity_score);
                            options += '<optgroup label="🔧 Altri File Sistema">';
                            otherImportantFiles.slice(0, 10).forEach(file => { // Limit to 10 files
                                const fileDesc = `${file.file_name} (${file.file_type.toUpperCase()})`;
                                options += `<option value="${file.id}">${fileDesc}</option>`;
                            });
                            options += '</optgroup>';
                        }
                        
                        fileSelect.innerHTML = options;
                    } else {
                        fileSelect.innerHTML = '<option value="">Errore caricamento file</option>';
                        console.error('Error loading files:', data.error);
                    }
                })
                .catch(error => {
                    fileSelect.innerHTML = '<option value="">Errore connessione</option>';
                    console.error('Network error:', error);
                });
        }
        
        // Handle form submission via AJAX
        let isSubmitting = false;
        
        function handleFormSubmit() {
            if (isSubmitting) return;
            
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (!message) return;
            
            isSubmitting = true;
            
            // Get context data
            const contextType = currentContext;
            const contextId = currentContext === 'file_analysis' ? 
                             document.getElementById('fileSelect').value : '';
            
            // Add user message to chat immediately
            addMessageToChat(message, 'user');
            
            // Clear input and show typing
            messageInput.value = '';
            showTypingIndicator();
            
            // AJAX call to send message
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('message', message);
            formData.append('context_type', contextType);
            formData.append('context_id', contextId);
            
            fetch('bait_ai_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideTypingIndicator();
                
                if (data.success) {
                    addMessageToChat(
                        data.data.ai_response, 
                        'ai', 
                        {
                            model: data.data.model,
                            responseTime: data.data.response_time,
                            tokensUsed: data.data.tokens_used
                        }
                    );
                } else {
                    addMessageToChat(
                        'Errore: ' + data.error, 
                        'error'
                    );
                }
            })
            .catch(error => {
                hideTypingIndicator();
                addMessageToChat(
                    'Errore di connessione: ' + error.message, 
                    'error'
                );
                console.error('Chat error:', error);
            })
            .finally(() => {
                isSubmitting = false;
                messageInput.focus();
            });
        }
        
        document.getElementById('chatForm').addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmit();
        });
        
        // Quick query function
        function quickQuery(query) {
            document.getElementById('messageInput').value = query;
            if (query.includes('alert')) {
                setContext('database_query');
            }
            handleFormSubmit();
        }
        
        // Show typing indicator
        function showTypingIndicator() {
            document.getElementById('typingIndicator').style.display = 'block';
            scrollToBottom();
        }
        
        // Hide typing indicator
        function hideTypingIndicator() {
            document.getElementById('typingIndicator').style.display = 'none';
        }
        
        // Add message to chat dynamically
        function addMessageToChat(message, type, meta = {}) {
            const messagesDiv = document.getElementById('chatMessages');
            const messageElement = document.createElement('div');
            messageElement.className = `message message-${type}`;
            
            let messageHTML = '';
            
            if (type === 'user') {
                messageHTML = `
                    <div class="message-bubble">
                        ${escapeHtml(message).replace(/\n/g, '<br>')}
                    </div>
                    <div class="message-meta">
                        <i class="bi bi-person-circle me-1"></i>
                        Tu • Adesso
                    </div>
                `;
            } else if (type === 'ai') {
                messageHTML = `
                    <div class="message-bubble">
                        ${escapeHtml(message).replace(/\n/g, '<br>')}
                    </div>
                    <div class="message-meta">
                        <i class="bi bi-robot me-1"></i>
                        ${meta.model || 'AI'} • ${meta.responseTime || 0}ms • ${meta.tokensUsed || 0} tokens
                    </div>
                `;
            } else if (type === 'error') {
                messageElement.className = 'message message-ai';
                messageHTML = `
                    <div class="message-bubble" style="border-left-color: #dc3545; background: #f8d7da; color: #721c24;">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        ${escapeHtml(message)}
                    </div>
                    <div class="message-meta">
                        <i class="bi bi-exclamation-circle me-1" style="color: #dc3545;"></i>
                        Sistema • Errore
                    </div>
                `;
            }
            
            messageElement.innerHTML = messageHTML;
            
            // Insert before typing indicator
            const typingIndicator = document.getElementById('typingIndicator');
            messagesDiv.insertBefore(messageElement, typingIndicator);
            
            scrollToBottom();
        }
        
        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Scroll to bottom of chat
        function scrollToBottom() {
            const messages = document.getElementById('chatMessages');
            messages.scrollTop = messages.scrollHeight;
        }
        
        // Clear chat
        function clearChat() {
            if (confirm('Vuoi cancellare la cronologia della chat?')) {
                // This would make an AJAX call to clear chat history
                location.reload();
            }
        }
        
        // Auto-scroll on page load
        document.addEventListener('DOMContentLoaded', function() {
            scrollToBottom();
            
            // Auto-focus message input
            document.getElementById('messageInput').focus();
        });
        
        // Handle Enter key
        document.getElementById('messageInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                handleFormSubmit();
            }
        });
    </script>
</body>
</html>