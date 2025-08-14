<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Alert;
use App\Models\Client;
use App\Models\Technician;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * BAIT Service - Business Rules Engine
 * 
 * Advanced business rules validation with confidence scoring
 * Direct migration from Python business_rules_v2.py
 */
class BusinessRulesEngine
{
    private Collection $alerts;
    private int $alertCounter = 0;

    // BAIT Service whitelist (from Python)
    private array $baitServiceWhitelist = [
        'BAIT Service S.r.l.',
        'BAIT Service',
        'BAIT'
    ];

    // Same group clients database (expandable)
    private array $sameGroupClients = [
        'ELECTRALINE' => ['ELECTRALINE 3PMARK SPA'],
        'SPOLIDORO' => ['SPOLIDORO STUDIO AVVOCATO'],
        'ISOTERMA_GROUP' => ['ISOTERMA SRL', 'GARIBALDINA SRL']
    ];

    public function __construct()
    {
        $this->alerts = collect();
    }

    /**
     * Execute all business rules validation
     * Migrated from Python validate_all_rules method
     */
    public function validateAllRules(): Collection
    {
        Log::info("🚀 Avvio Business Rules Engine Laravel 1.0...");
        
        $this->alerts = collect();
        $this->alertCounter = 0;

        // Rule 1: Temporal overlaps validation (CRITICAL)
        $this->validateTemporalOverlaps();

        // Rule 2: Travel time intelligent validation (MEDIUM filtered)
        $this->validateTravelTime();

        // Rule 3: Activity type vs TeamViewer validation (HIGH)
        $this->validateActivityType();

        // Rule 4: Time consistency validation (HIGH)
        $this->validateTimeConsistency();

        // Rule 5: Intelligent vehicle usage validation (MEDIUM)
        $this->validateVehicleUsage();

        Log::info("✅ Business Rules Engine completed: {$this->alerts->count()} alerts generated");
        
        return $this->alerts;
    }

    /**
     * Validate temporal overlaps with advanced confidence scoring
     * Migrated from Python _validate_temporal_overlaps_v2
     */
    private function validateTemporalOverlaps(): void
    {
        Log::info("🔍 Validating temporal overlaps v2.0...");

        $activities = Activity::with(['technician'])
            ->whereNotNull('creato_da')
            ->whereNotNull('iniziata_il')
            ->whereNotNull('conclusa_il')
            ->orderBy('iniziata_il')
            ->get();

        $activitiesByTech = $activities->groupBy('creato_da');

        foreach ($activitiesByTech as $tecnico => $techActivities) {
            if (empty($tecnico) || in_array($tecnico, ['nan', '00:45'])) {
                continue;
            }

            $sortedActivities = $techActivities->sortBy('iniziata_il');

            foreach ($sortedActivities as $i => $activity1) {
                $remainingActivities = $sortedActivities->skip($i + 1);
                
                foreach ($remainingActivities as $activity2) {
                    if ($activity1->hasTemporalOverlapWith($activity2)) {
                        $overlapInfo = $this->calculateOverlapInfo($activity1, $activity2);
                        $confidenceScore = $this->calculateOverlapConfidence($overlapInfo, $activity1, $activity2);

                        // Only create alerts with high confidence to avoid false positives
                        if ($confidenceScore >= 70) {
                            $this->createTemporalOverlapAlert($tecnico, $activity1, $activity2, $overlapInfo, $confidenceScore);
                        }
                    }
                }
            }
        }
    }

    /**
     * Calculate detailed overlap information
     * Migrated from Python _calculate_overlap
     */
    private function calculateOverlapInfo(Activity $att1, Activity $att2): array
    {
        try {
            $start1 = $att1->iniziata_il;
            $end1 = $att1->conclusa_il;
            $start2 = $att2->iniziata_il;
            $end2 = $att2->conclusa_il;

            $hasOverlap = $att1->hasTemporalOverlapWith($att2);
            $overlapMinutes = $hasOverlap ? $att1->getOverlapMinutesWith($att2) : 0;

            return [
                'has_overlap' => $hasOverlap,
                'overlap_minutes' => $overlapMinutes,
                'start1' => $start1,
                'end1' => $end1,
                'start2' => $start2,
                'end2' => $end2
            ];
        } catch (\Exception $e) {
            return ['has_overlap' => false, 'overlap_minutes' => 0];
        }
    }

