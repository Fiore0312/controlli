<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BAIT Service - Alert Model
 * 
 * Stores business rule violation alerts with confidence scoring
 * Migrated from Python business_rules_v2.py Alert class
 */
class Alert extends Model
{
    use HasFactory;

    const SEVERITY_CRITICO = 'CRITICO';
    const SEVERITY_ALTO = 'ALTO';
    const SEVERITY_MEDIO = 'MEDIO';
    const SEVERITY_BASSO = 'BASSO';

    const CONFIDENCE_MOLTO_ALTA = 'MOLTO_ALTA';
    const CONFIDENCE_ALTA = 'ALTA';
    const CONFIDENCE_MEDIA = 'MEDIA';
    const CONFIDENCE_BASSA = 'BASSA';
    const CONFIDENCE_MOLTO_BASSA = 'MOLTO_BASSA';

    protected $fillable = [
        'external_id',
        'severity',
        'confidence_score',
        'confidence_level',
        'tecnico',
        'message',
        'category',
        'details',
        'business_impact',
        'suggested_actions',
        'data_sources',
        'processing_batch_id',
        'is_resolved',
        'resolved_by',
        'resolved_at',
        'false_positive'
    ];

    protected $casts = [
        'details' => 'json',
        'suggested_actions' => 'json',
        'data_sources' => 'json',
        'confidence_score' => 'decimal:2',
        'is_resolved' => 'boolean',
        'false_positive' => 'boolean',
        'resolved_at' => 'datetime'
    ];

    /**
     * Get technician associated with this alert
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'tecnico', 'name');
    }

    /**
     * Scope: Critical alerts only
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', self::SEVERITY_CRITICO);
    }

    /**
     * Scope: High confidence alerts
     */
    public function scopeHighConfidence($query)
    {
        return $query->where('confidence_score', '>=', 70);
    }

    /**
     * Scope: Unresolved alerts
     */
    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    /**
     * Scope: By category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: Temporal overlap alerts
     */
    public function scopeTemporalOverlaps($query)
    {
        return $query->where('category', 'temporal_overlap');
    }

    /**
     * Scope: Travel time alerts
     */
    public function scopeTravelTime($query)
    {
        return $query->where('category', 'insufficient_travel_time');
    }

    /**
     * Get confidence level based on score
     * Migrated from Python _get_confidence_level method
     */
    public static function getConfidenceLevelFromScore(float $score): string
    {
        return match (true) {
            $score >= 90 => self::CONFIDENCE_MOLTO_ALTA,
            $score >= 70 => self::CONFIDENCE_ALTA,
            $score >= 50 => self::CONFIDENCE_MEDIA,
            $score >= 30 => self::CONFIDENCE_BASSA,
            default => self::CONFIDENCE_MOLTO_BASSA
        };
    }

