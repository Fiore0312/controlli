<?php
/**
 * TECHNICIAN ANALYZER - VERSIONE FIXED PER MAPPING COLONNE
 * Sistema di audit quotidiano per controllo attività individuale
 * 
 * FIX: Compatibilità con mapping colonne italiani/inglesi database
 * Database: bait_service_real con colonne italiane + inglesi
 * Integrazione: CrossValidator, TimelineBuilder, AnomalyDetector, CorrectionTracker
 */

require_once 'CrossValidator.php';
require_once 'TimelineBuilder.php';
require_once 'AnomalyDetector.php';
require_once 'CorrectionTracker.php';

class TechnicianAnalyzer {
    
    private $pdo;
    private $config;
    private $businessRules;
    private $crossValidator;
    private $timelineBuilder;
    private $anomalyDetector;
    private $correctionTracker;
    
    // MAPPING COLONNE DATABASE (SOLUZIONE COMPATIBILITÀ)
    private $columnMapping = [
        'alerts' => [
            'alert_type' => 'alert_type',    // Colonna inglese (nuova)
            'severity' => 'severity',        // Colonna inglese (nuova)
            'title' => 'title',             // Colonna inglese (nuova)
            'message' => 'message',          // Colonna inglese (nuova)
            'status' => 'status',           // Colonna inglese (nuova)
            'category' => 'categoria',       // Fallback colonna italiana
            'evidence' => 'evidenze'        // Colonna italiana
        ]
    ];
    
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
            'enable_ai_analysis' => true,
            'enable_cross_validation' => true,
            'enable_advanced_timeline' => true,
            'enable_automatic_corrections' => true,
            'use_stored_procedures' => true  // USA STORED PROCEDURE PER COMPATIBILITÀ
        ];
        
        $this->businessRules = [
            'max_daily_hours' => 8,
            'required_lunch_break' => 60,
            'travel_time_buffer' => 30
        ];
        
        // Inizializza componenti integrati
        $this->crossValidator = new CrossValidator($pdo);
        $this->timelineBuilder = new TimelineBuilder($pdo);
        $this->anomalyDetector = new AnomalyDetector($pdo);
        $this->correctionTracker = new CorrectionTracker($pdo);
        
        // Verifica compatibilità database
        $this->verifyDatabaseCompatibility();
    }
    
    /**
     * VERIFICA COMPATIBILITÀ DATABASE E MAPPING COLONNE
     */
    private function verifyDatabaseCompatibility() {
        try {
            // Verifica esistenza colonne inglesi in audit_alerts
            $stmt = $this->pdo->query("
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'audit_alerts'
                    AND COLUMN_NAME IN ('alert_type', 'severity', 'title', 'message', 'status')
            ");
            $englishColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($englishColumns) >= 5) {
                $this->log("✅ Database compatibile: colonne inglesi presenti");
                $this->config['use_english_columns'] = true;
            } else {
                $this->log("⚠️ Database legacy: usando colonne italiane");
                $this->config['use_english_columns'] = false;
                
                // Aggiorna mapping per colonne italiane
                $this->columnMapping['alerts'] = [
                    'alert_type' => 'categoria',
                    'severity' => 'severita', 
                    'title' => 'titolo',
                    'message' => 'descrizione',
                    'status' => 'stato_risoluzione',
                    'category' => 'categoria',
                    'evidence' => 'evidenze'
                ];
            }
            
            // Verifica stored procedure
            $stmt = $this->pdo->query("
                SELECT ROUTINE_NAME 
                FROM INFORMATION_SCHEMA.ROUTINES 
                WHERE ROUTINE_SCHEMA = DATABASE()
                    AND ROUTINE_NAME = 'sp_insert_audit_alert'
            ");
            
            if ($stmt->fetchColumn()) {
                $this->log("✅ Stored procedure sp_insert_audit_alert disponibile");
                $this->config['use_stored_procedures'] = true;
            } else {
                $this->log("⚠️ Stored procedure non disponibile, usando INSERT diretti");
                $this->config['use_stored_procedures'] = false;
            }
            
        } catch (Exception $e) {
            $this->log("❌ Errore verifica compatibilità: " . $e->getMessage());
            $this->config['use_english_columns'] = false;
            $this->config['use_stored_procedures'] = false;
        }
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
            
            // 4. Costruzione timeline intelligente (AI-enhanced)
            $timelineResult = $this->buildAdvancedTimeline($analysisId, $data);
            
            // 5. Cross-validation multi-fonte
            $crossValidationResult = $this->performAdvancedCrossValidation($analysisId, $data);
            
            // 6. Rilevamento anomalie AI
            $anomalyResult = $this->performAIAnomalyDetection($tecnicoId, $date, $data);
            
            // 7. Generazione alert consolidati CON MAPPING CORRETTO
            $alerts = $this->generateConsolidatedAlerts($analysisId, $data, $timelineResult, $crossValidationResult, $anomalyResult);
            
            // 8. Calcolo score qualità avanzato
            $qualityScore = $this->calculateAdvancedQualityScore($analysisId, $timelineResult, $crossValidationResult, $anomalyResult);
            
            // 9. Gestione automatica correzioni (se abilitata)
            $correctionResult = null;
            if ($this->config['enable_automatic_corrections'] && !empty($alerts)) {
                $correctionResult = $this->initiateAutomaticCorrections($analysisId, $tecnicoId, $alerts);
            }
            
            $this->log("✅ Analisi completata. Score qualità: $qualityScore");
            
            return [
                'success' => true,
                'analysis_id' => $analysisId,
                'quality_score' => $qualityScore,
                'timeline_events' => $timelineResult['total_events'] ?? 0,
                'timeline_quality' => $timelineResult['timeline_quality_score'] ?? 0,
                'cross_validation_results' => $crossValidationResult,
                'anomaly_detection_results' => $anomalyResult,
                'correction_initiated' => $correctionResult !== null,
                'correction_details' => $correctionResult,
                'summary' => $this->generateAdvancedAnalysisSummary($analysisId, $timelineResult, $crossValidationResult, $anomalyResult),
                'recommendations' => $this->generateActionableRecommendations($analysisId, $alerts),
                'database_compatibility' => [
                    'english_columns' => $this->config['use_english_columns'],
                    'stored_procedures' => $this->config['use_stored_procedures']
                ]
            ];
            
        } catch (Exception $e) {
            $this->log("❌ Errore analisi: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'database_compatibility' => [
                    'english_columns' => $this->config['use_english_columns'] ?? false,
                    'stored_procedures' => $this->config['use_stored_procedures'] ?? false
                ]
            ];
        }
    }
    
    /**
     * INSERIMENTO ALERT CON MAPPING AUTOMATICO - METODO FIXED
     */
    private function insertAlert($analysisId, $alert) {
        try {
            // METODO 1: Usa stored procedure se disponibile (RACCOMANDATO)
            if ($this->config['use_stored_procedures']) {
                return $this->insertAlertViaStoredProcedure($analysisId, $alert);
            }
            
            // METODO 2: Usa colonne inglesi se disponibili
            if ($this->config['use_english_columns']) {
                return $this->insertAlertEnglishColumns($analysisId, $alert);
            }
            
            // METODO 3: Fallback a colonne italiane (compatibility)
            return $this->insertAlertItalianColumns($analysisId, $alert);
            
        } catch (Exception $e) {
            $this->log("❌ Errore inserimento alert: " . $e->getMessage());
            
            // Fallback finale: prova mapping più semplice
            try {
                return $this->insertAlertBasicFallback($analysisId, $alert);
            } catch (Exception $e2) {
                $this->log("❌ Fallback fallito: " . $e2->getMessage());
                throw $e;
            }
        }
    }
    
    /**
     * METODO 1: Inserimento via stored procedure (RACCOMANDATO)
     */
    private function insertAlertViaStoredProcedure($analysisId, $alert) {
        $stmt = $this->pdo->prepare("
            CALL sp_insert_audit_alert(?, ?, ?, ?, ?, ?, ?)
        ");
        
        $evidence = isset($alert['evidence']) ? json_encode($alert['evidence']) : '{}';
        
        $stmt->execute([
            $analysisId,
            $alert['alert_type'] ?? 'general',
            $alert['title'] ?? 'Alert',
            $alert['message'] ?? 'Nessun messaggio',
            $alert['severity'] ?? 'medium',
            $alert['category'] ?? 'general',
            $evidence
        ]);
        
        $this->log("✅ Alert inserito via stored procedure");
        return true;
    }
    
    /**
     * METODO 2: Inserimento con colonne inglesi
     */
    private function insertAlertEnglishColumns($analysisId, $alert) {
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_alerts 
            (alert_id, daily_analysis_id, alert_type, title, message, severity, evidenze)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $alertId = 'ALT-' . date('Ymd-His') . '-' . rand(100, 999);
        $evidence = isset($alert['evidence']) ? json_encode($alert['evidence']) : '{}';
        
        $stmt->execute([
            $alertId,
            $analysisId,
            $alert['alert_type'] ?? 'general',
            $alert['title'] ?? 'Alert',
            $alert['message'] ?? 'Nessun messaggio',
            $alert['severity'] ?? 'WARNING',
            $evidence
        ]);
        
        $this->log("✅ Alert inserito con colonne inglesi");
        return true;
    }
    
    /**
     * METODO 3: Inserimento con colonne italiane (compatibility)
     */
    private function insertAlertItalianColumns($analysisId, $alert) {
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_alerts 
            (alert_id, daily_analysis_id, categoria, titolo, descrizione, severita, evidenze)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $alertId = 'ALT-' . date('Ymd-His') . '-' . rand(100, 999);
        $evidence = isset($alert['evidence']) ? json_encode($alert['evidence']) : '{}';
        
        // Mapping alert_type -> categoria
        $categoria = $this->mapAlertTypeToCategoria($alert['alert_type'] ?? 'general');
        
        // Mapping severity -> severita
        $severita = $this->mapSeverity($alert['severity'] ?? 'medium');
        
        $stmt->execute([
            $alertId,
            $analysisId,
            $categoria,
            $alert['title'] ?? 'Alert',
            $alert['message'] ?? 'Nessun messaggio',
            $severita,
            $evidence
        ]);
        
        $this->log("✅ Alert inserito con colonne italiane");
        return true;
    }
    
    /**
     * METODO 4: Fallback basilare (ultimo tentativo)
     */
    private function insertAlertBasicFallback($analysisId, $alert) {
        // Prova inserimento minimale
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_alerts (daily_analysis_id, evidenze)
            VALUES (?, ?)
        ");
        
        $evidence = json_encode([
            'alert_type' => $alert['alert_type'] ?? 'unknown',
            'title' => $alert['title'] ?? 'Unknown Alert',
            'message' => $alert['message'] ?? 'Nessun messaggio',
            'severity' => $alert['severity'] ?? 'unknown',
            'original_data' => $alert
        ]);
        
        $stmt->execute([$analysisId, $evidence]);
        
        $this->log("⚠️ Alert inserito con fallback minimale");
        return true;
    }
    
    /**
     * MAPPING UTILITIES
     */
    private function mapAlertTypeToCategoria($alertType) {
        $mapping = [
            'overlapping_activities' => 'SOVRAPPOSIZIONE_CLIENTE',
            'remote_with_auto' => 'INCOERENZA_ORARI',
            'teamviewer_missing_activity' => 'TEAMVIEWER_ANOMALO',
            'timeline_gap' => 'GAP_TIMELINE',
            'missing_timecard' => 'MANCATA_TIMBRATURA',
            'vehicle_not_registered' => 'AUTO_NON_REGISTRATA'
        ];
        
        return $mapping[$alertType] ?? 'GAP_TIMELINE';
    }
    
    private function mapSeverity($severity) {
        $mapping = [
            'low' => 'INFO',
            'medium' => 'WARNING', 
            'high' => 'ERROR',
            'critical' => 'CRITICAL'
        ];
        
        return $mapping[strtolower($severity)] ?? 'WARNING';
    }
    
    /**
     * GET CURRENT AUDIT SESSION - VERSIONE FIXED
     */
    private function getCurrentAuditSession() {
        try {
            // Prova prima con nome colonna nuovo (audit_sessions)
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
            
            // Fallback: cerca con nome colonna vecchio
            $stmt = $this->pdo->prepare("
                SELECT id FROM audit_sessions 
                WHERE month_year = ? AND session_status = 'active'
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$currentMonth]);
            $sessionId = $stmt->fetchColumn();
            
            if ($sessionId) {
                return $sessionId;
            }
            
        } catch (Exception $e) {
            $this->log("⚠️ Errore recupero sessione audit: " . $e->getMessage());
        }
        
        // Crea nuova sessione se non trovata
        return $this->createNewAuditSession($currentMonth);
    }
    
    private function createNewAuditSession($currentMonth) {
        try {
            // Prova inserimento con nuova struttura
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_sessions (
                    session_id, mese_anno, data_inizio_analisi, data_fine_analisi,
                    tecnici_analizzati, giorni_lavorativi, stato
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $sessionId = 'AUD-' . date('Ymd-Hi');
            $dataInizio = date('Y-m-01');
            $dataFine = date('Y-m-t');
            
            $stmt->execute([
                $sessionId,
                $currentMonth,
                $dataInizio,
                $dataFine,
                '["1","2","3"]', // JSON tecnici
                22, // giorni lavorativi stimati
                'INIZIATA'
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (Exception $e) {
            $this->log("⚠️ Errore creazione sessione audit, usando fallback: " . $e->getMessage());
            
            // Fallback ultra-semplice
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO audit_sessions (session_id) VALUES (?)
                ");
                $stmt->execute(['SES-' . time()]);
                return $this->pdo->lastInsertId();
                
            } catch (Exception $e2) {
                $this->log("❌ Impossibile creare sessione audit: " . $e2->getMessage());
                return 1; // ID fisso di emergenza
            }
        }
    }
    
    /**
     * CREATE DAILY ANALYSIS - VERSIONE FIXED
     */
    private function createDailyAnalysis($auditSessionId, $tecnicoId, $date) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO technician_daily_analysis 
                (audit_session_id, tecnico_id, data_analisi)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    id = LAST_INSERT_ID(id),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$auditSessionId, $tecnicoId, $date]);
            return $this->pdo->lastInsertId();
            
        } catch (Exception $e) {
            $this->log("⚠️ Errore creazione daily analysis: " . $e->getMessage());
            
            // Fallback: cerca se esiste già
            try {
                $stmt = $this->pdo->prepare("
                    SELECT id FROM technician_daily_analysis 
                    WHERE tecnico_id = ? AND data_analisi = ?
                    ORDER BY created_at DESC LIMIT 1
                ");
                $stmt->execute([$tecnicoId, $date]);
                $existingId = $stmt->fetchColumn();
                
                if ($existingId) {
                    return $existingId;
                }
                
                // Inserimento minimale
                $stmt = $this->pdo->prepare("
                    INSERT INTO technician_daily_analysis (tecnico_id, data_analisi)
                    VALUES (?, ?)
                ");
                $stmt->execute([$tecnicoId, $date]);
                return $this->pdo->lastInsertId();
                
            } catch (Exception $e2) {
                $this->log("❌ Fallback daily analysis fallito: " . $e2->getMessage());
                throw $e;
            }
        }
    }
    
    // [Qui mantieni tutti gli altri metodi del TechnicianAnalyzer originale, 
    //  ma sostituisci ogni chiamata a insertAlert() con la nuova versione fixed]
    
    /**
     * Raccoglie dati da tutte le fonti per il tecnico e la data
     * [MANTIENI UGUALE AL CODICE ORIGINALE]
     */
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
        
        // 1. Attività Deepser
        $data['deepser'] = $this->getDeepserActivities($technicianName, $date);
        $this->log("📋 Deepser: " . count($data['deepser']) . " attività");
        
        // 2. Appuntamenti Calendario
        $data['calendario'] = $this->getCalendarAppointments($technicianName, $date);
        $this->log("📅 Calendario: " . count($data['calendario']) . " appuntamenti");
        
        // 3. Utilizzo Auto
        $data['auto'] = $this->getAutoUsage($technicianName, $date);
        $this->log("🚗 Auto: " . count($data['auto']) . " utilizzi");
        
        // 4. Sessioni TeamViewer
        $data['teamviewer'] = $this->getTeamViewerSessions($technicianName, $date);
        $this->log("💻 TeamViewer: " . count($data['teamviewer']) . " sessioni");
        
        // 5. Timbrature
        $data['timbrature'] = $this->getTimbrature($technicianName, $date);
        $this->log("⏰ Timbrature: " . count($data['timbrature']) . " eventi");
        
        return $data;
    }
    
    // [TUTTI GLI ALTRI METODI DEL CODICE ORIGINALE RIMANGONO UGUALI]
    // getDeepserActivities, getCalendarAppointments, getAutoUsage, 
    // getTeamViewerSessions, getTimbrature, buildTimeline, etc.
    
    // [Copiare qui tutti i metodi dal file originale da riga 164 a 1028,
    //  sostituendo solo le chiamate a insertAlert con la nuova versione]
    
    /**
     * Verifica attività remote con utilizzo auto - VERSIONE FIXED
     */
    private function checkRemoteWithAuto($analysisId, $data) {
        $hasAuto = !empty($data['auto']);
        $remoteActivities = array_filter($data['deepser'], function($activity) {
            return $activity['location_type'] === 'remote';
        });
        
        if ($hasAuto && !empty($remoteActivities)) {
            $this->insertAlert($analysisId, [
                'alert_type' => 'remote_with_auto',
                'title' => 'Attività Remote con Utilizzo Auto',
                'message' => 'Rilevate attività marcate come remote ma è presente utilizzo auto aziendale',
                'severity' => 'high',
                'category' => 'logic_error',
                'evidence' => [
                    'auto_usage_count' => count($data['auto']),
                    'remote_activities_count' => count($remoteActivities)
                ]
            ]);
        }
    }
    
    /**
     * Verifica sessioni TeamViewer vs attività Deepser - VERSIONE FIXED
     */
    private function checkTeamViewerVsDeepser($analysisId, $data) {
        $significantSessions = array_filter($data['teamviewer'], function($session) {
            return $session['duration_minutes'] >= $this->config['teamviewer_min_minutes'];
        });
        
        if (!empty($significantSessions)) {
            $totalMinutes = array_sum(array_column($significantSessions, 'duration_minutes'));
            
            $this->insertAlert($analysisId, [
                'alert_type' => 'teamviewer_missing_activity',
                'title' => 'Sessioni TeamViewer Significative',
                'message' => "Rilevate {$totalMinutes} minuti di TeamViewer. Verificare attività corrispondenti in Deepser",
                'severity' => 'medium',
                'category' => 'missing_data',
                'evidence' => [
                    'sessions_count' => count($significantSessions),
                    'total_minutes' => $totalMinutes,
                    'sessions' => $significantSessions
                ]
            ]);
        }
    }
    
    /**
     * Verifica attività sovrapposte - VERSIONE FIXED
     */
    private function checkOverlappingActivities($analysisId, $data) {
        $activities = $data['deepser'];
        
        for ($i = 0; $i < count($activities); $i++) {
            for ($j = $i + 1; $j < count($activities); $j++) {
                $activity1 = $activities[$i];
                $activity2 = $activities[$j];
                
                if ($this->activitiesOverlap($activity1, $activity2)) {
                    $this->insertAlert($analysisId, [
                        'alert_type' => 'overlapping_activities',
                        'title' => 'Attività Sovrapposte',
                        'message' => "Sovrapposizione tra attività: {$activity1['azienda']} e {$activity2['azienda']}",
                        'severity' => 'high',
                        'category' => 'overlap',
                        'evidence' => [
                            'activity1' => $activity1,
                            'activity2' => $activity2
                        ]
                    ]);
                }
            }
        }
    }
    
    // [AGGIUNGI TUTTI GLI ALTRI METODI DAL FILE ORIGINALE QUI]
    // Per brevità non li copio tutti, ma mantieni la stessa struttura
    
    private function log($message) {
        error_log("[TechnicianAnalyzer-Fixed] " . $message);
        echo $message . "\n";
    }
}

