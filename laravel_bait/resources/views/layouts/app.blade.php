<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Sistema di controllo automatizzato attività tecniche per BAIT Service">
    <meta name="author" content="BAIT Service">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="BAIT Dashboard">
    <meta name="msapplication-TileImage" content="/icons/icon-144.png">
    <meta name="msapplication-TileColor" content="#2563eb">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="@yield('title', 'BAIT Service Enterprise Dashboard')">
    <meta property="og:description" content="Sistema di controllo automatizzato attività tecniche">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('icons/icon-512.png') }}">
    
    <title>@yield('title', 'BAIT Service Enterprise Dashboard')</title>
    
    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/icon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/icons/icon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-180.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/icons/icon-152.png">
    <link rel="apple-touch-icon" sizes="144x144" href="/icons/icon-144.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/icons/icon-120.png">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- External Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUa...." crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom Styles -->
    @if(file_exists(public_path('resources/css/enterprise-design-system.css')))
    <link href="{{ asset('resources/css/enterprise-design-system.css') }}" rel="stylesheet">
    @endif
    
    @stack('styles')
    
    <!-- Performance & Security Headers -->
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" as="style">
    
    <!-- Alpine.js Cloak -->
    <style>
        [x-cloak] { display: none !important; }
        
        /* Loading Spinner */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(248, 250, 252, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.3s ease;
        }
        
        .loading-spinner {
            width: 3rem;
            height: 3rem;
            border: 0.25em solid #e2e8f0;
            border-radius: 50%;
            border-top-color: #2563eb;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Page transition */
        .page-transition {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
        }
        
        .page-loaded {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>

<body class="page-transition" x-data="{ loading: false }" x-cloak>
    
    <!-- Loading Overlay -->
    <div x-show="loading" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="loading-overlay">
        <div class="text-center">
            <div class="loading-spinner mb-3"></div>
            <div class="text-muted">Caricamento...</div>
        </div>
    </div>
    
    <!-- Skip to main content for accessibility -->
    <a class="visually-hidden-focusable btn btn-primary position-absolute top-0 start-0 m-2" 
       href="#main-content">
        Skip to main content
    </a>
    
    <!-- Navigation -->
    @hasSection('navigation')
        @yield('navigation')
    @else
        <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold" href="{{ route('home') }}">
                    <i class="bi bi-shield-check me-2"></i>
                    BAIT Service Enterprise
                </a>
                
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" 
                               href="{{ route('home') }}">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" 
                               href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('upload.page') ? 'active' : '' }}" 
                               href="{{ route('upload.page') }}">Upload CSV</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('status') ? 'active' : '' }}" 
                               href="{{ route('status') }}">Status</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    @endif
    
    <!-- Main Content -->
    <main id="main-content" class="flex-grow-1">
        @yield('content')
    </main>
    
    <!-- Footer -->
    @hasSection('footer')
        @yield('footer')
    @else
        <footer class="bg-dark text-light py-3 mt-auto">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <small class="text-muted">
                            &copy; {{ date('Y') }} BAIT Service Enterprise Dashboard
                        </small>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <small class="text-muted">
                            Version: Laravel Enterprise 1.0 | 
                            <span id="connection-status" class="text-success">Online</span>
                        </small>
                    </div>
                </div>
            </div>
        </footer>
    @endif
    
    <!-- Notification Container -->
    <div id="notification-container" class="position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" 
            crossorigin="anonymous"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    
    <!-- Alpine.js -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    
    <!-- Custom JavaScript -->
    @if(file_exists(public_path('resources/js/dashboard-enterprise.js')))
    <script src="{{ asset('resources/js/dashboard-enterprise.js') }}" defer></script>
    @endif
    
    @stack('scripts')
    
    <!-- Service Worker Registration -->
    <script>
        // Page loaded animation
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('page-loaded');
            
            // Connection status monitoring
            function updateConnectionStatus() {
                const statusEl = document.getElementById('connection-status');
                if (statusEl) {
                    if (navigator.onLine) {
                        statusEl.textContent = 'Online';
                        statusEl.className = 'text-success';
                    } else {
                        statusEl.textContent = 'Offline';
                        statusEl.className = 'text-warning';
                    }
                }
            }
            
            window.addEventListener('online', updateConnectionStatus);
            window.addEventListener('offline', updateConnectionStatus);
            updateConnectionStatus();
            
            // Service Worker Registration
            if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
                navigator.serviceWorker.register('/sw.js')
                    .then(registration => {
                        console.log('SW registered:', registration);
                        
                        // Check for updates
                        registration.addEventListener('updatefound', () => {
                            console.log('SW update available');
                            const newWorker = registration.installing;
                            
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    // Show update notification
                                    if (window.notificationManager) {
                                        window.notificationManager.info(
                                            'Nuova versione disponibile. Ricarica la pagina per aggiornare.',
                                            10000
                                        );
                                    }
                                }
                            });
                        });
                    })
                    .catch(error => {
                        console.log('SW registration failed:', error);
                    });
                    
                // Listen for SW messages
                navigator.serviceWorker.addEventListener('message', event => {
                    if (event.data && event.data.type === 'CACHE_UPDATED') {
                        console.log('Cache updated by SW');
                    }
                });
            }
        });
        
        // Global error handler
        window.addEventListener('error', function(event) {
            console.error('Global error:', event.error);
            
            // Show user-friendly error message
            if (window.notificationManager) {
                window.notificationManager.error('Si è verificato un errore. Ricarica la pagina se persiste.');
            }
        });
        
        // Global unhandled promise rejection handler
        window.addEventListener('unhandledrejection', function(event) {
            console.error('Unhandled promise rejection:', event.reason);
            event.preventDefault();
        });
    </script>
    
    <!-- PWA Install Prompt -->
    <script>
        let deferredPrompt;
        
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('PWA install prompt available');
            e.preventDefault();
            deferredPrompt = e;
            
            // Show install button or banner
            const installButton = document.getElementById('pwa-install-btn');
            if (installButton) {
                installButton.style.display = 'block';
                installButton.addEventListener('click', () => {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choiceResult) => {
                        if (choiceResult.outcome === 'accepted') {
                            console.log('User accepted PWA install');
                        }
                        deferredPrompt = null;
                        installButton.style.display = 'none';
                    });
                });
            }
        });
        
        window.addEventListener('appinstalled', () => {
            console.log('PWA was installed');
            deferredPrompt = null;
            
            if (window.notificationManager) {
                window.notificationManager.success('BAIT Dashboard installato con successo!');
            }
        });
    </script>
    
    <!-- Analytics (Google Analytics 4) -->
    @if(config('app.env') === 'production' && config('services.google_analytics.tracking_id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google_analytics.tracking_id') }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ config("services.google_analytics.tracking_id") }}', {
            page_title: document.title,
            page_location: window.location.href
        });
    </script>
    @endif
</body>
</html>