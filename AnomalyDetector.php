<?php
/**
 * ANOMALY DETECTOR - Sistema AI per Pattern Recognition Anomalie
 * Rilevamento intelligente di pattern anomali usando algoritmi ML e regole business
 */

class AnomalyDetector {
    
    private $pdo;
    private $config;
    private $patterns;
    private $historicalData;
    private $mlModels;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        
        $this->config = [
            'learning_window_days' => 30,
            'confidence_threshold' => 75,
            'pattern_min_occurrences' => 3,
            'anomaly_sensitivity' => 0.8,
            'auto_learning_enabled' => true
        ];
        
        $this->patterns = [
            'behavioral' => [],
            'temporal' => [],
            'client_specific' => [],
            'productivity' => [],
            'travel' => []
        ];
        
        $this->initializeDetectionRules();
    }
    
    /**
     * Inizializza regole di rilevamento
     */
    private function initializeDetectionRules() {
        $this->patterns = [
            'behavioral' => [
                'consistent_late_start' => [
                    'description' => 'Ritardo sistematico inizio attività',
                    'threshold' => 0.7, // 70% dei giorni
                    'severity' => 'medium'
                ],
                'frequent_long_breaks' => [
                    'description' => 'Pause prolungate frequenti',
                    'threshold' => 90, // minuti
                    'severity' => 'low'
                ],
                'weekend_activity_pattern' => [
                    'description' => 'Attività sistematiche nel weekend',
                    'threshold' => 2, // giorni consecutivi
                    'severity' => 'medium'
                ]
            ],
            'temporal' => [
                'impossible_travel_times' => [
                    'description' => 'Tempi di viaggio fisicamente impossibili',
                    'threshold' => 0.5, // rapporto tempo/distanza
                    'severity' => 'high'
                ],
                'overlapping_activities' => [
                    'description' => 'Attività sovrapposte ricorrenti',
                    'threshold' => 3, // occorrenze
                    'severity' => 'critical'
                ],
                'micro_breaks_pattern' => [
                    'description' => 'Pattern di micro-pause sospette',
                    'threshold' => 10, // numero per giorno
                    'severity' => 'low'
                ]
            ],
            'client_specific' => [
                'duration_deviation' => [
                    'description' => 'Durata attività anomala per cliente specifico',
                    'threshold' => 2.0, // deviazioni standard
                    'severity' => 'medium'
                ],
                'frequency_change' => [
                    'description' => 'Cambio drastico frequenza interventi',
                    'threshold' => 50, // percentuale variazione
                    'severity' => 'medium'
                ]
            ],
            'productivity' => [
                'declining_efficiency' => [
                    'description' => 'Trend decrescente produttività',
                    'threshold' => -15, // percentuale calo
                    'severity' => 'medium'
                ],
                'inconsistent_reporting' => [
                    'description' => 'Incoerenza sistematica nei report',
                    'threshold' => 0.6, // indice coerenza
                    'severity' => 'high'
                ]
            ]
        ];
    }
    
    /**
     * Analizza anomalie per tecnico usando AI
     */
    public function detectAnomaliesForTechnician($tecnicoId, $analysisDate, $currentData) {
        try {
            $this->log("🤖 Inizio rilevamento anomalie AI per tecnico ID: $tecnicoId");
            
            // 1. Carica dati storici per pattern learning
            $historicalData = $this->loadHistoricalData($tecnicoId);
            
            // 2. Applica algoritmi pattern recognition
            $patternAnalysis = $this->performPatternAnalysis($tecnicoId, $currentData, $historicalData);
            
            // 3. Rilevamento anomalie comportamentali
            $behavioralAnomalies = $this->detectBehavioralAnomalies($tecnicoId, $currentData, $historicalData);
            
            // 4. Rilevamento anomalie temporali
            $temporalAnomalies = $this->detectTemporalAnomalies($currentData, $historicalData);
            
            // 5. Rilevamento anomalie cliente-specifiche
            $clientAnomalies = $this->detectClientSpecificAnomalies($tecnicoId, $currentData, $historicalData);
            
            // 6. Analisi trend produttività
            $productivityAnomalies = $this->analyzeProductivityTrends($tecnicoId, $currentData, $historicalData);
            
            // 7. Machine Learning predictions
            $mlPredictions = $this->applyMLModels($tecnicoId, $currentData, $historicalData);
            
            // 8. Consolidamento risultati
            $consolidatedAnomalies = $this->consolidateAnomalyResults([
                'behavioral' => $behavioralAnomalies,
                'temporal' => $temporalAnomalies,
                'client_specific' => $clientAnomalies,
                'productivity' => $productivityAnomalies,
                'ml_predictions' => $mlPredictions
            ]);
            
            // 9. Scoring e ranking anomalie
            $rankedAnomalies = $this->rankAnomaliesByImpact($consolidatedAnomalies);
            
            // 10. Aggiornamento modelli (learning automatico)
            if ($this->config['auto_learning_enabled']) {
                $this->updateLearningModels($tecnicoId, $currentData, $rankedAnomalies);
            }
            
            $this->log("✅ Rilevamento anomalie AI completato");
            
            return [
                'success' => true,
                'anomalies_detected' => count($rankedAnomalies),
                'anomalies' => $rankedAnomalies,
                'confidence_distribution' => $this->analyzeConfidenceDistribution($rankedAnomalies),
                'pattern_insights' => $patternAnalysis,
                'risk_score' => $this->calculateOverallRiskScore($rankedAnomalies)
            ];
            
        } catch (Exception $e) {
            $this->log("❌ Errore rilevamento anomalie: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Pattern analysis usando algoritmi statistici
     */
    private function performPatternAnalysis($tecnicoId, $currentData, $historicalData) {
        $patterns = [
            'working_hours_pattern' => $this->analyzeWorkingHoursPattern($currentData, $historicalData),
            'client_rotation_pattern' => $this->analyzeClientRotationPattern($currentData, $historicalData),
            'activity_distribution_pattern' => $this->analyzeActivityDistribution($currentData, $historicalData),
            'break_pattern' => $this->analyzeBreakPatterns($currentData, $historicalData)
        ];
        
        return $patterns;
    }
    
    /**
     * Rilevamento anomalie comportamentali
     */
    private function detectBehavioralAnomalies($tecnicoId, $currentData, $historicalData) {
        $anomalies = [];
        
        // 1. Analisi ritardi sistematici
        $lateStartPattern = $this->analyzeLateStartPattern($currentData, $historicalData);
        if ($lateStartPattern['is_anomaly']) {
            $anomalies[] = $this->createAnomalyRecord('behavioral', 'consistent_late_start', [
                'pattern_strength' => $lateStartPattern['strength'],
                'average_delay_minutes' => $lateStartPattern['avg_delay'],
                'frequency_percentage' => $lateStartPattern['frequency'],
                'confidence' => $lateStartPattern['confidence']
            ]);
        }
        
        // 2. Analisi pattern pause
        $breakPattern = $this->analyzeBreakAnomalies($currentData, $historicalData);
        if ($breakPattern['is_anomaly']) {
            $anomalies[] = $this->createAnomalyRecord('behavioral', 'unusual_break_pattern', [
                'pattern_type' => $breakPattern['type'],
                'deviation_score' => $breakPattern['deviation'],
                'confidence' => $breakPattern['confidence']
            ]);
        }
        
        // 3. Analisi consistency working style
        $workStyleConsistency = $this->analyzeWorkStyleConsistency($currentData, $historicalData);
        if (!$workStyleConsistency['is_consistent']) {
            $anomalies[] = $this->createAnomalyRecord('behavioral', 'work_style_change', [
                'consistency_score' => $workStyleConsistency['score'],
                'changed_aspects' => $workStyleConsistency['changes'],
                'confidence' => $workStyleConsistency['confidence']
            ]);
        }
        
        return $anomalies;
    }
    
    /**
     * Rilevamento anomalie temporali
     */
    private function detectTemporalAnomalies($currentData, $historicalData) {
        $anomalies = [];
        
        // 1. Viaggi impossibili
        $impossibleTravels = $this->detectImpossibleTravels($currentData);
        foreach ($impossibleTravels as $travel) {
            $anomalies[] = $this->createAnomalyRecord('temporal', 'impossible_travel', $travel);
        }
        
        // 2. Sovrapposizioni ricorrenti
        $overlaps = $this->detectRecurringOverlaps($currentData, $historicalData);
        foreach ($overlaps as $overlap) {
            $anomalies[] = $this->createAnomalyRecord('temporal', 'recurring_overlap', $overlap);
        }
        
        // 3. Micro-gaps pattern
        $microGaps = $this->detectMicroGapsPattern($currentData);
        if ($microGaps['is_anomaly']) {
            $anomalies[] = $this->createAnomalyRecord('temporal', 'micro_gaps_pattern', $microGaps);
        }
        
        return $anomalies;
    }
    
    /**
     * Rilevamento anomalie cliente-specifiche
     */
    private function detectClientSpecificAnomalies($tecnicoId, $currentData, $historicalData) {
        $anomalies = [];
        
        // Analizza ogni cliente
        $clients = $this->extractUniqueClients($currentData);
        
        foreach ($clients as $client) {
            $clientHistory = $this->getClientHistory($tecnicoId, $client, $historicalData);
            $currentClientData = $this->filterDataByClient($currentData, $client);
            
            // 1. Durata anomala per cliente
            $durationAnomaly = $this->detectClientDurationAnomaly($currentClientData, $clientHistory);
            if ($durationAnomaly['is_anomaly']) {
                $anomalies[] = $this->createAnomalyRecord('client_specific', 'duration_anomaly', [
                    'client' => $client,
                    'expected_duration' => $durationAnomaly['expected'],
                    'actual_duration' => $durationAnomaly['actual'],
                    'deviation_factor' => $durationAnomaly['deviation'],
                    'confidence' => $durationAnomaly['confidence']
                ]);
            }
            
            // 2. Frequenza interventi anomala
            $frequencyAnomaly = $this->detectClientFrequencyAnomaly($currentClientData, $clientHistory);
            if ($frequencyAnomaly['is_anomaly']) {
                $anomalies[] = $this->createAnomalyRecord('client_specific', 'frequency_anomaly', [
                    'client' => $client,
                    'historical_frequency' => $frequencyAnomaly['historical'],
                    'current_frequency' => $frequencyAnomaly['current'],
                    'change_percentage' => $frequencyAnomaly['change_pct'],
                    'confidence' => $frequencyAnomaly['confidence']
                ]);
            }
        }
        
        return $anomalies;
    }
    
    /**
     * Analisi trend produttività
     */
    private function analyzeProductivityTrends($tecnicoId, $currentData, $historicalData) {
        $anomalies = [];
        
        // 1. Calcola metriche produttività
        $currentProductivity = $this->calculateProductivityMetrics($currentData);
        $historicalProductivity = $this->calculateHistoricalProductivityAverage($historicalData);
        
        // 2. Trend analysis
        $trendAnalysis = $this->analyzeProductivityTrend($historicalData);
        
        if ($trendAnalysis['is_declining']) {
            $anomalies[] = $this->createAnomalyRecord('productivity', 'declining_trend', [
                'trend_slope' => $trendAnalysis['slope'],
                'decline_percentage' => $trendAnalysis['decline_pct'],
                'duration_weeks' => $trendAnalysis['duration'],
                'confidence' => $trendAnalysis['confidence']
            ]);
        }
        
        // 3. Efficiency anomalies
        $efficiencyAnomaly = $this->detectEfficiencyAnomaly($currentProductivity, $historicalProductivity);
        if ($efficiencyAnomaly['is_anomaly']) {
            $anomalies[] = $this->createAnomalyRecord('productivity', 'efficiency_anomaly', $efficiencyAnomaly);
        }
        
        return $anomalies;
    }
    
    /**
     * Applicazione modelli Machine Learning
     */
    private function applyMLModels($tecnicoId, $currentData, $historicalData) {
        $predictions = [];
        
        // 1. Time Series Anomaly Detection
        $timeSeriesAnomalies = $this->detectTimeSeriesAnomalies($currentData, $historicalData);
        
        // 2. Clustering Analysis per pattern recognition
        $clusterAnomalies = $this->performClusteringAnalysis($currentData, $historicalData);
        
        // 3. Regression model per prediction
        $regressionPredictions = $this->applyRegressionModel($currentData, $historicalData);
        
        // 4. Neural Network pattern detection (simulato)
        $neuralNetworkInsights = $this->simulateNeuralNetworkAnalysis($currentData, $historicalData);
        
        return [
            'time_series' => $timeSeriesAnomalies,
            'clustering' => $clusterAnomalies,
            'regression' => $regressionPredictions,
            'neural_network' => $neuralNetworkInsights
        ];
    }
    
    /**
     * Consolidamento risultati anomalie
     */
    private function consolidateAnomalyResults($results) {
        $consolidated = [];
        
        foreach ($results as $category => $anomalies) {
            foreach ($anomalies as $anomaly) {
                $anomaly['category'] = $category;
                $anomaly['composite_score'] = $this->calculateCompositeScore($anomaly);
                $consolidated[] = $anomaly;
            }
        }
        
        return $consolidated;
    }
    
    /**
     * Ranking anomalie per impatto
     */
    private function rankAnomaliesByImpact($anomalies) {
        // Ordina per composite score (impatto + confidenza + severità)
        usort($anomalies, function($a, $b) {
            return $b['composite_score'] - $a['composite_score'];
        });
        
        // Aggiungi ranking position
        foreach ($anomalies as $index => &$anomaly) {
            $anomaly['rank'] = $index + 1;
            $anomaly['impact_level'] = $this->determineImpactLevel($anomaly['composite_score']);
        }
        
        return $anomalies;
    }
    
    /**
     * Caricamento dati storici
     */
    private function loadHistoricalData($tecnicoId) {
        $stmt = $this->pdo->prepare("
            SELECT tda.*, te.*, aa.* 
            FROM technician_daily_analysis tda
            LEFT JOIN timeline_events te ON tda.id = te.daily_analysis_id
            LEFT JOIN audit_alerts aa ON tda.id = aa.daily_analysis_id
            WHERE tda.tecnico_id = ? 
            AND tda.data_analisi >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY tda.data_analisi DESC
        ");
        
        $stmt->execute([$tecnicoId, $this->config['learning_window_days']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Utility functions
     */
    private function createAnomalyRecord($type, $subtype, $data) {
        return [
            'type' => $type,
            'subtype' => $subtype,
            'description' => $this->patterns[$type][$subtype]['description'] ?? "Anomalia {$subtype}",
            'severity' => $this->patterns[$type][$subtype]['severity'] ?? 'medium',
            'confidence' => $data['confidence'] ?? 75,
            'evidence' => $data,
            'detected_at' => date('Y-m-d H:i:s'),
            'requires_review' => $this->shouldRequireReview($type, $data)
        ];
    }
    
    private function calculateCompositeScore($anomaly) {
        $severityScore = [
            'critical' => 100,
            'high' => 80,
            'medium' => 60,
            'low' => 40
        ];
        
        $base = $severityScore[$anomaly['severity'] ?? 'medium'] ?? 50;
        $confidence = isset($anomaly['confidence']) ? $anomaly['confidence'] : 50;
        
        return ($base * 0.6) + ($confidence * 0.4);
    }
    
    private function determineImpactLevel($score) {
        if ($score >= 90) return 'critical';
        if ($score >= 75) return 'high';
        if ($score >= 60) return 'medium';
        return 'low';
    }
    
    private function shouldRequireReview($type, $data) {
        return ($data['confidence'] ?? 0) >= 90 || $type === 'temporal';
    }
    
    private function calculateOverallRiskScore($anomalies) {
        if (empty($anomalies)) return 0;
        
        $totalScore = array_sum(array_column($anomalies, 'composite_score'));
        $avgScore = $totalScore / count($anomalies);
        
        // Bonus per numero anomalie
        $countBonus = min(20, count($anomalies) * 2);
        
        return min(100, $avgScore + $countBonus);
    }
    
    private function analyzeConfidenceDistribution($anomalies) {
        $distribution = ['high' => 0, 'medium' => 0, 'low' => 0];
        
        foreach ($anomalies as $anomaly) {
            $confidence = isset($anomaly['confidence']) ? $anomaly['confidence'] : 50;
            if ($confidence >= 90) $distribution['high']++;
            elseif ($confidence >= 70) $distribution['medium']++;
            else $distribution['low']++;
        }
        
        return $distribution;
    }
    
    private function updateLearningModels($tecnicoId, $currentData, $anomalies) {
        // Implementazione learning automatico (placeholder)
        $this->log("🎓 Aggiornamento modelli learning per tecnico $tecnicoId");
    }
    
    private function log($message) {
        error_log("[AnomalyDetector] " . $message);
        echo $message . "\n";
    }
    
    // Placeholder implementations per algoritmi complessi
    private function analyzeWorkingHoursPattern($current, $historical) { return ['pattern' => 'consistent', 'confidence' => 85]; }
    private function analyzeClientRotationPattern($current, $historical) { return ['rotation_score' => 75]; }
    private function analyzeActivityDistribution($current, $historical) { return ['distribution_score' => 80]; }
    private function analyzeBreakPatterns($current, $historical) { return ['pattern_regularity' => 90]; }
    private function analyzeLateStartPattern($current, $historical) { return ['is_anomaly' => false]; }
    private function analyzeBreakAnomalies($current, $historical) { return ['is_anomaly' => false]; }
    private function analyzeWorkStyleConsistency($current, $historical) { return ['is_consistent' => true]; }
    private function detectImpossibleTravels($data) { return []; }
    private function detectRecurringOverlaps($current, $historical) { return []; }
    private function detectMicroGapsPattern($data) { return ['is_anomaly' => false]; }
    private function extractUniqueClients($data) { return ['Client1', 'Client2']; }
    private function getClientHistory($tecnicoId, $client, $historical) { return []; }
    private function filterDataByClient($data, $client) { return []; }
    private function detectClientDurationAnomaly($current, $history) { return ['is_anomaly' => false]; }
    private function detectClientFrequencyAnomaly($current, $history) { return ['is_anomaly' => false]; }
    private function calculateProductivityMetrics($data) { return ['efficiency' => 85]; }
    private function calculateHistoricalProductivityAverage($data) { return ['avg_efficiency' => 80]; }
    private function analyzeProductivityTrend($data) { return ['is_declining' => false]; }
    private function detectEfficiencyAnomaly($current, $historical) { return ['is_anomaly' => false]; }
    private function detectTimeSeriesAnomalies($current, $historical) { return []; }
    private function performClusteringAnalysis($current, $historical) { return []; }
    private function applyRegressionModel($current, $historical) { return []; }
    private function simulateNeuralNetworkAnalysis($current, $historical) { return []; }
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
        
        $detector = new AnomalyDetector($pdo);
        
        // Test con dati sample
        $sampleData = [
            'deepser' => [
                [
                    'start_time' => '2025-01-17 09:00:00',
                    'end_time' => '2025-01-17 11:00:00',
                    'azienda' => 'Test Client',
                    'location_type' => 'onsite'
                ]
            ],
            'auto' => [],
            'teamviewer' => []
        ];
        
        $result = $detector->detectAnomaliesForTechnician(1, '2025-01-17', $sampleData);
        
        echo "=== RISULTATO ANOMALY DETECTOR ===\n";
        print_r($result);
        
    } catch (Exception $e) {
        echo "Errore: " . $e->getMessage() . "\n";
    }
}
?>