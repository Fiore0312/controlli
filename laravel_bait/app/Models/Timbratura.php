<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BAIT Service - Timbratura Model
 * 
 * Represents technician time tracking entries
 */
class Timbratura extends Model
{
    use HasFactory;

    protected $fillable = [
        'tecnico',
        'cliente',
        'ora_inizio',
        'ora_fine',
        'ore',
        'file_source',
        'processing_batch_id'
    ];

    protected $casts = [
        'ora_inizio' => 'datetime',
        'ora_fine' => 'datetime',
        'ore' => 'decimal:2'
    ];

    /**
     * Get technician for this timbratura
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'tecnico', 'name');
    }

    /**
     * Scope: Same day timbratures
     */
    public function scopeSameDay($query, \DateTime $date)
    {
        return $query->whereDate('ora_inizio', $date->format('Y-m-d'));
    }

    /**
     * Get duration in minutes
     */
    public function getDurationMinutes(): float
    {
        if (!$this->ora_inizio || !$this->ora_fine) {
            return $this->ore ? $this->ore * 60 : 0;
        }

        return $this->ora_inizio->diffInMinutes($this->ora_fine);
    }
}