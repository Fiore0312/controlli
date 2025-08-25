<?php
/**
 * BAIT SERVICE ENTERPRISE - Sistema Navigazione Unificato
 * Componente condiviso per tutte le dashboard del sistema
 */

// Configurazione navigazione
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Definizione struttura menu
$navigation = [
    'dashboard' => [
        'title' => 'Dashboard Enterprise',
        'url' => '/controlli/laravel_bait/public/index_standalone.php',
        'icon' => 'bi-speedometer2',
        'description' => 'Dashboard principale con KPI e alert in tempo reale'
    ],
    'audit_monthly' => [
        'title' => 'Audit Mensile',
        'url' => '/controlli/audit_monthly_manager.php', 
        'icon' => 'bi-calendar3',
        'description' => 'Gestione caricamento CSV e audit mensile progressivo'
    ],
    'audit_tecnico' => [
        'title' => 'Audit Tecnico',
        'url' => '/controlli/audit_tecnico_dashboard.php',
        'icon' => 'bi-person-check',
        'description' => 'Controllo dettagliato attività singolo tecnico'
    ],
    'bait_ai_chat' => [
        'title' => 'AI Chat',
        'url' => '/controlli/bait_ai_chat.php',
        'icon' => 'bi-robot',
        'description' => 'Assistente AI per analisi file e business intelligence'
    ],
    'gestione_csv' => [
        'title' => 'Gestione CSV',
        'icon' => 'bi-file-earmark-spreadsheet',
        'submenu' => [
            'attivita_deepser' => ['title' => 'Attività Deepser', 'url' => '/controlli/attivita_deepser.php', 'icon' => 'bi-table'],
            'utilizzo_auto' => ['title' => 'Utilizzo Auto', 'url' => '/controlli/utilizzo_auto.php', 'icon' => 'bi-car-front'],
            'richieste_permessi' => ['title' => 'Richieste Permessi', 'url' => '/controlli/richieste_permessi.php', 'icon' => 'bi-calendar-check'],
            'timbrature' => ['title' => 'Timbrature', 'url' => '/controlli/timbrature.php', 'icon' => 'bi-clock'],
            'sessioni_teamviewer' => ['title' => 'Sessioni TeamViewer', 'url' => '/controlli/sessioni_teamviewer.php', 'icon' => 'bi-display'],
            'calendario' => ['title' => 'Calendario', 'url' => '/controlli/calendario.php', 'icon' => 'bi-calendar-event']
        ]
    ],
    'sistema_test' => [
        'title' => 'Sistema Test',
        'icon' => 'bi-gear',
        'submenu' => [
            'demo_audit' => ['title' => 'Demo Sistema', 'url' => '/controlli/demo_audit_system.php', 'icon' => 'bi-info-circle'],
            'test_sistema' => ['title' => 'Test Funzionamento', 'url' => '/controlli/test_sistema_finale.php', 'icon' => 'bi-check-circle'],
            'test_database' => ['title' => 'Test Database', 'url' => '/controlli/laravel_bait/public/test_database_connection.php', 'icon' => 'bi-database-check']
        ]
    ]
];

// Funzione per determinare la pagina attiva
function isActivePage($pageKey, $currentPage) {
    global $navigation;
    
    if (isset($navigation[$pageKey]['url'])) {
        $pageFile = basename($navigation[$pageKey]['url'], '.php');
        return $currentPage === $pageFile;
    }
    
    if (isset($navigation[$pageKey]['submenu'])) {
        foreach ($navigation[$pageKey]['submenu'] as $subPageKey => $subPage) {
            $subPageFile = basename($subPage['url'], '.php');
            if ($currentPage === $subPageFile) {
                return true;
            }
        }
    }
    
    return false;
}

// Funzione per ottenere informazioni pagina corrente
function getCurrentPageInfo($currentPage) {
    global $navigation;
    
    foreach ($navigation as $pageKey => $page) {
        if (isset($page['url'])) {
            $pageFile = basename($page['url'], '.php');
            if ($currentPage === $pageFile) {
                return $page;
            }
        }
        
        if (isset($page['submenu'])) {
            foreach ($page['submenu'] as $subPageKey => $subPage) {
                $subPageFile = basename($subPage['url'], '.php');
                if ($currentPage === $subPageFile) {
                    return [
                        'title' => $subPage['title'],
                        'parent' => $page['title'],
                        'description' => $subPage['description'] ?? ''
                    ];
                }
            }
        }
    }
    
    return ['title' => 'BAIT Service Enterprise', 'description' => 'Sistema controllo attività tecnici'];
}

