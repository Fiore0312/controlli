<?php
/**
 * CORRECTION TRACKER - Sistema Tracking Correzioni e Follow-up
 * Gestione automatica richieste correzione, follow-up e escalation
 */

class CorrectionTracker {
    
    private $pdo;
    private $config;
    private $notificationRules;
    private $escalationLevels;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        
        $this->config = [
            'default_deadline_days' => 3,
            'reminder_intervals' => [1, 2, 5], // giorni
            'max_reminders' => 3,
            'auto_escalation_enabled' => true,
            'escalation_threshold_days' => 7
        ];
        
        $this->notificationRules = [
            'low' => ['deadline_days' => 5, 'reminders' => 2],
            'medium' => ['deadline_days' => 3, 'reminders' => 3],
            'high' => ['deadline_days' => 2, 'reminders' => 4],
            'urgent' => ['deadline_days' => 1, 'reminders' => 5]
        ];
        
        $this->escalationLevels = [
            'none' => ['days' => 0, 'notification_target' => null],
            'supervisor' => ['days' => 3, 'notification_target' => 'supervisor'],
            'manager' => ['days' => 7, 'notification_target' => 'manager'],
            'director' => ['days' => 14, 'notification_target' => 'director']
        ];
    }
    
    /**
     * Crea richiesta di correzione basata su alert audit
     */
    public function createCorrectionRequest($dailyAnalysisId, $tecnicoId, $alerts, $options = []) {
        try {
            $this->log("📝 Creazione richiesta correzione per tecnico ID: $tecnicoId");
            
            // 1. Analizza alert per determinare priorità e tipo
            $analysisResult = $this->analyzeAlertsForCorrection($alerts);
            
            // 2. Raggruppa alert per tipologia
            $groupedIssues = $this->groupIssuesByType($alerts);
            
            // 3. Determina priorità complessiva
            $priority = $this->determinePriority($analysisResult);
            
            // 4. Genera messaggio personalizzato
            $message = $this->generateCorrectionMessage($groupedIssues, $tecnicoId);
            
            // 5. Calcola deadline
            $deadline = $this->calculateDeadline($priority);
            
            // 6. Crea record richiesta correzione
            $requestId = $this->insertCorrectionRequest([
                'daily_analysis_id' => $dailyAnalysisId,
                'tecnico_id' => $tecnicoId,
                'request_date' => date('Y-m-d'),
                'correction_type' => $analysisResult['primary_type'],
                'priority' => $priority,
                'subject' => $this->generateSubject($analysisResult, $tecnicoId),
                'message' => $message,
                'specific_issues' => json_encode($groupedIssues),
                'deadline_date' => $deadline,
                'communication_method' => $options['method'] ?? 'email'
            ]);
            
            // 7. Inizializza tracking
            $this->initializeCorrectionTracking($requestId);
            
            // 8. Invia notifica iniziale
            if ($options['send_immediately'] ?? true) {
                $this->sendCorrectionNotification($requestId);
            }
            
            $this->log("✅ Richiesta correzione creata: ID $requestId");
            
            return [
                'success' => true,
                'request_id' => $requestId,
                'priority' => $priority,
                'deadline' => $deadline,
                'issues_count' => count($alerts),
                'message_preview' => substr($message, 0, 200) . '...'
            ];
            
        } catch (Exception $e) {
            $this->log("❌ Errore creazione richiesta correzione: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Processa risposta a richiesta correzione
     */
    public function processCorrectionResponse($requestId, $responseData) {
        try {
            $this->log("📨 Processamento risposta per richiesta ID: $requestId");
            
            // 1. Valida risposta
            $validationResult = $this->validateResponse($responseData);
            
            if (!$validationResult['is_valid']) {
                throw new Exception("Risposta non valida: " . $validationResult['error']);
            }
            
            // 2. Inserisci risposta nel database
            $responseId = $this->insertCorrectionResponse([
                'correction_request_id' => $requestId,
                'response_date' => date('Y-m-d H:i:s'),
                'response_type' => $responseData['type'],
                'response_message' => $responseData['message'],
                'corrected_data' => json_encode($responseData['corrections'] ?? []),
                'attachments' => json_encode($responseData['attachments'] ?? [])
            ]);
            
            // 3. Analizza qualità risposta
            $qualityScore = $this->analyzeResponseQuality($responseData);
            
            // 4. Determina se accettare o richiedere follow-up
            $acceptanceResult = $this->evaluateResponseAcceptance($responseData, $qualityScore);
            
            // 5. Aggiorna stato tracking
            $this->updateCorrectionTracking($requestId, [
                'current_status' => $acceptanceResult['new_status'],
                'tracking_notes' => $acceptanceResult['notes'],
                'next_action' => $acceptanceResult['next_action'],
                'next_action_date' => $acceptanceResult['next_action_date']
            ]);
            
            // 6. Aggiorna risposta con esito valutazione
            $this->updateCorrectionResponse($responseId, [
                'is_accepted' => $acceptanceResult['accepted'],
                'reviewer_notes' => $acceptanceResult['review_notes'],
                'requires_follow_up' => $acceptanceResult['requires_follow_up']
            ]);
            
            // 7. Azioni automatiche post-risposta
            if ($acceptanceResult['accepted']) {
                $this->completeCorrectionProcess($requestId);
            } else {
                $this->initiateFollowUp($requestId, $acceptanceResult);
            }
            
            $this->log("✅ Risposta processata con successo");
            
            return [
                'success' => true,
                'response_id' => $responseId,
                'accepted' => $acceptanceResult['accepted'],
                'quality_score' => $qualityScore,
                'next_action' => $acceptanceResult['next_action']
            ];
            
        } catch (Exception $e) {
            $this->log("❌ Errore processamento risposta: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Gestione automatica follow-up e reminder
     */
    public function processAutomaticFollowUp() {
        try {
            $this->log("🔄 Inizio processamento follow-up automatico");
            
            $results = [
                'reminders_sent' => 0,
                'escalations' => 0,
                'expired_requests' => 0,
                'processed_responses' => 0
            ];
            
            // 1. Gestione reminder automatici
            $pendingRequests = $this->getPendingCorrectionRequests();
            foreach ($pendingRequests as $request) {
                if ($this->shouldSendReminder($request)) {
                    $this->sendReminder($request['id']);
                    $results['reminders_sent']++;
                }
            }
            
            // 2. Gestione escalation automatiche
            $escalationCandidates = $this->getEscalationCandidates();
            foreach ($escalationCandidates as $request) {
                $this->processEscalation($request['id']);
                $results['escalations']++;
            }
            
            // 3. Gestione richieste scadute
            $expiredRequests = $this->getExpiredRequests();
            foreach ($expiredRequests as $request) {
                $this->processExpiredRequest($request['id']);
                $results['expired_requests']++;
            }
            
            // 4. Processamento risposte in attesa di review
            $pendingResponses = $this->getPendingResponses();
            foreach ($pendingResponses as $response) {
                $this->processDelayedResponse($response);
                $results['processed_responses']++;
            }
            
            $this->log("✅ Follow-up automatico completato");
            
            return [
                'success' => true,
                'results' => $results,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $this->log("❌ Errore follow-up automatico: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Analizza alert per correzione
     */
    private function analyzeAlertsForCorrection($alerts) {
        $analysis = [
            'total_alerts' => count($alerts),
            'severity_distribution' => [],
            'category_distribution' => [],
            'primary_type' => 'inconsistency',
            'complexity_score' => 0
        ];
        
        foreach ($alerts as $alert) {
            // Distribuzione severità
            $severity = $alert['severity'] ?? 'medium';
            $analysis['severity_distribution'][$severity] = ($analysis['severity_distribution'][$severity] ?? 0) + 1;
            
            // Distribuzione categoria
            $category = $alert['category'] ?? 'general';
            $analysis['category_distribution'][$category] = ($analysis['category_distribution'][$category] ?? 0) + 1;
            
            // Score complessità
            $analysis['complexity_score'] += $this->getAlertComplexityScore($alert);
        }
        
        // Determina tipo primario
        $analysis['primary_type'] = $this->determinePrimaryType($analysis['category_distribution']);
        
        return $analysis;
    }
    
    /**
     * Raggruppa problemi per tipologia
     */
    private function groupIssuesByType($alerts) {
        $grouped = [
            'timeline_gaps' => [],
            'data_inconsistencies' => [],
            'missing_activities' => [],
            'logic_errors' => [],
            'other' => []
        ];
        
        foreach ($alerts as $alert) {
            $category = $alert['category'] ?? 'other';
            
            switch ($category) {
                case 'timeline_gap':
                    $grouped['timeline_gaps'][] = $this->formatIssueForCorrection($alert);
                    break;
                case 'inconsistency':
                    $grouped['data_inconsistencies'][] = $this->formatIssueForCorrection($alert);
                    break;
                case 'missing_data':
                    $grouped['missing_activities'][] = $this->formatIssueForCorrection($alert);
                    break;
                case 'logic_error':
                    $grouped['logic_errors'][] = $this->formatIssueForCorrection($alert);
                    break;
                default:
                    $grouped['other'][] = $this->formatIssueForCorrection($alert);
            }
        }
        
        return array_filter($grouped); // Rimuove gruppi vuoti
    }
    
    /**
     * Genera messaggio di correzione personalizzato
     */
    private function generateCorrectionMessage($groupedIssues, $tecnicoId) {
        // Ottieni nome tecnico
        $stmt = $this->pdo->prepare("SELECT nome_completo FROM tecnici WHERE id = ?");
        $stmt->execute([$tecnicoId]);
        $technicianName = $stmt->fetchColumn();
        
        $message = "Gentile {$technicianName},\n\n";
        $message .= "Durante il controllo automatico delle attività sono state rilevate alcune incongruenze ";
        $message .= "che richiedono una verifica da parte tua.\n\n";
        
        foreach ($groupedIssues as $type => $issues) {
            if (empty($issues)) continue;
            
            $message .= $this->getSectionHeader($type) . "\n";
            
            foreach ($issues as $index => $issue) {
                $message .= ($index + 1) . ". " . $issue['description'] . "\n";
                if (!empty($issue['details'])) {
                    $message .= "   Dettagli: " . $issue['details'] . "\n";
                }
                if (!empty($issue['suggested_action'])) {
                    $message .= "   Azione suggerita: " . $issue['suggested_action'] . "\n";
                }
                $message .= "\n";
            }
            $message .= "\n";
        }
        
        $message .= "Ti preghiamo di verificare questi punti e fornire le correzioni necessarie ";
        $message .= "o spiegazioni per le incongruenze rilevate.\n\n";
        $message .= "Per qualsiasi dubbio, non esitare a contattarci.\n\n";
        $message .= "Grazie per la collaborazione!\n";
        $message .= "Sistema Audit BAIT Service";
        
        return $message;
    }
    
    /**
     * Invia notifica di correzione
     */
    private function sendCorrectionNotification($requestId) {
        // Ottieni dati richiesta
        $request = $this->getCorrectionRequestById($requestId);
        
        if (!$request) {
            throw new Exception("Richiesta correzione non trovata: $requestId");
        }
        
        // Ottieni contatti tecnico
        $stmt = $this->pdo->prepare("SELECT nome_completo, email FROM tecnici WHERE id = ?");
        $stmt->execute([$request['tecnico_id']]);
        $technician = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$technician) {
            throw new Exception("Tecnico non trovato: {$request['tecnico_id']}");
        }
        
        // Prepara notifica
        $notification = [
            'to' => $technician['email'],
            'subject' => $request['subject'],
            'message' => $request['message'],
            'priority' => $request['priority'],
            'deadline' => $request['deadline_date'],
            'method' => $request['communication_method']
        ];
        
        // Invia notifica (implementazione specifica del metodo)
        $sent = $this->sendNotification($notification);
        
        if ($sent) {
            // Aggiorna flag notifica inviata
            $stmt = $this->pdo->prepare("
                UPDATE correction_requests 
                SET notification_sent = 1 
                WHERE id = ?
            ");
            $stmt->execute([$requestId]);
            
            $this->log("📧 Notifica inviata per richiesta $requestId");
        }
        
        return $sent;
    }
    
    /**
     * Database operations
     */
    private function insertCorrectionRequest($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO correction_requests 
            (daily_analysis_id, tecnico_id, request_date, correction_type, priority,
             subject, message, specific_issues, deadline_date, communication_method)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['daily_analysis_id'],
            $data['tecnico_id'],
            $data['request_date'],
            $data['correction_type'],
            $data['priority'],
            $data['subject'],
            $data['message'],
            $data['specific_issues'],
            $data['deadline_date'],
            $data['communication_method']
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    private function insertCorrectionResponse($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO correction_responses 
            (correction_request_id, response_date, response_type, response_message,
             corrected_data, attachments)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['correction_request_id'],
            $data['response_date'],
            $data['response_type'],
            $data['response_message'],
            $data['corrected_data'],
            $data['attachments']
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    private function initializeCorrectionTracking($requestId) {
        $stmt = $this->pdo->prepare("
            INSERT INTO correction_tracking 
            (correction_request_id, current_status, status_date, days_since_request)
            VALUES (?, 'sent', NOW(), 0)
        ");
        $stmt->execute([$requestId]);
    }
    
    private function updateCorrectionTracking($requestId, $data) {
        $stmt = $this->pdo->prepare("
            UPDATE correction_tracking 
            SET current_status = ?, tracking_notes = ?, next_action = ?, 
                next_action_date = ?, updated_at = NOW()
            WHERE correction_request_id = ?
        ");
        
        $stmt->execute([
            $data['current_status'],
            $data['tracking_notes'],
            $data['next_action'],
            $data['next_action_date'],
            $requestId
        ]);
    }
    
    /**
     * Utility functions
     */
    private function determinePriority($analysis) {
        $criticalCount = $analysis['severity_distribution']['critical'] ?? 0;
        $highCount = $analysis['severity_distribution']['high'] ?? 0;
        
        if ($criticalCount > 0) return 'urgent';
        if ($highCount > 2) return 'high';
        if ($analysis['total_alerts'] > 5) return 'medium';
        return 'low';
    }
    
    private function calculateDeadline($priority) {
        $days = $this->notificationRules[$priority]['deadline_days'];
        return date('Y-m-d', strtotime("+{$days} days"));
    }
    
    private function generateSubject($analysis, $tecnicoId) {
        $stmt = $this->pdo->prepare("SELECT nome_completo FROM tecnici WHERE id = ?");
        $stmt->execute([$tecnicoId]);
        $name = $stmt->fetchColumn();
        
        $count = $analysis['total_alerts'];
        $type = ucfirst(str_replace('_', ' ', $analysis['primary_type']));
        
        return "Richiesta Correzione Attività - {$name} ({$count} punti da verificare)";
    }
    
    private function getSectionHeader($type) {
        $headers = [
            'timeline_gaps' => "🕐 GAP TEMPORALI:",
            'data_inconsistencies' => "⚠️ INCONGRUENZE DATI:",
            'missing_activities' => "❓ ATTIVITÀ MANCANTI:",
            'logic_errors' => "🔍 ERRORI LOGICI:",
            'other' => "📋 ALTRI PROBLEMI:"
        ];
        
        return $headers[$type] ?? "📋 PROBLEMI RILEVATI:";
    }
    
    private function formatIssueForCorrection($alert) {
        return [
            'description' => $alert['message'],
            'details' => $this->extractAlertDetails($alert),
            'suggested_action' => $alert['recommended_action'] ?? 'Verificare e correggere',
            'severity' => $alert['severity']
        ];
    }
    
    private function extractAlertDetails($alert) {
        $evidence = json_decode($alert['evidence'] ?? '{}', true);
        
        if (empty($evidence)) return '';
        
        $details = [];
        if (isset($evidence['time'])) $details[] = "Orario: {$evidence['time']}";
        if (isset($evidence['client'])) $details[] = "Cliente: {$evidence['client']}";
        if (isset($evidence['difference_minutes'])) $details[] = "Differenza: {$evidence['difference_minutes']} min";
        
        return implode(', ', $details);
    }
    
    private function validateResponse($responseData) {
        if (empty($responseData['type'])) {
            return ['is_valid' => false, 'error' => 'Tipo risposta mancante'];
        }
        
        if (empty($responseData['message'])) {
            return ['is_valid' => false, 'error' => 'Messaggio risposta mancante'];
        }
        
        return ['is_valid' => true];
    }
    
    private function analyzeResponseQuality($responseData) {
        $score = 50; // Base score
        
        // Fattori che aumentano qualità
        if (strlen($responseData['message']) > 100) $score += 10;
        if (!empty($responseData['corrections'])) $score += 20;
        if (!empty($responseData['attachments'])) $score += 10;
        if ($responseData['type'] === 'correction') $score += 20;
        
        return min(100, $score);
    }
    
    private function evaluateResponseAcceptance($responseData, $qualityScore) {
        $accepted = $qualityScore >= 70;
        
        return [
            'accepted' => $accepted,
            'new_status' => $accepted ? 'corrected' : 'in_progress',
            'notes' => $accepted ? 'Risposta accettata automaticamente' : 'Richiede follow-up',
            'review_notes' => "Score qualità: {$qualityScore}/100",
            'requires_follow_up' => !$accepted,
            'next_action' => $accepted ? null : 'follow_up_required',
            'next_action_date' => $accepted ? null : date('Y-m-d', strtotime('+2 days'))
        ];
    }
    
    private function log($message) {
        error_log("[CorrectionTracker] " . $message);
        echo $message . "\n";
    }
    
    // Placeholder implementations for complex operations
    private function getAlertComplexityScore($alert) { return 10; }
    private function determinePrimaryType($distribution) { return array_keys($distribution)[0] ?? 'inconsistency'; }
    private function getCorrectionRequestById($id) { return []; }
    private function sendNotification($notification) { return true; }
    private function getPendingCorrectionRequests() { return []; }
    private function shouldSendReminder($request) { return false; }
    private function sendReminder($requestId) { return true; }
    private function getEscalationCandidates() { return []; }
    private function processEscalation($requestId) { return true; }
    private function getExpiredRequests() { return []; }
    private function processExpiredRequest($requestId) { return true; }
    private function getPendingResponses() { return []; }
    private function processDelayedResponse($response) { return true; }
    private function completeCorrectionProcess($requestId) { return true; }
    private function initiateFollowUp($requestId, $result) { return true; }
    private function updateCorrectionResponse($responseId, $data) { return true; }
}

/**
 * ESEMPIO DI UTILIZZO
 */
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $config = [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'bait_service_real',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4'
    ];
    
    try {
        $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}", 
                       $config['username'], $config['password'], [
                           PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                           PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                       ]);
        
        $tracker = new CorrectionTracker($pdo);
        
        // Test creazione richiesta correzione
        $sampleAlerts = [
            [
                'severity' => 'high',
                'category' => 'timeline_gap',
                'message' => 'Gap temporale di 90 minuti rilevato',
                'evidence' => json_encode(['gap_minutes' => 90]),
                'recommended_action' => 'Verificare attività mancanti'
            ]
        ];
        
        $result = $tracker->createCorrectionRequest(1, 1, $sampleAlerts);
        
        echo "=== RISULTATO CORRECTION TRACKER ===\n";
        print_r($result);
        
    } catch (Exception $e) {
        echo "Errore: " . $e->getMessage() . "\n";
    }
}
?>