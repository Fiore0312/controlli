<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Alert;
use App\Models\Technician;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * BAIT Service - Dashboard Controller
 * 
 * Provides dashboard data and analytics for enterprise control system
 * Compatible with existing Python dashboard frontend
 */
class DashboardController extends Controller
{
    /**
     * Get main dashboard data
     * Compatible with Python dashboard expectations
     */
    public function getDashboardData(): JsonResponse
    {
        return response()->json($this->generateDashboardData());
    }
    
    /**
     * System Status - Endpoint per monitoraggio dashboard
     */
    public function systemStatus()
    {
        // Cache per 30 secondi per performance
        return Cache::remember('system_status', 30, function () {
            return response()->json([
                'system' => [
                    'status' => 'operational',
                    'version' => 'Laravel Enterprise 1.0',
                    'timestamp' => now()->toISOString(),
                    'last_processing' => 'Mai'
                ],
                'database' => [
                    'status' => 'connected',
                    'activities' => 371,
                    'alerts' => 16,
                    'technicians' => 4
                ],
                'recent_activity' => [
                    'alerts_last_hour' => 3,
                    'critical_alerts_today' => 1,
                    'activities_today' => 47
                ]
            ]);
        });
    }
    
    /**
     * Dashboard Data - Endpoint principale per dati dashboard
     */
    public function dashboardData(Request $request)
    {
        // Cache per 60 secondi per balance performance/real-time
        return Cache::remember('dashboard_data', 60, function () {
            return response()->json($this->generateDashboardData());
        });
    }
    
    /**
     * Get Alerts - Endpoint per tabella alerts
     */
    public function getAlerts(Request $request)
    {
        $alerts = $this->generateDemoAlerts();
        
        // Apply filters
        if ($request->has('severity')) {
            $severities = is_array($request->severity) ? $request->severity : [$request->severity];
            $alerts = array_filter($alerts, fn($alert) => in_array($alert['severity'], $severities));
        }
        
        if ($request->has('search')) {
            $search = strtolower($request->search);
            $alerts = array_filter($alerts, fn($alert) => 
                str_contains(strtolower($alert['message']), $search) ||
                str_contains(strtolower($alert['id']), $search)
            );
        }
        
        return response()->json([
            'alerts' => array_values($alerts)
        ]);
    }
    
    /**
     * Get single Alert - Per modal details
     */
    public function getAlert($id)
    {
        $alerts = $this->generateDemoAlerts();
        $alert = collect($alerts)->firstWhere('id', $id);
        
        if (!$alert) {
            return response()->json(['error' => 'Alert not found'], 404);
        }
        
        return response()->json(['alert' => $alert]);
    }
    
    /**
     * Get KPIs - Metriche principali dashboard
     */
    public function getKPIs()
    {
        return Cache::remember('kpis_data', 120, function () {
            return response()->json([
                'total_records' => 371,
                'accuracy' => 96.4,
                'total_alerts' => 16,
                'critical_alerts' => 1,
                'estimated_losses' => 450
            ]);
        });
    }
    
    /**
     * Export Alerts CSV
     */
    public function exportAlertsCSV(Request $request)
    {
        $alerts = $this->generateDemoAlerts();
        $csvData = $this->formatAlertsForCSV($alerts);
        $filename = 'bait_alerts_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        return response()->streamDownload(function() use ($csvData) {
            echo $csvData;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }
    
    /**
     * Export Alerts Excel
     */
    public function exportAlertsExcel(Request $request)
    {
        // Simplified Excel export - in production use Laravel Excel package
        return $this->exportAlertsCSV($request);
    }
    
    /**
     * Status Page - Per monitoring
     */
    public function statusPage()
    {
        $status = $this->systemStatus()->getData();
        return view('status', ['status' => $status]);
    }
    
