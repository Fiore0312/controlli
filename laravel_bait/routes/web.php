<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ActivityController;

/*
|--------------------------------------------------------------------------
| BAIT Service Enterprise Web Routes
|--------------------------------------------------------------------------
|
| Routes per dashboard enterprise Laravel che sostituisce Python Dash
| Ottimizzate per performance e compatibilità con sistema esistente
|
*/

// Home e Dashboard principale
Route::get('/', function () {
    return view('welcome', [
        'title' => 'BAIT Service Enterprise',
        'version' => 'Laravel Enterprise 1.0'
    ]);
})->name('home');

Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

// API Routes per compatibilità con sistema Python
Route::prefix('api')->group(function () {
    
    // Sistema Status - Compatibile con dashboard.js
    Route::get('/system/status', [DashboardController::class, 'systemStatus'])
        ->name('api.system.status');
    
    // Dashboard Data - Endpoint principale per dati live
    Route::get('/dashboard/data', [DashboardController::class, 'dashboardData'])
        ->name('api.dashboard.data');
    
    // Activities Processing - Trigger elaborazione manuale
    Route::post('/activities/process', [ActivityController::class, 'processActivities'])
        ->name('api.activities.process');
    
    // Activities Data - Per tabelle e export
    Route::get('/activities', [ActivityController::class, 'index'])
        ->name('api.activities.index');
    
    // Alerts endpoint - Compatibile con Python alerts
    Route::get('/alerts', [DashboardController::class, 'getAlerts'])
        ->name('api.alerts.index');
    
    Route::get('/alerts/{id}', [DashboardController::class, 'getAlert'])
        ->name('api.alerts.show');
    
    // KPIs e Metriche
    Route::get('/kpis', [DashboardController::class, 'getKPIs'])
        ->name('api.kpis');
    
    // Export endpoints
    Route::get('/export/alerts/csv', [DashboardController::class, 'exportAlertsCSV'])
        ->name('api.export.alerts.csv');
    
    Route::get('/export/alerts/excel', [DashboardController::class, 'exportAlertsExcel'])
        ->name('api.export.alerts.excel');
});

// Upload CSV - Compatibile con sistema Python esistente
Route::post('/upload/csv', [ActivityController::class, 'uploadCSV'])
    ->name('upload.csv');

// Process CSV - Trigger processing dopo upload
Route::post('/process/csv', [ActivityController::class, 'processCSV'])
    ->name('process.csv');

// Status page - Per monitoring sistema
Route::get('/status', [DashboardController::class, 'statusPage'])
    ->name('status');

// API Documentation - Self-documenting
Route::get('/api-docs', function () {
    return view('api-docs');
})->name('api.docs');

// Upload page - UI per upload CSV
Route::get('/upload', function () {
    return view('upload');
})->name('upload.page');

// Manual process page - Per elaborazioni manuali
Route::get('/process', function () {
    return view('process');
})->name('process.page');

// Health Check - Per load balancer/monitoring esterno
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => 'Laravel Enterprise 1.0',
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version()
    ]);
})->name('health');

// Metrics endpoint - Per Prometheus/monitoring
Route::get('/metrics', [DashboardController::class, 'metrics'])
    ->name('metrics');

// WebSocket endpoint per real-time updates (future)
Route::get('/ws', function () {
    return response()->json([
        'message' => 'WebSocket endpoint - Future implementation',
        'available' => false
    ]);
})->name('websocket');

// Backward compatibility con Python URLs
Route::get('/python-dashboard', function () {
    return redirect('/dashboard');
})->name('python.redirect');

// Compatibility endpoint per esistenti client Python
Route::post('/api/compatibility/python-data', [DashboardController::class, 'pythonCompatibilityData'])
    ->name('api.python.compatibility');