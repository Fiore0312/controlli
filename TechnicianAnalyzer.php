<?php
/**
 * TECHNICIAN ANALYZER - Analisi Dettagliata Singolo Tecnico
 * Sistema di audit quotidiano per controllo attività individuale
 * Integrazione completa: CrossValidator, TimelineBuilder, AnomalyDetector, CorrectionTracker
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
            'enable_automatic_corrections' => false
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
            
            // 7. Generazione alert consolidati
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
                'recommendations' => $this->generateActionableRecommendations($analysisId, $alerts)
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
     * Raccoglie dati da tutte le fonti per il tecnico e la data
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
    
    /**
     * Attività dal CSV Deepser
     */
    private function getDeepserActivities($technicianName, $date) {
        $csvPath = __DIR__ . '/upload_csv/attivita.csv';
        if (!file_exists($csvPath)) return [];
        
        $activities = [];
        $csvContent = file_get_contents($csvPath);
        $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);
        
        $lines = array_map('trim', explode("\n", $csvContent));
        $lines = array_filter($lines, function($line) { return !empty($line); });
        
        if (empty($lines)) return [];
        
        $headerLine = array_shift($lines);
        $delimiter = (substr_count($headerLine, ',') > substr_count($headerLine, ';')) ? ',' : ';';
        
        foreach ($lines as $line) {
            if (trim($line)) {
                $row = str_getcsv($line, $delimiter);
                
                // Verifica se è del tecnico giusto (colonna 11 - Creato da)
                if (count($row) > 11 && stripos($row[11], $technicianName) !== false) {
                    
                    // Verifica se è della data giusta (colonna 2 - Iniziata il)
                    if (count($row) > 2) {
                        $activityDate = $this->parseDate($row[2]);
                        if ($activityDate && $activityDate === $date) {
                            
                            $activities[] = [
                                'id' => $row[1] ?? 'N/A',
                                'azienda' => $row[4] ?? 'N/A',
                                'tipologia' => $row[5] ?? 'N/A',
                                'descrizione' => $row[7] ?? 'N/A',
                                'durata' => $row[9] ?? '0',
                                'iniziata_il' => $row[2] ?? '',
                                'conclusa_il' => $row[3] ?? '',
                                'start_time' => $this->parseDateTime($row[2]),
                                'end_time' => $this->parseDateTime($row[3]),
                                'location_type' => $this->detectLocationType($row[5] ?? '', $row[7] ?? '')
                            ];
                        }
                    }
                }
            }
        }
        
        return $activities;
    }
    
    /**
     * Appuntamenti dal calendario
     */
    private function getCalendarAppointments($technicianName, $date) {
        // Implementazione placeholder - adattare alla fonte calendario reale
        $csvPath = __DIR__ . '/upload_csv/calendario.csv';
        if (!file_exists($csvPath)) return [];
        
        // Logica simile per parsing calendario
        return [];
    }
    
    /**
     * Utilizzo auto dal CSV
     */
    private function getAutoUsage($technicianName, $date) {
        $csvPath = __DIR__ . '/upload_csv/auto.csv';
        if (!file_exists($csvPath)) return [];
        
        $autoUsage = [];
        $csvContent = file_get_contents($csvPath);
        $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);
        
        $lines = array_map('trim', explode("\n", $csvContent));
        $lines = array_filter($lines, function($line) { return !empty($line); });
        
        if (empty($lines)) return [];
        
        $headerLine = array_shift($lines);
        $delimiter = (substr_count($headerLine, ',') > substr_count($headerLine, ';')) ? ',' : ';';
        
        foreach ($lines as $line) {
            if (trim($line) && !strpos($line, 'Somma di Ore')) {
                $row = str_getcsv($line, $delimiter);
                
                // Verifica tecnico e data
                if (count($row) >= 6 && !empty($row[0])) {
                    $usageDate = $this->parseDate($row[0]);
                    if ($usageDate && $usageDate === $date && stripos($row[3] ?? '', $technicianName) !== false) {
                        
                        $autoUsage[] = [
                            'data' => $row[0],
                            'auto' => $row[1] ?? 'N/A',
                            'destinazione' => $row[2] ?? 'N/A',
                            'tecnico' => $row[3] ?? 'N/A',
                            'km' => $row[4] ?? '0',
                            'ore' => $row[5] ?? '0',
                            'start_time' => $this->estimateAutoStartTime($row),
                            'end_time' => $this->estimateAutoEndTime($row)
                        ];
                    }
                }
            }
        }
        
        return $autoUsage;
    }
    
    /**
     * Sessioni TeamViewer
     */
    private function getTeamViewerSessions($technicianName, $date) {
        $csvPath = __DIR__ . '/upload_csv/teamviewer_bait.csv';
        if (!file_exists($csvPath)) return [];
        
        $sessions = [];
        $csvContent = file_get_contents($csvPath);
        $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);
        
        $lines = array_map('trim', explode("\n", $csvContent));
        $lines = array_filter($lines, function($line) { return !empty($line); });
        
        if (empty($lines)) return [];
        
        $headerLine = array_shift($lines);
        $delimiter = (substr_count($headerLine, ',') > substr_count($headerLine, ';')) ? ',' : ';';
        
        foreach ($lines as $line) {
            if (trim($line)) {
                $row = str_getcsv($line, $delimiter);
                
                if (count($row) >= 8) {
                    $sessionDate = $this->parseDate($row[0]);
                    if ($sessionDate && $sessionDate === $date) {
                        
                        $duration = $this->parseTeamViewerDuration($row[2] ?? '0');
                        
                        $sessions[] = [
                            'data' => $row[0],
                            'ora' => $row[1] ?? '',
                            'durata' => $duration,
                            'computer_remoto' => $row[3] ?? 'N/A',
                            'utente' => $row[4] ?? 'N/A',
                            'start_time' => $this->parseDateTime($row[0] . ' ' . $row[1]),
                            'duration_minutes' => $duration,
                            'needs_activity' => ($duration >= $this->config['teamviewer_min_minutes'])
                        ];
                    }
                }
            }
        }
        
        return $sessions;
    }
    
    /**
     * Timbrature (placeholder per futuro)
     */
    private function getTimbrature($technicianName, $date) {
        // Implementazione futura per timbrature
        return [];
    }
    
    /**
     * Costruisce timeline eventi
     */
    private function buildTimeline($analysisId, $data) {
        $events = [];
        
        // Eventi Deepser
        foreach ($data['deepser'] as $activity) {
            if ($activity['start_time']) {
                $events[] = [
                    'daily_analysis_id' => $analysisId,
                    'event_source' => 'deepser',
                    'event_type' => 'activity',
                    'start_time' => $activity['start_time'],
                    'end_time' => $activity['end_time'],
                    'duration_minutes' => $this->calculateDurationMinutes($activity['start_time'], $activity['end_time']),
                    'client_name' => $activity['azienda'],
                    'activity_description' => $activity['descrizione'],
                    'location_type' => $activity['location_type'],
                    'source_record_id' => $activity['id'],
                    'source_data' => json_encode($activity)
                ];
            }
        }
        
        // Eventi Auto
        foreach ($data['auto'] as $usage) {
            if ($usage['start_time']) {
                $events[] = [
                    'daily_analysis_id' => $analysisId,
                    'event_source' => 'auto',
                    'event_type' => 'travel',
                    'start_time' => $usage['start_time'],
                    'end_time' => $usage['end_time'],
                    'duration_minutes' => $this->parseHoursToMinutes($usage['ore']),
                    'client_name' => $usage['destinazione'],
                    'activity_description' => "Utilizzo auto: " . $usage['auto'],
                    'location_type' => 'travel',
                    'source_data' => json_encode($usage)
                ];
            }
        }
        
        // Eventi TeamViewer
        foreach ($data['teamviewer'] as $session) {
            if ($session['start_time']) {
                $events[] = [
                    'daily_analysis_id' => $analysisId,
                    'event_source' => 'teamviewer',
                    'event_type' => 'session',
                    'start_time' => $session['start_time'],
                    'end_time' => date('Y-m-d H:i:s', strtotime($session['start_time']) + ($session['duration_minutes'] * 60)),
                    'duration_minutes' => $session['duration_minutes'],
                    'client_name' => $session['computer_remoto'],
                    'activity_description' => "Sessione remota: " . $session['utente'],
                    'location_type' => 'remote',
                    'source_data' => json_encode($session)
                ];
            }
        }
        
        // Salva eventi nel database
        foreach ($events as $event) {
            $this->insertTimelineEvent($event);
        }
        
        return $events;
    }
    
    /**
     * Analizza gaps nella timeline
     */
    private function analyzeTimelineGaps($analysisId, $events) {
        // Ordina eventi per orario
        usort($events, function($a, $b) {
            return strtotime($a['start_time']) - strtotime($b['start_time']);
        });
        
        $morningStart = null;
        $morningEnd = null;
        $afternoonStart = null;
        $afternoonEnd = null;
        
        foreach ($events as $event) {
            $startHour = date('H:i:s', strtotime($event['start_time']));
            $endHour = date('H:i:s', strtotime($event['end_time']));
            
            // Attività mattutine (prima delle 13:00)
            if ($startHour < '13:00:00') {
                if (!$morningStart || $startHour < $morningStart) {
                    $morningStart = $startHour;
                }
                if (!$morningEnd || $endHour > $morningEnd) {
                    $morningEnd = $endHour;
                }
            }
            
            // Attività pomeridiane (dopo le 14:00)
            if ($startHour >= '14:00:00') {
                if (!$afternoonStart || $startHour < $afternoonStart) {
                    $afternoonStart = $startHour;
                }
                if (!$afternoonEnd || $endHour > $afternoonEnd) {
                    $afternoonEnd = $endHour;
                }
            }
        }
        
        // Calcola gaps
        $morningGap = $morningStart ? $this->calculateMinutesDiff('09:00:00', $morningStart) : 240;
        $afternoonGap = $afternoonStart ? $this->calculateMinutesDiff('14:00:00', $afternoonStart) : 240;
        
        // Aggiorna analisi
        $stmt = $this->pdo->prepare("
            UPDATE technician_daily_analysis 
            SET morning_start_time = ?, afternoon_start_time = ?, 
                morning_gap_minutes = ?, afternoon_gap_minutes = ?,
                has_timeline_gaps = ?
            WHERE id = ?
        ");
        
        $hasGaps = ($morningGap > $this->config['gap_threshold_minutes'] || 
                   $afternoonGap > $this->config['gap_threshold_minutes']);
        
        $stmt->execute([
            $morningStart, $afternoonStart, 
            max(0, $morningGap), max(0, $afternoonGap),
            $hasGaps ? 1 : 0,
            $analysisId
        ]);
    }
    
    /**
     * Controlli di coerenza
     */
    private function performCoherenceChecks($analysisId, $data) {
        // 1. Verifica Remote vs Auto
        $this->checkRemoteWithAuto($analysisId, $data);
        
        // 2. Verifica TeamViewer vs Deepser
        $this->checkTeamViewerVsDeepser($analysisId, $data);
        
        // 3. Verifica sovrapposizioni
        $this->checkOverlappingActivities($analysisId, $data);
    }
    
    /**
     * Verifica attività remote con utilizzo auto
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
     * Verifica sessioni TeamViewer vs attività Deepser
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
     * Verifica attività sovrapposte
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
    
    /**
     * Utility functions
     */
    private function getCurrentAuditSession() {
        $currentMonth = date('Y-m');
        
        $stmt = $this->pdo->prepare("
            SELECT id FROM audit_sessions 
            WHERE month_year = ? AND session_status = 'active'
        ");
        $stmt->execute([$currentMonth]);
        $sessionId = $stmt->fetchColumn();
        
        if (!$sessionId) {
            // Crea nuova sessione con tutti i campi richiesti
            $sessionIdStr = 'AUDIT_' . date('Ym') . '_' . substr(md5(uniqid()), 0, 8);
            $startDate = date('Y-m-01'); // First day of current month
            $endDate = date('Y-m-t');   // Last day of current month
            
            // Calculate working days (rough estimate - excluding weekends)
            $workingDays = 0;
            $start = strtotime($startDate);
            $end = strtotime($endDate);
            for ($current = $start; $current <= $end; $current = strtotime('+1 day', $current)) {
                $dayOfWeek = date('N', $current);
                if ($dayOfWeek < 6) { // Monday = 1, Friday = 5
                    $workingDays++;
                }
            }
            
            // Create empty JSON for tecnici_analizzati
            $tecniciAnalizzati = json_encode([
                'tecnici_attivi' => [],
                'data_ultimo_aggiornamento' => date('Y-m-d H:i:s'),
                'stati_analisi' => []
            ]);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_sessions 
                (session_id, mese_anno, data_inizio_analisi, data_fine_analisi, 
                 tecnici_analizzati, giorni_lavorativi, month_year, current_day, session_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            
            $stmt->execute([
                $sessionIdStr,
                $currentMonth,
                $startDate,
                $endDate,
                $tecniciAnalizzati,
                $workingDays,
                $currentMonth,
                date('j')
            ]);
            
            $sessionId = $this->pdo->lastInsertId();
        }
        
        return $sessionId;
    }
    
    private function createDailyAnalysis($auditSessionId, $tecnicoId, $date) {
        // Prima cerca se esiste già
        $stmt = $this->pdo->prepare("
            SELECT id FROM technician_daily_analysis 
            WHERE audit_session_id = ? AND tecnico_id = ? AND data_analisi = ?
        ");
        $stmt->execute([$auditSessionId, $tecnicoId, $date]);
        $existingId = $stmt->fetchColumn();
        
        if ($existingId) {
            $this->log("📋 Usando analysis esistente ID: $existingId");
            return $existingId;
        }
        
        // Se non esiste, crea nuovo
        $stmt = $this->pdo->prepare("
            INSERT INTO technician_daily_analysis 
            (audit_session_id, tecnico_id, data_analisi)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$auditSessionId, $tecnicoId, $date]);
        $newId = $this->pdo->lastInsertId();
        
        $this->log("🆕 Creata nuova analysis ID: $newId");
        return $newId;
    }
    
    private function insertTimelineEvent($event) {
        $stmt = $this->pdo->prepare("
            INSERT INTO timeline_events 
            (daily_analysis_id, event_source, event_type, start_time, end_time, 
             duration_minutes, client_name, activity_description, location_type, 
             source_record_id, source_data)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $event['daily_analysis_id'],
            $event['event_source'],
            $event['event_type'],
            $event['start_time'],
            $event['end_time'],
            $event['duration_minutes'],
            $event['client_name'],
            $event['activity_description'],
            $event['location_type'],
            $event['source_record_id'] ?? null,
            $event['source_data']
        ]);
    }
    
    private function generateAlertId($alert) {
        static $counter = 0; // Static counter per garantire unicità in questa esecuzione
        
        // Generate unique alert_id with max 20 chars constraint
        $microtime = microtime(true);
        $date = date('md'); // Solo mese e giorno (4 chars)
        $time = date('His', (int)$microtime); // Ora minuti secondi (6 chars)  
        $microsec = substr(sprintf("%.6f", $microtime), -3); // Ultimi 3 digit microsecondi
        $counter++; // Increment counter for this execution
        
        // Format: AUDIT_MMDD_HHMMSS_XXX (max 20 chars: 5+4+6+3+2 = 20)
        $alertId = "A{$date}{$time}{$microsec}" . sprintf("%02d", $counter % 100);
        
        // Ensure exactly 20 chars
        $alertId = substr($alertId, 0, 20);
        
        // Double-check uniqueness
        $attempts = 0;
        while ($attempts < 5) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_alerts WHERE alert_id = ?");
            $stmt->execute([$alertId]);
            
            if ($stmt->fetchColumn() == 0) {
                return $alertId; // Unique alert_id found
            }
            
            // Extremely rare case - modify last digits
            $counter++;
            $lastTwoDigits = sprintf("%02d", $counter % 100);
            $alertId = substr($alertId, 0, 18) . $lastTwoDigits;
            $attempts++;
            
            // Add small delay to ensure different microtime
            usleep(2000); // 2ms delay
        }
        
        // Ultimate fallback with timestamp + random
        return "A" . date('mdHis') . mt_rand(100, 999) . sprintf("%02d", mt_rand(0, 99));
    }

    private function insertAlert($analysisId, $alert) {
        // Generate unique alert_id
        $alertId = $this->generateAlertId($alert);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_alerts 
            (alert_id, daily_analysis_id, alert_type, titolo, descrizione, severita, categoria, evidenze)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $alertId,
            $analysisId,
            (($alert['alert_type'] ?? $alert['type'] ?? 'unknown') ?? $alert['type'] ?? 'unknown'),
            (($alert['title'] ?? 'Alert') ?? 'Alert'),
            (($alert['message'] ?? 'Nessun messaggio') ?? 'Nessun messaggio'),
            (($alert['severity'] ?? 'INFO') ?? 'INFO'),
            (($alert['category'] ?? $alert['type'] ?? 'general') ?? $alert['type'] ?? 'general'),
            json_encode((($alert['evidence'] ?? []) ?? []))
        ]);
    }
    
    private function calculateQualityScore($analysisId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as alert_count FROM audit_alerts 
            WHERE daily_analysis_id = ? AND status = 'new'
        ");
        $stmt->execute([$analysisId]);
        $alertCount = $stmt->fetchColumn();
        
        $score = max(0, 100 - ($alertCount * 5));
        
        // Aggiorna score nel database
        $stmt = $this->pdo->prepare("
            UPDATE technician_daily_analysis 
            SET quality_score = ?, total_alerts = ?
            WHERE id = ?
        ");
        $stmt->execute([$score, $alertCount, $analysisId]);
        
        return $score;
    }
    
    private function generateAnalysisSummary($analysisId) {
        $stmt = $this->pdo->prepare("
            SELECT tda.*, t.nome_completo,
                   COUNT(te.id) as timeline_events,
                   COUNT(aa.id) as total_alerts
            FROM technician_daily_analysis tda
            JOIN tecnici t ON tda.tecnico_id = t.id
            LEFT JOIN timeline_events te ON tda.id = te.daily_analysis_id
            LEFT JOIN audit_alerts aa ON tda.id = aa.daily_analysis_id
            WHERE tda.id = ?
            GROUP BY tda.id
        ");
        $stmt->execute([$analysisId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Utility parsing functions
    private function parseDate($dateString) {
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y'];
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }
        return null;
    }
    
    private function parseDateTime($dateTimeString) {
        $formats = [
            'Y-m-d H:i:s',
            'd/m/Y H:i:s',
            'm/d/Y H:i:s A',
            'd/m/Y G:i:s'
        ];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateTimeString);
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        return null;
    }
    
    private function detectLocationType($tipologia, $descrizione) {
        $remoteKeywords = ['remoto', 'remote', 'teamviewer', 'assistenza remota'];
        $onsiteKeywords = ['onsite', 'on-site', 'presso', 'cliente', 'trasferta'];
        
        $text = strtolower($tipologia . ' ' . $descrizione);
        
        foreach ($remoteKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return 'remote';
            }
        }
        
        foreach ($onsiteKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return 'onsite';
            }
        }
        
        return 'remote'; // Default
    }
    
    private function parseTeamViewerDuration($durationString) {
        // Parse "45m" or "1h 30m" format
        $minutes = 0;
        
        if (preg_match('/(\d+)h/', $durationString, $matches)) {
            $minutes += intval($matches[1]) * 60;
        }
        
        if (preg_match('/(\d+)m/', $durationString, $matches)) {
            $minutes += intval($matches[1]);
        }
        
        if ($minutes === 0 && is_numeric($durationString)) {
            $minutes = intval($durationString);
        }
        
        return $minutes;
    }
    
    private function calculateDurationMinutes($startTime, $endTime) {
        if (!$startTime || !$endTime) return 0;
        
        $start = strtotime($startTime);
        $end = strtotime($endTime);
        
        return max(0, ($end - $start) / 60);
    }
    
    private function calculateMinutesDiff($time1, $time2) {
        $start = strtotime($time1);
        $end = strtotime($time2);
        return ($end - $start) / 60;
    }
    
    private function parseHoursToMinutes($hoursString) {
        if (is_numeric($hoursString)) {
            return floatval($hoursString) * 60;
        }
        return 0;
    }
    
    private function activitiesOverlap($activity1, $activity2) {
        if (!$activity1['start_time'] || !$activity1['end_time'] || 
            !$activity2['start_time'] || !$activity2['end_time']) {
            return false;
        }
        
        $start1 = strtotime($activity1['start_time']);
        $end1 = strtotime($activity1['end_time']);
        $start2 = strtotime($activity2['start_time']);
        $end2 = strtotime($activity2['end_time']);
        
        return ($start1 < $end2 && $start2 < $end1);
    }
    
    private function estimateAutoStartTime($autoRow) {
        // Logica per stimare orario inizio utilizzo auto
        // Placeholder - da implementare con logica business specifica
        return date('Y-m-d 09:00:00');
    }
    
    private function estimateAutoEndTime($autoRow) {
        // Logica per stimare orario fine utilizzo auto
        // Placeholder - da implementare con logica business specifica
        $hours = floatval($autoRow[5] ?? 0);
        return date('Y-m-d H:i:s', strtotime('09:00:00') + ($hours * 3600));
    }
    
    /**
     * METODI INTEGRATI - Utilizzano i componenti specializzati
     */
    
    /**
     * Costruzione timeline avanzata con AI
     */
    private function buildAdvancedTimeline($analysisId, $data) {
        if ($this->config['enable_advanced_timeline']) {
            $result = $this->timelineBuilder->buildIntelligentTimeline($analysisId, $data);
            $this->log("🏗️ Timeline intelligente: {$result['total_events']} eventi, qualità {$result['timeline_quality_score']}%");
            return $result;
        } else {
            // Fallback al metodo base
            $events = $this->buildTimeline($analysisId, $data);
            return [
                'success' => true,
                'total_events' => count($events),
                'timeline_quality_score' => 75
            ];
        }
    }
    
    /**
     * Cross-validation avanzata
     */
    private function performAdvancedCrossValidation($analysisId, $data) {
        if ($this->config['enable_cross_validation']) {
            $result = $this->crossValidator->performCrossValidation($analysisId, $data);
            $this->log("🔍 Cross-validation: {$result['total_checks']} controlli, {$result['failed_checks']} falliti");
            return $result;
        } else {
            // Fallback ai controlli base
            $this->performCoherenceChecks($analysisId, $data);
            return [
                'success' => true,
                'total_checks' => 3,
                'failed_checks' => 0
            ];
        }
    }
    
    /**
     * Rilevamento anomalie AI
     */
    private function performAIAnomalyDetection($tecnicoId, $date, $data) {
        if ($this->config['enable_ai_analysis']) {
            $result = $this->anomalyDetector->detectAnomaliesForTechnician($tecnicoId, $date, $data);
            $this->log("🤖 AI Anomalie: {$result['anomalies_detected']} rilevate, risk score {$result['risk_score']}");
            return $result;
        } else {
            return [
                'success' => true,
                'anomalies_detected' => 0,
                'risk_score' => 0
            ];
        }
    }
    
    /**
     * Generazione alert consolidati
     */
    private function generateConsolidatedAlerts($analysisId, $data, $timelineResult, $crossValidationResult, $anomalyResult) {
        $alerts = [];
        
        // Alert da cross-validation
        if (isset($crossValidationResult['validation_results'])) {
            foreach ($crossValidationResult['validation_results'] as $category => $checks) {
                foreach ($checks as $check) {
                    if ($check['check_status'] === 'failed') {
                        $alerts[] = [
                            'source' => 'cross_validation',
                            'category' => $category,
                            'severity' => $check['severity'],
                            'title' => $check['check_description'],
                            'message' => $check['recommendation'],
                            'evidence' => $check['evidence_data']
                        ];
                    }
                }
            }
        }
        
        // Alert da anomalie AI
        if (isset($anomalyResult['anomalies'])) {
            foreach ($anomalyResult['anomalies'] as $anomaly) {
                $alerts[] = [
                    'source' => 'ai_anomaly',
                    'category' => isset($anomaly['type']) ? $anomaly['type'] : 'unknown',
                    'alert_type' => isset($anomaly['type']) ? $anomaly['type'] : 'unknown_anomaly',
                    'severity' => isset($anomaly['severity']) ? $anomaly['severity'] : 'INFO',
                    'title' => isset($anomaly['description']) ? $anomaly['description'] : 'Anomalia rilevata',
                    'message' => "Anomalia rilevata: " . (isset($anomaly['subtype']) ? $anomaly['subtype'] : 'Dettagli non disponibili'),
                    'evidence' => isset($anomaly['evidence']) ? $anomaly['evidence'] : [],
                    'confidence' => isset($anomaly['confidence']) ? $anomaly['confidence'] : 50
                ];
            }
        }
        
        // Salva alert nel database
        foreach ($alerts as $alert) {
            $this->insertAlert($analysisId, $alert);
        }
        
        $this->log("⚠️ Alert consolidati: " . count($alerts) . " generati");
        
        return $alerts;
    }
    
    /**
     * Calcolo score qualità avanzato
     */
    private function calculateAdvancedQualityScore($analysisId, $timelineResult, $crossValidationResult, $anomalyResult) {
        // Score base dalla timeline
        $timelineScore = $timelineResult['timeline_quality_score'] ?? 75;
        
        // Penalità da cross-validation
        $crossValidationPenalty = ($crossValidationResult['failed_checks'] ?? 0) * 5;
        
        // Penalità da anomalie AI
        $anomalyPenalty = ($anomalyResult['risk_score'] ?? 0) * 0.3;
        
        // Score finale
        $finalScore = max(0, min(100, $timelineScore - $crossValidationPenalty - $anomalyPenalty));
        
        // Aggiorna nel database
        $stmt = $this->pdo->prepare("
            UPDATE technician_daily_analysis 
            SET quality_score = ?
            WHERE id = ?
        ");
        $stmt->execute([$finalScore, $analysisId]);
        
        return $finalScore;
    }
    
    /**
     * Avvio correzioni automatiche
     */
    private function initiateAutomaticCorrections($analysisId, $tecnicoId, $alerts) {
        if (empty($alerts)) return null;
        
        $result = $this->correctionTracker->createCorrectionRequest($analysisId, $tecnicoId, $alerts, [
            'send_immediately' => true,
            'method' => 'email'
        ]);
        
        $this->log("📝 Correzioni automatiche: " . ($result['success'] ? 'avviate' : 'fallite'));
        
        return $result;
    }
    
    /**
     * Genera summary avanzato
     */
    private function generateAdvancedAnalysisSummary($analysisId, $timelineResult, $crossValidationResult, $anomalyResult) {
        $summary = $this->generateAnalysisSummary($analysisId);
        
        // Aggiungi dati avanzati
        $summary['timeline_quality'] = $timelineResult['timeline_quality_score'] ?? 0;
        $summary['timeline_coverage'] = $timelineResult['coverage_percentage'] ?? 0;
        $summary['cross_validation_score'] = $crossValidationResult['success'] ? 
            (100 - (($crossValidationResult['failed_checks'] ?? 0) * 10)) : 0;
        $summary['ai_risk_score'] = $anomalyResult['risk_score'] ?? 0;
        $summary['ai_anomalies_count'] = $anomalyResult['anomalies_detected'] ?? 0;
        
        // Flags avanzati
        $summary['has_ai_anomalies'] = ($anomalyResult['anomalies_detected'] ?? 0) > 0;
        $summary['has_cross_validation_issues'] = ($crossValidationResult['failed_checks'] ?? 0) > 0;
        $summary['timeline_quality_level'] = $this->getQualityLevel($summary['timeline_quality']);
        
        return $summary;
    }
    
    /**
     * Genera raccomandazioni azionabili
     */
    private function generateActionableRecommendations($analysisId, $alerts) {
        $recommendations = [];
        
        // Analizza tipologie di alert
        $alertsByCategory = [];
        foreach ($alerts as $alert) {
            $category = (($alert['category'] ?? $alert['type'] ?? 'general') ?? $alert['type'] ?? 'general');
            $alertsByCategory[$category] = ($alertsByCategory[$category] ?? 0) + 1;
        }
        
        // Genera raccomandazioni specifiche
        foreach ($alertsByCategory as $category => $count) {
            switch ($category) {
                case 'timeline_gap':
                    $recommendations[] = [
                        'priority' => 'high',
                        'action' => 'Verificare e registrare attività mancanti nei gap temporali',
                        'details' => "Rilevati {$count} gap nella timeline giornaliera",
                        'expected_time' => '15 minuti'
                    ];
                    break;
                    
                case 'logic_error':
                    $recommendations[] = [
                        'priority' => 'critical',
                        'action' => 'Correggere incongruenze logiche (es. remote + auto)',
                        'details' => "Rilevati {$count} errori logici che richiedono correzione immediata",
                        'expected_time' => '10 minuti'
                    ];
                    break;
                    
                case 'missing_data':
                    $recommendations[] = [
                        'priority' => 'medium',
                        'action' => 'Integrare dati mancanti nelle registrazioni',
                        'details' => "Rilevati {$count} casi di dati incompleti",
                        'expected_time' => '20 minuti'
                    ];
                    break;
            }
        }
        
        // Raccomandazione generale se molti alert
        if (count($alerts) > 5) {
            $recommendations[] = [
                'priority' => 'high',
                'action' => 'Revisione generale del processo di registrazione attività',
                'details' => 'Alto numero di problemi rilevati suggerisce necessità di formazione',
                'expected_time' => '30 minuti'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Utility functions
     */
    private function getQualityLevel($score) {
        if ($score >= 90) return 'excellent';
        if ($score >= 75) return 'good';
        if ($score >= 60) return 'acceptable';
        if ($score >= 40) return 'poor';
        return 'critical';
    }
    
    private function log($message) {
        error_log("[TechnicianAnalyzer] " . $message);
        // echo $message . "\n"; // Removed to prevent debug output in web interface
    }
}

/**
 * ESEMPIO DI UTILIZZO
 */
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    // Test del sistema
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
        
        $analyzer = new TechnicianAnalyzer($pdo);
        
        // Test con tecnico ID 1 (Davide Cestone) per oggi
        $result = $analyzer->analyzeTechnicianDay(1, date('Y-m-d'));
        
        echo "=== RISULTATO ANALISI ===\n";
        print_r($result);
        
    } catch (Exception $e) {
        echo "Errore: " . $e->getMessage() . "\n";
    }
}
?>