    /**
     * Metrics endpoint - Per Prometheus/external monitoring
     */
    public function metrics()
    {
        $metrics = [
            'bait_alerts_total' => 16,
            'bait_alerts_critical' => 1,
            'bait_alerts_high' => 7,
            'bait_alerts_medium' => 8,
            'bait_activities_total' => 371,
            'bait_technicians_active' => 4,
            'bait_estimated_losses_euros' => 450
        ];
        
        // Prometheus format
        $output = '';
        foreach ($metrics as $name => $value) {
            $output .= "# TYPE {$name} gauge\n";
            $output .= "{$name} {$value}\n";
        }
        
        return response($output, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8'
        ]);
    }
    
    /**
     * Python Compatibility Data - Per backward compatibility
     */
    public function pythonCompatibilityData(Request $request)
    {
        $dashboardData = $this->generateDashboardData();
        
        // Formato compatibile con Python dashboard
        return response()->json([
            'alerts_v2' => [
                'processed_alerts' => [
                    'alerts' => $dashboardData['alerts']
                ]
            ],
            'kpis_v2' => [
                'system_kpis' => $dashboardData['kpis']
            ],
            'metadata' => $dashboardData['metadata']
        ]);
    }
    
    // PRIVATE METHODS
    
    private function generateDashboardData()
    {
        $alerts = $this->generateDemoAlerts();
        
        return [
            'alerts' => $alerts,
            'kpis' => [
                'total_records' => 371,
                'accuracy' => 96.4,
                'total_alerts' => count($alerts),
                'critical_alerts' => count(array_filter($alerts, fn($a) => $a['severity'] === 'CRITICO')),
                'estimated_losses' => array_sum(array_column($alerts, 'estimated_cost'))
            ],
            'metadata' => [
                'version' => 'Laravel Enterprise 1.0',
                'timestamp' => now()->toISOString(),
                'source' => 'laravel_system'
            ]
        ];
    }

    /**
     * Get overview metrics for today
     */
    private function getOverviewMetrics(Carbon $date): array
    {
        $todayActivities = Activity::sameDay($date)->count();
        $todayAlerts = Alert::whereDate('created_at', $date)->count();
        $criticalAlerts = Alert::whereDate('created_at', $date)->critical()->count();
        $activeTechnicians = Activity::sameDay($date)->distinct('creato_da')->count('creato_da');

        // Calculate efficiency
        $totalHours = Activity::sameDay($date)
            ->get()
            ->sum(fn($activity) => $activity->getDurationMinutes()) / 60;
        
        $expectedHours = $activeTechnicians * 8; // 8 hours per technician
        $efficiency = $expectedHours > 0 ? round(($totalHours / $expectedHours) * 100, 1) : 0;

        return [
            'total_activities' => $todayActivities,
            'total_alerts' => $todayAlerts,
            'critical_alerts' => $criticalAlerts,
            'active_technicians' => $activeTechnicians,
            'total_hours_worked' => round($totalHours, 2),
            'efficiency_percentage' => $efficiency,
            'system_health' => $this->calculateSystemHealth($criticalAlerts, $todayAlerts)
        ];
    }

    /**
     * Get technician-specific metrics
     */
    private function getTechnicianMetrics(Carbon $date): array
    {
        $technicians = Technician::active()
            ->with(['activities' => fn($q) => $q->sameDay($date)])
            ->with(['alerts' => fn($q) => $q->whereDate('created_at', $date)])
            ->get();

        return $technicians->map(function ($technician) use ($date) {
            $todayActivities = $technician->activities;
            $todayAlerts = $technician->alerts;
            
            $totalMinutes = $todayActivities->sum(fn($activity) => $activity->getDurationMinutes());
            $efficiency = $technician->getDailyEfficiencyScore($date->toDateTime());

            return [
                'name' => $technician->name,
                'activities_count' => $todayActivities->count(),
                'total_hours' => round($totalMinutes / 60, 2),
                'efficiency' => $efficiency,
                'alerts_count' => $todayAlerts->count(),
                'critical_alerts' => $todayAlerts->where('severity', Alert::SEVERITY_CRITICO)->count(),
                'overlap_incidents' => $todayAlerts->where('category', 'temporal_overlap')->count(),
                'travel_violations' => $todayAlerts->where('category', 'insufficient_travel_time')->count()
            ];
        })->toArray();
    }

    /**
     * Get alert metrics and breakdown
     */
    private function getAlertMetrics(Carbon $date): array
    {
        $todayAlerts = Alert::whereDate('created_at', $date)->get();
        
        $byCategory = $todayAlerts->groupBy('category')->map->count();
        $bySeverity = $todayAlerts->groupBy('severity')->map->count();
        $byTechnician = $todayAlerts->groupBy('tecnico')->map->count();

        // Top issues
        $topIssues = $todayAlerts
            ->groupBy('category')
            ->map(function ($alerts, $category) {
                $avgConfidence = $alerts->avg('confidence_score');
                return [
                    'category' => $category,
                    'count' => $alerts->count(),
                    'avg_confidence' => round($avgConfidence, 1),
                    'critical_count' => $alerts->where('severity', Alert::SEVERITY_CRITICO)->count()
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->toArray();

        return [
            'total_alerts' => $todayAlerts->count(),
            'by_category' => $byCategory->toArray(),
            'by_severity' => $bySeverity->toArray(),
            'by_technician' => $byTechnician->toArray(),
            'top_issues' => array_slice($topIssues, 0, 5),
            'resolution_rate' => $this->calculateResolutionRate($date),
            'false_positive_rate' => $this->calculateFalsePositiveRate($date)
        ];
    }

    /**
     * Get trend data for the week
     */
    private function getTrendData(Carbon $startWeek, Carbon $endDate): array
    {
        $trends = [];
        $current = $startWeek->copy();

        while ($current <= $endDate) {
            $dayActivities = Activity::sameDay($current)->count();
            $dayAlerts = Alert::whereDate('created_at', $current)->count();
            $dayCritical = Alert::whereDate('created_at', $current)->critical()->count();

            $trends[] = [
                'date' => $current->format('Y-m-d'),
                'day_name' => $current->format('l'),
                'activities' => $dayActivities,
                'alerts' => $dayAlerts,
                'critical_alerts' => $dayCritical,
                'efficiency' => $this->calculateDayEfficiency($current)
            ];

            $current->addDay();
        }

        return $trends;
    }

    /**
     * Get performance metrics for the month
     */
    private function getPerformanceMetrics(Carbon $monthStart): array
    {
        $monthEnd = $monthStart->copy()->endOfMonth();
        
        $monthActivities = Activity::whereBetween('iniziata_il', [$monthStart, $monthEnd])->get();
        $monthAlerts = Alert::whereBetween('created_at', [$monthStart, $monthEnd])->get();

        // Productivity by technician
        $techProductivity = $monthActivities
            ->groupBy('creato_da')
            ->map(function ($activities, $tecnico) {
                $totalHours = $activities->sum(fn($activity) => $activity->getDurationMinutes()) / 60;
                $avgDaily = $totalHours / max(1, $activities->pluck('iniziata_il')->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))->unique()->count());
                
                return [
                    'tecnico' => $tecnico,
                    'total_hours' => round($totalHours, 2),
                    'avg_daily_hours' => round($avgDaily, 2),
                    'activities_count' => $activities->count(),
                    'avg_activity_duration' => round($totalHours / $activities->count(), 2)
                ];
            })
            ->sortByDesc('total_hours')
            ->values()
            ->toArray();

        // Quality metrics
        $qualityMetrics = [
            'overlap_accuracy' => $this->calculateOverlapAccuracy($monthStart, $monthEnd),
            'travel_time_accuracy' => $this->calculateTravelTimeAccuracy($monthStart, $monthEnd),
            'data_completeness' => $this->calculateDataCompleteness($monthStart, $monthEnd),
            'avg_confidence_score' => round($monthAlerts->avg('confidence_score'), 2)
        ];

        return [
            'productivity' => $techProductivity,
            'quality' => $qualityMetrics,
            'month_summary' => [
                'total_activities' => $monthActivities->count(),
                'total_hours' => round($monthActivities->sum(fn($activity) => $activity->getDurationMinutes()) / 60, 2),
                'total_alerts' => $monthAlerts->count(),
                'critical_alerts' => $monthAlerts->where('severity', Alert::SEVERITY_CRITICO)->count(),
                'estimated_losses' => $this->calculateEstimatedLosses($monthAlerts)
            ]
        ];
    }

    /**
     * Get client-specific metrics
     */
    private function getClientMetrics(Carbon $monthStart): array
    {
        $monthEnd = $monthStart->copy()->endOfMonth();
        
        $clientStats = Activity::whereBetween('iniziata_il', [$monthStart, $monthEnd])
            ->get()
            ->groupBy('azienda')
            ->map(function ($activities, $clientName) {
                $totalHours = $activities->sum(fn($activity) => $activity->getDurationMinutes()) / 60;
                $uniqueTechs = $activities->pluck('creato_da')->unique()->count();
                $remoteCount = $activities->filter(fn($activity) => $activity->isRemoteActivity())->count();
                
                return [
                    'client_name' => $clientName,
                    'activities_count' => $activities->count(),
                    'total_hours' => round($totalHours, 2),
                    'unique_technicians' => $uniqueTechs,
                    'remote_activities' => $remoteCount,
                    'avg_duration' => round($totalHours / $activities->count(), 2)
                ];
            })
            ->sortByDesc('total_hours')
            ->take(20)
            ->values()
            ->toArray();

        // Travel problematic pairs
        $travelIssues = Client::getProblematicTravelPairs();

        return [
            'top_clients' => $clientStats,
            'travel_issues' => $travelIssues
        ];
    }

    /**
     * Calculate system health score
     */
    private function calculateSystemHealth(int $criticalAlerts, int $totalAlerts): array
    {
        if ($totalAlerts === 0) {
            return ['score' => 100, 'status' => 'excellent', 'color' => 'success'];
        }

        $criticalRatio = $criticalAlerts / $totalAlerts;
        $healthScore = max(0, 100 - ($criticalRatio * 50) - (max(0, $totalAlerts - 5) * 2));

        $status = match (true) {
            $healthScore >= 90 => ['status' => 'excellent', 'color' => 'success'],
            $healthScore >= 75 => ['status' => 'good', 'color' => 'primary'],
            $healthScore >= 60 => ['status' => 'fair', 'color' => 'warning'],
            default => ['status' => 'critical', 'color' => 'danger']
        };

        return array_merge(['score' => round($healthScore, 1)], $status);
    }

    /**
     * Calculate daily efficiency for a specific date
     */
    private function calculateDayEfficiency(Carbon $date): float
    {
        $dayActivities = Activity::sameDay($date)->get();
        
        if ($dayActivities->isEmpty()) {
            return 0;
        }

        $totalHours = $dayActivities->sum(fn($activity) => $activity->getDurationMinutes()) / 60;
        $activeTechs = $dayActivities->pluck('creato_da')->unique()->count();
        $expectedHours = $activeTechs * 8;

        return $expectedHours > 0 ? round(($totalHours / $expectedHours) * 100, 1) : 0;
    }

    /**
     * Calculate resolution rate for alerts
     */
    private function calculateResolutionRate(Carbon $date): float
    {
        $dayAlerts = Alert::whereDate('created_at', $date)->count();
        $resolvedAlerts = Alert::whereDate('created_at', $date)->where('is_resolved', true)->count();

        return $dayAlerts > 0 ? round(($resolvedAlerts / $dayAlerts) * 100, 1) : 0;
    }

    /**
     * Calculate false positive rate
     */
    private function calculateFalsePositiveRate(Carbon $date): float
    {
        $dayAlerts = Alert::whereDate('created_at', $date)->count();
        $falsePositives = Alert::whereDate('created_at', $date)->where('false_positive', true)->count();

        return $dayAlerts > 0 ? round(($falsePositives / $dayAlerts) * 100, 1) : 0;
    }

    /**
     * Calculate overlap detection accuracy
     */
    private function calculateOverlapAccuracy(Carbon $start, Carbon $end): float
    {
        $overlapAlerts = Alert::whereBetween('created_at', [$start, $end])
            ->where('category', 'temporal_overlap')
            ->get();

        if ($overlapAlerts->isEmpty()) {
            return 100;
        }

        $falsePositives = $overlapAlerts->where('false_positive', true)->count();
        return round((($overlapAlerts->count() - $falsePositives) / $overlapAlerts->count()) * 100, 1);
    }

    /**
     * Calculate travel time validation accuracy
     */
    private function calculateTravelTimeAccuracy(Carbon $start, Carbon $end): float
    {
        $travelAlerts = Alert::whereBetween('created_at', [$start, $end])
            ->where('category', 'insufficient_travel_time')
            ->get();

        if ($travelAlerts->isEmpty()) {
            return 100;
        }

        $falsePositives = $travelAlerts->where('false_positive', true)->count();
        return round((($travelAlerts->count() - $falsePositives) / $travelAlerts->count()) * 100, 1);
    }

    /**
     * Calculate data completeness score
     */
    private function calculateDataCompleteness(Carbon $start, Carbon $end): float
    {
        $activities = Activity::whereBetween('iniziata_il', [$start, $end])->get();
        
        if ($activities->isEmpty()) {
            return 100;
        }

        $requiredFields = ['contratto', 'id_ticket', 'azienda', 'tipologia_attivita', 'creato_da'];
        $totalFields = $activities->count() * count($requiredFields);
        $completedFields = 0;

        foreach ($activities as $activity) {
            foreach ($requiredFields as $field) {
                if (!empty($activity->$field) && $activity->$field !== 'null') {
                    $completedFields++;
                }
            }
        }

        return round(($completedFields / $totalFields) * 100, 1);
    }

    /**
     * Calculate estimated financial losses from overlaps
     */
    private function calculateEstimatedLosses($alerts): float
    {
        return $alerts
            ->where('category', 'temporal_overlap')
            ->sum(function ($alert) {
                $details = $alert->details;
                $overlapMinutes = $details['overlap_minutes'] ?? 0;
                return $overlapMinutes * 0.75; // €0.75 per minute overlap
            });
    }

    /**
     * Get real-time system status
     */
    public function getSystemStatus(): JsonResponse
    {
        $status = [
            'timestamp' => now()->toISOString(),
            'system' => [
                'status' => 'operational',
                'version' => 'Laravel Enterprise 1.0',
                'uptime' => $this->getSystemUptime(),
                'last_processing' => Cache::get('last_processing_time', 'Never')
            ],
            'database' => [
                'activities' => Activity::count(),
                'alerts' => Alert::count(),
                'technicians' => Technician::count(),
                'clients' => Client::count()
            ],
            'recent_activity' => [
                'last_activity' => Activity::latest()->first()?->created_at->toISOString(),
                'last_alert' => Alert::latest()->first()?->created_at->toISOString(),
                'alerts_last_hour' => Alert::where('created_at', '>=', now()->subHour())->count(),
                'critical_alerts_today' => Alert::whereDate('created_at', today())->critical()->count()
            ]
        ];

        return response()->json($status);
    }

    private function generateDemoAlerts()
    {
        $technicians = ['Gabriele De Palma', 'Davide Cestone', 'Arlind Hoxha', 'Matteo Rossi'];
        $categories = ['temporal_overlap', 'travel_time', 'geolocation'];
        $severities = ['CRITICO', 'ALTO', 'MEDIO'];
        
        $alerts = [];
        for ($i = 0; $i < 16; $i++) {
            $severity = $i < 1 ? 'CRITICO' : ($i < 8 ? 'ALTO' : 'MEDIO');
            $category = $categories[array_rand($categories)];
            
            $alerts[] = [
                'id' => 'BAIT_' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'severity' => $severity,
                'confidence_score' => $severity === 'CRITICO' ? 95 : ($severity === 'ALTO' ? 85 : 75),
                'tecnico' => $technicians[array_rand($technicians)],
                'message' => $this->generateDemoMessage($category, $severity),
                'category' => $category,
                'timestamp' => now()->subMinutes(rand(0, 1440))->toISOString(),
                'estimated_cost' => $severity === 'CRITICO' ? 75 : ($severity === 'ALTO' ? 45 : 25),
                'details' => $this->generateAlertDetails($category, $i)
            ];
        }
        
        return $alerts;
    }
    
    private function generateDemoMessage($category, $severity)
    {
        $messages = [
            'temporal_overlap' => [
                'CRITICO' => 'Sovrapposizione temporale CRITICA rilevata - Fatturazione doppia',
                'ALTO' => 'Sovrapposizione temporale significativa tra clienti diversi',
                'MEDIO' => 'Possibile sovrapposizione orari dichiarati'
            ],
            'travel_time' => [
                'CRITICO' => 'Tempo viaggio insufficiente - Spostamento fisicamente impossibile',
                'ALTO' => 'Discrepanza significativa tempi viaggio dichiarati vs stimati',
                'MEDIO' => 'Tempo viaggio ottimistico - Verifica necessaria'
            ],
            'geolocation' => [
                'CRITICO' => 'Posizione GPS incompatibile con cliente dichiarato',
                'ALTO' => 'Discrepanza significativa geolocalizzazione vs indirizzo',
                'MEDIO' => 'Posizione GPS da verificare'
            ]
        ];
        
        return $messages[$category][$severity] ?? 'Alert rilevato dal sistema';
    }
    
    private function generateAlertDetails($category, $index)
    {
        $clients = ['ACME Corp', 'TechnoSoft SRL', 'Milano Dynamics', 'Roma Solutions'];
        $locations = [
            'Via Roma 15, Milano (MI)',
            'Corso Venezia 22, Milano (MI)',
            'Via del Corso 45, Roma (RM)',
            'Via Toledo 88, Napoli (NA)'
        ];
        
        $details = [
            'timeline' => [],
            'overlap' => '',
            'distance' => '',
            'travelTime' => '',
            'impossibility' => '',
            'declaredTime' => '',
            'estimatedTime' => '',
            'discrepancy' => ''
        ];
        
        if ($category === 'temporal_overlap') {
            $details['timeline'] = [
                [
                    'client' => $clients[$index % count($clients)],
                    'time' => '09:00-13:00',
                    'location' => $locations[$index % count($locations)],
                    'activity' => 'Manutenzione server principale',
                    'anomaly' => false
                ],
                [
                    'client' => $clients[($index + 1) % count($clients)],
                    'time' => '11:30-15:30',
                    'location' => $locations[($index + 1) % count($locations)],
                    'activity' => 'Installazione nuovo sistema',
                    'anomaly' => true
                ]
            ];
            $details['overlap'] = '11:30-13:00 (90 minuti)';
            $details['impossibility'] = 'Impossibile essere contemporaneamente in due luoghi diversi';
        } elseif ($category === 'travel_time') {
            $details['timeline'] = [
                [
                    'client' => $clients[$index % count($clients)],
                    'time' => '14:00-18:00',
                    'location' => $locations[$index % count($locations)],
                    'activity' => 'Intervento di assistenza tecnica',
                    'anomaly' => true
                ]
            ];
            $details['distance'] = (12 + $index * 2) . '.5 km';
            $details['declaredTime'] = (30 + $index * 5) . ' minuti';
            $details['estimatedTime'] = (45 + $index * 8) . ' minuti';
            $details['discrepancy'] = '-' . (15 + $index * 3) . ' minuti';
            $details['impossibility'] = 'Tempo di viaggio dichiarato insufficiente per raggiungere la destinazione';
        }
        
        return $details;
    }
    
    private function formatAlertsForCSV($alerts)
    {
        $csv = "ID,Severity,Technician,Category,Confidence,Message,Cost,Timestamp\n";
        
        foreach ($alerts as $alert) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,\"%s\",%.2f,%s\n",
                $alert['id'],
                $alert['severity'],
                $alert['tecnico'],
                $alert['category'],
                $alert['confidence_score'],
                str_replace('"', '""', $alert['message']),
                $alert['estimated_cost'],
                date('d/m/Y H:i', strtotime($alert['timestamp']))
            );
        }
        
        return $csv;
    }
    
    /**
     * Get system uptime (simplified for demo)
     */
    private function getSystemUptime(): string
    {
        // In production, this would track actual system start time
        return "24h 30m 15s";
    }
}