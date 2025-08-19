<?php
/**
 * TECHNICIAN ANALYZER - VERSIONE CLEAN FIXED
 * Sistema di audit quotidiano per controllo attività individuale
 */

require_once 'CrossValidator.php';
require_once 'TimelineBuilder.php';
require_once 'AnomalyDetector.php';
require_once 'CorrectionTracker.php';

class TechnicianAnalyzer {
    
    private $pdo;
    private $config;
    private $crossValidator;
    private $timelineBuilder;
    private $anomalyDetector;
    private $correctionTracker;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->config = [
            'working_hours' => [
                'morning_start' => '09:00:00',
                'morning_end' => '13:00:00',
                'afternoon_start' => '14:00:00',
                'afternoon_end' => '18:00:00'
            ],
            'gap_threshold_minutes' => 30,
            'teamviewer_min_minutes' => 15,
            'enable_ai_analysis' => true
        ];
        
        // Inizializza componenti integrati
        $this->crossValidator = new CrossValidator($pdo);
        $this->timelineBuilder = new TimelineBuilder($pdo);
        $this->anomalyDetector = new AnomalyDetector($pdo);
        $this->correctionTracker = new CorrectionTracker($pdo);
        
        $this->log("✅ TechnicianAnalyzer inizializzato");
    }
    
    /**
     * Analizza completamente un tecnico per una data specifica
     */
    public function analyzeTechnicianDay($tecnicoId, $date) {
        try {
            $this->log("🔍 Inizio analisi tecnico ID: $tecnicoId per data: $date");
            
            // 1. Ottieni sessione audit corrente
            $auditSessionId = $this->getCurrentAuditSession();
            
            // 2. Crea o aggiorna analisi giornaliera
            $analysisId = $this->createDailyAnalysis($auditSessionId, $tecnicoId, $date);
            
            // 3. Raccolta dati da tutte le fonti
            $data = $this->collectAllData($tecnicoId, $date);
            
            // 4. Costruzione timeline intelligente
            $timelineResult = $this->buildAdvancedTimeline($analysisId, $data);
            
            // 5. Cross-validation multi-fonte
            $crossValidationResult = $this->performAdvancedCrossValidation($analysisId, $data);
            
            // 6. Rilevamento anomalie AI
            $anomalyResult = $this->performAIAnomalyDetection($tecnicoId, $date, $data);
            
            // 7. Generazione alert consolidati
            $alerts = $this->generateConsolidatedAlerts($analysisId, $data, $timelineResult, $crossValidationResult, $anomalyResult);
            
            // 8. Calcolo score qualità
            $qualityScore = $this->calculateAdvancedQualityScore($analysisId, $timelineResult, $crossValidationResult, $anomalyResult);
            
            $this->log("✅ Analisi completata. Score qualità: $qualityScore");
            
            return [
                'success' => true,
                'analysis' => [
                    'analysis_id' => $analysisId,
                    'copertura_timeline_score' => $timelineResult['timeline_quality_score'] ?? 0,
                    'coerenza_cross_validation_score' => $crossValidationResult['overall_consistency_score'] ?? 0,
                    'efficienza_operativa_score' => $qualityScore
                ],
                'alerts' => $alerts,
                'timeline_events' => $timelineResult['total_events'] ?? 0,
                'anomaly_detection_results' => $anomalyResult
            ];
            
        } catch (Exception $e) {
            $this->log("❌ Errore analisi: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Inserimento alert con ID unico garantito - VERSIONE FIXED
     */
    private function insertAlert($dailyAnalysisId, $alert) {
        try {
            $this->log("📝 Inserimento alert: " . ($alert['title'] ?? 'Alert'));
            
            // Usa stored procedure ULTIMATE per ID matematicamente impossibili da duplicare
            $stmt = $this->pdo->prepare("CALL sp_insert_audit_alert_ultimate(?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $dailyAnalysisId,
                $alert['alert_type'] ?? $alert['type'] ?? 'unknown',
                $alert['title'] ?? 'Alert',
                $alert['message'] ?? 'Nessun messaggio',
                $alert['severity'] ?? 'INFO',
                $alert['category'] ?? $alert['type'] ?? 'general',
                json_encode($alert['evidence'] ?? [])
            ]);
            
            $this->log("✅ Alert inserito con stored procedure");
            return true;
            
        } catch (Exception $e) {
            $this->log("❌ Errore inserimento alert: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function getCurrentAuditSession() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM audit_sessions 
                WHERE mese_anno = ? AND stato = 'INIZIATA'
                ORDER BY created_at DESC LIMIT 1
            ");
            $currentMonth = date('Y-m');
            $stmt->execute([$currentMonth]);
            $sessionId = $stmt->fetchColumn();
            
            if ($sessionId) {
                return $sessionId;
            }
            
            // Crea nuova sessione
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_sessions (session_id, mese_anno, stato) 
                VALUES (?, ?, ?)
            ");
            $sessionId = 'AUD-' . date('Ymd-Hi');
            $stmt->execute([$sessionId, $currentMonth, 'INIZIATA']);
            
            return $this->pdo->lastInsertId();
            
        } catch (Exception $e) {
            $this->log("⚠️ Errore sessione audit: " . $e->getMessage());
            return 1; // ID fisso di emergenza
        }
    }
    
    private function createDailyAnalysis($auditSessionId, $tecnicoId, $date) {
        try {
            // Prima verifica se esiste già
            $stmt = $this->pdo->prepare("
                SELECT id FROM technician_daily_analysis 
                WHERE tecnico_id = ? AND data_analisi = ?
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$tecnicoId, $date]);
            $existingId = $stmt->fetchColumn();
            
            if ($existingId) {
                $this->log("✅ Analysis esistente trovata: ID $existingId");
                return $existingId;
            }
            
            // Se non esiste, crea nuovo
            $stmt = $this->pdo->prepare("
                INSERT INTO technician_daily_analysis 
                (audit_session_id, tecnico_id, data_analisi, quality_score, total_alerts)
                VALUES (?, ?, ?, 0, 0)
            ");
            $stmt->execute([$auditSessionId, $tecnicoId, $date]);
            $newId = $this->pdo->lastInsertId();
            
            if ($newId > 0) {
                $this->log("✅ Nuovo analysis creato: ID $newId");
                return $newId;
            }
            
            throw new Exception("lastInsertId() returned 0");
            
        } catch (Exception $e) {
            $this->log("❌ Errore createDailyAnalysis: " . $e->getMessage());
            
            // Fallback finale: cerca qualsiasi record esistente
            try {
                $stmt = $this->pdo->prepare("
                    SELECT id FROM technician_daily_analysis 
                    WHERE tecnico_id = ? AND data_analisi = ?
                    LIMIT 1
                ");
                $stmt->execute([$tecnicoId, $date]);
                $fallbackId = $stmt->fetchColumn();
                
                if ($fallbackId) {
                    $this->log("⚠️ Usando fallback ID: $fallbackId");
                    return $fallbackId;
                }
                
                throw new Exception("Nessun analysis ID disponibile");
                
            } catch (Exception $e2) {
                $this->log("❌ Fallback fallito: " . $e2->getMessage());
                throw new Exception("Impossibile creare/recuperare analysis ID");
            }
        }
    }
    
    private function collectAllData($tecnicoId, $date) {
        $data = [
            'deepser' => [],
            'calendario' => [],
            'auto' => [],
            'teamviewer' => [],
            'timbrature' => []
        ];
        
        // Ottieni nome tecnico
        $stmt = $this->pdo->prepare("SELECT nome_completo FROM tecnici WHERE id = ?");
        $stmt->execute([$tecnicoId]);
        $technicianName = $stmt->fetchColumn();
        
        if (!$technicianName) {
            throw new Exception("Tecnico non trovato: ID $tecnicoId");
        }
        
        $this->log("📊 Raccolta dati per: $technicianName");
        
        // Per sicurezza, simula dati vuoti se le tabelle non esistono
        try {
            $data['deepser'] = $this->getDeepserActivities($technicianName, $date);
        } catch (Exception $e) {
            $this->log("⚠️ Deepser non disponibile: " . $e->getMessage());
        }
        
        $this->log("📋 Deepser: " . count($data['deepser']) . " attività");
        $this->log("📅 Calendario: " . count($data['calendario']) . " appuntamenti");
        $this->log("🚗 Auto: " . count($data['auto']) . " utilizzi");
        $this->log("💻 TeamViewer: " . count($data['teamviewer']) . " sessioni");
        $this->log("⏰ Timbrature: " . count($data['timbrature']) . " eventi");
        
        return $data;
    }
    
    private function getDeepserActivities($technicianName, $date) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM deepser 
                WHERE tecnico = ? AND DATE(start_time) = ?
                ORDER BY start_time
            ");
            $stmt->execute([$technicianName, $date]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return []; // Tabella non esiste o errore
        }
    }
    
    private function buildAdvancedTimeline($analysisId, $data) {
        try {
            $this->log("🏗️ Inizio costruzione timeline intelligente per analisi ID: $analysisId");
            
            $result = $this->timelineBuilder->buildIntelligentTimeline($analysisId, $data);
            
            $this->log("✅ Timeline intelligente completata");
            $this->log("🏗️ Timeline intelligente: " . ($result['total_events'] ?? 0) . " eventi, qualità " . ($result['timeline_quality_score'] ?? 0) . "%");
            
            return $result;
            
        } catch (Exception $e) {
            $this->log("❌ Errore timeline: " . $e->getMessage());
            return ['total_events' => 0, 'timeline_quality_score' => 0];
        }
    }
    
    private function performAdvancedCrossValidation($analysisId, $data) {
        try {
            $this->log("🔍 Inizio cross-validation per analisi ID: $analysisId");
            
            $result = $this->crossValidator->performCrossValidation($analysisId, $data);
            
            $this->log("✅ Cross-validation completata");
            $this->log("🔍 Cross-validation: " . ($result['total_validations'] ?? 0) . " controlli, " . ($result['failed_validations'] ?? 0) . " falliti");
            
            return $result;
            
        } catch (Exception $e) {
            $this->log("❌ Errore cross-validation: " . $e->getMessage());
            return ['total_validations' => 0, 'failed_validations' => 0, 'overall_consistency_score' => 0];
        }
    }
    
    private function performAIAnomalyDetection($tecnicoId, $date, $data) {
        try {
            $this->log("🤖 Inizio rilevamento anomalie AI per tecnico ID: $tecnicoId");
            
            $result = $this->anomalyDetector->detectAnomaliesForTechnician($tecnicoId, $date, $data);
            
            $this->log("✅ Rilevamento anomalie AI completato");
            $this->log("🤖 AI Anomalie: " . ($result['anomalies_detected'] ?? 0) . " rilevate, risk score " . ($result['risk_score'] ?? 0));
            
            return $result;
            
        } catch (Exception $e) {
            $this->log("❌ Errore AI anomalies: " . $e->getMessage());
            return ['anomalies_detected' => 0, 'risk_score' => 0, 'anomalies' => []];
        }
    }
    
    private function generateConsolidatedAlerts($analysisId, $data, $timelineResult, $crossValidationResult, $anomalyResult) {
        $alerts = [];
        
        try {
            // Converti anomalie AI in alert
            if (!empty($anomalyResult['anomalies'])) {
                foreach ($anomalyResult['anomalies'] as $anomaly) {
                    $this->insertAlert($analysisId, [
                        'alert_type' => isset($anomaly['type']) ? $anomaly['type'] : 'unknown_anomaly',
                        'title' => isset($anomaly['description']) ? $anomaly['description'] : 'Anomalia rilevata',
                        'message' => "Anomalia rilevata: " . (isset($anomaly['subtype']) ? $anomaly['subtype'] : 'Dettagli non disponibili'),
                        'severity' => isset($anomaly['severity']) ? $anomaly['severity'] : 'INFO',
                        'category' => isset($anomaly['type']) ? $anomaly['type'] : 'unknown',
                        'evidence' => isset($anomaly['evidence']) ? $anomaly['evidence'] : []
                    ]);
                    
                    $alerts[] = $anomaly;
                }
            }
            
        } catch (Exception $e) {
            $this->log("❌ Errore generazione alert: " . $e->getMessage());
        }
        
        return $alerts;
    }
    
    private function calculateAdvancedQualityScore($analysisId, $timelineResult, $crossValidationResult, $anomalyResult) {
        $timelineScore = $timelineResult['timeline_quality_score'] ?? 0;
        $crossValidationScore = $crossValidationResult['overall_consistency_score'] ?? 100;
        $anomalyPenalty = min(50, ($anomalyResult['anomalies_detected'] ?? 0) * 5);
        
        $qualityScore = max(0, ($timelineScore + $crossValidationScore) / 2 - $anomalyPenalty);
        
        // Aggiorna score nel database
        try {
            $stmt = $this->pdo->prepare("
                UPDATE technician_daily_analysis 
                SET quality_score = ?, total_alerts = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $qualityScore, 
                $anomalyResult['anomalies_detected'] ?? 0,
                $analysisId
            ]);
        } catch (Exception $e) {
            $this->log("⚠️ Errore aggiornamento quality score: " . $e->getMessage());
        }
        
        return $qualityScore;
    }
    
    private function log($message) {
        error_log("[TechnicianAnalyzer-Clean] " . $message);
        echo $message . "\n";
    }
}

/**
 * ESEMPIO DI UTILIZZO
 */
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=bait_service_real;charset=utf8mb4", 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        echo "🧪 TEST TECHNICIAN ANALYZER CLEAN\n";
        echo "=================================\n";
        
        $analyzer = new TechnicianAnalyzer($pdo);
        
        // Test con Alex Ferrario per il 1° agosto 2025
        $result = $analyzer->analyzeTechnicianDay(4, '2025-08-01');
        
        echo "\n=== RISULTATO ANALISI ===\n";
        echo "✅ Successo: " . ($result['success'] ? 'SI' : 'NO') . "\n";
        
        if ($result['success']) {
            echo "📊 Score efficienza: " . ($result['analysis']['efficienza_operativa_score'] ?? 'N/A') . "\n";
            echo "🔍 Alert generati: " . count($result['alerts']) . "\n";
            echo "⏰ Eventi timeline: " . ($result['timeline_events'] ?? 0) . "\n";
        } else {
            echo "❌ Errore: " . ($result['error'] ?? 'Unknown') . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Errore: " . $e->getMessage() . "\n";
    }
}
?>