/**
 * BAIT Service Enterprise Dashboard - Service Worker
 * =================================================
 * PWA Service Worker per caching e offline support
 */

const CACHE_NAME = 'bait-dashboard-v1.0.0';
const STATIC_CACHE_NAME = 'bait-static-v1.0.0';
const DYNAMIC_CACHE_NAME = 'bait-dynamic-v1.0.0';
const API_CACHE_NAME = 'bait-api-v1.0.0';

// Static assets da cachare immediatamente
const STATIC_ASSETS = [
    '/',
    '/dashboard',
    '/manifest.json',
    '/resources/css/enterprise-design-system.css',
    '/resources/js/dashboard-enterprise.js',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
    'https://cdn.jsdelivr.net/npm/chart.js',
    'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js',
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap'
];

// API endpoints da cachare con strategia network-first
const API_ENDPOINTS = [
    '/api/dashboard/data',
    '/api/system/status',
    '/api/alerts',
    '/api/kpis'
];

// Install event - Cache static assets
self.addEventListener('install', event => {
    console.log('[SW] Installing Service Worker');
    
    event.waitUntil(
        Promise.all([
            // Cache static assets
            caches.open(STATIC_CACHE_NAME).then(cache => {
                console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_ASSETS.filter(url => !url.startsWith('http')));
            }),
            
            // Cache external CDN assets
            caches.open(STATIC_CACHE_NAME).then(cache => {
                console.log('[SW] Caching CDN assets');
                const cdnAssets = STATIC_ASSETS.filter(url => url.startsWith('http'));
                return Promise.allSettled(
                    cdnAssets.map(url => 
                        cache.add(url).catch(err => console.warn('[SW] Failed to cache:', url, err))
                    )
                );
            })
        ])
    );
    
    // Force activate immediately
    self.skipWaiting();
});

// Activate event - Clean old caches
self.addEventListener('activate', event => {
    console.log('[SW] Activating Service Worker');
    
    event.waitUntil(
        Promise.all([
            // Clean old caches
            caches.keys().then(cacheNames => {
                const cacheWhitelist = [STATIC_CACHE_NAME, DYNAMIC_CACHE_NAME, API_CACHE_NAME];
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (!cacheWhitelist.includes(cacheName)) {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            }),
            
            // Take control immediately
            self.clients.claim()
        ])
    );
});

// Fetch event - Network strategies
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip cross-origin requests (except CDNs)
    if (url.origin !== location.origin && !isCDNRequest(url)) {
        return;
    }
    
    // Route requests based on type
    if (isAPIRequest(url)) {
        event.respondWith(handleAPIRequest(request));
    } else if (isStaticAsset(url)) {
        event.respondWith(handleStaticAsset(request));
    } else {
        event.respondWith(handlePageRequest(request));
    }
});

// Check if request is to API endpoint
function isAPIRequest(url) {
    return url.pathname.startsWith('/api/') || 
           API_ENDPOINTS.some(endpoint => url.pathname.startsWith(endpoint));
}

// Check if request is for static asset
function isStaticAsset(url) {
    const staticExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.svg', '.woff2', '.woff'];
    return staticExtensions.some(ext => url.pathname.endsWith(ext)) ||
           url.pathname.includes('/css/') ||
           url.pathname.includes('/js/') ||
           url.pathname.includes('/icons/');
}

// Check if request is to CDN
function isCDNRequest(url) {
    const cdnDomains = ['cdn.jsdelivr.net', 'fonts.googleapis.com', 'fonts.gstatic.com'];
    return cdnDomains.some(domain => url.hostname.includes(domain));
}

// Handle API requests - Network first with cache fallback
async function handleAPIRequest(request) {
    const cache = await caches.open(API_CACHE_NAME);
    
    try {
        // Try network first
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache successful responses (but not for too long)
            const responseClone = networkResponse.clone();
            const cacheControl = responseClone.headers.get('cache-control');
            
            // Only cache if not explicitly told not to
            if (!cacheControl || !cacheControl.includes('no-cache')) {
                // Add timestamp for cache expiry
                const response = responseClone.clone();
                const body = await response.text();
                const cachedResponse = new Response(body, {
                    status: response.status,
                    statusText: response.statusText,
                    headers: {
                        ...Object.fromEntries(response.headers),
                        'sw-cached-at': Date.now().toString()
                    }
                });
                cache.put(request, cachedResponse);
            }
        }
        
        return networkResponse;
    } catch (error) {
        console.log('[SW] Network failed for API, trying cache:', request.url);
        
        // Network failed, try cache
        const cachedResponse = await cache.match(request);
        if (cachedResponse) {
            // Check if cache is too old (5 minutes for API data)
            const cachedAt = cachedResponse.headers.get('sw-cached-at');
            if (cachedAt) {
                const age = Date.now() - parseInt(cachedAt);
                if (age > 5 * 60 * 1000) { // 5 minutes
                    console.log('[SW] Cache too old for API request');
                    // Return cache but with warning header
                    return new Response(await cachedResponse.text(), {
                        status: cachedResponse.status,
                        statusText: cachedResponse.statusText,
                        headers: {
                            ...Object.fromEntries(cachedResponse.headers),
                            'sw-cache-warning': 'stale-data'
                        }
                    });
                }
            }
            return cachedResponse;
        }
        
        // No cache available, return offline response
        return new Response(JSON.stringify({
            error: 'offline',
            message: 'No network connection and no cached data available'
        }), {
            status: 503,
            headers: {
                'Content-Type': 'application/json',
                'sw-offline': 'true'
            }
        });
    }
}