    /**
     * Calculate confidence score for temporal overlap
     * Migrated from Python _calculate_overlap_confidence
     */
    private function calculateOverlapConfidence(array $overlapInfo, Activity $att1, Activity $att2): float
    {
        $baseConfidence = 50;
        $overlapMinutes = $overlapInfo['overlap_minutes'];

        // Factor 1: Overlap duration (longer = more critical)
        if ($overlapMinutes > 60) $baseConfidence += 40;
        elseif ($overlapMinutes > 30) $baseConfidence += 30;
        elseif ($overlapMinutes > 15) $baseConfidence += 20;
        else $baseConfidence += 10;

        // Factor 2: Different clients (critical for billing)
        if (trim($att1->azienda) !== trim($att2->azienda)) {
            $baseConfidence += 20;
        }

        // Factor 3: Same day (more problematic)
        if ($overlapInfo['start1']->toDateString() === $overlapInfo['start2']->toDateString()) {
            $baseConfidence += 10;
        }

        // Factor 4: Working hours standard
        if ($att1->isDuringWorkingHours() && $att2->isDuringWorkingHours()) {
            $baseConfidence += 10;
        }

        return min($baseConfidence, 100);
    }

    /**
     * Validate travel time intelligently with false positive elimination
     * Migrated from Python _validate_travel_time_v2
     */
    private function validateTravelTime(): void
    {
        Log::info("🚗 Validating travel time v2.0 (intelligent)...");

        $activities = Activity::whereNotNull('creato_da')
            ->whereNotNull('iniziata_il')
            ->whereNotNull('conclusa_il')
            ->orderBy('iniziata_il')
            ->get();

        $activitiesByTech = $activities->groupBy('creato_da');

        foreach ($activitiesByTech as $tecnico => $techActivities) {
            if (empty($tecnico) || in_array($tecnico, ['nan', '00:45'])) {
                continue;
            }

            $sortedActivities = $techActivities->sortBy('iniziata_il')->values();

            for ($i = 0; $i < $sortedActivities->count() - 1; $i++) {
                $prevActivity = $sortedActivities->get($i);
                $nextActivity = $sortedActivities->get($i + 1);

                $travelAnalysis = $this->analyzeTravelRequirement($prevActivity, $nextActivity);

                if ($travelAnalysis['requires_travel'] && $travelAnalysis['insufficient_time']) {
                    $confidenceScore = $travelAnalysis['confidence_score'];

                    // Only alerts with medium-high confidence (filters false positives)
                    if ($confidenceScore >= 60) {
                        $this->createTravelTimeAlert($tecnico, $prevActivity, $nextActivity, $travelAnalysis);
                    }
                }
            }
        }
    }

    /**
     * Intelligently analyze if travel is required between activities
     * Migrated from Python _analyze_travel_requirement
     */
    private function analyzeTravelRequirement(Activity $prevActivity, Activity $nextActivity): array
    {
        try {
            $travelMinutes = $prevActivity->getTravelTimeToNext($nextActivity);
            $clientPrev = trim($prevActivity->azienda);
            $clientNext = trim($nextActivity->azienda);

            // BAIT Service WHITELIST (eliminates false positives from Task 11)
            foreach ($this->baitServiceWhitelist as $baitName) {
                if (stripos($clientPrev, $baitName) !== false || stripos($clientNext, $baitName) !== false) {
                    return [
                        'requires_travel' => false,
                        'insufficient_time' => false,
                        'confidence_score' => 0,
                        'reason' => 'BAIT Service interno - whitelisted'
                    ];
                }
            }

            // Same client = no travel required
            if ($clientPrev === $clientNext) {
                return [
                    'requires_travel' => false,
                    'insufficient_time' => false,
                    'confidence_score' => 0,
                    'reason' => 'Stesso cliente'
                ];
            }

            // Same group clients
            if ($this->areSameGroupClients($clientPrev, $clientNext)) {
                return [
                    'requires_travel' => false,
                    'insufficient_time' => false,
                    'confidence_score' => 0,
                    'reason' => 'Clienti stesso gruppo'
                ];
            }

            // Calculate minimum travel time required
            $estimatedDistance = $this->estimateDistance($clientPrev, $clientNext);
            $minTravelTime = $this->getMinTravelTime($estimatedDistance);

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

        } catch (\Exception $e) {
            return ['requires_travel' => false, 'insufficient_time' => false, 'confidence_score' => 0];
        }
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

        // Base confidence inversely proportional to available travel time
        $timeRatio = $requiredTime > 0 ? $actualTime / $requiredTime : 0;
        $baseConfidence = (1 - $timeRatio) * 70; // Max 70 from time ratio

        // Add confidence based on estimated distance
        if ($distance > 15) $baseConfidence += 20; // >15km = significant travel
        elseif ($distance > 8) $baseConfidence += 10;

        // Penalty for very short times (more likely false positives)
        if ($actualTime == 0) $baseConfidence *= 0.7; // Reduce by 30% for 0 minutes
        elseif ($actualTime < 5) $baseConfidence *= 0.8; // Reduce by 20% for <5 minutes

        return min($baseConfidence, 85); // Max 85% for travel time alerts
    }

