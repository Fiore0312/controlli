<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Alert;
use App\Models\Client;
use App\Models\Technician;
use App\Services\BusinessRulesEngine;
use App\Services\CsvProcessorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * BAIT Service - Activity Controller
 * 
 * Handles CSV processing and business rule validation
 * Migrated from Python BAITEnterpriseController
 */
class ActivityController extends Controller
{
    private BusinessRulesEngine $businessRulesEngine;
    private CsvProcessorService $csvProcessor;

    public function __construct(
        BusinessRulesEngine $businessRulesEngine,
        CsvProcessorService $csvProcessor
    ) {
        $this->businessRulesEngine = $businessRulesEngine;
        $this->csvProcessor = $csvProcessor;
    }

    /**
     * Process all CSV files and generate dashboard data
     * Migrated from Python process_all_files method
     */
    public function processAllFiles(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $processingId = substr(md5(now()->toString()), 0, 8);
        
        Log::info("🔄 Starting enterprise processing [ID: {$processingId}]");

        try {
            // Check cache first unless force refresh
            $cacheKey = 'bait_processing_result';
            $forceRefresh = $request->boolean('force_refresh', false);
            
            if (!$forceRefresh && Cache::has($cacheKey)) {
                Log::info("⚡ Using cached result for performance");
                return response()->json(Cache::get($cacheKey));
            }

            // Process CSV files
            $processingResult = $this->csvProcessor->processAllFiles();
            
            if (!$processingResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'CSV processing failed',
                    'errors' => $processingResult['errors']
                ], 422);
            }

            // Run business rules validation
            $alerts = $this->businessRulesEngine->validateAllRules();

            // Calculate KPIs
            $kpis = $this->calculateSystemKpis($processingResult['data'], $alerts);

            // Generate statistics
            $statistics = $this->calculateAlertStatistics($alerts);

            // Create result structure (matching Python format)
            $result = [
                'metadata' => [
                    'version' => 'Enterprise Laravel 1.0',
                    'processing_id' => $processingId,
                    'generation_time' => now()->toISOString(),
                    'processing_duration' => microtime(true) - $startTime,
                    'data_source' => 'laravel_controller',
                    'files_processed' => $processingResult['files_processed']
                ],
                'kpis_v2' => [
                    'system_kpis' => $kpis
                ],
                'alerts_v2' => [
                    'processed_alerts' => [
                        'alerts' => $alerts->map(fn($alert) => $this->formatAlertForApi($alert))
                    ],
                    'statistics' => $statistics
                ],
                'system_metrics' => $this->getSystemMetrics()
            ];

            // Cache result for 5 minutes
            Cache::put($cacheKey, $result, 300);

            $processingTime = microtime(true) - $startTime;
            Log::info("✅ Processing completed successfully in {$processingTime}s");

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error("❌ Processing failed: {$e->getMessage()}", [
                'exception' => $e,
                'processing_id' => $processingId
            ]);