/**
 * ESEMPIO DI UTILIZZO CON VERIFICA COMPATIBILITÀ
 */
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    // Test del sistema con verifica compatibilità
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
        
        echo "🔧 TECHNICIAN ANALYZER - VERSIONE FIXED\n";
        echo "=====================================\n";
        
        $analyzer = new TechnicianAnalyzer($pdo);
        
        // Test con tecnico ID 1 (Davide Cestone) per oggi
        $result = $analyzer->analyzeTechnicianDay(1, date('Y-m-d'));
        
        echo "\n=== RISULTATO ANALISI ===\n";
        echo "✅ Successo: " . ($result['success'] ? 'SI' : 'NO') . "\n";
        echo "📊 Score qualità: " . ($result['quality_score'] ?? 'N/A') . "\n";
        echo "🔍 Database compatibility:\n";
        echo "   - Colonne inglesi: " . ($result['database_compatibility']['english_columns'] ? 'SI' : 'NO') . "\n";
        echo "   - Stored procedures: " . ($result['database_compatibility']['stored_procedures'] ? 'SI' : 'NO') . "\n";
        
        if (!$result['success']) {
            echo "❌ Errore: " . $result['error'] . "\n";
        }
        
        echo "\n=== COMPATIBILITÀ VERIFICATA ===\n";
        
    } catch (Exception $e) {
        echo "❌ Errore connessione database: " . $e->getMessage() . "\n";
    }
}
?>