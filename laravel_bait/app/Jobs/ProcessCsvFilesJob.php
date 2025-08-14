<?php

namespace App\Jobs;

use App\Services\BusinessRulesEngine;
use App\Services\CsvProcessorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * BAIT Service - CSV Processing Job
 * 
 * Asynchronous CSV processing job for large file handling
 * Migrated from Python scheduling system
 */
class ProcessCsvFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout
    public $maxAttempts = 3;

    private bool $forceRefresh;
    private ?string $processingBatchId;

    /**
     * Create a new job instance.
     */
    public function __construct(bool $forceRefresh = false, ?string $processingBatchId = null)
    {
        $this->forceRefresh = $forceRefresh;
        $this->processingBatchId = $processingBatchId;
        
        // Set queue for background processing
        $this->onQueue('csv-processing');
    }

    /**
     * Execute the job.
     */
    public function handle(CsvProcessorService $csvProcessor, BusinessRulesEngine $businessRules): void
    {
        $startTime = microtime(true);
        $jobId = $this->processingBatchId ?: uniqid('job_', true);
        
        Log::info("🔄 Starting asynchronous CSV processing job [ID: {$jobId}]");

        try {
            // Check cache first unless force refresh
            $cacheKey = 'bait_processing_result';
            if (!$this->forceRefresh && Cache::has($cacheKey)) {
                Log::info("⚡ Using cached result for job {$jobId}");
                return;
            }

            // Step 1: Process CSV files
            Log::info("📄 Processing CSV files...");
            $processingResult = $csvProcessor->processAllFiles();
            
            if (!$processingResult['success']) {
                Log::error("❌ CSV processing failed", $processingResult['errors']);
                $this->fail(new \Exception('CSV processing failed: ' . implode(', ', $processingResult['errors'])));
                return;
            }

            Log::info("✅ CSV processing completed: {$processingResult['total_records']} records processed");

            // Step 2: Run business rules validation
            Log::info("🧠 Running business rules validation...");
            $alerts = $businessRules->validateAllRules();
            
            Log::info("✅ Business rules completed: {$alerts->count()} alerts generated");

            // Step 3: Calculate KPIs and statistics
            $kpis = $this->calculateSystemKpis($processingResult['data'], $alerts);
            $statistics = $this->calculateAlertStatistics($alerts);

            // Step 4: Create and cache result
            $result = [
                'metadata' => [
                    'version' => 'Enterprise Laravel 1.0 (Async)',
                    'processing_id' => $jobId,
                    'generation_time' => now()->toISOString(),
                    'processing_duration' => microtime(true) - $startTime,
                    'data_source' => 'async_job',
                    'files_processed' => $processingResult['files_processed']
                ],
                'kpis_v2' => [
                    'system_kpis' => $kpis
                ],
                'alerts_v2' => [
                    'processed_alerts' => [
                        'alerts' => $alerts->map(fn($alert) => $this->formatAlertForResult($alert))
                    ],
                    'statistics' => $statistics
                ],
                'system_metrics' => $this->getSystemMetrics()
            ];

            // Cache result for 5 minutes
            Cache::put($cacheKey, $result, 300);
            
            // Store processing timestamp
            Cache::put('last_processing_time', now()->toISOString(), 3600);

            $processingTime = microtime(true) - $startTime;
            Log::info("✅ Asynchronous processing completed successfully in {$processingTime}s");

        } catch (\Exception $e) {
            Log::error("❌ Asynchronous processing failed: {$e->getMessage()}", [
                'exception' => $e,
                'job_id' => $jobId
            ]);
            
            // Store error in cache for debugging
            Cache::put('last_processing_error', [
                'message' => $e->getMessage(),
                'time' => now()->toISOString(),
                'job_id' => $jobId
            ], 3600);

            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("🔥 CSV Processing job failed permanently", [
            'exception' => $exception->getMessage(),
            'job_id' => $this->processingBatchId,
            'attempts' => $this->attempts()
        ]);

        // Notify system administrators or trigger fallback processing
        // This could send emails, Slack notifications, etc.
    }

    /**
     * Calculate system KPIs
     */
    private function calculateSystemKpis(array $processedData, $alerts): array
    {
        $totalRecords = collect($processedData)->sum(fn($data) => count($data));
        $criticalAlerts = $alerts->where('severity', 'CRITICO')->count();
        $highAlerts = $alerts->where('severity', 'ALTO')->count();

        // Calculate accuracy (higher when fewer critical issues)
        $accuracy = max(85, 100 - ($criticalAlerts * 2) - ($highAlerts * 1));

        // Estimate losses based on overlap alerts
        $estimatedLosses = $alerts
            ->where('category', 'temporal_overlap')
            ->sum(function ($alert) {
                $details = $alert->details ?? [];
                $overlapMinutes = $details['overlap_minutes'] ?? 0;
                return $overlapMinutes * 0.75; // €0.75 per minute overlap
            });

        return [
            'total_records_processed' => $totalRecords,
            'estimated_accuracy' => round($accuracy, 1),
            'alerts_generated' => $alerts->count(),
            'critical_alerts' => $criticalAlerts,
            'high_alerts' => $highAlerts,
            'medium_alerts' => $alerts->where('severity', 'MEDIO')->count(),
            'low_alerts' => $alerts->where('severity', 'BASSO')->count(),
            'estimated_losses' => round($estimatedLosses, 2),
            'files_processed' => count($processedData),
            'processing_timestamp' => now()->toISOString(),
            'processing_mode' => 'asynchronous'
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
     * Format alert for result
     */
    private function formatAlertForResult($alert): array
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
            'version' => 'Laravel Enterprise 1.0 (Async)',
            'processing_mode' => 'asynchronous',
            'start_time' => now()->toISOString(),
            'total_activities' => \App\Models\Activity::count(),
            'total_alerts' => \App\Models\Alert::count(),
            'active_technicians' => \App\Models\Technician::active()->count(),
            'active_clients' => \App\Models\Client::active()->count(),
            'queue_status' => 'processing'
        ];
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['csv-processing', 'business-rules', 'bait-service'];
    }
}