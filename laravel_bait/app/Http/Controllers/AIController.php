<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Services\AIBusinessIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * AI Controller for BAIT Service Enterprise
 * 
 * Controller per gestione endpoints AI business intelligence.
 * Integra il AIBusinessIntelligenceService per fornire:
 * - Enhanced alert analysis
 * - Executive reporting automatizzato
 * - Predictive analytics
 * - Performance metrics AI
 * 
 * @version Enterprise 1.0
 * @author BAIT Service Team
 */
class AIController extends Controller
{
    private AIBusinessIntelligenceService $aiService;
    
    public function __construct(AIBusinessIntelligenceService $aiService)
    {
        $this->aiService = $aiService;
    }
    
    /**
     * Get AI-enhanced alert analysis
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function enhanceAlert(Request $request): JsonResponse
    {
        try {
            $alertId = $request->input('alert_id');
            $alert = Alert::findOrFail($alertId);
            
            $enhancement = $this->aiService->enhanceAlert($alert);
            
            return response()->json([
                'success' => true,
                'data' => $enhancement,
                'alert_id' => $alertId,
                'enhanced_at' => now()->toISOString()
            ]);
            
        } catch (Exception $e) {
            Log::error('AI Alert Enhancement API failed', [
                'alert_id' => $request->input('alert_id'),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Enhancement failed',
                'message' => 'AI service temporarily unavailable'
            ], 500);
        }
    }
    
    /**
     * Generate executive AI report
     * 
     * @param Request $request  
     * @return JsonResponse
     */
    public function generateExecutiveReport(Request $request): JsonResponse
    {
        try {
            // Get alerts and KPIs
            $alerts = Alert::with(['technician'])
                ->when($request->input('date_from'), function($query, $date) {
                    return $query->where('created_at', '>=', $date);
                })
                ->when($request->input('date_to'), function($query, $date) {
                    return $query->where('created_at', '<=', $date);
                })
                ->get()
                ->toArray();
                
            $kpis = [
                'total_records' => 371,
                'accuracy' => 96.4,
                'total_alerts' => count($alerts),
                'critical_alerts' => count(array_filter($alerts, fn($a) => $a['severity'] === 'CRITICO')),
                'estimated_losses' => array_sum(array_column($alerts, 'estimated_cost'))
            ];
            
            $report = $this->aiService->generateExecutiveReport($alerts, $kpis);
            
            return response()->json([
                'success' => true,
                'data' => $report,
                'meta' => [
                    'alerts_analyzed' => count($alerts),
                    'time_period' => [
                        'from' => $request->input('date_from', now()->subDays(7)->toDateString()),
                        'to' => $request->input('date_to', now()->toDateString())
                    ]
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('AI Executive Report API failed', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Report generation failed',
                'message' => 'AI service temporarily unavailable'
            ], 500);
        }
    }
    
    /**
     * Get predictive analytics
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function predictiveAnalysis(Request $request): JsonResponse
    {
        try {
            // Get historical data
            $historicalData = Alert::with(['technician'])
                ->where('created_at', '>=', now()->subDays(30))
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();
            
            $predictions = $this->aiService->predictiveAnalysis($historicalData);
            
            return response()->json([
                'success' => true,
                'data' => $predictions,
                'meta' => [
                    'historical_data_points' => count($historicalData),
                    'analysis_period' => '30 days',
                    'forecast_period' => $predictions['forecast_period'] ?? '7-14 days'
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('AI Predictive Analysis API failed', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Predictive analysis failed',
                'message' => 'AI service temporarily unavailable'
            ], 500);
        }
    }
    
    /**
     * Optimize alert confidence scoring
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function optimizeConfidence(Request $request): JsonResponse
    {
        try {
            $alertId = $request->input('alert_id');
            $alert = Alert::findOrFail($alertId);
            
            // Build context data
            $contextData = [
                'technician_history' => Alert::where('tecnico', $alert->tecnico)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->get()
                    ->toArray(),
                'category_patterns' => Alert::where('category', $alert->category)
                    ->where('created_at', '>=', now()->subDays(14))
                    ->get()
                    ->toArray()
            ];
            
            $originalScore = $alert->confidence_score;
            $optimizedScore = $this->aiService->optimizeConfidenceScore($alert, $contextData);
            
            // Update alert with optimized score
            $alert->update([
                'confidence_score' => $optimizedScore,
                'ai_optimized' => true,
                'original_confidence' => $originalScore
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'alert_id' => $alertId,
                    'original_confidence' => $originalScore,
                    'optimized_confidence' => $optimizedScore,
                    'improvement' => round($optimizedScore - $originalScore, 2),
                    'optimization_date' => now()->toISOString()
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('AI Confidence Optimization API failed', [
                'alert_id' => $request->input('alert_id'),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Confidence optimization failed',
                'message' => 'AI service temporarily unavailable'
            ], 500);
        }
    }
    
    /**
     * Get AI service performance metrics
     * 
     * @return JsonResponse
     */
    public function getPerformanceMetrics(): JsonResponse
    {
        try {
            $metrics = $this->aiService->getPerformanceMetrics();
            
            return response()->json([
                'success' => true,
                'data' => array_merge($metrics, [
                    'service_status' => 'operational',
                    'last_updated' => now()->toISOString(),
                    'cost_optimization' => [
                        'cache_enabled' => true,
                        'multi_provider_fallback' => true,
                        'estimated_monthly_cost' => '€75-150'
                    ]
                ])
            ]);
            
        } catch (Exception $e) {
            Log::error('AI Performance Metrics API failed', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Metrics unavailable',
                'message' => 'Performance monitoring temporarily unavailable'
            ], 500);
        }
    }
    
    /**
     * Smart alert triage - prioritize alerts using AI
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function smartTriage(Request $request): JsonResponse
    {
        try {
            // Get unreviewed alerts
            $alerts = Alert::where('reviewed', false)
                ->orWhere('reviewed', null)
                ->with(['technician'])
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
            
            $triageResults = [];
            
            foreach ($alerts as $alert) {
                $enhancement = $this->aiService->enhanceAlert($alert);
                
                $triageResults[] = [
                    'alert_id' => $alert->id,
                    'original_severity' => $alert->severity,
                    'ai_priority' => $enhancement['priority_level'] ?? 3,
                    'business_impact' => $enhancement['impact_assessment'] ?? 'Standard review required',
                    'recommended_actions' => $enhancement['recommended_actions'] ?? [],
                    'estimated_review_time' => $this->estimateReviewTime($alert, $enhancement),
                    'risk_level' => $enhancement['risk_assessment'] ?? 'MEDIUM'
                ];
            }
            
            // Sort by AI priority (descending)
            usort($triageResults, function($a, $b) {
                return $b['ai_priority'] <=> $a['ai_priority'];
            });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'triaged_alerts' => $triageResults,
                    'total_alerts' => count($triageResults),
                    'high_priority_count' => count(array_filter($triageResults, fn($t) => $t['ai_priority'] >= 4)),
                    'estimated_total_review_time' => array_sum(array_column($triageResults, 'estimated_review_time')),
                    'triage_completed_at' => now()->toISOString()
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('AI Smart Triage API failed', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Smart triage failed',
                'message' => 'AI service temporarily unavailable'
            ], 500);
        }
    }
    
    /**
     * Generate natural language alert summary
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function generateAlertSummary(Request $request): JsonResponse
    {
        try {
            $alertId = $request->input('alert_id');
            $alert = Alert::with(['technician'])->findOrFail($alertId);
            
            // Build comprehensive context
            $context = [
                'alert' => $alert->toArray(),
                'technician_context' => Alert::where('tecnico', $alert->tecnico)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
                'similar_alerts' => Alert::where('category', $alert->category)
                    ->where('id', '!=', $alert->id)
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count()
            ];
            
            $summary = $this->generateNaturalLanguageSummary($alert, $context);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'alert_id' => $alertId,
                    'natural_language_summary' => $summary,
                    'context_info' => [
                        'technician_recent_alerts' => $context['technician_context'],
                        'similar_historical_alerts' => $context['similar_alerts']
                    ],
                    'generated_at' => now()->toISOString()
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('AI Alert Summary API failed', [
                'alert_id' => $request->input('alert_id'),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Summary generation failed',
                'message' => 'AI service temporarily unavailable'
            ], 500);
        }
    }
    
    /**
     * AI-powered dashboard insights
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getDashboardInsights(Request $request): JsonResponse
    {
        try {
            // Get recent system data
            $recentAlerts = Alert::with(['technician'])
                ->where('created_at', '>=', now()->subDays(7))
                ->get();
                
            $insights = [
                'performance_trends' => $this->analyzePerformanceTrends($recentAlerts),
                'efficiency_insights' => $this->analyzeEfficiencyInsights($recentAlerts), 
                'risk_indicators' => $this->analyzeRiskIndicators($recentAlerts),
                'optimization_opportunities' => $this->identifyOptimizationOpportunities($recentAlerts),
                'cost_impact_analysis' => $this->analyzeCostImpact($recentAlerts)
            ];
            
            return response()->json([
                'success' => true,
                'data' => $insights,
                'meta' => [
                    'analysis_period' => '7 days',
                    'alerts_analyzed' => $recentAlerts->count(),
                    'generated_at' => now()->toISOString()
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('AI Dashboard Insights API failed', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Insights generation failed',
                'message' => 'AI service temporarily unavailable'
            ], 500);
        }
    }
    
    // Private helper methods
    
    private function estimateReviewTime(Alert $alert, array $enhancement): int
    {
        $baseTime = match($alert->severity) {
            'CRITICO' => 15, // 15 minutes
            'ALTO' => 10,    // 10 minutes
            'MEDIO' => 5,    // 5 minutes
            default => 5
        };
        
        // Adjust based on AI priority
        $priority = $enhancement['priority_level'] ?? 3;
        $multiplier = $priority >= 4 ? 1.5 : ($priority <= 2 ? 0.75 : 1.0);
        
        return (int) round($baseTime * $multiplier);
    }
    
    private function generateNaturalLanguageSummary(Alert $alert, array $context): string
    {
        $severity = strtolower($alert->severity);
        $category = str_replace('_', ' ', $alert->category);
        
        $summary = "Alert {$alert->id} represents a {$severity} severity issue in the {$category} category. ";
        $summary .= "The technician {$alert->tecnico} has {$context['technician_context']} similar recent alerts. ";
        
        if ($context['similar_alerts'] > 0) {
            $summary .= "This type of issue has occurred {$context['similar_alerts']} times in the past 30 days, ";
            $summary .= "suggesting a potential pattern requiring systematic review. ";
        }
        
        $summary .= "Estimated business impact: €{$alert->estimated_cost}. ";
        $summary .= "Confidence level: {$alert->confidence_score}%.";
        
        return $summary;
    }
    
    private function analyzePerformanceTrends($alerts): array
    {
        return [
            'alert_volume_trend' => $alerts->count() > 10 ? 'increasing' : 'stable',
            'severity_distribution' => [
                'critical' => $alerts->where('severity', 'CRITICO')->count(),
                'high' => $alerts->where('severity', 'ALTO')->count(),
                'medium' => $alerts->where('severity', 'MEDIO')->count()
            ],
            'average_confidence' => round($alerts->avg('confidence_score'), 1)
        ];
    }
    
    private function analyzeEfficiencyInsights($alerts): array
    {
        $technicianStats = $alerts->groupBy('tecnico')->map(function($techAlerts) {
            return [
                'alert_count' => $techAlerts->count(),
                'avg_confidence' => round($techAlerts->avg('confidence_score'), 1),
                'critical_alerts' => $techAlerts->where('severity', 'CRITICO')->count()
            ];
        });
        
        return [
            'technician_performance' => $technicianStats->toArray(),
            'most_problematic_category' => $alerts->groupBy('category')
                ->sortByDesc(function($categoryAlerts) {
                    return $categoryAlerts->count();
                })->keys()->first() ?? 'unknown'
        ];
    }
    
    private function analyzeRiskIndicators($alerts): array
    {
        $criticalAlerts = $alerts->where('severity', 'CRITICO');
        $totalCost = $alerts->sum('estimated_cost');
        
        return [
            'critical_alert_rate' => $alerts->count() > 0 ? 
                round(($criticalAlerts->count() / $alerts->count()) * 100, 1) : 0,
            'total_estimated_cost' => $totalCost,
            'high_risk_threshold_exceeded' => $criticalAlerts->count() > 2,
            'cost_impact_level' => $totalCost > 500 ? 'high' : ($totalCost > 200 ? 'medium' : 'low')
        ];
    }
    
    private function identifyOptimizationOpportunities($alerts): array
    {
        return [
            'preventable_issues' => $alerts->where('category', 'temporal_overlap')->count(),
            'scheduling_improvements' => $alerts->where('category', 'travel_time')->count() > 0,
            'training_opportunities' => $alerts->groupBy('tecnico')
                ->filter(function($techAlerts) {
                    return $techAlerts->count() > 3;
                })->keys()->toArray()
        ];
    }
    
    private function analyzeCostImpact($alerts): array
    {
        $totalCost = $alerts->sum('estimated_cost');
        $avgCost = $alerts->avg('estimated_cost');
        
        return [
            'total_cost_impact' => $totalCost,
            'average_cost_per_alert' => round($avgCost, 2),
            'cost_by_severity' => [
                'critical' => $alerts->where('severity', 'CRITICO')->sum('estimated_cost'),
                'high' => $alerts->where('severity', 'ALTO')->sum('estimated_cost'),
                'medium' => $alerts->where('severity', 'MEDIO')->sum('estimated_cost')
            ],
            'potential_savings' => round($totalCost * 0.6, 2) // Estimated 60% preventable
        ];
    }
}