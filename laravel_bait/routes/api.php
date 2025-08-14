<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AIController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| BAIT Service API Routes
|--------------------------------------------------------------------------
|
| Enterprise API routes for BAIT Service control system
| Compatible with existing Python dashboard frontend
|
*/

// System status and health check
Route::get('/system/status', [DashboardController::class, 'getSystemStatus'])
    ->name('api.system.status');

// Main processing endpoint (migrated from Python)
Route::post('/activities/process', [ActivityController::class, 'processAllFiles'])
    ->name('api.activities.process');

// Dashboard data endpoints
Route::prefix('dashboard')->group(function () {
    Route::get('/data', [DashboardController::class, 'getDashboardData'])
        ->name('api.dashboard.data');
});

// Activity analysis endpoints
Route::prefix('activities')->group(function () {
    // Temporal overlaps detection
    Route::get('/overlaps', [ActivityController::class, 'getTemporalOverlaps'])
        ->name('api.activities.overlaps');
    
    // Travel time analysis
    Route::get('/travel-time', [ActivityController::class, 'getTravelTimeAnalysis'])
        ->name('api.activities.travel-time');
});

// Alert management endpoints
Route::prefix('alerts')->group(function () {
    // Get all alerts with filtering
    Route::get('/', function (Request $request) {
        $query = \App\Models\Alert::query();
        
        if ($request->has('severity')) {
            $query->where('severity', $request->input('severity'));
        }
        
        if ($request->has('tecnico')) {
            $query->where('tecnico', $request->input('tecnico'));
        }
        
        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }
        
        if ($request->boolean('unresolved_only', false)) {
            $query->unresolved();
        }
        
        $alerts = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 50));
        
        return response()->json($alerts);
    })->name('api.alerts.index');
    
    // Get specific alert
    Route::get('/{alert}', function (\App\Models\Alert $alert) {
        return response()->json($alert->load('technician'));
    })->name('api.alerts.show');
    
    // Resolve alert
    Route::patch('/{alert}/resolve', function (\App\Models\Alert $alert, Request $request) {
        $alert->resolve($request->input('resolved_by'));
        return response()->json(['message' => 'Alert resolved successfully', 'alert' => $alert]);
    })->name('api.alerts.resolve');
    
    // Mark as false positive
    Route::patch('/{alert}/false-positive', function (\App\Models\Alert $alert, Request $request) {
        $alert->markAsFalsePositive($request->input('resolved_by'));
        return response()->json(['message' => 'Alert marked as false positive', 'alert' => $alert]);
    })->name('api.alerts.false-positive');
});

// Technician endpoints
Route::prefix('technicians')->group(function () {
    // List all technicians
    Route::get('/', function (Request $request) {
        $query = \App\Models\Technician::query();
        
        if ($request->boolean('active_only', false)) {
            $query->active();
        }
        
        $technicians = $query->with(['activities' => function ($q) use ($request) {
            if ($request->has('date')) {
                $q->sameDay(\Carbon\Carbon::parse($request->input('date')));
            }
        }])->get();
        
        return response()->json($technicians);
    })->name('api.technicians.index');
    
    // Get technician performance metrics
    Route::get('/{technician}/metrics', function (\App\Models\Technician $technician, Request $request) {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);
        
        $metrics = $technician->getMonthlyMetrics($year, $month);
        $trend = $technician->getPerformanceTrend($request->input('days', 30));
        
        return response()->json([
            'technician' => $technician,
            'metrics' => $metrics,
            'trend' => $trend
        ]);
    })->name('api.technicians.metrics');
});

// Client endpoints
Route::prefix('clients')->group(function () {
    // List all clients
    Route::get('/', function (Request $request) {
        $query = \App\Models\Client::query();
        
        if ($request->boolean('active_only', false)) {
            $query->active();
        }
        
        $clients = $query->get();
        return response()->json($clients);
    })->name('api.clients.index');
    
    // Get problematic travel pairs
    Route::get('/travel-issues', function () {
        $travelIssues = \App\Models\Client::getProblematicTravelPairs();
        return response()->json($travelIssues);
    })->name('api.clients.travel-issues');
    
    // Get client monthly stats
    Route::get('/{client}/stats', function (\App\Models\Client $client, Request $request) {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);
        
        $stats = $client->getMonthlyStats($year, $month);
        return response()->json($stats);
    })->name('api.clients.stats');
});

// CSV Processing endpoints
Route::prefix('processing')->group(function () {
    // Get processing statistics
    Route::get('/stats', function () {
        $csvProcessor = new \App\Services\CsvProcessorService();
        $stats = $csvProcessor->getProcessingStats();
        return response()->json($stats);
    })->name('api.processing.stats');
    
    // Manual trigger processing (for testing)
    Route::post('/trigger', [ActivityController::class, 'processAllFiles'])
        ->name('api.processing.trigger');
});