// Handle static assets - Cache first with network update
async function handleStaticAsset(request) {
    const cache = await caches.open(STATIC_CACHE_NAME);
    
    // Try cache first
    const cachedResponse = await cache.match(request);
    if (cachedResponse) {
        // Update in background
        fetch(request).then(networkResponse => {
            if (networkResponse.ok) {
                cache.put(request, networkResponse);
            }
        }).catch(() => {
            // Ignore network errors for static assets
        });
        
        return cachedResponse;
    }
    
    // Not in cache, fetch from network
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        // For critical assets, return a fallback
        if (request.url.includes('.css')) {
            return new Response('/* Offline - CSS not available */', {
                headers: { 'Content-Type': 'text/css' }
            });
        }
        if (request.url.includes('.js')) {
            return new Response('console.warn("Offline - JS not available");', {
                headers: { 'Content-Type': 'application/javascript' }
            });
        }
        
        throw error;
    }
}

// Handle page requests - Network first, cache fallback
async function handlePageRequest(request) {
    const cache = await caches.open(DYNAMIC_CACHE_NAME);
    
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline page
        return new Response(`
            <!DOCTYPE html>
            <html lang="it">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>BAIT Service - Offline</title>
                <style>
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        min-height: 100vh;
                        margin: 0;
                        background: #f8fafc;
                        color: #374151;
                    }
                    .container {
                        text-align: center;
                        padding: 2rem;
                        background: white;
                        border-radius: 12px;
                        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                        max-width: 400px;
                        margin: 0 20px;
                    }
                    .icon {
                        font-size: 4rem;
                        margin-bottom: 1rem;
                        color: #6b7280;
                    }
                    h1 {
                        margin-bottom: 0.5rem;
                        color: #111827;
                    }
                    p {
                        margin-bottom: 1.5rem;
                        color: #6b7280;
                    }
                    button {
                        background: #2563eb;
                        color: white;
                        border: none;
                        padding: 0.75rem 1.5rem;
                        border-radius: 8px;
                        cursor: pointer;
                        font-weight: 600;
                    }
                    button:hover {
                        background: #1d4ed8;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="icon">📡</div>
                    <h1>Offline</h1>
                    <p>Non è possibile connettersi al server BAIT Service. Controlla la connessione internet e riprova.</p>
                    <button onclick="window.location.reload()">Riprova</button>
                </div>
            </body>
            </html>
        `, {
            headers: {
                'Content-Type': 'text/html',
                'sw-offline-page': 'true'
            }
        });
    }
}

// Listen for messages from main thread
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'CLEAR_CACHE') {
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => caches.delete(cacheName))
            );
        }).then(() => {
            event.ports[0].postMessage({ success: true });
        });
    }
});

// Background sync for offline actions
self.addEventListener('sync', event => {
    console.log('[SW] Background sync:', event.tag);
    
    if (event.tag === 'upload-csv') {
        event.waitUntil(syncUploadQueue());
    }
    
    if (event.tag === 'process-data') {
        event.waitUntil(syncProcessQueue());
    }
});

async function syncUploadQueue() {
    // Get queued uploads from IndexedDB and process them
    // This would be implemented based on specific upload queue logic
    console.log('[SW] Processing upload queue...');
}

async function syncProcessQueue() {
    // Get queued processing tasks and execute them
    console.log('[SW] Processing data queue...');
}

// Push notification handler
self.addEventListener('push', event => {
    console.log('[SW] Push received:', event);
    
    const options = {
        body: 'BAIT Service has new critical alerts',
        icon: '/icons/icon-192.png',
        badge: '/icons/badge-72.png',
        tag: 'bait-alert',
        renotify: true,
        actions: [
            {
                action: 'view',
                title: 'View Dashboard'
            },
            {
                action: 'dismiss',
                title: 'Dismiss'
            }
        ],
        data: {
            url: '/dashboard'
        }
    };
    
    event.waitUntil(
        self.registration.showNotification('BAIT Service Alert', options)
    );
});

// Notification click handler
self.addEventListener('notificationclick', event => {
    console.log('[SW] Notification click:', event);
    
    event.notification.close();
    
    if (event.action === 'view') {
        event.waitUntil(
            clients.openWindow(event.notification.data.url || '/dashboard')
        );
    }
});

console.log('[SW] Service Worker loaded and ready');