<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BAIT Service - Client Model
 * 
 * Represents client companies with geographical intelligence
 */
class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'city',
        'postal_code',
        'latitude',
        'longitude',
        'zone_type',
        'is_same_group',
        'group_identifier',
        'distance_from_headquarters',
        'active'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'distance_from_headquarters' => 'decimal:2',
        'active' => 'boolean',
        'is_same_group' => 'boolean'
    ];

    // Zone types for travel time calculations
    const ZONE_CENTRAL_MILAN = 'CENTRAL_MILAN';
    const ZONE_PERIPHERY = 'PERIPHERY';
    const ZONE_INDUSTRIAL = 'INDUSTRIAL';

    /**
     * Get activities for this client
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'azienda', 'name');
    }

    /**
     * Scope: Active clients only
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope: Clients in same group
     */
    public function scopeSameGroup($query, string $groupIdentifier)
    {
        return $query->where('group_identifier', $groupIdentifier);
    }

    /**
     * Check if this client is in same group as another
     * Migrated from Python _are_same_group_clients logic
     */
    public function isSameGroupAs(Client $otherClient): bool
    {
        if (!$this->is_same_group || !$otherClient->is_same_group) {
            return false;
        }

        return $this->group_identifier === $otherClient->group_identifier;
    }

    /**
     * Estimate travel distance to another client
     * Migrated from Python _estimate_distance logic
     */
    public function estimateDistanceTo(Client $otherClient): float
    {
        // If we have GPS coordinates, use Haversine formula
        if ($this->latitude && $this->longitude && 
            $otherClient->latitude && $otherClient->longitude) {
            return $this->calculateHaversineDistance($otherClient);
        }

        // Fallback to zone-based estimation (from Python logic)
        return $this->getZoneBasedDistance($otherClient);
    }

    /**
     * Calculate Haversine distance between two GPS points
     */
    private function calculateHaversineDistance(Client $otherClient): float
    {
        $earthRadius = 6371; // Earth radius in kilometers

        $lat1 = deg2rad($this->latitude);
        $lon1 = deg2rad($this->longitude);
        $lat2 = deg2rad($otherClient->latitude);
        $lon2 = deg2rad($otherClient->longitude);

        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1) * cos($lat2) *
             sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Get zone-based distance estimation
     * Migrated from Python distance_matrix logic
     */
    private function getZoneBasedDistance(Client $otherClient): float
    {
        $distanceMatrix = [
            [self::ZONE_CENTRAL_MILAN, self::ZONE_PERIPHERY] => 15,
            [self::ZONE_PERIPHERY, self::ZONE_PERIPHERY] => 25,
            [self::ZONE_CENTRAL_MILAN, self::ZONE_CENTRAL_MILAN] => 8,
            [self::ZONE_INDUSTRIAL, self::ZONE_INDUSTRIAL] => 12,
            [self::ZONE_CENTRAL_MILAN, self::ZONE_INDUSTRIAL] => 18,
            [self::ZONE_PERIPHERY, self::ZONE_INDUSTRIAL] => 22
        ];

        $thisZone = $this->zone_type ?: self::ZONE_CENTRAL_MILAN;
        $otherZone = $otherClient->zone_type ?: self::ZONE_CENTRAL_MILAN;

        $key = [$thisZone, $otherZone];
        $reverseKey = [$otherZone, $thisZone];

        return $distanceMatrix[$key] ?? $distanceMatrix[$reverseKey] ?? 12; // Default Milano distance
    }

    /**
     * Calculate minimum travel time to another client
     * Migrated from Python _get_min_travel_time logic
     */
    public function getMinTravelTimeTo(Client $otherClient): float
    {
        $distance = $this->estimateDistanceTo($otherClient);
        
        // Average speed in Milan: 20 km/h (includes traffic)
        $travelTime = ($distance / 20) * 60; // minutes
        
        return max($travelTime, 15); // Minimum 15 minutes
    }

    /**
     * Get monthly activity statistics for client
     */
    public function getMonthlyStats(int $year, int $month): array
    {
        $startDate = new \DateTime("{$year}-{$month}-01");
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');

        $activities = $this->activities()
            ->whereBetween('iniziata_il', [$startDate, $endDate])
            ->get();

        $totalHours = $activities->sum(fn($activity) => $activity->getDurationMinutes()) / 60;

        return [
            'total_activities' => $activities->count(),
            'total_hours' => round($totalHours, 2),
            'unique_technicians' => $activities->pluck('creato_da')->unique()->count(),
            'remote_activities' => $activities->filter(fn($activity) => $activity->isRemoteActivity())->count(),
            'average_duration' => $activities->count() > 0 ? round($totalHours / $activities->count(), 2) : 0
        ];
    }

    /**
     * Get clients that frequently cause travel time issues
     */
    public static function getProblematicTravelPairs(): array
    {
        $alerts = Alert::travelTime()
            ->highConfidence()
            ->unresolved()
            ->get();

        $pairs = [];

        foreach ($alerts as $alert) {
            $details = $alert->details;
            $from = $details['attivita_precedente']['cliente'] ?? 'Unknown';
            $to = $details['attivita_successiva']['cliente'] ?? 'Unknown';
            $key = "{$from} -> {$to}";

            if (!isset($pairs[$key])) {
                $pairs[$key] = [
                    'from' => $from,
                    'to' => $to,
                    'count' => 0,
                    'avg_confidence' => 0
                ];
            }

            $pairs[$key]['count']++;
            $pairs[$key]['avg_confidence'] = (($pairs[$key]['avg_confidence'] * ($pairs[$key]['count'] - 1)) + $alert->confidence_score) / $pairs[$key]['count'];
        }

        // Sort by count descending
        uasort($pairs, fn($a, $b) => $b['count'] <=> $a['count']);

        return array_slice($pairs, 0, 10); // Top 10 problematic pairs
    }

    /**
     * Setup default BAIT Service clients
     */
    public static function seedDefaultClients(): void
    {
        $defaultClients = [
            [
                'name' => 'BAIT Service S.r.l.',
                'zone_type' => self::ZONE_CENTRAL_MILAN,
                'is_same_group' => false,
                'distance_from_headquarters' => 0,
                'active' => true
            ],
            [
                'name' => 'ELECTRALINE 3PMARK SPA',
                'zone_type' => self::ZONE_INDUSTRIAL,
                'is_same_group' => true,
                'group_identifier' => 'ELECTRALINE',
                'distance_from_headquarters' => 18,
                'active' => true
            ],
            [
                'name' => 'SPOLIDORO STUDIO AVVOCATO',
                'zone_type' => self::ZONE_CENTRAL_MILAN,
                'is_same_group' => true,
                'group_identifier' => 'SPOLIDORO',
                'distance_from_headquarters' => 8,
                'active' => true
            ]
        ];

        foreach ($defaultClients as $clientData) {
            self::updateOrCreate(
                ['name' => $clientData['name']],
                $clientData
            );
        }
    }
}