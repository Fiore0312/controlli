<?php
/**
 * TIMELINE BUILDER - Ricostruzione Intelligente Timeline Giornaliera
 * Sistema avanzato per creare timeline coerenti da fonti dati multiple
 */

class TimelineBuilder {
    
    private $pdo;
    private $config;
    private $timelineRules;
    private $confidenceThresholds;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        
        $this->config = [
            'min_activity_minutes' => 15,
            'gap_merge_threshold' => 10,
            'travel_time_default' => 30,
            'confidence_threshold' => 70,
            'auto_correction_enabled' => true
        ];
        
        $this->timelineRules = [
            'working_hours' => ['09:00:00', '18:00:00'],
            'lunch_period' => ['13:00:00', '14:00:00'],
            'max_event_duration' => 480, // 8 ore
            'min_break_between_clients' => 15
        ];
        
        $this->confidenceThresholds = [
            'high' => 90,
            'medium' => 70,
            'low' => 50
        ];
    }
    
    /**
     * Costruisce timeline intelligente da dati multi-fonte
     */
    public function buildIntelligentTimeline($dailyAnalysisId, $data, $options = []) {
        try {
            $this->log("🏗️ Inizio costruzione timeline intelligente per analisi ID: $dailyAnalysisId");
            
            // 1. Pre-processing e pulizia dati
            $cleanedData = $this->preprocessData($data);
            
            // 2. Estrazione eventi candidati
            $candidateEvents = $this->extractCandidateEvents($cleanedData);
            
            // 3. Risoluzione conflitti temporali
            $resolvedEvents = $this->resolveTemporalConflicts($candidateEvents);
            
            // 4. Integrazione intelligente multi-fonte
            $integratedEvents = $this->integrateMultiSourceEvents($resolvedEvents);
            
            // 5. Inferenza eventi mancanti
            $completedTimeline = $this->inferMissingEvents($integratedEvents, $cleanedData);
            
            // 6. Validazione coerenza finale
            $validatedTimeline = $this->validateTimelineCoherence($completedTimeline);
            
            // 7. Calcolo confidence scores
            $finalTimeline = $this->calculateEventConfidenceScores($validatedTimeline);
            
            // 8. Salvataggio nel database
            $this->saveTimelineToDatabase($dailyAnalysisId, $finalTimeline);
            
            $this->log("✅ Timeline intelligente completata");
            
            return [
                'success' => true,
                'timeline_events' => $finalTimeline,
                'total_events' => count($finalTimeline),
                'timeline_quality_score' => $this->calculateTimelineQuality($finalTimeline),
                'coverage_percentage' => $this->calculateTimelineCoverage($finalTimeline),
                'confidence_distribution' => $this->analyzeConfidenceDistribution($finalTimeline)
            ];
            
        } catch (Exception $e) {
            $this->log("❌ Errore costruzione timeline: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Pre-processing e normalizzazione dati
     */
    private function preprocessData($data) {
        $cleaned = [
            'deepser' => [],
            'calendario' => [],
            'auto' => [],
            'teamviewer' => [],
            'timbrature' => []
        ];
        
        // Pulizia e normalizzazione Deepser
        foreach ($data['deepser'] as $activity) {
            if ($this->isValidActivity($activity)) {
                $cleaned['deepser'][] = $this->normalizeDeepserActivity($activity);
            }
        }
        
        // Pulizia e normalizzazione Auto
        foreach ($data['auto'] as $usage) {
            if ($this->isValidAutoUsage($usage)) {
                $cleaned['auto'][] = $this->normalizeAutoUsage($usage);
            }
        }
        
        // Pulizia e normalizzazione TeamViewer
        foreach ($data['teamviewer'] as $session) {
            if ($this->isValidTeamViewerSession($session)) {
                $cleaned['teamviewer'][] = $this->normalizeTeamViewerSession($session);
            }
        }
        
        // Pulizia altri dati...
        $cleaned['calendario'] = $data['calendario'];
        $cleaned['timbrature'] = $data['timbrature'];
        
        return $cleaned;
    }
    
    /**
     * Estrazione eventi candidati da tutte le fonti
     */
    private function extractCandidateEvents($data) {
        $events = [];
        
        // Eventi da Deepser
        foreach ($data['deepser'] as $activity) {
            $events[] = [
                'source' => 'deepser',
                'type' => 'activity',
                'start_time' => $activity['start_time'],
                'end_time' => $activity['end_time'],
                'duration_minutes' => $this->calculateDuration($activity['start_time'], $activity['end_time']),
                'client_name' => $activity['azienda'],
                'description' => $activity['descrizione'],
                'location_type' => $activity['location_type'],
                'confidence' => 95, // Deepser è fonte primaria
                'source_data' => $activity,
                'validation_status' => 'primary'
            ];
        }
        
        // Eventi da Auto
        foreach ($data['auto'] as $usage) {
            $events[] = [
                'source' => 'auto',
                'type' => 'travel',
                'start_time' => $usage['start_time'],
                'end_time' => $usage['end_time'],
                'duration_minutes' => $this->parseHoursToMinutes($usage['ore']),
                'client_name' => $usage['destinazione'],
                'description' => "Viaggio: {$usage['destinazione']}",
                'location_type' => 'travel',
                'confidence' => 85,
                'source_data' => $usage,
                'validation_status' => 'supporting'
            ];
        }
        
        // Eventi da TeamViewer
        foreach ($data['teamviewer'] as $session) {
            $events[] = [
                'source' => 'teamviewer',
                'type' => 'remote_session',
                'start_time' => $session['start_time'],
                'end_time' => date('Y-m-d H:i:s', strtotime($session['start_time']) + ($session['duration_minutes'] * 60)),
                'duration_minutes' => $session['duration_minutes'],
                'client_name' => $session['computer_remoto'],
                'description' => "Sessione remota: {$session['utente']}",
                'location_type' => 'remote',
                'confidence' => 90,
                'source_data' => $session,
                'validation_status' => 'supporting'
            ];
        }
        
        // Ordina per orario di inizio
        usort($events, function($a, $b) {
            return strtotime($a['start_time']) - strtotime($b['start_time']);
        });
        
        return $events;
    }
    
    /**
     * Risoluzione conflitti temporali tra eventi
     */
    private function resolveTemporalConflicts($events) {
        $resolved = [];
        $conflicts = [];
        
        for ($i = 0; $i < count($events); $i++) {
            $currentEvent = $events[$i];
            $hasConflict = false;
            
            // Cerca conflitti con eventi già risolti
            foreach ($resolved as $resolvedEvent) {
                if ($this->eventsOverlap($currentEvent, $resolvedEvent)) {
                    $conflicts[] = [
                        'event1' => $currentEvent,
                        'event2' => $resolvedEvent,
                        'resolution_strategy' => $this->determineResolutionStrategy($currentEvent, $resolvedEvent)
                    ];
                    $hasConflict = true;
                    break;
                }
            }
            
            if (!$hasConflict) {
                $resolved[] = $currentEvent;
            } else {
                // Applica strategia di risoluzione
                $resolvedConflict = $this->applyConflictResolution($conflicts[count($conflicts) - 1]);
                
                if ($resolvedConflict) {
                    // Sostituisci o modifica eventi in conflitto
                    $resolved = $this->updateResolvedEvents($resolved, $resolvedConflict);
                }
            }
        }
        
        return $resolved;
    }
    
    /**
     * Integrazione intelligente eventi multi-fonte
     */
    private function integrateMultiSourceEvents($events) {
        $integrated = [];
        $groupedByTime = $this->groupEventsByTimeProximity($events);
        
        foreach ($groupedByTime as $timeGroup) {
            if (count($timeGroup) == 1) {
                // Evento singolo, mantieni così com'è
                $integrated[] = $timeGroup[0];
            } else {
                // Eventi multipli nello stesso periodo temporale
                $mergedEvent = $this->mergeRelatedEvents($timeGroup);
                if ($mergedEvent) {
                    $integrated[] = $mergedEvent;
                }
            }
        }
        
        return $integrated;
    }
    
    /**
     * Inferenza eventi mancanti nella timeline
     */
    private function inferMissingEvents($events, $originalData) {
        $timeline = $events;
        
        // 1. Inferisci viaggi mancanti
        $timeline = $this->inferMissingTravelEvents($timeline, $originalData);
        
        // 2. Inferisci pause e break
        $timeline = $this->inferBreakEvents($timeline);
        
        // 3. Inferisci attività remote da TeamViewer orfane
        $timeline = $this->inferRemoteActivitiesFromTeamViewer($timeline, $originalData);
        
        // 4. Inferisci preparazione/chiusura attività
        $timeline = $this->inferPreparationEvents($timeline);
        
        // Riordina timeline
        usort($timeline, function($a, $b) {
            return strtotime($a['start_time']) - strtotime($b['start_time']);
        });
        
        return $timeline;
    }
    
    /**
     * Inferisce eventi di viaggio mancanti
     */
    private function inferMissingTravelEvents($timeline, $originalData) {
        $enhanced = $timeline;
        
        for ($i = 0; $i < count($timeline) - 1; $i++) {
            $current = $timeline[$i];
            $next = $timeline[$i + 1];
            
            // Se entrambi sono onsite ma in location diverse, serve un viaggio
            if ($current['location_type'] === 'onsite' && 
                $next['location_type'] === 'onsite' && 
                $current['client_name'] !== $next['client_name']) {
                
                $timeBetween = $this->calculateTimeBetween($current['end_time'], $next['start_time']);
                $estimatedTravelTime = $this->estimateTravelTime($current['client_name'], $next['client_name']);
                
                if ($timeBetween >= $estimatedTravelTime && $timeBetween <= ($estimatedTravelTime * 2)) {
                    // Crea evento viaggio inferito
                    $travelEvent = [
                        'source' => 'inferred',
                        'type' => 'travel',
                        'start_time' => $current['end_time'],
                        'end_time' => $next['start_time'],
                        'duration_minutes' => $timeBetween,
                        'client_name' => "Da {$current['client_name']} a {$next['client_name']}",
                        'description' => 'Viaggio inferito tra clienti',
                        'location_type' => 'travel',
                        'confidence' => 75,
                        'validation_status' => 'inferred',
                        'inference_reason' => 'travel_between_onsite_clients'
                    ];
                    
                    $enhanced[] = $travelEvent;
                }
            }
        }
        
        return $enhanced;
    }
    
    /**
     * Inferisce eventi break/pause
     */
    private function inferBreakEvents($timeline) {
        $enhanced = $timeline;
        
        for ($i = 0; $i < count($timeline) - 1; $i++) {
            $current = $timeline[$i];
            $next = $timeline[$i + 1];
            
            $gap = $this->calculateTimeBetween($current['end_time'], $next['start_time']);
            
            // Gap significativo che potrebbe essere una pausa
            if ($gap >= 30 && $gap <= 120) { // Tra 30 minuti e 2 ore
                $startTime = date('H:i', strtotime($current['end_time']));
                $endTime = date('H:i', strtotime($next['start_time']));
                
                // Determina tipo pausa
                $breakType = $this->determineBreakType($startTime, $endTime, $gap);
                
                if ($breakType) {
                    $breakEvent = [
                        'source' => 'inferred',
                        'type' => 'break',
                        'start_time' => $current['end_time'],
                        'end_time' => $next['start_time'],
                        'duration_minutes' => $gap,
                        'client_name' => null,
                        'description' => "Pausa {$breakType}",
                        'location_type' => 'office',
                        'confidence' => 80,
                        'validation_status' => 'inferred',
                        'inference_reason' => "break_{$breakType}",
                        'break_type' => $breakType
                    ];
                    
                    $enhanced[] = $breakEvent;
                }
            }
        }
        
        return $enhanced;
    }
    
    /**
     * Validazione coerenza finale timeline
     */
    private function validateTimelineCoherence($timeline) {
        $validated = [];
        
        foreach ($timeline as $event) {
            $validationResult = $this->validateEvent($event, $timeline);
            
            if ($validationResult['is_valid']) {
                $event['validation_score'] = $validationResult['score'];
                $event['validation_notes'] = $validationResult['notes'];
                $validated[] = $event;
            } else {
                // Tentativo di correzione automatica
                $correctedEvent = $this->attemptEventCorrection($event, $validationResult);
                if ($correctedEvent) {
                    $correctedEvent['validation_score'] = 60; // Score ridotto per correzione
                    $correctedEvent['validation_notes'] = 'Auto-corrected: ' . $validationResult['notes'];
                    $validated[] = $correctedEvent;
                }
                // Altrimenti scarta evento
            }
        }
        
        return $validated;
    }
    
    /**
     * Calcolo confidence scores per ogni evento
     */
    private function calculateEventConfidenceScores($timeline) {
        foreach ($timeline as &$event) {
            $baseConfidence = $event['confidence'] ?? 50;
            
            // Fattori che aumentano confidence
            if ($event['source'] === 'deepser') $baseConfidence += 10;
            if (isset($event['validation_score']) && $event['validation_score'] > 80) $baseConfidence += 10;
            if ($event['duration_minutes'] >= 30) $baseConfidence += 5;
            
            // Fattori che riducono confidence
            if ($event['source'] === 'inferred') $baseConfidence -= 15;
            if (isset($event['validation_notes']) && strpos($event['validation_notes'], 'corrected') !== false) $baseConfidence -= 10;
            
            // Cross-validation con altre fonti
            $crossValidationBonus = $this->calculateCrossValidationBonus($event, $timeline);
            $baseConfidence += $crossValidationBonus;
            
            $event['final_confidence'] = max(0, min(100, $baseConfidence));
            $event['confidence_level'] = $this->getConfidenceLevel($event['final_confidence']);
        }
        
        return $timeline;
    }
    
    /**
     * Salvataggio timeline nel database
     */
    private function saveTimelineToDatabase($dailyAnalysisId, $timeline) {
        // Elimina eventi esistenti per questa analisi
        $stmt = $this->pdo->prepare("DELETE FROM timeline_events WHERE daily_analysis_id = ?");
        $stmt->execute([$dailyAnalysisId]);
        
        // Inserisci nuovi eventi
        $stmt = $this->pdo->prepare("
            INSERT INTO timeline_events 
            (daily_analysis_id, event_source, event_type, start_time, end_time, 
             duration_minutes, client_name, activity_description, location_type, 
             source_record_id, source_data, is_validated, validation_notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($timeline as $event) {
            $stmt->execute([
                $dailyAnalysisId,
                $event['source'],
                $event['type'],
                $event['start_time'],
                $event['end_time'],
                $event['duration_minutes'],
                $event['client_name'],
                $event['description'],
                $event['location_type'],
                $event['source_data']['id'] ?? null,
                json_encode($event),
                ($event['final_confidence'] >= $this->confidenceThresholds['medium']) ? 1 : 0,
                $event['validation_notes'] ?? null
            ]);
        }
    }
    
    /**
     * Utility functions
     */
    private function isValidActivity($activity) {
        return !empty($activity['start_time']) && !empty($activity['azienda']);
    }
    
    private function isValidAutoUsage($usage) {
        return !empty($usage['destinazione']) && !empty($usage['ore']);
    }
    
    private function isValidTeamViewerSession($session) {
        return !empty($session['start_time']) && $session['duration_minutes'] >= $this->config['min_activity_minutes'];
    }
    
    private function normalizeDeepserActivity($activity) {
        return [
            'id' => $activity['id'],
            'azienda' => trim($activity['azienda']),
            'descrizione' => trim($activity['descrizione']),
            'start_time' => $this->normalizeDateTime($activity['start_time']),
            'end_time' => $this->normalizeDateTime($activity['end_time']),
            'location_type' => $activity['location_type'] ?? 'remote'
        ];
    }
    
    private function normalizeAutoUsage($usage) {
        $estimatedStart = $this->estimateAutoStartTime($usage);
        $estimatedEnd = $this->estimateAutoEndTime($usage, $estimatedStart);
        
        return [
            'destinazione' => trim($usage['destinazione']),
            'ore' => $usage['ore'],
            'start_time' => $estimatedStart,
            'end_time' => $estimatedEnd
        ];
    }
    
    private function normalizeTeamViewerSession($session) {
        return [
            'computer_remoto' => trim($session['computer_remoto']),
            'utente' => trim($session['utente']),
            'start_time' => $this->normalizeDateTime($session['start_time']),
            'duration_minutes' => $session['duration_minutes']
        ];
    }
    
    private function normalizeDateTime($dateTime) {
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dateTime)) {
            return $dateTime;
        }
        
        // Tentativi di parsing diversi formati
        $formats = ['Y-m-d H:i:s', 'd/m/Y H:i:s', 'm/d/Y H:i:s'];
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateTime);
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        
        return null;
    }
    
    private function calculateDuration($startTime, $endTime) {
        if (!$startTime || !$endTime) return 0;
        return max(0, (strtotime($endTime) - strtotime($startTime)) / 60);
    }
    
    private function parseHoursToMinutes($hours) {
        return is_numeric($hours) ? floatval($hours) * 60 : 0;
    }
    
    private function eventsOverlap($event1, $event2) {
        return (strtotime($event1['start_time']) < strtotime($event2['end_time']) && 
                strtotime($event2['start_time']) < strtotime($event1['end_time']));
    }
    
    private function calculateTimeBetween($time1, $time2) {
        return abs(strtotime($time2) - strtotime($time1)) / 60;
    }
    
    private function estimateTravelTime($location1, $location2) {
        // Logica di stima tempo viaggio basata su database distanze
        return 30; // Placeholder
    }
    
    private function determineBreakType($startTime, $endTime, $duration) {
        if ($startTime >= '12:00' && $endTime <= '15:00' && $duration >= 30) {
            return 'pranzo';
        } elseif ($duration >= 15 && $duration <= 30) {
            return 'coffee';
        } elseif ($duration > 60) {
            return 'extended';
        }
        return null;
    }
    
    private function calculateTimelineQuality($timeline) {
        if (empty($timeline)) return 0;
        
        $totalConfidence = array_sum(array_column($timeline, 'final_confidence'));
        return $totalConfidence / count($timeline);
    }
    
    private function calculateTimelineCoverage($timeline) {
        if (empty($timeline)) return 0;
        
        $totalMinutes = array_sum(array_column($timeline, 'duration_minutes'));
        $workingMinutes = 8 * 60; // 8 ore lavorative
        
        return min(100, ($totalMinutes / $workingMinutes) * 100);
    }
    
    private function analyzeConfidenceDistribution($timeline) {
        $distribution = ['high' => 0, 'medium' => 0, 'low' => 0];
        
        foreach ($timeline as $event) {
            $level = $event['confidence_level'] ?? 'low';
            $distribution[$level]++;
        }
        
        return $distribution;
    }
    
    private function getConfidenceLevel($confidence) {
        if ($confidence >= $this->confidenceThresholds['high']) return 'high';
        if ($confidence >= $this->confidenceThresholds['medium']) return 'medium';
        return 'low';
    }
    
    private function log($message) {
        error_log("[TimelineBuilder] " . $message);
        echo $message . "\n";
    }
    
    // Placeholder implementations for complex methods
    private function determineResolutionStrategy($event1, $event2) { return 'merge'; }
    private function applyConflictResolution($conflict) { return $conflict['event1']; }
    private function updateResolvedEvents($resolved, $resolvedConflict) { return $resolved; }
    private function groupEventsByTimeProximity($events) { return array_map(function($e) { return [$e]; }, $events); }
    private function mergeRelatedEvents($timeGroup) { return $timeGroup[0]; }
    private function inferRemoteActivitiesFromTeamViewer($timeline, $data) { return $timeline; }
    private function inferPreparationEvents($timeline) { return $timeline; }
    private function validateEvent($event, $timeline) { return ['is_valid' => true, 'score' => 90, 'notes' => 'Valid']; }
    private function attemptEventCorrection($event, $validation) { return $event; }
    private function calculateCrossValidationBonus($event, $timeline) { return 5; }
    private function estimateAutoStartTime($usage) { return date('Y-m-d 09:00:00'); }
    private function estimateAutoEndTime($usage, $start) { return date('Y-m-d H:i:s', strtotime($start) + ($this->parseHoursToMinutes($usage['ore']) * 60)); }
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
        
        $builder = new TimelineBuilder($pdo);
        
        // Test con dati sample
        $sampleData = [
            'deepser' => [
                [
                    'id' => 'T001',
                    'azienda' => 'Test Client',
                    'descrizione' => 'Manutenzione server',
                    'start_time' => '2025-01-17 09:00:00',
                    'end_time' => '2025-01-17 11:00:00',
                    'location_type' => 'onsite'
                ]
            ],
            'auto' => [
                [
                    'destinazione' => 'Test Client',
                    'ore' => '0.5',
                    'start_time' => '2025-01-17 08:30:00',
                    'end_time' => '2025-01-17 09:00:00'
                ]
            ],
            'teamviewer' => [],
            'calendario' => [],
            'timbrature' => []
        ];
        
        $result = $builder->buildIntelligentTimeline(1, $sampleData);
        
        echo "=== RISULTATO TIMELINE BUILDER ===\n";
        print_r($result);
        
    } catch (Exception $e) {
        echo "Errore: " . $e->getMessage() . "\n";
    }
}
?>