// Business Rules Engine endpoints
Route::prefix('business-rules')->group(function () {
    // Run business rules validation manually
    Route::post('/validate', function () {
        $engine = new \App\Services\BusinessRulesEngine();
        $alerts = $engine->validateAllRules();
        
        return response()->json([
            'message' => 'Business rules validation completed',
            'alerts_generated' => $alerts->count(),
            'alerts' => $alerts
        ]);
    })->name('api.business-rules.validate');
});

// AI Business Intelligence endpoints
Route::prefix('ai')->group(function () {
    // Enhance alert with AI analysis
    Route::post('/alerts/enhance', [AIController::class, 'enhanceAlert'])
        ->name('api.ai.alerts.enhance');
    
    // Generate executive AI report
    Route::post('/reports/executive', [AIController::class, 'generateExecutiveReport'])
        ->name('api.ai.reports.executive');
    
    // Get predictive analytics
    Route::get('/predictions', [AIController::class, 'predictiveAnalysis'])
        ->name('api.ai.predictions');
    
    // Optimize alert confidence scoring
    Route::post('/alerts/optimize-confidence', [AIController::class, 'optimizeConfidence'])
        ->name('api.ai.alerts.optimize-confidence');
    
    // AI service performance metrics
    Route::get('/metrics', [AIController::class, 'getPerformanceMetrics'])
        ->name('api.ai.metrics');
    
    // Smart alert triage
    Route::get('/triage', [AIController::class, 'smartTriage'])
        ->name('api.ai.triage');
    
    // Natural language alert summary
    Route::post('/alerts/summarize', [AIController::class, 'generateAlertSummary'])
        ->name('api.ai.alerts.summarize');
    
    // AI-powered dashboard insights
    Route::get('/dashboard/insights', [AIController::class, 'getDashboardInsights'])
        ->name('api.ai.dashboard.insights');
});

// Analytics endpoints
Route::prefix('analytics')->group(function () {
    // Overview analytics
    Route::get('/overview', function (Request $request) {
        $dateFrom = $request->input('date_from', now()->subMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());
        
        $activities = \App\Models\Activity::whereBetween('iniziata_il', [$dateFrom, $dateTo])->get();
        $alerts = \App\Models\Alert::whereBetween('created_at', [$dateFrom, $dateTo])->get();
        
        $analytics = [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'activities' => [
                'total' => $activities->count(),
                'by_technician' => $activities->groupBy('creato_da')->map->count(),
                'by_client' => $activities->groupBy('azienda')->map->count(),
                'total_hours' => round($activities->sum(fn($a) => $a->getDurationMinutes()) / 60, 2)
            ],
            'alerts' => [
                'total' => $alerts->count(),
                'by_severity' => $alerts->groupBy('severity')->map->count(),
                'by_category' => $alerts->groupBy('category')->map->count(),
                'resolution_rate' => $alerts->count() > 0 ? round($alerts->where('is_resolved', true)->count() / $alerts->count() * 100, 2) : 0
            ]
        ];
        
        return response()->json($analytics);
    })->name('api.analytics.overview');
    
    // Efficiency trends
    Route::get('/efficiency', function (Request $request) {
        $days = $request->input('days', 30);
        $endDate = now();
        $startDate = now()->subDays($days);
        
        $trends = [];
        $current = $startDate->copy();
        
        while ($current <= $endDate) {
            $dayActivities = \App\Models\Activity::sameDay($current)->get();
            $totalHours = $dayActivities->sum(fn($a) => $a->getDurationMinutes()) / 60;
            $activeTechs = $dayActivities->pluck('creato_da')->unique()->count();
            $expectedHours = $activeTechs * 8;
            
            $trends[] = [
                'date' => $current->toDateString(),
                'efficiency' => $expectedHours > 0 ? round(($totalHours / $expectedHours) * 100, 2) : 0,
                'total_hours' => round($totalHours, 2),
                'active_technicians' => $activeTechs,
                'activities_count' => $dayActivities->count()
            ];
            
            $current->addDay();
        }
        
        return response()->json($trends);
    })->name('api.analytics.efficiency');
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => 'Laravel Enterprise 1.0',
        'database' => [
            'activities' => \App\Models\Activity::count(),
            'alerts' => \App\Models\Alert::count(),
            'technicians' => \App\Models\Technician::count(),
            'clients' => \App\Models\Client::count()
        ]
    ]);
})->name('api.health');

// Fallback route for API
Route::fallback(function () {
    return response()->json([
        'message' => 'API endpoint not found',
        'available_endpoints' => [
            'GET /api/health' => 'System health check',
            'POST /api/activities/process' => 'Process CSV files',
            'GET /api/dashboard/data' => 'Get dashboard data',
            'GET /api/activities/overlaps' => 'Get temporal overlaps',
            'GET /api/alerts' => 'Get alerts with filtering',
            'GET /api/technicians' => 'Get technicians list',
            'GET /api/analytics/overview' => 'Get analytics overview'
        ]
    ], 404);
});