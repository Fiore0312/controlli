<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BAIT Service - Technician Model
 * 
 * Represents technical staff members
 */
class Technician extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'active',
        'hire_date',
        'specializations',
        'efficiency_rating'
    ];

    protected $casts = [
        'active' => 'boolean',
        'hire_date' => 'date',
        'specializations' => 'json',
        'efficiency_rating' => 'decimal:2'
    ];

    /**
     * Get activities for this technician
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'creato_da', 'name');
    }

    /**
     * Get alerts for this technician
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'tecnico', 'name');
    }

    /**
     * Get timbratures for this technician
     */
    public function timbratures(): HasMany
    {
        return $this->hasMany(Timbratura::class, 'tecnico', 'name');
    }

    /**
     * Scope: Active technicians only
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Get daily efficiency score
     */
    public function getDailyEfficiencyScore(\DateTime $date): float
    {
        $activities = $this->activities()
            ->whereDate('iniziata_il', $date->format('Y-m-d'))
            ->get();

        if ($activities->isEmpty()) {
            return 0;
        }

        $totalMinutes = $activities->sum(fn($activity) => $activity->getDurationMinutes());
        $standardWorkDay = 8 * 60; // 8 hours in minutes

        return min(($totalMinutes / $standardWorkDay) * 100, 100);
    }

    /**
     * Get critical alerts count for technician
     */
    public function getCriticalAlertsCount(): int
    {
        return $this->alerts()->critical()->unresolved()->count();
    }

    /**
     * Get overlap incidents count
     */
    public function getOverlapIncidentsCount(\DateTime $date = null): int
    {
        $query = $this->alerts()->temporalOverlaps()->unresolved();
        
        if ($date) {
            $query->whereDate('created_at', $date->format('Y-m-d'));
        }
        
        return $query->count();
    }

    /**
     * Calculate monthly productivity metrics
     */
    public function getMonthlyMetrics(int $year, int $month): array
    {
        $startDate = new \DateTime("{$year}-{$month}-01");
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');

        $activities = $this->activities()
            ->whereBetween('iniziata_il', [$startDate, $endDate])
            ->get();

        $alerts = $this->alerts()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalHours = $activities->sum(fn($activity) => $activity->getDurationMinutes()) / 60;
        $workingDays = $this->getWorkingDaysInMonth($year, $month);
        $expectedHours = $workingDays * 8;

        return [
            'total_activities' => $activities->count(),
            'total_hours' => round($totalHours, 2),
            'expected_hours' => $expectedHours,
            'efficiency_percentage' => $expectedHours > 0 ? round(($totalHours / $expectedHours) * 100, 1) : 0,
            'critical_alerts' => $alerts->where('severity', Alert::SEVERITY_CRITICO)->count(),
            'high_alerts' => $alerts->where('severity', Alert::SEVERITY_ALTO)->count(),
            'overlap_incidents' => $alerts->where('category', 'temporal_overlap')->count(),
            'travel_violations' => $alerts->where('category', 'insufficient_travel_time')->count()
        ];
    }

    /**
     * Get working days in month (excluding weekends)
     */
    private function getWorkingDaysInMonth(int $year, int $month): int
    {
        $startDate = new \DateTime("{$year}-{$month}-01");
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');

        $workingDays = 0;
        $current = clone $startDate;

        while ($current <= $endDate) {
            $dayOfWeek = (int) $current->format('N'); // 1 (Monday) to 7 (Sunday)
            if ($dayOfWeek <= 5) { // Monday to Friday
                $workingDays++;
            }
            $current->modify('+1 day');
        }

        return $workingDays;
    }

    /**
     * Get recent performance trend
     */
    public function getPerformanceTrend(int $days = 30): array
    {
        $endDate = now();
        $startDate = now()->subDays($days);

        $activities = $this->activities()
            ->whereBetween('iniziata_il', [$startDate, $endDate])
            ->orderBy('iniziata_il')
            ->get()
            ->groupBy(fn($activity) => $activity->iniziata_il->format('Y-m-d'));

        $alerts = $this->alerts()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->groupBy(fn($alert) => $alert->created_at->format('Y-m-d'));

        $trend = [];
        $current = clone $startDate;

        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $dayActivities = $activities->get($dateKey, collect());
            $dayAlerts = $alerts->get($dateKey, collect());

            $trend[] = [
                'date' => $dateKey,
                'activities_count' => $dayActivities->count(),
                'total_hours' => round($dayActivities->sum(fn($activity) => $activity->getDurationMinutes()) / 60, 2),
                'alerts_count' => $dayAlerts->count(),
                'critical_alerts' => $dayAlerts->where('severity', Alert::SEVERITY_CRITICO)->count()
            ];

            $current->modify('+1 day');
        }

        return $trend;
    }
}