            // Return demo data as fallback
            return response()->json($this->generateDemoResult());
        }
    }

    /**
     * Get temporal overlaps for specific technician
     * Core business logic from Python _detect_temporal_overlaps
     */
    public function getTemporalOverlaps(Request $request): JsonResponse
    {
        $request->validate([
            'tecnico' => 'sometimes|string',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date'
        ]);

        $query = Activity::query();

        if ($request->has('tecnico')) {
            $query->forTechnician($request->input('tecnico'));
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->withinDateRange(
                Carbon::parse($request->input('date_from')),
                Carbon::parse($request->input('date_to'))
            );
        }

        $activities = $query->orderBy('iniziata_il')->get();
        $overlaps = [];

        // Group by technician and detect overlaps
        $activitiesByTech = $activities->groupBy('creato_da');

        foreach ($activitiesByTech as $tecnico => $techActivities) {
            $overlaps[$tecnico] = $this->detectOverlapsForTechnician($techActivities);
        }

        return response()->json([
            'overlaps' => $overlaps,
            'total_overlaps' => collect($overlaps)->flatten(1)->count()
        ]);
    }

    /**
     * Detect overlaps for a single technician
     * Migrated from Python logic
     */
    private function detectOverlapsForTechnician($activities): array
    {
        $overlaps = [];
        $activitiesArray = $activities->sortBy('iniziata_il')->values()->all();

        for ($i = 0; $i < count($activitiesArray) - 1; $i++) {
            for ($j = $i + 1; $j < count($activitiesArray); $j++) {
                $activity1 = $activitiesArray[$i];
                $activity2 = $activitiesArray[$j];

                if ($activity1->hasTemporalOverlapWith($activity2)) {
                    $overlapMinutes = $activity1->getOverlapMinutesWith($activity2);
                    $impact = $activity1->calculateOverlapImpactWith($activity2);

                    $overlaps[] = [
                        'activity1' => $this->formatActivityForApi($activity1),
                        'activity2' => $this->formatActivityForApi($activity2),
                        'overlap_minutes' => round($overlapMinutes, 1),
                        'impact' => $impact,
                        'same_client' => trim($activity1->azienda) === trim($activity2->azienda)
                    ];
                }
            }
        }

        return $overlaps;
    }

    /**
     * Get travel time analysis
     * Migrated from Python _analyze_travel_requirement logic
     */
    public function getTravelTimeAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'tecnico' => 'sometimes|string',
            'date' => 'sometimes|date'
        ]);

        $query = Activity::query();

        if ($request->has('tecnico')) {
            $query->forTechnician($request->input('tecnico'));
        }

        if ($request->has('date')) {
            $query->sameDay(Carbon::parse($request->input('date')));
        }

        $activities = $query->orderBy('iniziata_il')->get();
        $travelAnalysis = [];

        $activitiesByTech = $activities->groupBy('creato_da');

        foreach ($activitiesByTech as $tecnico => $techActivities) {
            $techActivitiesArray = $techActivities->sortBy('iniziata_il')->values()->all();
            
            for ($i = 0; $i < count($techActivitiesArray) - 1; $i++) {
                $currentActivity = $techActivitiesArray[$i];
                $nextActivity = $techActivitiesArray[$i + 1];
                
                $analysis = $this->analyzeTravelRequirement($currentActivity, $nextActivity);
                
                if ($analysis['requires_travel'] && $analysis['insufficient_time']) {
                    $travelAnalysis[] = [
                        'tecnico' => $tecnico,
                        'from_activity' => $this->formatActivityForApi($currentActivity),
                        'to_activity' => $this->formatActivityForApi($nextActivity),
                        'analysis' => $analysis
                    ];
                }
            }
        }

        return response()->json([
            'travel_violations' => $travelAnalysis,
            'total_violations' => count($travelAnalysis)
        ]);
    }

    /**
     * Analyze travel requirement between activities
     * Migrated from Python _analyze_travel_requirement method
     */
    private function analyzeTravelRequirement(Activity $prevActivity, Activity $nextActivity): array
    {
        if (!$prevActivity->conclusa_il || !$nextActivity->iniziata_il) {
            return ['requires_travel' => false, 'insufficient_time' => false];
        }

        $travelMinutes = $prevActivity->getTravelTimeToNext($nextActivity);
        $prevClient = trim($prevActivity->azienda);
        $nextClient = trim($nextActivity->azienda);

        // BAIT Service whitelist (from Python business rules)
        $baitServiceWhitelist = ['BAIT Service S.r.l.', 'BAIT Service', 'BAIT'];
        
        foreach ($baitServiceWhitelist as $baitName) {
            if (stripos($prevClient, $baitName) !== false || stripos($nextClient, $baitName) !== false) {
                return [
                    'requires_travel' => false,
                    'insufficient_time' => false,
                    'confidence_score' => 0,
                    'reason' => 'BAIT Service interno - whitelisted'
                ];
            }
        }

        // Same client = no travel required
        if ($prevClient === $nextClient) {
            return [
                'requires_travel' => false,
                'insufficient_time' => false,
                'confidence_score' => 0,
                'reason' => 'Stesso cliente'
            ];
        }

        // Get clients and calculate minimum travel time
        $prevClientModel = Client::where('name', $prevClient)->first();
        $nextClientModel = Client::where('name', $nextClient)->first();

        $estimatedDistance = 12; // Default Milano distance
        $minTravelTime = 15; // Minimum 15 minutes

        if ($prevClientModel && $nextClientModel) {
            if ($prevClientModel->isSameGroupAs($nextClientModel)) {
                return [
                    'requires_travel' => false,
                    'insufficient_time' => false,
                    'confidence_score' => 0,
                    'reason' => 'Clienti stesso gruppo'
                ];
            }
            
            $estimatedDistance = $prevClientModel->estimateDistanceTo($nextClientModel);
            $minTravelTime = $prevClientModel->getMinTravelTimeTo($nextClientModel);
        }

        $insufficientTime = $travelMinutes < $minTravelTime;
        $confidenceScore = $this->calculateTravelConfidence($travelMinutes, $minTravelTime, $estimatedDistance);

        return [
            'requires_travel' => true,
            'insufficient_time' => $insufficientTime,
            'travel_minutes' => $travelMinutes,
            'min_required' => $minTravelTime,
            'estimated_distance' => $estimatedDistance,
            'confidence_score' => $confidenceScore
        ];
    }

    /**
     * Calculate travel confidence score
     * Migrated from Python _calculate_travel_confidence
     */
    private function calculateTravelConfidence(float $actualTime, float $requiredTime, float $distance): float
    {
        if ($actualTime >= $requiredTime) {
            return 0; // No alert needed
        }

        $timeRatio = $requiredTime > 0 ? $actualTime / $requiredTime : 0;
        $baseConfidence = (1 - $timeRatio) * 70;

        // Distance factor
        if ($distance > 15) $baseConfidence += 20;
        elseif ($distance > 8) $baseConfidence += 10;

        // Penalty for very short times
        if ($actualTime == 0) $baseConfidence *= 0.7;
        elseif ($actualTime < 5) $baseConfidence *= 0.8;

        return min($baseConfidence, 85);
    }

    /**
     * Calculate system KPIs
     * Migrated from Python _calculate_kpis method
     */
    private function calculateSystemKpis(array $processedData, $alerts): array
    {
        $totalRecords = collect($processedData)->sum(fn($data) => count($data));
        $criticalAlerts = $alerts->where('severity', Alert::SEVERITY_CRITICO)->count();
        $highAlerts = $alerts->where('severity', Alert::SEVERITY_ALTO)->count();

        // Calculate accuracy (higher when fewer critical issues)
        $accuracy = max(85, 100 - ($criticalAlerts * 2) - ($highAlerts * 1));

        // Estimate losses based on overlap alerts
        $estimatedLosses = $alerts
            ->where('category', 'temporal_overlap')
            ->sum(function ($alert) {
                $details = $alert->details;
                $overlapMinutes = $details['overlap_minutes'] ?? 0;
                return $overlapMinutes * 0.75; // €0.75 per minute overlap
            });

        return [
            'total_records_processed' => $totalRecords,
            'estimated_accuracy' => round($accuracy, 1),
            'alerts_generated' => $alerts->count(),
            'critical_alerts' => $criticalAlerts,
            'high_alerts' => $highAlerts,
            'medium_alerts' => $alerts->where('severity', Alert::SEVERITY_MEDIO)->count(),
            'low_alerts' => $alerts->where('severity', Alert::SEVERITY_BASSO)->count(),
            'estimated_losses' => round($estimatedLosses, 2),
            'files_processed' => count($processedData),
            'processing_timestamp' => now()->toISOString()
        ];
    }

    /**
     * Calculate alert statistics
     */
    private function calculateAlertStatistics($alerts): array
    {
        $stats = [
            'total_alerts' => $alerts->count(),
            'by_severity' => [],
            'by_technician' => [],
            'by_category' => []
        ];

        $severityGroups = $alerts->groupBy('severity');
        $technicianGroups = $alerts->groupBy('tecnico');
        $categoryGroups = $alerts->groupBy('category');

        foreach ($severityGroups as $severity => $group) {
            $stats['by_severity'][$severity] = $group->count();
        }

        foreach ($technicianGroups as $technician => $group) {
            $stats['by_technician'][$technician] = $group->count();
        }

        foreach ($categoryGroups as $category => $group) {
            $stats['by_category'][$category] = $group->count();
        }

        return $stats;
    }

    /**
     * Format activity for API response
     */
    private function formatActivityForApi(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'id_ticket' => $activity->id_ticket,
            'azienda' => $activity->azienda,
            'tecnico' => $activity->creato_da,
            'iniziata_il' => $activity->iniziata_il->toISOString(),
            'conclusa_il' => $activity->conclusa_il->toISOString(),
            'durata_minuti' => $activity->getDurationMinutes(),
            'tipologia' => $activity->tipologia_attivita
        ];
    }

    /**
     * Format alert for API response
     */
    private function formatAlertForApi(Alert $alert): array
    {
        return [
            'id' => $alert->external_id,
            'severity' => $alert->severity,
            'confidence_score' => $alert->confidence_score,
            'confidence_level' => $alert->confidence_level,
            'tecnico' => $alert->tecnico,
            'messaggio' => $alert->message,
            'categoria' => $alert->category,
            'dettagli' => $alert->details,
            'business_impact' => $alert->business_impact,
            'suggested_actions' => $alert->suggested_actions,
            'data_sources' => $alert->data_sources,
            'timestamp' => $alert->created_at->toISOString()
        ];
    }

    /**
     * Get system metrics
     */
    private function getSystemMetrics(): array
    {
        return [
            'version' => 'Laravel Enterprise 1.0',
            'start_time' => now()->toISOString(),
            'total_activities' => Activity::count(),
            'total_alerts' => Alert::count(),
            'active_technicians' => Technician::active()->count(),
            'active_clients' => Client::active()->count()
        ];
    }

    /**
     * Generate demo result as fallback
     * Migrated from Python _generate_demo_result
     */
    private function generateDemoResult(): array
    {
        Log::info("🎭 Generating demo data for demonstration");

        $demoAlerts = [];
        $technicians = ['Alex Ferrario', 'Gabriele De Palma', 'Matteo Signo', 'Davide Cestone', 'Marco Birocchi'];

        for ($i = 0; $i < 17; $i++) {
            $severity = $i < 5 ? 'CRITICO' : ($i < 10 ? 'ALTO' : ($i < 15 ? 'MEDIO' : 'BASSO'));
            
            $demoAlerts[] = [
                'id' => sprintf('BAIT_DEMO_%04d', $i),
                'severity' => $severity,
                'confidence_score' => $severity === 'CRITICO' ? rand(70, 100) : rand(50, 85),
                'confidence_level' => $severity === 'CRITICO' ? 'ALTA' : 'MEDIA',
                'tecnico' => $technicians[array_rand($technicians)],
                'categoria' => ['temporal_overlap', 'insufficient_travel_time', 'data_quality'][array_rand(['temporal_overlap', 'insufficient_travel_time', 'data_quality'])],
                'messaggio' => "Demo alert " . ($i + 1) . ": {$severity} issue detected in system",
                'dettagli' => [
                    'demo' => true,
                    'overlap_minutes' => strpos((string)$i, 'overlap') ? rand(15, 120) : 0
                ],
                'timestamp' => now()->subHours(rand(0, 48))->toISOString()
            ];
        }

        return [
            'metadata' => [
                'version' => 'Demo Laravel Enterprise 1.0',
                'processing_id' => 'demo',
                'generation_time' => now()->toISOString(),
                'data_source' => 'demo_generator'
            ],
            'kpis_v2' => [
                'system_kpis' => [
                    'total_records_processed' => 371,
                    'estimated_accuracy' => 96.4,
                    'alerts_generated' => count($demoAlerts),
                    'critical_alerts' => 5,
                    'high_alerts' => 5,
                    'medium_alerts' => 5,
                    'low_alerts' => 2,
                    'estimated_losses' => 157.50
                ]
            ],
            'alerts_v2' => [
                'processed_alerts' => [
                    'alerts' => $demoAlerts
                ],
                'statistics' => [
                    'total_alerts' => count($demoAlerts),
                    'by_severity' => [
                        'CRITICO' => 5,
                        'ALTO' => 5,
                        'MEDIO' => 5,
                        'BASSO' => 2
                    ]
                ]
            ]
        ];
    }
}