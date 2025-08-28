<?php
/**
 * BAIT Service Enterprise Dashboard - Clean Professional Version
 * Dashboard professionale per controllo attività tecnici
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Rome');

// Database configuration
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'bait_service_real',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

// Database connection
function getDatabase() {
    global $config;
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);
        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}

// Load dashboard data
function loadDashboardData() {
    $pdo = getDatabase();
    if (!$pdo) {
        return [
            'kpis' => [
                'tecnici_attivi' => 0,
                'attivita_oggi' => 0,
                'alert_attivi' => 0,
                'coverage_percentage' => 0
            ]
        ];
    }
    
    try {
        // KPI principali
        $kpis = [];
        
        // Tecnici attivi
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM tecnici WHERE attivo = 1");
        $kpis['tecnici_attivi'] = $stmt->fetchColumn();
        
        // Attività di oggi - controlla varie tabelle
        $attivita_oggi = 0;
        try {
            // Prova deepser_attivita
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM deepser_attivita WHERE DATE(iniziata_il) = CURDATE()");
            $stmt->execute();
            $attivita_oggi += $stmt->fetchColumn();
        } catch (Exception $e) {}
        
        try {
            // Prova utilizzi_auto di oggi
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilizzi_auto WHERE DATE(data_utilizzo) = CURDATE()");
            $stmt->execute();
            $attivita_oggi += $stmt->fetchColumn();
        } catch (Exception $e) {}
        
        try {
            // Prova timbrature di oggi
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT tecnico_id) FROM timbrature WHERE DATE(data) = CURDATE()");
            $stmt->execute();
            $attivita_oggi += $stmt->fetchColumn();
        } catch (Exception $e) {}
        
        $kpis['attivita_oggi'] = $attivita_oggi;
        
        // Alert attivi - controlla varie tabelle
        $alert_attivi = 0;
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM audit_alerts WHERE status = 'new'");
            $alert_attivi += $stmt->fetchColumn();
        } catch (Exception $e) {}
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM alerts WHERE status = 'active'");
            $alert_attivi += $stmt->fetchColumn();
        } catch (Exception $e) {}
        
        $kpis['alert_attivi'] = $alert_attivi;
        
        // Coverage percentage - calcolo più realistico
        $tecnici_con_attivita = 0;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT tecnico_id) FROM utilizzi_auto WHERE DATE(data_utilizzo) = CURDATE()");
            $stmt->execute();
            $tecnici_con_attivita = $stmt->fetchColumn();
        } catch (Exception $e) {}
        
        $kpis['coverage_percentage'] = $kpis['tecnici_attivi'] > 0 ? 
            round(($tecnici_con_attivita / $kpis['tecnici_attivi']) * 100) : 0;
        
        return [
            'kpis' => $kpis
        ];
        
    } catch (Exception $e) {
        error_log("Error loading dashboard data: " . $e->getMessage());
        return [
            'kpis' => [
                'tecnici_attivi' => 0,
                'attivita_oggi' => 0,
                'alert_attivi' => 0,
                'coverage_percentage' => 0
            ]
        ];
    }
}

// Utility functions
function getSeverityBadge($severity) {
    $classes = [
        'critical' => 'bg-danger',
        'high' => 'bg-warning',
        'medium' => 'bg-info',
        'low' => 'bg-secondary'
    ];
    return $classes[$severity] ?? 'bg-secondary';
}

function getQualityBadge($score) {
    if ($score >= 90) return 'bg-success';
    if ($score >= 75) return 'bg-info';
    if ($score >= 60) return 'bg-warning';
    return 'bg-danger';
}

// Load data
$data = loadDashboardData();
$isConnected = getDatabase() !== null;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BAIT Service - Dashboard Enterprise</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            /* Light Theme (Default) */
            --bait-primary: #2563eb;
            --bait-secondary: #64748b;
            --bait-success: #059669;
            --bait-warning: #d97706;
            --bait-danger: #dc2626;
            
            /* Background Colors */
            --bait-bg-primary: #ffffff;
            --bait-bg-secondary: #f8fafc;
            --bait-bg-tertiary: #f1f5f9;
            --bait-bg-card: #ffffff;
            --bait-bg-sidebar: #ffffff;
            
            /* Text Colors */
            --bait-text-primary: #1e293b;
            --bait-text-secondary: #64748b;
            --bait-text-muted: #94a3b8;
            --bait-text-light: #cbd5e1;
            
            /* Border Colors */
            --bait-border-primary: #e2e8f0;
            --bait-border-secondary: #cbd5e1;
            --bait-border-muted: #f1f5f9;
            
            /* Shadow Colors */
            --bait-shadow-sm: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            --bait-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --bait-shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            
            /* Module Category Colors */
            --bait-transport: #059669;
            --bait-activities: #2563eb;
            --bait-permissions: #dc2626;
            --bait-time: #0891b2;
            --bait-remote: #d97706;
            --bait-calendar: #64748b;
            --bait-audit: #7c3aed;
            --bait-ai: #db2777;
            --bait-upload: #0e7490;
            --bait-filter: #4f46e5;
        }
        
        /* Dark Theme */
        [data-theme="dark"] {
            /* Background Colors */
            --bait-bg-primary: #0f172a;
            --bait-bg-secondary: #1e293b;
            --bait-bg-tertiary: #334155;
            --bait-bg-card: #1e293b;
            --bait-bg-sidebar: #0f172a;
            
            /* Text Colors */
            --bait-text-primary: #f8fafc;
            --bait-text-secondary: #cbd5e1;
            --bait-text-muted: #94a3b8;
            --bait-text-light: #64748b;
            
            /* Border Colors */
            --bait-border-primary: #334155;
            --bait-border-secondary: #475569;
            --bait-border-muted: #1e293b;
            
            /* Shadow Colors */
            --bait-shadow-sm: 0 1px 3px rgba(0,0,0,0.3), 0 1px 2px rgba(0,0,0,0.2);
            --bait-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
            --bait-shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.4), 0 10px 10px -5px rgba(0, 0, 0, 0.3);
            
            /* Adjust primary colors for dark mode */
            --bait-primary: #3b82f6;
            --bait-secondary: #94a3b8;
            --bait-success: #10b981;
            --bait-warning: #f59e0b;
            --bait-danger: #ef4444;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--bait-bg-secondary);
            color: var(--bait-text-primary);
            transition: background-color 0.4s cubic-bezier(0.4, 0, 0.2, 1), color 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Smooth transitions for all theme-aware elements */
        * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--bait-primary) !important;
        }
        
        .kpi-card {
            background: var(--bait-bg-card);
            border: 1px solid var(--bait-border-primary);
            border-radius: 16px;
            box-shadow: var(--bait-shadow-sm);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--bait-shadow-lg);
        }
        
        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .section-card {
            background: var(--bait-bg-card);
            border: 1px solid var(--bait-border-primary);
            border-radius: 16px;
            box-shadow: var(--bait-shadow-sm);
            transition: all 0.3s ease;
        }
        
        .alert-item {
            padding: 1rem;
            border: 1px solid var(--bait-border-primary);
            border-left: 4px solid;
            border-radius: 0 8px 8px 0;
            margin-bottom: 0.5rem;
            background: var(--bait-bg-tertiary);
            transition: all 0.3s ease;
        }
        
        .alert-item:hover {
            background: var(--bait-bg-secondary);
            transform: translateX(4px);
        }
        
        .alert-critical { border-left-color: var(--bait-danger); }
        .alert-high { border-left-color: var(--bait-warning); }
        .alert-medium { border-left-color: var(--bait-primary); }
        .alert-low { border-left-color: var(--bait-secondary); }
        
        .tech-stat {
            padding: 1rem;
            border-radius: 12px;
            background: var(--bait-bg-tertiary);
            border: 1px solid var(--bait-border-primary);
            transition: all 0.3s ease;
        }
        
        .tech-stat:hover {
            background: #f1f5f9;
            border-color: var(--bait-primary);
        }
        
        .status-online {
            color: var(--bait-success);
        }
        
        .status-offline {
            color: var(--bait-secondary);
        }

        /* VS Code Style Sidebar */
        .vscode-sidebar {
            background: var(--bait-bg-sidebar);
            position: fixed;
            top: 56px; /* Height of navbar */
            left: 0;
            width: 64px;
            height: calc(100vh - 56px);
            z-index: 1000;
            padding: 0.5rem 0.25rem;
            border-right: 1px solid var(--bait-border-primary);
        }
        
        .vscode-module-grid {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .vscode-module-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            background: transparent;
            border: none;
        }
        
        .vscode-module-icon:hover {
            background: var(--bait-bg-tertiary);
            transform: translateX(2px);
        }
        
        .vscode-module-icon.active {
            background: var(--bait-primary);
            color: white;
        }
        
        /* Drag & Drop Styles */
        .vscode-module-icon.dragging {
            opacity: 0.5;
            transform: rotate(5deg);
        }
        
        .vscode-module-icon.drag-over {
            background: var(--bait-bg-tertiary);
            transform: translateY(-2px);
        }
        
        .vscode-module-icon i {
            font-size: 1.2rem;
            color: var(--bait-text-secondary);
        }
        
        .vscode-module-icon:hover i,
        .vscode-module-icon.active i {
            color: white;
        }
        

        /* Modern Enterprise Card Grid Styles - Legacy */
        .bait-module-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin: 0;
        }

        .bait-module-card {
            background: var(--bait-bg-card);
            border-radius: 16px;
            box-shadow: var(--bait-shadow-md);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--bait-border-primary);
            overflow: hidden;
            position: relative;
        }

        .bait-module-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--bait-shadow-lg);
        }

        .bait-module-card[data-category="transport"] {
            border-top: 4px solid var(--bait-transport);
        }

        .bait-module-card[data-category="activities"] {
            border-top: 4px solid var(--bait-activities);
        }

        .bait-module-card[data-category="permissions"] {
            border-top: 4px solid var(--bait-permissions);
        }

        .bait-module-card[data-category="time"] {
            border-top: 4px solid var(--bait-time);
        }

        .bait-module-card[data-category="remote"] {
            border-top: 4px solid var(--bait-remote);
        }

        .bait-module-card[data-category="calendar"] {
            border-top: 4px solid var(--bait-calendar);
        }

        .bait-module-card[data-category="audit"] {
            border-top: 4px solid var(--bait-audit);
        }

        .bait-module-card[data-category="ai"] {
            border-top: 4px solid var(--bait-ai);
        }

        .bait-module-card[data-category="upload"] {
            border-top: 4px solid var(--bait-upload);
        }

        .bait-module-card[data-category="filter"] {
            border-top: 4px solid var(--bait-filter);
        }

        .module-link {
            display: block;
            padding: 1.5rem;
            text-decoration: none;
            color: inherit;
            height: 100%;
        }

        .module-link:hover {
            text-decoration: none;
            color: inherit;
        }

        .module-icon {
            text-align: center;
            margin-bottom: 1rem;
            font-size: 2.5rem;
            color: var(--bait-text-secondary);
            transition: transform 0.3s ease;
        }

        .bait-module-card:hover .module-icon {
            transform: scale(1.1);
        }

        .module-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--bait-text-primary);
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .module-description {
            font-size: 0.875rem;
            color: var(--bait-text-secondary);
            text-align: center;
            line-height: 1.4;
        }

        /* SPA Content Area Styles */
        
        .breadcrumb-item .bi {
            font-size: 0.9rem;
        }
        
        /* Module Cards in Sidebar */
        .bait-module-card {
            transition: all 0.2s ease;
        }
        
        .bait-module-card.active {
            transform: scale(0.98);
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
            border-color: var(--bait-primary);
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .bait-module-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .bait-module-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.75rem;
            }
            
            .module-title {
                font-size: 0.95rem;
            }
            
            .module-description {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 768px) {
            .bait-module-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
            }
            
            .module-icon {
                font-size: 2rem;
                margin-bottom: 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .bait-module-grid {
                grid-template-columns: 1fr;
            }
            
            .bait-module-card {
                max-width: 280px;
                margin: 0 auto;
            }
        }
        
        /* Theme-aware Bootstrap overrides */
        .card-header {
            background-color: var(--bait-bg-card) !important;
            border-bottom: 1px solid var(--bait-border-primary) !important;
            color: var(--bait-text-primary) !important;
        }
        
        .text-primary {
            color: var(--bait-primary) !important;
        }
        
        .text-secondary {
            color: var(--bait-text-secondary) !important;
        }
        
        .text-muted {
            color: var(--bait-text-muted) !important;
        }
        
        .border-bottom {
            border-bottom-color: var(--bait-border-primary) !important;
        }
        
        /* Theme toggle button styles */
        #themeToggle {
            transition: all 0.3s ease;
            border-color: var(--bait-border-secondary);
            color: var(--bait-text-secondary);
        }
        
        #themeToggle:hover {
            background-color: var(--bait-bg-tertiary);
            border-color: var(--bait-primary);
            color: var(--bait-primary);
        }
        
        /* Dark theme specific adjustments */
        [data-theme="dark"] .navbar-light .navbar-brand,
        [data-theme="dark"] .navbar-light .navbar-nav .nav-link {
            color: var(--bait-text-primary) !important;
        }
        
        [data-theme="dark"] .badge.bg-success {
            background-color: var(--bait-success) !important;
        }
        
        [data-theme="dark"] .badge.bg-warning {
            background-color: var(--bait-warning) !important;
        }
        
        [data-theme="dark"] .btn-outline-secondary {
            border-color: var(--bait-border-secondary);
            color: var(--bait-text-secondary);
        }
        
        [data-theme="dark"] .btn-outline-secondary:hover {
            background-color: var(--bait-bg-tertiary);
            border-color: var(--bait-primary);
            color: var(--bait-primary);
        }
        
        /* Enhanced AJAX Module Styles */
        .bait-module-container {
            min-height: 400px;
            animation: moduleSlideIn 0.3s ease-out;
        }
        
        @keyframes moduleSlideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .bait-module-quick-actions {
            background: var(--bait-bg-card);
            border: 1px solid var(--bait-border-primary);
            border-radius: 12px;
            padding: 1rem;
            box-shadow: var(--bait-shadow-sm);
        }
        
        .bait-ai-quick-prompts {
            background: linear-gradient(135deg, var(--bait-bg-card) 0%, var(--bait-bg-tertiary) 100%);
            border: 1px solid var(--bait-border-primary);
            border-radius: 12px;
            padding: 1rem;
            box-shadow: var(--bait-shadow-sm);
        }
        
        .bait-module-quick-actions .btn-group .btn,
        .bait-ai-quick-prompts .btn {
            transition: all 0.2s ease;
            border-color: var(--bait-border-secondary);
        }
        
        .bait-module-quick-actions .btn-group .btn:hover,
        .bait-ai-quick-prompts .btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--bait-shadow-sm);
        }
        
        /* Loading states for modules */
        .bait-module-loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .bait-module-error {
            border-left: 4px solid var(--bait-danger);
            background: rgba(220, 38, 38, 0.1);
            padding: 1rem;
            border-radius: 8px;
        }
        
        /* Enhanced error display */
        .bait-error-container {
            background: var(--bait-bg-card);
            border: 1px solid var(--bait-danger);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            animation: errorShake 0.5s ease-in-out;
        }
        
        @keyframes errorShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        /* VS Code Sidebar Responsive */
        @media (max-width: 992px) {
            .vscode-sidebar {
                padding: 0.25rem;
            }
            
            .vscode-module-icon {
                width: 40px;
                height: 40px;
            }
            
            .vscode-module-icon i {
                font-size: 1rem;
            }
        }
        
        @media (max-width: 768px) {
            .vscode-module-icon {
                width: 36px;
                height: 36px;
            }
            
            .vscode-module-icon i {
                font-size: 0.9rem;
            }
        }

        /* Advanced Mobile Optimizations */
        @media (max-width: 1200px) {
            .container-fluid {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
        
        @media (max-width: 992px) {
            /* Tablet Layout Optimizations */
            .row {
                margin-left: -0.5rem;
                margin-right: -0.5rem;
            }
            
            .col-lg-3, .col-lg-9 {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            
            /* Make sidebar full width on tablet */
            .col-lg-3.col-md-4 {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 1rem;
            }
            
            .col-lg-9.col-md-8 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            
            /* Compact module grid for tablet */
            .bait-module-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 0.75rem;
            }
            
            .module-title {
                font-size: 0.9rem;
            }
            
            .module-description {
                font-size: 0.8rem;
            }
            
            .module-icon {
                font-size: 2rem;
                margin-bottom: 0.5rem;
            }
        }
        
        @media (max-width: 768px) {
            /* Mobile Layout Optimizations */
            body {
                font-size: 0.9rem;
            }
            
            .container-fluid {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
                padding-top: 1rem;
                padding-bottom: 1rem;
            }
            
            /* Mobile navbar adjustments */
            .navbar {
                padding: 0.5rem 1rem;
            }
            
            .navbar-brand {
                font-size: 1.1rem;
            }
            
            /* Mobile module grid */
            .bait-module-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.5rem;
            }
            
            .bait-module-card {
                border-radius: 12px;
            }
            
            .module-link {
                padding: 1rem 0.75rem;
            }
            
            .module-icon {
                font-size: 1.8rem;
                margin-bottom: 0.4rem;
            }
            
            .module-title {
                font-size: 0.85rem;
                margin-bottom: 0.25rem;
            }
            
            .module-description {
                font-size: 0.75rem;
                line-height: 1.2;
            }
            
            /* Mobile content area */
            
            /* Mobile quick actions */
            .bait-module-quick-actions {
                padding: 0.75rem;
                margin-bottom: 1rem;
            }
            
            .bait-module-quick-actions .btn-group {
                width: 100%;
                flex-direction: column;
            }
            
            .bait-module-quick-actions .btn-group .btn {
                font-size: 0.875rem;
                padding: 0.75rem;
                border-radius: 8px !important;
                margin-bottom: 0.5rem;
            }
            
            .bait-ai-quick-prompts {
                padding: 0.75rem;
                margin-bottom: 1rem;
            }
            
            .bait-ai-quick-prompts .btn {
                font-size: 0.875rem;
                padding: 0.75rem;
                margin-bottom: 0.5rem;
                text-align: left;
            }
            
            /* Mobile breadcrumb */
            .breadcrumb {
                background: none;
                padding: 0.5rem 0;
                margin-bottom: 0.5rem;
            }
            
            .breadcrumb-item {
                font-size: 0.875rem;
            }
            
            /* Mobile theme toggle */
            #themeToggle {
                padding: 0.375rem 0.75rem;
                font-size: 0.875rem;
            }
            
            /* Mobile error container */
            .bait-error-container {
                padding: 1.5rem;
                margin: 1rem 0;
            }
        }
        
        @media (max-width: 576px) {
            /* Extra small mobile optimizations */
            .container-fluid {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            
            /* Single column layout for very small screens */
            .bait-module-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
            }
            
            .bait-module-card {
                max-width: none;
                margin: 0;
            }
            
            .module-link {
                padding: 0.75rem 0.5rem;
            }
            
            .module-icon {
                font-size: 1.5rem;
                margin-bottom: 0.3rem;
            }
            
            .module-title {
                font-size: 0.8rem;
                margin-bottom: 0.2rem;
            }
            
            .module-description {
                font-size: 0.7rem;
                line-height: 1.1;
            }
            
            /* Mobile navbar ultra-compact */
            .navbar {
                padding: 0.375rem 0.75rem;
            }
            
            .navbar-brand {
                font-size: 1rem;
            }
            
            /* Hide database status text on very small screens */
            .badge .d-none.d-md-inline {
                display: none !important;
            }
            
            /* Mobile welcome screen removed */
            
            /* Stack buttons vertically on very small screens */
            .bait-module-quick-actions .btn-group,
            .d-flex.gap-2 {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .d-flex.gap-2 .btn {
                width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            /* Ultra-compact mobile */
            .bait-module-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.4rem;
            }
            
            .module-link {
                padding: 0.6rem 0.4rem;
            }
            
            .module-icon {
                font-size: 1.4rem;
                margin-bottom: 0.2rem;
            }
            
            .module-title {
                font-size: 0.75rem;
                margin-bottom: 0.1rem;
            }
            
            .module-description {
                font-size: 0.65rem;
                display: none; /* Hide descriptions on ultra-small screens */
            }
        }
        
        /* Smooth transitions for all module content */
        .bait-module-container * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        
        /* Enhanced breadcrumb styling */
        .breadcrumb-item span:hover {
            color: var(--bait-primary) !important;
            text-decoration: underline;
        }
        
        /* Module-specific styling */
        .bait-module-container[data-module="ai-chat"] {
            border-left: 4px solid var(--bait-ai);
        }
        
        .bait-module-container[data-module="auto"] {
            border-left: 4px solid var(--bait-transport);
        }
        
        .bait-module-container[data-module="attivita"] {
            border-left: 4px solid var(--bait-activities);
        }
        
        .bait-module-container[data-module="audit-mensile"] {
            border-left: 4px solid var(--bait-audit);
        }
        
        /* Touch and Mobile-Specific Optimizations */
        
        /* Touch targets - minimum 44px for accessibility */
        @media (pointer: coarse) {
            .module-link,
            .btn,
            .breadcrumb-item span,
            #themeToggle {
                min-height: 44px;
                min-width: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .module-link {
                touch-action: manipulation;
                -webkit-tap-highlight-color: rgba(0,0,0,0.1);
            }
            
            /* Larger touch targets for buttons */
            .bait-module-quick-actions .btn,
            .bait-ai-quick-prompts .btn {
                min-height: 48px;
                padding: 0.75rem 1rem;
            }
        }
        
        /* Hover effects only on devices that support them */
        @media (hover: hover) {
            .bait-module-card:hover {
                transform: translateY(-4px);
                box-shadow: var(--bait-shadow-lg);
            }
            
            .bait-module-quick-actions .btn-group .btn:hover,
            .bait-ai-quick-prompts .btn:hover {
                transform: translateY(-1px);
                box-shadow: var(--bait-shadow-sm);
            }
        }
        
        /* Disable hover effects on touch devices */
        @media (hover: none) {
            .bait-module-card:hover {
                transform: none;
                box-shadow: var(--bait-shadow-md);
            }
            
            .bait-module-quick-actions .btn-group .btn:hover,
            .bait-ai-quick-prompts .btn:hover {
                transform: none;
                box-shadow: none;
            }
        }
        
        /* Active states for touch feedback */
        .module-link:active,
        .btn:active {
            transform: scale(0.98);
            transition: transform 0.1s ease;
        }
        
        /* Prevent text selection on interactive elements */
        .module-link,
        .btn,
        .navbar-brand {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        
        /* Improve touch scrolling */
        .bait-module-container,
        .card-body {
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }
        
        /* Mobile-specific loading optimizations */
        @media (max-width: 768px) {
            /* Reduce motion for better performance on mobile */
            @media (prefers-reduced-motion: reduce) {
                * {
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                }
                
                .bait-module-card:hover {
                    transform: none;
                }
            }
            
            /* Optimize font rendering for mobile */
            body {
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
                text-rendering: optimizeSpeed;
            }
            
            /* Mobile-specific scroll behavior */
            html {
                scroll-behavior: smooth;
            }
            
            /* Prevent zoom on input focus */
            input[type="date"],
            button,
            select {
                font-size: 16px;
            }
        }
        
        /* Dark mode mobile optimizations */
        @media (max-width: 768px) and (prefers-color-scheme: dark) {
            body {
                background-color: var(--bait-bg-secondary);
            }
            
            .navbar {
                background-color: var(--bait-bg-primary) !important;
                border-bottom: 1px solid var(--bait-border-primary);
            }
        }
        
        /* Landscape mobile optimizations */
        @media (max-width: 896px) and (orientation: landscape) {
            .bait-module-grid {
                grid-template-columns: repeat(6, 1fr);
                gap: 0.5rem;
            }
            
            .module-link {
                padding: 0.5rem;
            }
            
            .module-icon {
                font-size: 1.5rem;
                margin-bottom: 0.25rem;
            }
            
            .module-title {
                font-size: 0.8rem;
            }
            
            .module-description {
                display: none;
            }
            
            
            .navbar {
                padding: 0.25rem 1rem;
            }
        }
        
        /* High DPI display optimizations */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .module-icon,
            .bi {
                image-rendering: -webkit-optimize-contrast;
                image-rendering: crisp-edges;
            }
        }
        
        /* Print styles */
        @media print {
            .navbar,
            .bait-module-quick-actions,
            .bait-ai-quick-prompts,
            #themeToggle {
                display: none !important;
            }
            
            body {
                font-size: 12pt;
                line-height: 1.4;
                color: black !important;
                background: white !important;
            }
            
            .bait-module-container {
                border: 1px solid #ccc;
                margin: 1cm 0;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light border-bottom shadow-sm" style="background-color: var(--bait-bg-primary); border-color: var(--bait-border-primary) !important;">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="#" style="font-weight: 400;">
                <i class="bi bi-shield-check me-2"></i>
                BAIT Service
            </a>
            
            <div class="d-flex align-items-center">                
                <span class="badge <?= $isConnected ? 'bg-success' : 'bg-warning' ?> me-3">
                    <i class="bi bi-database me-1"></i>
                    <?= $isConnected ? 'Database Online' : 'Database Offline' ?>
                </span>
            </div>
        </div>
    </nav>

    <!-- VS Code Style Fixed Sidebar -->
    <div class="vscode-sidebar">
        <div class="vscode-sidebar-content">
            <!-- Compact Icon Grid -->
            <div class="vscode-module-grid" id="moduleGrid">
                            <!-- Auto -->
                            <div class="vscode-module-icon" data-module="auto" data-category="transport" title="Auto - Gestione utilizzi auto" onclick="loadModule('auto', '/controlli/utilizzo_auto.php')" draggable="true">
                                <i class="bi bi-car-front-fill"></i>
                            </div>

                            <!-- Attività -->
                            <div class="vscode-module-icon" data-module="attivita" data-category="activities" title="Attività - Gestione attività Deepser" onclick="loadModule('attivita', '/controlli/attivita_deepser_unified.php')" draggable="true">
                                <i class="bi bi-briefcase-fill"></i>
                            </div>

                            <!-- Permessi -->
                            <div class="vscode-module-icon" data-module="permessi" data-category="permissions" title="Permessi - Richieste permessi" onclick="loadModule('permessi', '/controlli/richieste_permessi.php')" draggable="true">
                                <i class="bi bi-calendar-check-fill"></i>
                            </div>

                            <!-- Timbrature -->
                            <div class="vscode-module-icon" data-module="timbrature" data-category="time" title="Timbrature - Ore lavorate e presenze" onclick="loadModule('timbrature', '/controlli/timbrature.php')" draggable="true">
                                <i class="bi bi-clock-fill"></i>
                            </div>

                            <!-- TeamViewer -->
                            <div class="vscode-module-icon" data-module="teamviewer" data-category="remote" title="TeamViewer - Sessioni remote" onclick="loadModule('teamviewer', '/controlli/teamviewer_dashboard_fixed.php')" draggable="true">
                                <i class="bi bi-display-fill"></i>
                            </div>

                            <!-- Calendario -->
                            <div class="vscode-module-icon" data-module="calendario" data-category="calendar" title="Calendario - Eventi e pianificazione" onclick="loadModule('calendario', '/controlli/calendario.php')" draggable="true">
                                <i class="bi bi-calendar-fill"></i>
                            </div>

                            <!-- Audit Tecnico -->
                            <div class="vscode-module-icon" data-module="audit-tecnico" data-category="audit" title="Audit Tecnico - Controlli tecnici" onclick="loadModule('audit-tecnico', '/controlli/audit_tecnico_dashboard.php')" draggable="true">
                                <i class="bi bi-person-check-fill"></i>
                            </div>

                            <!-- Audit Mensile -->
                            <div class="vscode-module-icon" data-module="audit-mensile" data-category="audit" title="Audit Mensile - Report mensili" onclick="loadModule('audit-mensile', '/controlli/audit_monthly_manager.php')" draggable="true">
                                <i class="bi bi-calendar3-fill"></i>
                            </div>

                            <!-- Incongruenze -->
                            <div class="vscode-module-icon" data-module="incongruenze" data-category="audit" title="Incongruenze - Rilevamento anomalie" onclick="loadModule('incongruenze', '/controlli/bait_incongruenze_manager.php')" draggable="true">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                            </div>

                            <!-- AI Chat -->
                            <div class="vscode-module-icon" data-module="ai-chat" data-category="ai" title="AI Chat - Assistente intelligente" onclick="loadModule('ai-chat', '/controlli/bait_ai_chat.php')" draggable="true">
                                <i class="bi bi-robot"></i>
                            </div>

                            <!-- Carica File -->
                            <div class="vscode-module-icon" data-module="carica-file" data-category="upload" title="Carica File - Upload CSV Incrementale" onclick="loadModule('carica-file', '/controlli/upload_csv_incremental_iframe.php?iframe=1')" draggable="true">
                                <i class="bi bi-cloud-upload-fill"></i>
                            </div>

                            <!-- Filtro Date -->
                            <div class="vscode-module-icon" data-module="filtro-date" data-category="filter" title="Filtro Date - Filtra per periodo" onclick="toggleDateFilter()" draggable="true">
                                <i class="bi bi-calendar-range-fill"></i>
                            </div>
            </div>
        </div>
    </div>

    <!-- Main Content with Sidebar Offset -->
    <div style="margin-left: 64px; margin-top: 56px;">
        <div class="container-fluid py-4">
            <!-- Status Alert -->
            <?php if (!$isConnected): ?>
            <div class="alert alert-warning mb-4" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Attenzione:</strong> Impossibile connettersi al database. Verificare che il database 'bait_service_real' sia disponibile.
            </div>
            <?php endif; ?>

            <!-- Main Content Area -->
            <div>
                <!-- Dynamic Content Container -->
                <div class="position-relative" id="mainContent">
                        
                        <!-- Loading Spinner -->
                        <div class="text-center py-5 d-none" id="loadingSpinner">
                            <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                                <span class="visually-hidden">Caricamento...</span>
                            </div>
                            <h5 class="text-muted">Caricamento modulo in corso...</h5>
                        </div>
                        
                        <!-- Module Content -->
                        <div class="d-none" id="moduleContent">
                            <!-- Content will be loaded here via AJAX -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Date Filter Panel (Hidden by default) -->
        <div class="row mb-4" id="dateFilterPanel" style="display: none;">
            <div class="col-12">
                <div class="card section-card">
                    <div class="card-header bg-primary text-white border-bottom-0 py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-calendar-range me-2"></i>
                            Filtro Date per Report Dinamici
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Data Inizio</label>
                                <input type="date" id="startDate" class="form-control form-control-lg" 
                                       value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Data Fine</label>
                                <input type="date" id="endDate" class="form-control form-control-lg" 
                                       value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-success btn-lg w-100" onclick="applyDateFilter()">
                                    <i class="bi bi-filter me-1"></i>Applica
                                </button>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-secondary btn-lg w-100" onclick="resetDateFilter()">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Reset
                                </button>
                            </div>
                            <div class="col-md-2">
                                <div class="text-center">
                                    <div class="badge bg-primary fs-6 px-3 py-2" id="dateRangeDisplay">
                                        Ultimi 30 giorni
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Date Buttons -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setQuickDate(1)">Oggi</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setQuickDate(7)">Ultimi 7 giorni</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setQuickDate(30)">Ultimi 30 giorni</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setQuickDate(90)">Ultimi 3 mesi</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setCurrentMonth()">Mese corrente</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // SPA Router System
        let currentModule = null;
        let moduleCache = new Map();
        let cacheTimeout = 300000; // 5 minutes cache
        
        // Module configuration
        const modules = {
            'auto': { 
                title: 'Gestione Auto', 
                url: '/controlli/utilizzo_auto.php',
                icon: 'bi-car-front-fill',
                category: 'transport'
            },
            'attivita': { 
                title: 'Attività Deepser', 
                url: '/controlli/attivita_deepser_unified.php',
                icon: 'bi-briefcase-fill',
                category: 'activities'
            },
            'permessi': { 
                title: 'Richieste Permessi', 
                url: '/controlli/richieste_permessi.php',
                icon: 'bi-calendar-check-fill',
                category: 'permissions'
            },
            'timbrature': { 
                title: 'Timbrature', 
                url: '/controlli/timbrature.php',
                icon: 'bi-clock-fill',
                category: 'time'
            },
            'teamviewer': { 
                title: 'TeamViewer', 
                url: '/controlli/teamviewer_dashboard_fixed.php',
                icon: 'bi-display-fill',
                category: 'remote'
            },
            'calendario': { 
                title: 'Calendario', 
                url: '/controlli/calendario.php',
                icon: 'bi-calendar-fill',
                category: 'calendar'
            },
            'audit-tecnico': { 
                title: 'Audit Tecnico', 
                url: '/controlli/audit_tecnico_dashboard.php',
                icon: 'bi-person-check-fill',
                category: 'audit'
            },
            'audit-mensile': { 
                title: 'Audit Mensile', 
                url: '/controlli/audit_monthly_manager.php',
                icon: 'bi-calendar3-fill',
                category: 'audit'
            },
            'incongruenze': { 
                title: 'Incongruenze', 
                url: '/controlli/bait_incongruenze_manager.php',
                icon: 'bi-exclamation-triangle-fill',
                category: 'audit'
            },
            'ai-chat': { 
                title: 'AI Chat', 
                url: '/controlli/bait_ai_chat.php',
                icon: 'bi-robot',
                category: 'ai'
            },
            'carica-file': { 
                title: 'Carica File', 
                url: '/controlli/upload_csv_incremental_iframe.php?iframe=1',
                icon: 'bi-cloud-upload-fill',
                category: 'upload'
            }
        };
        
        // Enhanced AJAX Module Loading System
        function loadModule(moduleId, url) {
            if (!modules[moduleId]) {
                console.error('Module not found:', moduleId);
                return Promise.reject(new Error('Module not found'));
            }
            
            // Update active state
            setActiveModule(moduleId);
            
            // Show loading spinner
            showLoading();
            
            // Module title update (breadcrumb removed)
            
            // Stop auto-refresh while in module
            stopAutoRefresh();
            
            // Check cache first
            const cachedContent = getCachedModule(moduleId);
            if (cachedContent) {
                console.log(`Loading ${moduleId} from cache`);
                showModuleContent(cachedContent);
                initializeModuleFeatures(moduleId);
                history.pushState({ module: moduleId }, modules[moduleId].title, `#${moduleId}`);
                currentModule = moduleId;
                return Promise.resolve({ moduleId, success: true, cached: true });
            }
            
            // Enhanced AJAX loading with timeout and retry
            return fetchWithRetry(url, { timeout: 10000, retries: 2 })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.text();
                })
                .then(html => {
                    // Process and sanitize content
                    const processedContent = processModuleContent(html, moduleId);
                    
                    // Cache the processed content
                    cacheModule(moduleId, processedContent);
                    
                    // Show module content
                    showModuleContent(processedContent);
                    
                    // Initialize module-specific features
                    initializeModuleFeatures(moduleId);
                    
                    // Update URL without page reload
                    history.pushState({ module: moduleId }, modules[moduleId].title, `#${moduleId}`);
                    
                    currentModule = moduleId;
                    
                    // Log successful load
                    console.log(`Module ${moduleId} loaded successfully`);
                    
                    return { moduleId, success: true };
                })
                .catch(error => {
                    console.error(`Error loading module ${moduleId}:`, error);
                    showError(`Errore nel caricamento del modulo "${modules[moduleId].title}". ${error.message}`);
                    return { moduleId, success: false, error: error.message };
                });
        }
        
        // Advanced fetch with retry and timeout
        function fetchWithRetry(url, options = {}) {
            const { timeout = 8000, retries = 1 } = options;
            
            return new Promise((resolve, reject) => {
                const attemptFetch = (attempt) => {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), timeout);
                    
                    fetch(url, { 
                        ...options, 
                        signal: controller.signal,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Cache-Control': 'no-cache',
                            ...options.headers
                        }
                    })
                    .then(response => {
                        clearTimeout(timeoutId);
                        resolve(response);
                    })
                    .catch(error => {
                        clearTimeout(timeoutId);
                        if (attempt < retries && (error.name === 'AbortError' || error.name === 'TypeError')) {
                            console.warn(`Fetch attempt ${attempt + 1} failed, retrying...`);
                            setTimeout(() => attemptFetch(attempt + 1), 1000);
                        } else {
                            reject(error);
                        }
                    });
                };
                
                attemptFetch(0);
            });
        }
        
        // Advanced content processing and sanitization
        function processModuleContent(html, moduleId) {
            let content = html;
            
            // Extract body content if full HTML document
            const bodyMatch = content.match(/<body[^>]*>([\s\S]*)<\/body>/i);
            if (bodyMatch) {
                content = bodyMatch[1];
            }
            
            // Remove script tags that might conflict (keep data scripts)
            content = content.replace(/<script(?![^>]*type=["'](?:application\/json|text\/template)["'])[^>]*>[\s\S]*?<\/script>/gi, '');
            
            // Remove duplicate stylesheets (keep module-specific ones)
            content = content.replace(/<link[^>]*href=["'][^"']*bootstrap[^"']*["'][^>]*>/gi, '');
            
            // Convert relative URLs to absolute for assets
            content = content.replace(/src=["'](?!http|\/\/|data:)([^"']+)["']/gi, (match, url) => {
                const baseUrl = '/controlli/';
                return `src="${baseUrl}${url}"`;
            });
            
            // Process module-specific content enhancements
            content = enhanceModuleContent(content, moduleId);
            
            // Wrap content in themed container
            return `
                <div class="bait-module-container" data-module="${moduleId}" data-theme="${document.documentElement.getAttribute('data-theme') || 'light'}">
                    ${content}
                </div>
            `;
        }
        
        // Module-specific content enhancements
        function enhanceModuleContent(content, moduleId) {
            switch (moduleId) {
                case 'auto':
                    // Enhanced auto management features
                    content = addAutoManagementFeatures(content);
                    break;
                case 'attivita':
                    // Enhanced activity tracking
                    content = addActivityTrackingFeatures(content);
                    break;
                case 'audit-mensile':
                    // Enhanced audit reports
                    content = addAuditReportFeatures(content);
                    break;
                case 'ai-chat':
                    // Enhanced AI chat features
                    content = addAIchatFeatures(content);
                    break;
                case 'teamviewer':
                    // Enhanced TeamViewer features
                    content = addTeamViewerFeatures(content);
                    break;
                default:
                    // Generic enhancements
                    content = addGenericModuleFeatures(content);
            }
            
            return content;
        }
        
        // Module-specific feature additions
        function addAutoManagementFeatures(content) {
            // Add quick filters, export buttons, etc.
            const quickActions = `
                <div class="bait-module-quick-actions mb-3">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary" onclick="filterToday()">
                            <i class="bi bi-calendar-day me-1"></i>Oggi
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="filterWeek()">
                            <i class="bi bi-calendar-week me-1"></i>Settimana
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="exportData()">
                            <i class="bi bi-download me-1"></i>Esporta
                        </button>
                    </div>
                </div>
            `;
            
            // Insert after first heading or at beginning
            const headingMatch = content.match(/(<h[1-6][^>]*>.*?<\/h[1-6]>)/i);
            if (headingMatch) {
                content = content.replace(headingMatch[1], headingMatch[1] + quickActions);
            } else {
                content = quickActions + content;
            }
            
            return content;
        }
        
        function addActivityTrackingFeatures(content) {
            // Add activity-specific quick actions
            const quickActions = `
                <div class="bait-module-quick-actions mb-3">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-info" onclick="refreshActivities()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Aggiorna
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="showConflicts()">
                            <i class="bi bi-exclamation-triangle me-1"></i>Conflitti
                        </button>
                    </div>
                </div>
            `;
            
            return addQuickActionsToContent(content, quickActions);
        }
        
        function addAuditReportFeatures(content) {
            // Add audit-specific features
            const quickActions = `
                <div class="bait-module-quick-actions mb-3">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary" onclick="generateReport()">
                            <i class="bi bi-file-earmark-text me-1"></i>Genera Report
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="showAnalytics()">
                            <i class="bi bi-graph-up me-1"></i>Analytics
                        </button>
                    </div>
                </div>
            `;
            
            return addQuickActionsToContent(content, quickActions);
        }
        
        function addAIchatFeatures(content) {
            // Enhanced AI chat with quick prompts
            const quickPrompts = `
                <div class="bait-ai-quick-prompts mb-3">
                    <div class="btn-group-vertical btn-group-sm d-grid gap-1" role="group">
                        <button type="button" class="btn btn-outline-primary" onclick="sendQuickPrompt('analizza-giorno')">
                            <i class="bi bi-calendar-check me-1"></i>Analizza giornata corrente
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="sendQuickPrompt('conflitti-orari')">
                            <i class="bi bi-clock-history me-1"></i>Verifica conflitti orari
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="sendQuickPrompt('report-anomalie')">
                            <i class="bi bi-exclamation-diamond me-1"></i>Report anomalie
                        </button>
                    </div>
                </div>
            `;
            
            return addQuickActionsToContent(content, quickPrompts);
        }
        
        function addTeamViewerFeatures(content) {
            // Enhanced TeamViewer with session management tools
            const quickActions = `
                <div class="bait-module-quick-actions mb-3">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-success" onclick="refreshTeamViewerData()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Aggiorna Sessioni
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="showTeamViewerStats()">
                            <i class="bi bi-bar-chart me-1"></i>Statistiche
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="checkTeamViewerOverlaps()">
                            <i class="bi bi-clock-history me-1"></i>Verifica Sovrapposizioni
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="exportTeamViewerData()">
                            <i class="bi bi-download me-1"></i>Esporta
                        </button>
                    </div>
                </div>
            `;
            
            return addQuickActionsToContent(content, quickActions);
        }
        
        function addGenericModuleFeatures(content) {
            // Add generic refresh and back buttons
            const quickActions = `
                <div class="bait-module-quick-actions mb-3">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary" onclick="showWelcome()">
                            <i class="bi bi-arrow-left me-1"></i>Dashboard
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Aggiorna
                        </button>
                    </div>
                </div>
            `;
            
            return addQuickActionsToContent(content, quickActions);
        }
        
        function addQuickActionsToContent(content, actions) {
            const headingMatch = content.match(/(<h[1-6][^>]*>.*?<\/h[1-6]>)/i);
            if (headingMatch) {
                return content.replace(headingMatch[1], headingMatch[1] + actions);
            } else {
                return actions + content;
            }
        }
        
        // Initialize module-specific features after loading
        function initializeModuleFeatures(moduleId) {
            // Apply current theme to new content
            applyThemeToModules();
            
            // Initialize tooltips and popovers if Bootstrap is available
            if (typeof bootstrap !== 'undefined') {
                // Initialize Bootstrap tooltips
                const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
                tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
                
                // Initialize Bootstrap popovers
                const popovers = document.querySelectorAll('[data-bs-toggle="popover"]');
                popovers.forEach(popover => new bootstrap.Popover(popover));
            }
            
            // Module-specific initializations
            switch (moduleId) {
                case 'auto':
                    initializeAutoManagement();
                    break;
                case 'attivita':
                    initializeActivityTracking();
                    break;
                case 'ai-chat':
                    initializeAIChat();
                    break;
                case 'teamviewer':
                    initializeTeamViewer();
                    break;
            }
            
            // Add keyboard shortcuts
            addModuleKeyboardShortcuts(moduleId);
        }
        
        // Module-specific initialization functions
        function initializeAutoManagement() {
            // Auto module specific initialization
            console.log('Auto management module initialized');
        }
        
        function initializeActivityTracking() {
            // Activity module specific initialization
            console.log('Activity tracking module initialized');
        }
        
        function initializeAIChat() {
            // AI Chat module specific initialization
            console.log('AI Chat module initialized');
        }
        
        function initializeTeamViewer() {
            // TeamViewer module specific initialization
            console.log('TeamViewer module initialized');
            
            // Add TeamViewer specific functionality
            window.refreshTeamViewerData = function() {
                console.log('Refreshing TeamViewer data...');
                loadModule('teamviewer', '/controlli/teamviewer_dashboard_fixed.php');
            };
            
            window.showTeamViewerStats = function() {
                console.log('Showing TeamViewer statistics...');
                // Implementation for showing stats
            };
            
            window.checkTeamViewerOverlaps = function() {
                console.log('Checking TeamViewer overlaps...');
                // Implementation for overlap detection
            };
            
            window.exportTeamViewerData = function() {
                console.log('Exporting TeamViewer data...');
                // Implementation for data export
            };
        }
        
        // Add keyboard shortcuts for modules
        function addModuleKeyboardShortcuts(moduleId) {
            document.addEventListener('keydown', function(e) {
                // Escape key to return to dashboard
                if (e.key === 'Escape' && currentModule === moduleId) {
                    showWelcome();
                }
                
                // Ctrl+R to refresh module content
                if (e.ctrlKey && e.key === 'r' && currentModule === moduleId) {
                    e.preventDefault();
                    loadModule(moduleId, modules[moduleId].url);
                }
            });
        }
        
        // Set active module visual state
        function setActiveModule(moduleId) {
            // Remove active class from all icons
            document.querySelectorAll('.vscode-module-icon').forEach(icon => {
                icon.classList.remove('active');
            });
            
            // Add active class to selected icon
            const activeIcon = document.querySelector(`[data-module="${moduleId}"]`);
            if (activeIcon) {
                activeIcon.classList.add('active');
            }
        }
        
        // Breadcrumb removed - clean interface
        
        // Show loading state
        function showLoading() {
            const moduleContent = document.getElementById('moduleContent');
            const loadingSpinner = document.getElementById('loadingSpinner');
            
            if (moduleContent) moduleContent.classList.add('d-none');
            if (loadingSpinner) loadingSpinner.classList.remove('d-none');
        }
        
        // Show module content
        function showModuleContent(html) {
            const loadingSpinner = document.getElementById('loadingSpinner');
            const moduleContent = document.getElementById('moduleContent');
            
            if (loadingSpinner) loadingSpinner.classList.add('d-none');
            if (moduleContent) {
                moduleContent.innerHTML = html;
                moduleContent.classList.remove('d-none');
            }
        }
        
        // Enhanced error message display
        function showError(message) {
            const errorHtml = `
                <div class="bait-error-container">
                    <div class="mb-4">
                        <i class="bi bi-exclamation-triangle" style="font-size: 4rem; color: var(--bait-danger);"></i>
                    </div>
                    <h4 class="mb-3" style="color: var(--bait-text-primary);">Errore di Caricamento</h4>
                    <p class="mb-4" style="color: var(--bait-text-secondary);">${message}</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-primary" onclick="showWelcome()">
                            <i class="bi bi-house-door me-1"></i>
                            Torna alla Dashboard
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise me-1"></i>
                            Ricarica Pagina
                        </button>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Se il problema persiste, contatta l'amministratore del sistema.
                        </small>
                    </div>
                </div>
            `;
            showModuleContent(errorHtml);
        }
        
        // Show welcome screen
        function showWelcome() {
            document.getElementById('loadingSpinner').classList.add('d-none');
            document.getElementById('moduleContent').classList.add('d-none');
            
            // Clear active module
            setActiveModule(null);
            currentModule = null;
            
            // Update URL
            history.pushState({}, 'BAIT Service Dashboard', location.pathname);
            
            // Restart auto-refresh
            startAutoRefresh();
        }
        
        // Handle browser back/forward
        window.addEventListener('popstate', function(event) {
            if (event.state && event.state.module) {
                const moduleId = event.state.module;
                if (modules[moduleId]) {
                    loadModule(moduleId, modules[moduleId].url);
                }
            } else {
                showWelcome();
            }
        });
        
        // Enhanced Module Caching System
        function cacheModule(moduleId, content) {
            moduleCache.set(moduleId, {
                content: content,
                timestamp: Date.now(),
                size: content.length
            });
            
            // Clean old cache entries if needed
            cleanupCache();
        }
        
        function getCachedModule(moduleId) {
            const cached = moduleCache.get(moduleId);
            if (!cached) return null;
            
            // Check if cache is still valid
            if (Date.now() - cached.timestamp > cacheTimeout) {
                moduleCache.delete(moduleId);
                return null;
            }
            
            return cached.content;
        }
        
        function cleanupCache() {
            const now = Date.now();
            const maxCacheSize = 10; // Maximum cached modules
            
            // Remove expired entries
            for (const [moduleId, cached] of moduleCache.entries()) {
                if (now - cached.timestamp > cacheTimeout) {
                    moduleCache.delete(moduleId);
                }
            }
            
            // If still too many entries, remove oldest
            if (moduleCache.size > maxCacheSize) {
                const entries = Array.from(moduleCache.entries());
                entries.sort((a, b) => a[1].timestamp - b[1].timestamp);
                
                const toRemove = entries.slice(0, moduleCache.size - maxCacheSize);
                toRemove.forEach(([moduleId]) => moduleCache.delete(moduleId));
            }
        }
        
        function clearModuleCache(moduleId = null) {
            if (moduleId) {
                moduleCache.delete(moduleId);
                console.log(`Cache cleared for module: ${moduleId}`);
            } else {
                moduleCache.clear();
                console.log('All module cache cleared');
            }
        }
        
        function getCacheStats() {
            const stats = {
                total: moduleCache.size,
                modules: [],
                totalSize: 0
            };
            
            for (const [moduleId, cached] of moduleCache.entries()) {
                stats.modules.push({
                    moduleId,
                    size: cached.size,
                    age: Date.now() - cached.timestamp
                });
                stats.totalSize += cached.size;
            }
            
            return stats;
        }
        
        // Preload popular modules
        function preloadModules() {
            const popularModules = ['auto', 'attivita', 'ai-chat'];
            
            popularModules.forEach(moduleId => {
                if (!getCachedModule(moduleId) && modules[moduleId]) {
                    // Preload in background without showing
                    fetchWithRetry(modules[moduleId].url, { timeout: 5000 })
                        .then(response => response.text())
                        .then(html => {
                            const processedContent = processModuleContent(html, moduleId);
                            cacheModule(moduleId, processedContent);
                            console.log(`Preloaded module: ${moduleId}`);
                        })
                        .catch(error => {
                            console.warn(`Failed to preload module ${moduleId}:`, error);
                        });
                }
            });
        }
        
        // Auto-refresh ogni 30 secondi (disabilitato quando il filtro è attivo)
        let autoRefreshEnabled = true;
        let autoRefreshTimer;
        
        function startAutoRefresh() {
            if (autoRefreshEnabled) {
                autoRefreshTimer = setTimeout(() => {
                    location.reload();
                }, 30000);
            }
        }
        
        function stopAutoRefresh() {
            if (autoRefreshTimer) {
                clearTimeout(autoRefreshTimer);
                autoRefreshTimer = null;
            }
        }
        
        // Toggle del pannello filtro date
        function toggleDateFilter() {
            const panel = document.getElementById('dateFilterPanel');
            const isVisible = panel.style.display !== 'none';
            
            if (isVisible) {
                panel.style.display = 'none';
                autoRefreshEnabled = true;
                startAutoRefresh();
            } else {
                panel.style.display = 'block';
                autoRefreshEnabled = false;
                stopAutoRefresh();
            }
        }
        
        // Theme Management System
        function initializeTheme() {
            // Check for saved theme preference or default to 'light'
            const savedTheme = localStorage.getItem('bait-theme') || 'light';
            setTheme(savedTheme);
        }
        
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            setTheme(newTheme);
        }
        
        function setTheme(theme) {
            // Apply theme to document
            document.documentElement.setAttribute('data-theme', theme);
            
            // Save preference
            localStorage.setItem('bait-theme', theme);
            
            // Update theme toggle button
            updateThemeToggle(theme);
            
            // Apply theme to dynamically loaded content
            applyThemeToModules();
        }
        
        function updateThemeToggle(theme) {
            // Update old theme button (if exists)
            const themeIcon = document.getElementById('themeIcon');
            const themeText = document.getElementById('themeText');
            
            if (themeIcon && themeText) {
                if (theme === 'dark') {
                    themeIcon.className = 'bi bi-moon-fill';
                    themeText.textContent = 'Scuro';
                } else {
                    themeIcon.className = 'bi bi-sun-fill';
                    themeText.textContent = 'Chiaro';
                }
            }
            
        }
        
        function applyThemeToModules() {
            // Apply theme to any dynamically loaded module content
            const moduleContent = document.getElementById('moduleContent');
            if (moduleContent && !moduleContent.classList.contains('d-none')) {
                const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
                
                // Find all elements that need theme updates in loaded modules
                const elementsToUpdate = moduleContent.querySelectorAll('*');
                elementsToUpdate.forEach(element => {
                    // Add data-theme attribute to maintain consistency
                    element.setAttribute('data-theme', currentTheme);
                });
            }
        }
        
        // Enhanced color scheme detection
        function detectSystemTheme() {
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return 'dark';
            }
            return 'light';
        }
        
        // Listen for system theme changes
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
                // Only auto-switch if user hasn't set a preference
                if (!localStorage.getItem('bait-theme')) {
                    setTheme(e.matches ? 'dark' : 'light');
                }
            });
        }
        
        // Theme persistence across module loads
        function preserveThemeInModules() {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            
            // Override the loadModule function to preserve theme
            const originalLoadModule = loadModule;
            loadModule = function(moduleId, url) {
                originalLoadModule(moduleId, url).then(() => {
                    setTimeout(() => {
                        applyThemeToModules();
                    }, 100);
                });
            };
        }
        
        // Imposta date rapide
        function setQuickDate(days) {
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(endDate.getDate() - days);
            
            document.getElementById('endDate').value = endDate.toISOString().split('T')[0];
            document.getElementById('startDate').value = startDate.toISOString().split('T')[0];
            
            updateDateRangeDisplay();
        }
        
        // Imposta mese corrente
        function setCurrentMonth() {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth(), 1);
            const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            
            document.getElementById('startDate').value = start.toISOString().split('T')[0];
            document.getElementById('endDate').value = end.toISOString().split('T')[0];
            
            updateDateRangeDisplay();
        }
        
        // Aggiorna display intervallo date
        function updateDateRangeDisplay() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const display = document.getElementById('dateRangeDisplay');
            
            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                display.textContent = `${diffDays + 1} giorni`;
            }
        }
        
        // Applica filtro date
        function applyDateFilter() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (!startDate || !endDate) {
                alert('Seleziona entrambe le date');
                return;
            }
            
            if (new Date(startDate) > new Date(endDate)) {
                alert('La data di inizio deve essere precedente alla data di fine');
                return;
            }
            
            // Mostra loading
            showLoadingSpinner();
            
            // Aggiorna badge date
            const startFormatted = new Date(startDate).toLocaleDateString('it-IT');
            const endFormatted = new Date(endDate).toLocaleDateString('it-IT');
            const dateRange = `${startFormatted} - ${endFormatted}`;
            
            document.getElementById('alertDateRange').textContent = dateRange;
            document.getElementById('statsDateRange').textContent = dateRange;
            
            // Chiamata AJAX per aggiornare i dati
            fetch(`dashboard_api.php?action=filter&start=${startDate}&end=${endDate}`)
                .then(response => response.json())
                .then(data => {
                    updateAlertRecenti(data.alerts || []);
                    updateStatisticheTecnici(data.stats || []);
                    hideLoadingSpinner();
                })
                .catch(error => {
                    console.error('Errore nel caricamento dati filtrati:', error);
                    hideLoadingSpinner();
                    alert('Errore nel caricamento dei dati filtrati');
                });
        }
        
        // Reset filtro date
        function resetDateFilter() {
            // Reset alle date default
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(endDate.getDate() - 30);
            
            document.getElementById('startDate').value = startDate.toISOString().split('T')[0];
            document.getElementById('endDate').value = endDate.toISOString().split('T')[0];
            document.getElementById('dateRangeDisplay').textContent = 'Ultimi 30 giorni';
            
            // Reset badge
            document.getElementById('alertDateRange').textContent = 'Tutti';
            document.getElementById('statsDateRange').textContent = 'Tutti';
            
            // Ricarica la pagina per tornare ai dati non filtrati
            location.reload();
        }
        
        // Mostra spinner di caricamento
        function showLoadingSpinner() {
            const alertContent = document.getElementById('alertRecentiContent');
            const statsContent = document.getElementById('statisticheTecniciContent');
            
            const spinner = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Caricamento...</span>
                    </div>
                    <p class="mt-2 text-muted">Aggiornamento dati in corso...</p>
                </div>
            `;
            
            alertContent.innerHTML = spinner;
            statsContent.innerHTML = spinner;
        }
        
        // Nascondi spinner
        function hideLoadingSpinner() {
            // Lo spinner verrà sostituito dai dati aggiornati
        }
        
        
        // Event listeners per aggiornamento automatico display date
        // VS Code Style Drag & Drop for Module Icons
        function initializeDragAndDrop() {
            const moduleGrid = document.getElementById('moduleGrid');
            const icons = moduleGrid.querySelectorAll('.vscode-module-icon');
            
            icons.forEach(icon => {
                icon.addEventListener('dragstart', handleDragStart);
                icon.addEventListener('dragend', handleDragEnd);
                icon.addEventListener('dragover', handleDragOver);
                icon.addEventListener('drop', handleDrop);
                icon.addEventListener('dragenter', handleDragEnter);
                icon.addEventListener('dragleave', handleDragLeave);
            });
        }
        
        let draggedElement = null;
        
        function handleDragStart(e) {
            draggedElement = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', this.outerHTML);
        }
        
        function handleDragEnd(e) {
            this.classList.remove('dragging');
            
            // Remove drag-over class from all icons
            document.querySelectorAll('.vscode-module-icon').forEach(icon => {
                icon.classList.remove('drag-over');
            });
            
            draggedElement = null;
        }
        
        function handleDragOver(e) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            e.dataTransfer.dropEffect = 'move';
            return false;
        }
        
        function handleDragEnter(e) {
            if (this !== draggedElement) {
                this.classList.add('drag-over');
            }
        }
        
        function handleDragLeave(e) {
            this.classList.remove('drag-over');
        }
        
        function handleDrop(e) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }
            
            if (draggedElement !== this) {
                // Get the parent container
                const container = this.parentNode;
                
                // Insert dragged element before or after this element
                const rect = this.getBoundingClientRect();
                const midpoint = rect.top + (rect.height / 2);
                
                if (e.clientY < midpoint) {
                    container.insertBefore(draggedElement, this);
                } else {
                    container.insertBefore(draggedElement, this.nextSibling);
                }
                
                // Save new order to localStorage
                saveModuleOrder();
            }
            
            this.classList.remove('drag-over');
            return false;
        }
        
        function saveModuleOrder() {
            const moduleGrid = document.getElementById('moduleGrid');
            const order = Array.from(moduleGrid.querySelectorAll('.vscode-module-icon')).map(icon => 
                icon.getAttribute('data-module')
            );
            localStorage.setItem('bait-module-order', JSON.stringify(order));
        }
        
        function loadModuleOrder() {
            const savedOrder = localStorage.getItem('bait-module-order');
            if (savedOrder) {
                try {
                    const order = JSON.parse(savedOrder);
                    const moduleGrid = document.getElementById('moduleGrid');
                    const icons = Array.from(moduleGrid.querySelectorAll('.vscode-module-icon'));
                    
                    // Sort icons according to saved order
                    const sortedIcons = order.map(moduleId => 
                        icons.find(icon => icon.getAttribute('data-module') === moduleId)
                    ).filter(Boolean);
                    
                    // Add any icons not in the saved order
                    icons.forEach(icon => {
                        if (!sortedIcons.includes(icon)) {
                            sortedIcons.push(icon);
                        }
                    });
                    
                    // Reorder DOM elements
                    sortedIcons.forEach(icon => {
                        moduleGrid.appendChild(icon);
                    });
                } catch (e) {
                    console.warn('Could not load module order:', e);
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            console.log('Dashboard caricata alle:', now.toLocaleString('it-IT'));
            
            // Initialize theme system
            initializeTheme();
            
            // Initialize drag & drop
            initializeDragAndDrop();
            
            // Load saved module order
            loadModuleOrder();
            
            // Preload popular modules for better performance
            setTimeout(() => {
                preloadModules();
            }, 2000);
            
            // Event listeners per i campi data
            document.getElementById('startDate').addEventListener('change', updateDateRangeDisplay);
            document.getElementById('endDate').addEventListener('change', updateDateRangeDisplay);
            
            updateDateRangeDisplay();
            startAutoRefresh();
            
            // Initialize mobile optimizations
            initializeMobileOptimizations();
            
            // Initialize iframe communication
            initializeIframeCommunication();
        });
        
        // Iframe communication system for upload reports
        function initializeIframeCommunication() {
            window.addEventListener('message', function(event) {
                if (event.data.type === 'upload_success') {
                    const data = event.data.data;
                    showUploadSuccessNotification(data);
                } else if (event.data.type === 'upload_started') {
                    showUploadProgressNotification();
                }
            });
        }
        
        function showUploadSuccessNotification(data) {
            // Create success notification
            const notification = document.createElement('div');
            notification.className = 'upload-success-notification';
            notification.innerHTML = `
                <div class="notification-content">
                    <div class="notification-header">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        <strong>Upload Completato!</strong>
                        <button class="btn-close" onclick="this.closest('.upload-success-notification').remove()"></button>
                    </div>
                    <div class="notification-body">
                        📁 ${data.files} file processati • 
                        ✅ ${data.newRecords} nuovi record • 
                        📦 ${data.backups} backup creati
                    </div>
                </div>
            `;
            
            // Add notification styles if not already present
            if (!document.getElementById('upload-notification-styles')) {
                const style = document.createElement('style');
                style.id = 'upload-notification-styles';
                style.textContent = `
                    .upload-success-notification {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        background: linear-gradient(135deg, #d4edda, #c3e6cb);
                        border: 1px solid #c3e6cb;
                        border-radius: 10px;
                        padding: 15px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                        z-index: 9999;
                        max-width: 400px;
                        animation: slideInFromRight 0.5s ease-out;
                    }
                    
                    .notification-header {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        margin-bottom: 8px;
                        font-weight: 600;
                    }
                    
                    .notification-body {
                        font-size: 0.9rem;
                        color: #155724;
                    }
                    
                    .btn-close {
                        background: none;
                        border: none;
                        font-size: 1.2rem;
                        cursor: pointer;
                        color: #155724;
                        opacity: 0.6;
                    }
                    
                    .btn-close:hover {
                        opacity: 1;
                    }
                    
                    @keyframes slideInFromRight {
                        from {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                `;
                document.head.appendChild(style);
            }
            
            document.body.appendChild(notification);
            
            // Auto remove after 8 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.animation = 'slideInFromRight 0.5s ease-out reverse';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.remove();
                        }
                    }, 500);
                }
            }, 8000);
        }
        
        function showUploadProgressNotification() {
            // Show a simple progress indicator
            const existing = document.querySelector('.upload-progress-notification');
            if (existing) existing.remove();
            
            const notification = document.createElement('div');
            notification.className = 'upload-progress-notification';
            notification.innerHTML = `
                <div class="notification-content">
                    <div class="d-flex align-items-center">
                        <div class="spinner-border spinner-border-sm text-primary me-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div>
                            <strong>Upload in corso...</strong><br>
                            <small>Elaborazione file CSV in corso</small>
                        </div>
                    </div>
                </div>
            `;
            
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 10px;
                padding: 15px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 9999;
                max-width: 300px;
            `;
            
            document.body.appendChild(notification);
        }
        
        // Mobile-specific optimizations and features
        function initializeMobileOptimizations() {
            // Detect mobile device
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            const isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
            
            if (isMobile || isTouch) {
                document.body.classList.add('mobile-device');
                
                // Add mobile-specific behaviors
                addTouchGestures();
                optimizeScrolling();
                addMobileKeyboardSupport();
                preventZoomOnFocus();
                
                console.log('Mobile optimizations enabled');
            }
            
            // Detect device orientation changes
            if (window.DeviceOrientationEvent) {
                window.addEventListener('orientationchange', handleOrientationChange);
            }
            
            // Optimize for reduced motion
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                document.body.classList.add('reduced-motion');
            }
            
            // Add responsive font size adjustment
            adjustFontSizeForViewport();
            window.addEventListener('resize', adjustFontSizeForViewport);
        }
        
        // Touch gesture support
        function addTouchGestures() {
            let touchStartX = 0;
            let touchStartY = 0;
            let isSwipe = false;
            
            // Swipe to return to dashboard
            document.addEventListener('touchstart', function(e) {
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
                isSwipe = true;
            }, { passive: true });
            
            document.addEventListener('touchend', function(e) {
                if (!isSwipe) return;
                
                const touchEndX = e.changedTouches[0].clientX;
                const touchEndY = e.changedTouches[0].clientY;
                const diffX = touchStartX - touchEndX;
                const diffY = touchStartY - touchEndY;
                
                // Swipe right to go back (only if viewing a module)
                if (currentModule && diffX < -50 && Math.abs(diffY) < 100) {
                    showWelcome();
                }
                
                isSwipe = false;
            }, { passive: true });
        }
        
        // Optimize scrolling performance
        function optimizeScrolling() {
            // Use passive event listeners for better performance
            document.addEventListener('touchmove', function() {}, { passive: true });
            document.addEventListener('wheel', function() {}, { passive: true });
            
            // Prevent overscroll on body
            document.body.style.overscrollBehavior = 'none';
        }
        
        // Mobile keyboard support
        function addMobileKeyboardSupport() {
            // Adjust viewport when keyboard opens
            let initialViewportHeight = window.innerHeight;
            
            window.addEventListener('resize', function() {
                const currentHeight = window.innerHeight;
                const heightDiff = initialViewportHeight - currentHeight;
                
                if (heightDiff > 150) {
                    // Keyboard is likely open
                    document.body.classList.add('keyboard-open');
                } else {
                    // Keyboard is likely closed
                    document.body.classList.remove('keyboard-open');
                }
            });
        }
        
        // Prevent zoom on input focus
        function preventZoomOnFocus() {
            const inputs = document.querySelectorAll('input, button, select, textarea');
            inputs.forEach(input => {
                if (input.style.fontSize !== '16px') {
                    input.style.fontSize = '16px';
                }
            });
        }
        
        // Handle orientation changes
        function handleOrientationChange() {
            // Add a small delay to ensure the orientation has fully changed
            setTimeout(() => {
                // Trigger a resize event to recalculate layouts
                window.dispatchEvent(new Event('resize'));
                
                // Update grid layout for landscape mode
                updateGridForOrientation();
                
                // Force redraw to prevent layout issues
                document.body.style.display = 'none';
                document.body.offsetHeight; // Trigger reflow
                document.body.style.display = '';
                
                console.log('Orientation changed, layout updated');
            }, 100);
        }
        
        // Update grid layout based on orientation
        function updateGridForOrientation() {
            const isLandscape = window.innerWidth > window.innerHeight;
            const moduleGrid = document.getElementById('moduleGrid');
            
            if (moduleGrid && window.innerWidth <= 896) {
                if (isLandscape) {
                    moduleGrid.style.gridTemplateColumns = 'repeat(6, 1fr)';
                } else {
                    // Reset to CSS-defined responsive layout
                    moduleGrid.style.gridTemplateColumns = '';
                }
            }
        }
        
        // Responsive font size adjustment
        function adjustFontSizeForViewport() {
            const viewportWidth = window.innerWidth;
            let scaleFactor = 1;
            
            if (viewportWidth <= 480) {
                scaleFactor = 0.9;
            } else if (viewportWidth <= 576) {
                scaleFactor = 0.95;
            } else if (viewportWidth <= 768) {
                scaleFactor = 1;
            }
            
            document.documentElement.style.setProperty('--font-scale', scaleFactor);
        }
        
        // Enhanced error handling for mobile
        function showMobileError(message) {
            const isMobile = document.body.classList.contains('mobile-device');
            
            if (isMobile) {
                // Create a mobile-optimized error display
                const errorHtml = `
                    <div class="bait-error-container mobile-error">
                        <div class="mb-3">
                            <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: var(--bait-danger);"></i>
                        </div>
                        <h5 class="mb-2" style="color: var(--bait-text-primary);">Errore</h5>
                        <p class="mb-3" style="color: var(--bait-text-secondary); font-size: 0.9rem;">${message}</p>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary btn-lg" onclick="showWelcome()">
                                <i class="bi bi-house-door me-1"></i>
                                Torna alla Dashboard
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise me-1"></i>
                                Ricarica Pagina
                            </button>
                        </div>
                    </div>
                `;
                showModuleContent(errorHtml);
            } else {
                // Use standard error display for desktop
                showError(message);
            }
        }
        
        // Mobile-optimized loading spinner
        function showMobileLoading() {
            const isMobile = document.body.classList.contains('mobile-device');
            
            if (isMobile) {
                const loadingHtml = `
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary mb-2" role="status" style="width: 2.5rem; height: 2.5rem;">
                            <span class="visually-hidden">Caricamento...</span>
                        </div>
                        <h6 class="text-muted">Caricamento...</h6>
                    </div>
                `;
                
                const moduleContent = document.getElementById('moduleContent');
                if (moduleContent) moduleContent.classList.add('d-none');
                document.getElementById('loadingSpinner').innerHTML = loadingHtml;
                document.getElementById('loadingSpinner').classList.remove('d-none');
            } else {
                showLoading();
            }
        }
        
        // Add mobile-specific CSS classes
        document.addEventListener('DOMContentLoaded', function() {
            const style = document.createElement('style');
            style.textContent = `
                .mobile-device .keyboard-open {
                    height: 100vh;
                    overflow: hidden;
                }
                
                .mobile-device .reduced-motion * {
                    animation-duration: 0.01ms !important;
                    transition-duration: 0.01ms !important;
                }
                
                .mobile-error {
                    max-width: 320px;
                    margin: 0 auto;
                }
                
                @media (max-width: 768px) {
                    body {
                        font-size: calc(1rem * var(--font-scale, 1));
                    }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>