    /**
     * Validate activity type vs TeamViewer sessions
     * Migrated from Python _validate_activity_type_v2
     */
    private function validateActivityType(): void
    {
        Log::info("💻 Validating remote activities vs TeamViewer v2.0...");

        $remoteActivities = Activity::whereNotNull('creato_da')
            ->where('tipologia_attivita', 'LIKE', '%remoto%')
            ->get();

        foreach ($remoteActivities as $activity) {
            if (empty($activity->creato_da) || in_array($activity->creato_da, ['nan', '00:45'])) {
                continue;
            }

            $confidenceScore = $this->validateRemoteActivity($activity);

            if ($confidenceScore >= 60) { // Only alerts with medium-high confidence
                $this->createActivityTypeAlert($activity, $confidenceScore, 'missing_teamviewer');
            }
        }
    }

    /**
     * Validate time consistency between activities and timbratures
     * Simplified implementation for now
     */
    private function validateTimeConsistency(): void
    {
        Log::info("⏰ Validating time consistency v2.0...");
        // TODO: Implement intelligent matching between activities and timbratures
    }

    /**
     * Validate intelligent vehicle usage
     * Simplified implementation for now
     */
    private function validateVehicleUsage(): void
    {
        Log::info("🚗 Validating vehicle usage v2.0...");
        // TODO: Implement vehicle usage validation with confidence scoring
    }

    // UTILITY METHODS

    /**
     * Check if two clients belong to the same group
     * Migrated from Python _are_same_group_clients
     */
    private function areSameGroupClients(string $client1, string $client2): bool
    {
        foreach ($this->sameGroupClients as $group => $clients) {
            $client1InGroup = false;
            $client2InGroup = false;

            foreach ($clients as $groupClient) {
                if (stripos($client1, $groupClient) !== false) {
                    $client1InGroup = true;
                }
                if (stripos($client2, $groupClient) !== false) {
                    $client2InGroup = true;
                }
            }

            if ($client1InGroup && $client2InGroup) {
                return true;
            }
        }

        return false;
    }

    /**
     * Estimate distance between two clients
     * Migrated from Python _estimate_distance
     */
    private function estimateDistance(string $client1, string $client2): float
    {
        // Try to find clients in database for accurate distance
        $client1Model = Client::where('name', 'LIKE', "%{$client1}%")->first();
        $client2Model = Client::where('name', 'LIKE', "%{$client2}%")->first();

        if ($client1Model && $client2Model) {
            return $client1Model->estimateDistanceTo($client2Model);
        }

        // Fallback to simplified estimation based on client names
        if (stripos($client1, 'CENTRAL') !== false || stripos($client2, 'CENTRAL') !== false) {
            return 8; // Milano centro
        } elseif (stripos($client1, 'INDUSTRIAL') !== false || stripos($client2, 'INDUSTRIAL') !== false) {
            return 15; // Zona industriale
        } else {
            return 12; // Default Milano
        }
    }

    /**
     * Calculate minimum travel time based on distance
     * Migrated from Python _get_min_travel_time
     */
    private function getMinTravelTime(float $distanceKm): float
    {
        // Average speed in Milan: 20 km/h (includes traffic)
        $travelTime = ($distanceKm / 20) * 60; // minutes
        return max($travelTime, 15); // Minimum 15 minutes
    }

    /**
     * Validate remote activity against TeamViewer sessions
     * Simplified implementation for now
     */
    private function validateRemoteActivity(Activity $activity): float
    {
        // TODO: Implement intelligent matching with TeamViewer data
        return 50; // Default medium confidence
    }

    // ALERT CREATION METHODS

