<?php
/**
 * CROSS VALIDATOR - Sistema Validazione Incrociata Multi-Fonte
 * Controllo coerenza tra Deepser, Calendar, Auto, TeamViewer, Timbrature
 */

class CrossValidator {
    
    private $pdo;
    private $config;
    private $businessRules;
    private $validationMatrix;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        
        $this->config = [
            'max_travel_time_minutes' => 60,
            'min_onsite_duration_minutes' => 30,
            'teamviewer_significant_threshold' => 15,
            'overlap_tolerance_minutes' => 5,
            'distance_per_km_minutes' => 2 // 2 minuti per km di distanza
        ];
        
        $this->businessRules = [
            'working_hours_start' => '09:00:00',
            'working_hours_end' => '18:00:00',
            'lunch_break_start' => '13:00:00',
            'lunch_break_end' => '14:00:00',
            'max_daily_hours' => 8
        ];
        
        $this->initValidationMatrix();
    }
    
    /**
     * Matrice di validazione incrociata tra fonti dati
     */
    private function initValidationMatrix() {
        $this->validationMatrix = [
            'deepser_vs_calendar' => [
                'description' => 'Verifica coerenza attività registrate vs appuntamenti pianificati',
                'severity' => 'high',
                'checks' => ['missing_activities', 'time_mismatch', 'client_mismatch']
            ],
            'deepser_vs_auto' => [
                'description' => 'Verifica coerenza attività onsite vs utilizzo auto',
                'severity' => 'critical',
                'checks' => ['remote_with_auto', 'onsite_without_auto', 'travel_time_logic']
            ],
            'deepser_vs_teamviewer' => [
                'description' => 'Verifica attività remote vs sessioni TeamViewer',
                'severity' => 'medium',
                'checks' => ['missing_remote_activities', 'duration_mismatch', 'client_correlation']
            ],
            'auto_vs_calendar' => [
                'description' => 'Verifica utilizzo auto vs appuntamenti cliente',
                'severity' => 'medium',
                'checks' => ['destination_mismatch', 'timing_inconsistency']
            ],
            'timeline_consistency' => [
                'description' => 'Verifica coerenza temporale generale',
                'severity' => 'high',
                'checks' => ['overlapping_events', 'impossible_transitions', 'working_hours_violations']
            ]
        ];
    }
    
    /**
     * Esegue validazione completa per analisi giornaliera
     */
    public function performCrossValidation($dailyAnalysisId, $data) {
        try {
            $this->log("🔍 Inizio cross-validation per analisi ID: $dailyAnalysisId");
            
            $validationResults = [];
            
            // 1. Deepser vs Calendar
            $validationResults['deepser_calendar'] = $this->validateDeepserVsCalendar($dailyAnalysisId, $data);
            
            // 2. Deepser vs Auto
            $validationResults['deepser_auto'] = $this->validateDeepserVsAuto($dailyAnalysisId, $data);
            
            // 3. Deepser vs TeamViewer
            $validationResults['deepser_teamviewer'] = $this->validateDeepserVsTeamViewer($dailyAnalysisId, $data);
            
            // 4. Auto vs Calendar
            $validationResults['auto_calendar'] = $this->validateAutoVsCalendar($dailyAnalysisId, $data);
            
            // 5. Timeline Consistency
            $validationResults['timeline_consistency'] = $this->validateTimelineConsistency($dailyAnalysisId, $data);
            
            // 6. Business Rules Compliance
            $validationResults['business_rules'] = $this->validateBusinessRules($dailyAnalysisId, $data);
            
            $this->log("✅ Cross-validation completata");
            
            return [
                'success' => true,
                'validation_results' => $validationResults,
                'total_checks' => $this->countTotalChecks($validationResults),
                'failed_checks' => $this->countFailedChecks($validationResults)
            ];
            
        } catch (Exception $e) {
            $this->log("❌ Errore cross-validation: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validazione Deepser vs Calendar
     */
    private function validateDeepserVsCalendar($dailyAnalysisId, $data) {
        $checks = [];
        
        // Check 1: Appuntamenti calendar senza attività Deepser corrispondenti
        foreach ($data['calendario'] as $appointment) {
            $matchingActivity = $this->findMatchingActivity(
                $data['deepser'], 
                $appointment, 
                ['client', 'time']
            );
            
            if (!$matchingActivity) {
                $checks[] = $this->createValidationCheck(
                    $dailyAnalysisId,
                    'missing_deepser_activity',
                    'Appuntamento calendario senza attività Deepser',
                    'calendario',
                    'deepser',
                    'Appuntamento programmato',
                    'Nessuna attività corrispondente',
                    'failed',
                    'high',
                    [
                        'appointment' => $appointment,
                        'search_criteria' => ['client', 'time']
                    ],
                    'Creare attività Deepser per appuntamento programmato'
                );
            }
        }
        
        // Check 2: Attività Deepser senza appuntamenti calendar
        foreach ($data['deepser'] as $activity) {
            if ($activity['location_type'] === 'onsite') {
                $matchingAppointment = $this->findMatchingActivity(
                    $data['calendario'], 
                    $activity, 
                    ['client', 'time']
                );
                
                if (!$matchingAppointment) {
                    $checks[] = $this->createValidationCheck(
                        $dailyAnalysisId,
                        'missing_calendar_appointment',
                        'Attività onsite senza appuntamento calendario',
                        'deepser',
                        'calendario',
                        'Attività onsite registrata',
                        'Nessun appuntamento programmato',
                        'warning',
                        'medium',
                        [
                            'activity' => $activity,
                            'check_type' => 'onsite_without_appointment'
                        ],
                        'Verificare se appuntamento era programmato ma non registrato'
                    );
                }
            }
        }
        
        return $checks;
    }
    
    /**
     * Validazione Deepser vs Auto
     */
    private function validateDeepserVsAuto($dailyAnalysisId, $data) {
        $checks = [];
        
        // Check 1: Attività remote con utilizzo auto (CRITICO)
        $remoteActivities = array_filter($data['deepser'], function($activity) {
            return $activity['location_type'] === 'remote';
        });
        
        if (!empty($remoteActivities) && !empty($data['auto'])) {
            foreach ($remoteActivities as $activity) {
                $overlappingAuto = $this->findOverlappingEvents(
                    [$activity], 
                    $data['auto'], 
                    $this->config['overlap_tolerance_minutes']
                );
                
                if (!empty($overlappingAuto)) {
                    $checks[] = $this->createValidationCheck(
                        $dailyAnalysisId,
                        'remote_activity_with_auto',
                        'Attività remota con utilizzo auto contemporaneo',
                        'deepser',
                        'auto',
                        'Attività marcata come remota',
                        'Utilizzo auto nello stesso periodo',
                        'failed',
                        'critical',
                        [
                            'remote_activity' => $activity,
                            'auto_usage' => $overlappingAuto[0],
                            'overlap_minutes' => $this->calculateOverlapMinutes($activity, $overlappingAuto[0])
                        ],
                        'Verificare correttezza location type o eliminare utilizzo auto'
                    );
                }
            }
        }
        
        // Check 2: Attività onsite senza utilizzo auto
        $onsiteActivities = array_filter($data['deepser'], function($activity) {
            return $activity['location_type'] === 'onsite';
        });
        
        foreach ($onsiteActivities as $activity) {
            $relatedAuto = $this->findRelatedAutoUsage($activity, $data['auto']);
            
            if (!$relatedAuto) {
                $checks[] = $this->createValidationCheck(
                    $dailyAnalysisId,
                    'onsite_without_auto',
                    'Attività onsite senza utilizzo auto registrato',
                    'deepser',
                    'auto',
                    'Attività presso cliente',
                    'Nessun utilizzo auto corrispondente',
                    'warning',
                    'medium',
                    [
                        'onsite_activity' => $activity,
                        'search_criteria' => 'destination_and_time'
                    ],
                    'Verificare se spostamento effettuato con auto aziendale'
                );
            }
        }
        
        // Check 3: Logica tempi di viaggio
        foreach ($data['auto'] as $autoUsage) {
            $estimatedDistance = $this->estimateDistance($autoUsage['destinazione']);
            $expectedTravelTime = $estimatedDistance * $this->config['distance_per_km_minutes'];
            $actualUsageTime = $this->parseHoursToMinutes($autoUsage['ore']);
            
            if (abs($actualUsageTime - $expectedTravelTime) > 30) {
                $checks[] = $this->createValidationCheck(
                    $dailyAnalysisId,
                    'unrealistic_travel_time',
                    'Tempo utilizzo auto non realistico',
                    'auto',
                    'sistema',
                    "Utilizzo registrato: {$actualUsageTime} min",
                    "Tempo stimato: {$expectedTravelTime} min",
                    'warning',
                    'low',
                    [
                        'auto_usage' => $autoUsage,
                        'estimated_distance_km' => $estimatedDistance,
                        'expected_time_minutes' => $expectedTravelTime,
                        'actual_time_minutes' => $actualUsageTime,
                        'difference_minutes' => abs($actualUsageTime - $expectedTravelTime)
                    ],
                    'Verificare correttezza ore utilizzo auto o destinazione'
                );
            }
        }
        
        return $checks;
    }
    
    /**
     * Validazione Deepser vs TeamViewer
     */
    private function validateDeepserVsTeamViewer($dailyAnalysisId, $data) {
        $checks = [];
        
        // Check 1: Sessioni TeamViewer significative senza attività Deepser
        $significantSessions = array_filter($data['teamviewer'], function($session) {
            return $session['duration_minutes'] >= $this->config['teamviewer_significant_threshold'];
        });
        
        foreach ($significantSessions as $session) {
            $matchingActivity = $this->findMatchingRemoteActivity($session, $data['deepser']);
            
            if (!$matchingActivity) {
                $checks[] = $this->createValidationCheck(
                    $dailyAnalysisId,
                    'teamviewer_without_activity',
                    'Sessione TeamViewer significativa senza attività Deepser',
                    'teamviewer',
                    'deepser',
                    "Sessione {$session['duration_minutes']} minuti",
                    'Nessuna attività remota corrispondente',
                    'failed',
                    'medium',
                    [
                        'teamviewer_session' => $session,
                        'required_duration' => $this->config['teamviewer_significant_threshold']
                    ],
                    'Creare attività Deepser per sessione remote significativa'
                );
            }
        }
        
        // Check 2: Durata TeamViewer vs Deepser
        foreach ($data['deepser'] as $activity) {
            if ($activity['location_type'] === 'remote') {
                $relatedSessions = $this->findRelatedTeamViewerSessions($activity, $data['teamviewer']);
                $totalTeamViewerMinutes = array_sum(array_column($relatedSessions, 'duration_minutes'));
                $activityMinutes = $this->calculateDurationMinutes($activity['start_time'], $activity['end_time']);
                
                if ($totalTeamViewerMinutes > 0 && abs($activityMinutes - $totalTeamViewerMinutes) > 30) {
                    $checks[] = $this->createValidationCheck(
                        $dailyAnalysisId,
                        'duration_mismatch_remote',
                        'Discrepanza durata attività remota vs TeamViewer',
                        'deepser',
                        'teamviewer',
                        "Attività Deepser: {$activityMinutes} min",
                        "TeamViewer totale: {$totalTeamViewerMinutes} min",
                        'warning',
                        'low',
                        [
                            'activity' => $activity,
                            'teamviewer_sessions' => $relatedSessions,
                            'difference_minutes' => abs($activityMinutes - $totalTeamViewerMinutes)
                        ],
                        'Verificare correttezza durata attività o sessioni TeamViewer'
                    );
                }
            }
        }
        
        return $checks;
    }
    
    /**
     * Validazione Auto vs Calendar
     */
    private function validateAutoVsCalendar($dailyAnalysisId, $data) {
        $checks = [];
        
        foreach ($data['auto'] as $autoUsage) {
            $matchingAppointments = array_filter($data['calendario'], function($appointment) use ($autoUsage) {
                return stripos($appointment['location'] ?? '', $autoUsage['destinazione']) !== false ||
                       stripos($autoUsage['destinazione'], $appointment['client'] ?? '') !== false;
            });
            
            if (empty($matchingAppointments)) {
                $checks[] = $this->createValidationCheck(
                    $dailyAnalysisId,
                    'auto_without_appointment',
                    'Utilizzo auto senza appuntamento corrispondente',
                    'auto',
                    'calendario',
                    "Destinazione: {$autoUsage['destinazione']}",
                    'Nessun appuntamento corrispondente',
                    'warning',
                    'low',
                    [
                        'auto_usage' => $autoUsage,
                        'search_destination' => $autoUsage['destinazione']
                    ],
                    'Verificare se spostamento collegato ad appuntamento non registrato'
                );
            }
        }
        
        return $checks;
    }
    
    /**
     * Validazione coerenza timeline
     */
    private function validateTimelineConsistency($dailyAnalysisId, $data) {
        $checks = [];
        
        // Combina tutti gli eventi
        $allEvents = $this->combineAllEvents($data);
        
        // Ordina per orario
        usort($allEvents, function($a, $b) {
            return strtotime($a['start_time']) - strtotime($b['start_time']);
        });
        
        // Check 1: Sovrapposizioni impossibili
        for ($i = 0; $i < count($allEvents) - 1; $i++) {
            $current = $allEvents[$i];
            $next = $allEvents[$i + 1];
            
            if ($this->eventsOverlap($current, $next)) {
                $checks[] = $this->createValidationCheck(
                    $dailyAnalysisId,
                    'overlapping_events',
                    'Eventi sovrapposti nella timeline',
                    $current['source'],
                    $next['source'],
                    "Evento 1: {$current['start_time']} - {$current['end_time']}",
                    "Evento 2: {$next['start_time']} - {$next['end_time']}",
                    'failed',
                    'high',
                    [
                        'event_1' => $current,
                        'event_2' => $next,
                        'overlap_minutes' => $this->calculateOverlapMinutes($current, $next)
                    ],
                    'Correggere orari degli eventi sovrapposti'
                );
            }
        }
        
        // Check 2: Transizioni impossibili
        for ($i = 0; $i < count($allEvents) - 1; $i++) {
            $current = $allEvents[$i];
            $next = $allEvents[$i + 1];
            
            if ($this->isImpossibleTransition($current, $next)) {
                $checks[] = $this->createValidationCheck(
                    $dailyAnalysisId,
                    'impossible_transition',
                    'Transizione impossibile tra eventi',
                    $current['source'],
                    $next['source'],
                    "Da: {$current['location']} ({$current['end_time']})",
                    "A: {$next['location']} ({$next['start_time']})",
                    'failed',
                    'medium',
                    [
                        'current_event' => $current,
                        'next_event' => $next,
                        'travel_time_needed' => $this->calculateTravelTime($current['location'], $next['location']),
                        'available_time' => $this->calculateTimeBetween($current['end_time'], $next['start_time'])
                    ],
                    'Verificare logica degli spostamenti o correggere orari'
                );
            }
        }
        
        // Check 3: Violazioni orari lavorativi
        foreach ($allEvents as $event) {
            if ($this->violatesWorkingHours($event)) {
                $checks[] = $this->createValidationCheck(
                    $dailyAnalysisId,
                    'working_hours_violation',
                    'Evento fuori orario lavorativo',
                    $event['source'],
                    'business_rules',
                    "Evento: {$event['start_time']} - {$event['end_time']}",
                    "Orario lavorativo: {$this->businessRules['working_hours_start']} - {$this->businessRules['working_hours_end']}",
                    'warning',
                    'medium',
                    [
                        'event' => $event,
                        'working_hours' => $this->businessRules
                    ],
                    'Verificare se straordinario autorizzato o correggere orario'
                );
            }
        }
        
        return $checks;
    }
    
    /**
     * Validazione business rules
     */
    private function validateBusinessRules($dailyAnalysisId, $data) {
        $checks = [];
        
        // Check 1: Ore totali giornaliere
        $totalHours = $this->calculateTotalDailyHours($data);
        if ($totalHours > $this->businessRules['max_daily_hours']) {
            $checks[] = $this->createValidationCheck(
                $dailyAnalysisId,
                'excessive_daily_hours',
                'Ore giornaliere eccessive',
                'deepser',
                'business_rules',
                "Ore registrate: {$totalHours}h",
                "Limite aziendale: {$this->businessRules['max_daily_hours']}h",
                'warning',
                'medium',
                [
                    'total_hours' => $totalHours,
                    'max_allowed' => $this->businessRules['max_daily_hours'],
                    'excess_hours' => $totalHours - $this->businessRules['max_daily_hours']
                ],
                'Verificare se straordinario autorizzato'
            );
        }
        
        // Check 2: Pausa pranzo
        $hasLunchBreak = $this->hasAdequateLunchBreak($data);
        if (!$hasLunchBreak) {
            $checks[] = $this->createValidationCheck(
                $dailyAnalysisId,
                'missing_lunch_break',
                'Pausa pranzo non rispettata',
                'timeline',
                'business_rules',
                'Attività continue dalle 12:00 alle 15:00',
                'Richiesta pausa pranzo di almeno 30 minuti',
                'warning',
                'low',
                [
                    'lunch_period_start' => $this->businessRules['lunch_break_start'],
                    'lunch_period_end' => $this->businessRules['lunch_break_end']
                ],
                'Verificare se pausa pranzo effettuata ma non registrata'
            );
        }
        
        return $checks;
    }
    
    /**
     * Utility functions
     */
    private function createValidationCheck($dailyAnalysisId, $checkType, $description, $source1, $source2, $expected, $actual, $status, $severity, $evidence, $recommendation) {
        $check = [
            'daily_analysis_id' => $dailyAnalysisId,
            'check_type' => $checkType,
            'check_description' => $description,
            'source_1' => $source1,
            'source_2' => $source2,
            'expected_result' => $expected,
            'actual_result' => $actual,
            'check_status' => $status,
            'severity' => $severity,
            'evidence_data' => json_encode($evidence),
            'recommendation' => $recommendation,
            'confidence_score' => $this->calculateConfidenceScore($evidence)
        ];
        
        // Salva nel database
        $this->insertCrossValidationCheck($check);
        
        return $check;
    }
    
    private function insertCrossValidationCheck($check) {
        $stmt = $this->pdo->prepare("
            INSERT INTO cross_validation_checks 
            (daily_analysis_id, check_type, check_description, source_1, source_2, 
             expected_result, actual_result, check_status, severity, evidence_data, 
             recommendation, confidence_score)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $check['daily_analysis_id'],
            $check['check_type'],
            $check['check_description'],
            $check['source_1'],
            $check['source_2'],
            $check['expected_result'],
            $check['actual_result'],
            $check['check_status'],
            $check['severity'],
            $check['evidence_data'],
            $check['recommendation'],
            $check['confidence_score']
        ]);
    }
    
    private function findMatchingActivity($activities, $target, $criteria) {
        foreach ($activities as $activity) {
            $matches = true;
            
            if (in_array('client', $criteria)) {
                $matches = $matches && (stripos($activity['azienda'] ?? '', $target['client'] ?? '') !== false ||
                                      stripos($target['client'] ?? '', $activity['azienda'] ?? '') !== false);
            }
            
            if (in_array('time', $criteria)) {
                $matches = $matches && $this->timesOverlap(
                    $activity['start_time'], $activity['end_time'],
                    $target['start_time'], $target['end_time']
                );
            }
            
            if ($matches) return $activity;
        }
        
        return null;
    }
    
    private function findOverlappingEvents($events1, $events2, $toleranceMinutes = 0) {
        $overlapping = [];
        
        foreach ($events1 as $event1) {
            foreach ($events2 as $event2) {
                if ($this->eventsOverlap($event1, $event2, $toleranceMinutes)) {
                    $overlapping[] = $event2;
                }
            }
        }
        
        return $overlapping;
    }
    
    private function eventsOverlap($event1, $event2, $toleranceMinutes = 0) {
        if (!isset($event1['start_time'], $event1['end_time'], $event2['start_time'], $event2['end_time'])) {
            return false;
        }
        
        $start1 = strtotime($event1['start_time']) - ($toleranceMinutes * 60);
        $end1 = strtotime($event1['end_time']) + ($toleranceMinutes * 60);
        $start2 = strtotime($event2['start_time']);
        $end2 = strtotime($event2['end_time']);
        
        return ($start1 < $end2 && $start2 < $end1);
    }
    
    private function calculateOverlapMinutes($event1, $event2) {
        if (!$this->eventsOverlap($event1, $event2)) return 0;
        
        $start1 = strtotime($event1['start_time']);
        $end1 = strtotime($event1['end_time']);
        $start2 = strtotime($event2['start_time']);
        $end2 = strtotime($event2['end_time']);
        
        $overlapStart = max($start1, $start2);
        $overlapEnd = min($end1, $end2);
        
        return max(0, ($overlapEnd - $overlapStart) / 60);
    }
    
    private function calculateDurationMinutes($startTime, $endTime) {
        if (!$startTime || !$endTime) return 0;
        return max(0, (strtotime($endTime) - strtotime($startTime)) / 60);
    }
    
    private function parseHoursToMinutes($hoursString) {
        return is_numeric($hoursString) ? floatval($hoursString) * 60 : 0;
    }
    
    private function estimateDistance($destination) {
        // Database distanze sede-clienti (placeholder)
        $distances = [
            'Settala' => 15,
            'Milano' => 25,
            'Bergamo' => 45,
            'Brescia' => 60
        ];
        
        foreach ($distances as $location => $km) {
            if (stripos($destination, $location) !== false) {
                return $km;
            }
        }
        
        return 20; // Default
    }
    
    private function calculateConfidenceScore($evidence) {
        // Calcola confidence score basato su qualità evidenze
        $baseScore = 75;
        
        if (isset($evidence['exact_match']) && $evidence['exact_match']) {
            $baseScore += 20;
        }
        
        if (isset($evidence['time_overlap']) && $evidence['time_overlap'] > 80) {
            $baseScore += 10;
        }
        
        return min(100, max(0, $baseScore));
    }
    
    private function countTotalChecks($results) {
        $total = 0;
        foreach ($results as $checkGroup) {
            $total += count($checkGroup);
        }
        return $total;
    }
    
    private function countFailedChecks($results) {
        $failed = 0;
        foreach ($results as $checkGroup) {
            foreach ($checkGroup as $check) {
                if ($check['check_status'] === 'failed') {
                    $failed++;
                }
            }
        }
        return $failed;
    }
    
    private function log($message) {
        error_log("[CrossValidator] " . $message);
        echo $message . "\n";
    }
    
    // Additional helper methods (placeholder implementations)
    private function findRelatedAutoUsage($activity, $autoData) { return null; }
    private function findMatchingRemoteActivity($session, $activities) { return null; }
    private function findRelatedTeamViewerSessions($activity, $sessions) { return []; }
    private function combineAllEvents($data) { return []; }
    private function isImpossibleTransition($event1, $event2) { return false; }
    private function violatesWorkingHours($event) { return false; }
    private function calculateTotalDailyHours($data) { return 8; }
    private function hasAdequateLunchBreak($data) { return true; }
    private function timesOverlap($start1, $end1, $start2, $end2) { return false; }
    private function calculateTravelTime($location1, $location2) { return 30; }
    private function calculateTimeBetween($time1, $time2) { return 60; }
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
        
        $validator = new CrossValidator($pdo);
        
        // Test con dati sample
        $sampleData = [
            'deepser' => [
                ['azienda' => 'Test Client', 'start_time' => '2025-01-17 09:00:00', 'end_time' => '2025-01-17 11:00:00', 'location_type' => 'remote']
            ],
            'calendario' => [],
            'auto' => [
                ['destinazione' => 'Test Client', 'ore' => '2', 'start_time' => '2025-01-17 09:30:00', 'end_time' => '2025-01-17 11:30:00']
            ],
            'teamviewer' => [],
            'timbrature' => []
        ];
        
        $result = $validator->performCrossValidation(1, $sampleData);
        
        echo "=== RISULTATO CROSS-VALIDATION ===\n";
        print_r($result);
        
    } catch (Exception $e) {
        echo "Errore: " . $e->getMessage() . "\n";
    }
}
?>