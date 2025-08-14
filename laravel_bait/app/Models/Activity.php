<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * BAIT Service - Activity Model
 * 
 * Represents work activities from CSV imports
 * Business Logic: Temporal overlap detection, travel time validation
 */
class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'contratto',
        'id_ticket',
        'iniziata_il',
        'conclusa_il',
        'azienda',
        'tipologia_attivita',
        'descrizione',
        'durata',
        'creato_da',
        'file_source',
        'processing_batch_id',
        'confidence_score',
        'is_validated'
    ];

    protected $casts = [
        'iniziata_il' => 'datetime',
        'conclusa_il' => 'datetime',
        'durata' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'is_validated' => 'boolean'
    ];

    protected $dates = [
        'iniziata_il',
        'conclusa_il'
    ];

    /**
     * Get technician that created this activity
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'creato_da', 'name');
    }

    /**
     * Get client for this activity
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'azienda', 'name');
    }

    /**
     * Scope: Activities for specific technician
     */
    public function scopeForTechnician($query, string $technicianName)
    {
        return $query->where('creato_da', $technicianName);
    }

    /**
     * Scope: Activities within date range
     */
    public function scopeWithinDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('iniziata_il', [$startDate, $endDate]);
    }

    /**
     * Scope: Same day activities
     */
    public function scopeSameDay($query, Carbon $date)
    {
        return $query->whereDate('iniziata_il', $date->toDateString());
    }

    /**
     * Check if this activity overlaps with another temporally
     * BUSINESS CRITICAL: Core overlap detection logic from Python
     */
    public function hasTemporalOverlapWith(Activity $other): bool
    {
        if (!$this->iniziata_il || !$this->conclusa_il || 
            !$other->iniziata_il || !$other->conclusa_il) {
            return false;
        }

        return !($this->conclusa_il <= $other->iniziata_il || 
                 $other->conclusa_il <= $this->iniziata_il);
    }

    /**
     * Calculate overlap minutes with another activity
     */
    public function getOverlapMinutesWith(Activity $other): float
    {
        if (!$this->hasTemporalOverlapWith($other)) {
            return 0;
        }

        $overlapStart = max($this->iniziata_il, $other->iniziata_il);
        $overlapEnd = min($this->conclusa_il, $other->conclusa_il);

        return $overlapStart->diffInMinutes($overlapEnd);
    }

    /**
     * Get travel time to next activity in minutes
     */
    public function getTravelTimeToNext(Activity $nextActivity): float
    {
        if (!$this->conclusa_il || !$nextActivity->iniziata_il) {
            return 0;
        }

        return $this->conclusa_il->diffInMinutes($nextActivity->iniziata_il);
    }

    /**
     * Check if activity is during working hours
     */
    public function isDuringWorkingHours(): bool
    {
        $startHour = $this->iniziata_il->hour;
        $endHour = $this->conclusa_il->hour;

        return (($startHour >= 9 && $startHour <= 13) || 
                ($startHour >= 14 && $startHour <= 18)) &&
               (($endHour >= 9 && $endHour <= 13) || 
                ($endHour >= 14 && $endHour <= 18));
    }

    /**
     * Check if activity is marked as remote
     */
    public function isRemoteActivity(): bool
    {
        return stripos($this->tipologia_attivita ?? '', 'remoto') !== false;
    }

    /**
     * Calculate business impact score for overlaps
     * CRITICAL: Same client overlaps = high business impact
     */
    public function calculateOverlapImpactWith(Activity $other): array
    {
        $overlapMinutes = $this->getOverlapMinutesWith($other);
        
        if ($overlapMinutes === 0) {
            return ['impact' => 'none', 'score' => 0, 'severity' => 'BASSO'];
        }

        $sameClient = trim($this->azienda) === trim($other->azienda);
        $sameDay = $this->iniziata_il->toDateString() === $other->iniziata_il->toDateString();
        
        $baseScore = 50;
        
        // Critical factors from Python logic
        if ($overlapMinutes > 60) $baseScore += 40;
        elseif ($overlapMinutes > 30) $baseScore += 30;
        elseif ($overlapMinutes > 15) $baseScore += 20;
        else $baseScore += 10;
        
        if ($sameClient) $baseScore += 20;
        if ($sameDay) $baseScore += 10;
        if ($this->isDuringWorkingHours() && $other->isDuringWorkingHours()) $baseScore += 10;

        $severity = match (true) {
            $sameClient && $overlapMinutes > 30 => 'CRITICO',
            $sameClient => 'ALTO',
            $overlapMinutes > 60 => 'ALTO',
            default => 'MEDIO'
        };

        return [
            'impact' => $sameClient ? 'billing' : 'operational',
            'score' => min($baseScore, 100),
            'severity' => $severity,
            'same_client' => $sameClient,
            'overlap_minutes' => $overlapMinutes
        ];
    }

    /**
     * Get duration in minutes
     */
    public function getDurationMinutes(): float
    {
        if (!$this->iniziata_il || !$this->conclusa_il) {
            return 0;
        }

        return $this->iniziata_il->diffInMinutes($this->conclusa_il);
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDuration(): string
    {
        $minutes = $this->getDurationMinutes();
        $hours = intval($minutes / 60);
        $mins = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }
}