// Funzione per generare breadcrumb
function generateBreadcrumb($currentPage) {
    global $navigation;
    $breadcrumb = ['<a href="/controlli/laravel_bait/public/index_standalone.php" class="text-decoration-none"><i class="bi bi-house"></i> Dashboard</a>'];
    
    foreach ($navigation as $pageKey => $page) {
        if (isset($page['url'])) {
            $pageFile = basename($page['url'], '.php');
            if ($currentPage === $pageFile) {
                $breadcrumb[] = '<span class="text-muted">' . $page['title'] . '</span>';
                break;
            }
        }
        
        if (isset($page['submenu'])) {
            foreach ($page['submenu'] as $subPageKey => $subPage) {
                $subPageFile = basename($subPage['url'], '.php');
                if ($currentPage === $subPageFile) {
                    $breadcrumb[] = '<span class="text-muted">' . $page['title'] . '</span>';
                    $breadcrumb[] = '<span class="text-muted">' . $subPage['title'] . '</span>';
                    break 2;
                }
            }
        }
    }
    
    return implode(' <i class="bi bi-chevron-right text-muted mx-2"></i> ', $breadcrumb);
}

// Funzione per renderizzare la navbar completa
function renderBaitNavigation($currentPage, $dataSource = 'database') {
    global $navigation;
    
    $currentPageInfo = getCurrentPageInfo($currentPage);
    $breadcrumb = generateBreadcrumb($currentPage);
    
    ?>
    <!-- BAIT Enterprise Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="/controlli/laravel_bait/public/index_standalone.php">
                <i class="bi bi-shield-check me-2"></i>
                BAIT Service Enterprise
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    
                    <!-- Dashboard Enterprise -->
                    <li class="nav-item">
                        <a class="nav-link <?= isActivePage('dashboard', $currentPage) ? 'active' : '' ?>" 
                           href="<?= $navigation['dashboard']['url'] ?>">
                            <i class="<?= $navigation['dashboard']['icon'] ?> me-1"></i>
                            <?= $navigation['dashboard']['title'] ?>
                        </a>
                    </li>
                    
                    <!-- Audit Mensile -->
                    <li class="nav-item">
                        <a class="nav-link <?= isActivePage('audit_monthly', $currentPage) ? 'active' : '' ?>" 
                           href="<?= $navigation['audit_monthly']['url'] ?>">
                            <i class="<?= $navigation['audit_monthly']['icon'] ?> me-1"></i>
                            <?= $navigation['audit_monthly']['title'] ?>
                        </a>
                    </li>
                    
                    <!-- Audit Tecnico -->
                    <li class="nav-item">
                        <a class="nav-link <?= isActivePage('audit_tecnico', $currentPage) ? 'active' : '' ?>" 
                           href="<?= $navigation['audit_tecnico']['url'] ?>">
                            <i class="<?= $navigation['audit_tecnico']['icon'] ?> me-1"></i>
                            <?= $navigation['audit_tecnico']['title'] ?>
                        </a>
                    </li>
                    
                    <!-- AI Chat -->
                    <li class="nav-item">
                        <a class="nav-link <?= isActivePage('bait_ai_chat', $currentPage) ? 'active' : '' ?>" 
                           href="<?= $navigation['bait_ai_chat']['url'] ?>">
                            <i class="<?= $navigation['bait_ai_chat']['icon'] ?> me-1"></i>
                            <?= $navigation['bait_ai_chat']['title'] ?>
                            <span class="badge bg-warning text-dark ms-1" style="font-size: 0.6em;">AI</span>
                        </a>
                    </li>
                    
                    <!-- Gestione CSV -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= isActivePage('gestione_csv', $currentPage) ? 'active' : '' ?>" 
                           href="#" role="button" data-bs-toggle="dropdown">
                            <i class="<?= $navigation['gestione_csv']['icon'] ?> me-1"></i>
                            <?= $navigation['gestione_csv']['title'] ?>
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach ($navigation['gestione_csv']['submenu'] as $key => $item): ?>
                            <li>
                                <a class="dropdown-item" href="<?= $item['url'] ?>">
                                    <i class="<?= $item['icon'] ?> me-2"></i>
                                    <?= $item['title'] ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    
                    <!-- Sistema Test -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= isActivePage('sistema_test', $currentPage) ? 'active' : '' ?>" 
                           href="#" role="button" data-bs-toggle="dropdown">
                            <i class="<?= $navigation['sistema_test']['icon'] ?> me-1"></i>
                            <?= $navigation['sistema_test']['title'] ?>
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach ($navigation['sistema_test']['submenu'] as $key => $item): ?>
                            <li>
                                <a class="dropdown-item" href="<?= $item['url'] ?>">
                                    <i class="<?= $item['icon'] ?> me-2"></i>
                                    <?= $item['title'] ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    
                </ul>
                
                <!-- Status e Informazioni -->
                <div class="d-flex align-items-center text-white">
                    <span class="me-3">
                        <span class="status-indicator <?= $dataSource === 'database' ? 'status-online' : 'status-demo' ?>"></span>
                        <?= $dataSource === 'database' ? 'Live Data (MySQL)' : 'Demo Mode' ?>
                    </span>
                    <small><?= date('H:i:s') ?></small>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Breadcrumb -->
    <div class="container-fluid py-2" style="background-color: #f8f9fa; border-bottom: 1px solid #dee2e6;">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <?= $breadcrumb ?>
            </ol>
        </nav>
    </div>
    
    <!-- Page Header -->
    <div class="container-fluid py-3" style="background-color: #ffffff; border-bottom: 1px solid #dee2e6;">
        <div class="row align-items-center">
            <div class="col">
                <h4 class="mb-1">
                    <i class="bi bi-<?= $currentPage === 'index_standalone' ? 'speedometer2' : 
                        ($currentPage === 'audit_monthly_manager' ? 'calendar3' : 
                         ($currentPage === 'audit_tecnico_dashboard' ? 'person-check' : 'gear')) ?> me-2 text-primary"></i>
                    <?= $currentPageInfo['title'] ?>
                    <?php if (isset($currentPageInfo['parent'])): ?>
                    <small class="text-muted">/ <?= $currentPageInfo['parent'] ?></small>
                    <?php endif; ?>
                </h4>
                <p class="text-muted mb-0"><?= $currentPageInfo['description'] ?? 'Sistema controllo attività tecnici BAIT Service' ?></p>
            </div>
            <div class="col-auto">
                <div class="d-flex gap-2">
                    <!-- Quick Actions Context-Sensitive -->
                    <?php if ($currentPage === 'index_standalone'): ?>
                    <a href="/controlli/audit_monthly_manager.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-calendar3 me-1"></i>Audit Mensile
                    </a>
                    <a href="/controlli/audit_tecnico_dashboard.php" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-person-check me-1"></i>Audit Tecnico
                    </a>
                    <?php elseif ($currentPage === 'audit_monthly_manager'): ?>
                    <a href="/controlli/laravel_bait/public/index_standalone.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                    <a href="/controlli/audit_tecnico_dashboard.php" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-person-check me-1"></i>Audit Tecnico
                    </a>
                    <?php elseif ($currentPage === 'audit_tecnico_dashboard'): ?>
                    <a href="/controlli/laravel_bait/public/index_standalone.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                    <a href="/controlli/audit_monthly_manager.php" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-calendar3 me-1"></i>Audit Mensile
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .status-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 8px;
    }
    .status-online { background-color: #10b981; }
    .status-demo { background-color: #f59e0b; }
    
    .navbar-nav .nav-link.active {
        background-color: rgba(255,255,255,0.2);
        border-radius: 4px;
    }
    
    .navbar-nav .nav-link:hover {
        background-color: rgba(255,255,255,0.1);
        border-radius: 4px;
        transition: all 0.2s ease;
    }
    
    .breadcrumb-item + .breadcrumb-item::before {
        content: none;
    }
    </style>
    <?php
}
?>