    /**
     * Create temporal overlap alert
     * Migrated from Python _create_temporal_overlap_alert
     */
    private function createTemporalOverlapAlert(
        string $tecnico,
        Activity $att1,
        Activity $att2,
        array $overlapInfo,
        float $confidenceScore
    ): void {
        $this->alertCounter++;

        $alert = Alert::create([
            'external_id' => Alert::generateExternalId(),
            'severity' => Alert::SEVERITY_CRITICO,
            'confidence_score' => $confidenceScore,
            'confidence_level' => Alert::getConfidenceLevelFromScore($confidenceScore),
            'tecnico' => $tecnico,
            'message' => "{$tecnico}: sovrapposizione temporale clienti {$att1->azienda} e {$att2->azienda} ({$overlapInfo['overlap_minutes']} min)",
            'category' => 'temporal_overlap',
            'details' => [
                'attivita_1' => [
                    'id' => $att1->id_ticket ?? 'N/A',
                    'cliente' => $att1->azienda,
                    'orario' => $att1->iniziata_il->format('Y-m-d H:i') . ' - ' . $att1->conclusa_il->format('Y-m-d H:i')
                ],
                'attivita_2' => [
                    'id' => $att2->id_ticket ?? 'N/A',
                    'cliente' => $att2->azienda,
                    'orario' => $att2->iniziata_il->format('Y-m-d H:i') . ' - ' . $att2->conclusa_il->format('Y-m-d H:i')
                ],
                'overlap_minutes' => $overlapInfo['overlap_minutes']
            ],
            'business_impact' => 'billing',
            'suggested_actions' => ['Verificare doppia fatturazione', 'Controllare planning tecnico'],
            'data_sources' => ['attivita']
        ]);

        $this->alerts->push($alert);
    }

    /**
     * Create travel time alert
     * Migrated from Python _create_travel_time_alert
     */
    private function createTravelTimeAlert(
        string $tecnico,
        Activity $prevActivity,
        Activity $nextActivity,
        array $travelAnalysis
    ): void {
        $this->alertCounter++;

        $alert = Alert::create([
            'external_id' => Alert::generateExternalId(),
            'severity' => Alert::SEVERITY_MEDIO,
            'confidence_score' => $travelAnalysis['confidence_score'],
            'confidence_level' => Alert::getConfidenceLevelFromScore($travelAnalysis['confidence_score']),
            'tecnico' => $tecnico,
            'message' => "{$tecnico}: tempo viaggio insufficiente {$prevActivity->azienda} -> {$nextActivity->azienda} ({$travelAnalysis['travel_minutes']} min disponibili, {$travelAnalysis['min_required']} min richiesti)",
            'category' => 'insufficient_travel_time',
            'details' => [
                'attivita_precedente' => [
                    'cliente' => $prevActivity->azienda,
                    'fine' => $prevActivity->conclusa_il->format('Y-m-d H:i'),
                    'id' => $prevActivity->id_ticket ?? 'N/A'
                ],
                'attivita_successiva' => [
                    'cliente' => $nextActivity->azienda,
                    'inizio' => $nextActivity->iniziata_il->format('Y-m-d H:i'),
                    'id' => $nextActivity->id_ticket ?? 'N/A'
                ],
                'tempo_viaggio_minuti' => $travelAnalysis['travel_minutes'],
                'tempo_richiesto_minuti' => $travelAnalysis['min_required'],
                'distanza_stimata_km' => $travelAnalysis['estimated_distance']
            ],
            'business_impact' => 'operational',
            'suggested_actions' => ['Verificare fattibilità spostamento', 'Ottimizzare planning'],
            'data_sources' => ['attivita']
        ]);

        $this->alerts->push($alert);
    }

    /**
     * Create activity type alert
     * Migrated from Python _create_activity_type_alert
     */
    private function createActivityTypeAlert(Activity $activity, float $confidenceScore, string $alertType): void
    {
        $this->alertCounter++;

        $alert = Alert::create([
            'external_id' => Alert::generateExternalId(),
            'severity' => Alert::SEVERITY_ALTO,
            'confidence_score' => $confidenceScore,
            'confidence_level' => Alert::getConfidenceLevelFromScore($confidenceScore),
            'tecnico' => $activity->creato_da,
            'message' => "{$activity->creato_da}: attività remota senza sessione TeamViewer - {$activity->azienda}",
            'category' => 'activity_type_mismatch',
            'details' => [
                'attivita_id' => $activity->id_ticket ?? 'N/A',
                'cliente' => $activity->azienda,
                'tipo_dichiarato' => $activity->tipologia_attivita,
                'orario' => $activity->iniziata_il->format('Y-m-d H:i') . ' - ' . $activity->conclusa_il->format('Y-m-d H:i')
            ],
            'business_impact' => 'compliance',
            'suggested_actions' => ['Verificare sessione TeamViewer', 'Controllare tipo attività'],
            'data_sources' => ['attivita', 'teamviewer']
        ]);

        $this->alerts->push($alert);
    }
}