    /**
     * Generate external ID for alert
     */
    public static function generateExternalId(): string
    {
        return 'BAIT_ENT_' . now()->format('Ymd') . '_' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create temporal overlap alert
     * Migrated from Python _create_temporal_overlap_alert
     */
    public static function createTemporalOverlapAlert(
        string $tecnico,
        Activity $activity1,
        Activity $activity2,
        array $overlapInfo
    ): self {
        $confidenceScore = self::calculateOverlapConfidence($overlapInfo, $activity1, $activity2);
        
        return self::create([
            'external_id' => self::generateExternalId(),
            'severity' => self::SEVERITY_CRITICO,
            'confidence_score' => $confidenceScore,
            'confidence_level' => self::getConfidenceLevelFromScore($confidenceScore),
            'tecnico' => $tecnico,
            'message' => "{$tecnico}: sovrapposizione temporale clienti {$activity1->azienda} e {$activity2->azienda} ({$overlapInfo['overlap_minutes']} min)",
            'category' => 'temporal_overlap',
            'details' => [
                'attivita_1' => [
                    'id' => $activity1->id_ticket,
                    'cliente' => $activity1->azienda,
                    'orario' => $activity1->iniziata_il->format('Y-m-d H:i') . ' - ' . $activity1->conclusa_il->format('Y-m-d H:i')
                ],
                'attivita_2' => [
                    'id' => $activity2->id_ticket,
                    'cliente' => $activity2->azienda,
                    'orario' => $activity2->iniziata_il->format('Y-m-d H:i') . ' - ' . $activity2->conclusa_il->format('Y-m-d H:i')
                ],
                'overlap_minutes' => $overlapInfo['overlap_minutes']
            ],
            'business_impact' => 'billing',
            'suggested_actions' => ['Verificare doppia fatturazione', 'Controllare planning tecnico'],
            'data_sources' => ['attivita']
        ]);
    }

    /**
     * Create travel time alert
     * Migrated from Python _create_travel_time_alert
     */
    public static function createTravelTimeAlert(
        string $tecnico,
        Activity $prevActivity,
        Activity $nextActivity,
        array $travelAnalysis
    ): self {
        return self::create([
            'external_id' => self::generateExternalId(),
            'severity' => self::SEVERITY_MEDIO,
            'confidence_score' => $travelAnalysis['confidence_score'],
            'confidence_level' => self::getConfidenceLevelFromScore($travelAnalysis['confidence_score']),
            'tecnico' => $tecnico,
            'message' => "{$tecnico}: tempo viaggio insufficiente {$prevActivity->azienda} -> {$nextActivity->azienda} ({$travelAnalysis['travel_minutes']} min disponibili, {$travelAnalysis['min_required']} min richiesti)",
            'category' => 'insufficient_travel_time',
            'details' => [
                'attivita_precedente' => [
                    'cliente' => $prevActivity->azienda,
                    'fine' => $prevActivity->conclusa_il->format('Y-m-d H:i'),
                    'id' => $prevActivity->id_ticket
                ],
                'attivita_successiva' => [
                    'cliente' => $nextActivity->azienda,
                    'inizio' => $nextActivity->iniziata_il->format('Y-m-d H:i'),
                    'id' => $nextActivity->id_ticket
                ],
                'tempo_viaggio_minuti' => $travelAnalysis['travel_minutes'],
                'tempo_richiesto_minuti' => $travelAnalysis['min_required'],
                'distanza_stimata_km' => $travelAnalysis['estimated_distance']
            ],
            'business_impact' => 'operational',
            'suggested_actions' => ['Verificare fattibilità spostamento', 'Ottimizzare planning'],
            'data_sources' => ['attivita']
        ]);
    }

    /**
     * Calculate overlap confidence score
     * Migrated from Python _calculate_overlap_confidence
     */
    private static function calculateOverlapConfidence(array $overlapInfo, Activity $att1, Activity $att2): float
    {
        $baseConfidence = 50;
        $overlapMinutes = $overlapInfo['overlap_minutes'];
        
        // Duration factor
        if ($overlapMinutes > 60) $baseConfidence += 40;
        elseif ($overlapMinutes > 30) $baseConfidence += 30;
        elseif ($overlapMinutes > 15) $baseConfidence += 20;
        else $baseConfidence += 10;
        
        // Different clients factor
        if ($att1->azienda !== $att2->azienda) {
            $baseConfidence += 20;
        }
        
        // Same day factor
        if ($att1->iniziata_il->toDateString() === $att2->iniziata_il->toDateString()) {
            $baseConfidence += 10;
        }
        
        // Working hours factor
        if ($att1->isDuringWorkingHours() && $att2->isDuringWorkingHours()) {
            $baseConfidence += 10;
        }
        
        return min($baseConfidence, 100);
    }

    /**
     * Mark alert as resolved
     */
    public function resolve(string $resolvedBy = null): void
    {
        $this->update([
            'is_resolved' => true,
            'resolved_by' => $resolvedBy,
            'resolved_at' => now()
        ]);
    }

    /**
     * Mark as false positive
     */
    public function markAsFalsePositive(string $resolvedBy = null): void
    {
        $this->update([
            'false_positive' => true,
            'is_resolved' => true,
            'resolved_by' => $resolvedBy,
            'resolved_at' => now()
        ]);
    }

    /**
     * Get severity color for UI
     */
    public function getSeverityColor(): string
    {
        return match ($this->severity) {
            self::SEVERITY_CRITICO => 'danger',
            self::SEVERITY_ALTO => 'warning',
            self::SEVERITY_MEDIO => 'info',
            self::SEVERITY_BASSO => 'secondary',
            default => 'light'
        };
    }

    /**
     * Get confidence badge color
     */
    public function getConfidenceBadgeColor(): string
    {
        return match ($this->confidence_level) {
            self::CONFIDENCE_MOLTO_ALTA, self::CONFIDENCE_ALTA => 'success',
            self::CONFIDENCE_MEDIA => 'primary',
            self::CONFIDENCE_BASSA, self::CONFIDENCE_MOLTO_BASSA => 'secondary',
            default => 'light'
        };
    }
}