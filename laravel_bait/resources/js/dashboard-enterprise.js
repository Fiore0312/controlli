/**
 * BAIT Service Enterprise Dashboard JavaScript
 * ===========================================
 * Modern Alpine.js + Vanilla JS per dashboard enterprise
 * Charts, interattività, PWA, performance ottimizzate
 */

// Service Worker Registration per PWA
if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => console.log('SW registered:', registration))
            .catch(error => console.log('SW registration failed:', error));
    });
}

// Performance Monitoring
class PerformanceMonitor {
    constructor() {
        this.startTime = performance.now();
        this.metrics = {};
    }

    mark(name) {
        this.metrics[name] = performance.now() - this.startTime;
    }

    getMetrics() {
        return this.metrics;
    }

    logMetrics() {
        console.table(this.metrics);
    }
}

const perfMonitor = new PerformanceMonitor();

// Chart Utilities
class ChartManager {
    constructor() {
        this.charts = new Map();
        this.defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            family: 'Inter',
                            size: 12
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#2563eb',
                    borderWidth: 1,
                    cornerRadius: 8,
                    titleFont: {
                        family: 'Inter',
                        weight: 'bold'
                    },
                    bodyFont: {
                        family: 'Inter'
                    }
                }
            },
            animation: {
                duration: 750,
                easing: 'easeOutQuart'
            }
        };
    }

    createPieChart(canvasId, data, labels, colors = null) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;

        const defaultColors = [
            '#dc2626', // CRITICO - Red
            '#d97706', // ALTO - Orange
            '#3b82f6', // MEDIO - Blue
            '#059669', // SUCCESS - Green
            '#6b7280'  // SECONDARY - Gray
        ];

        const chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors || defaultColors,
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverBorderWidth: 4,
                    hoverBorderColor: '#ffffff'
                }]
            },
            options: {
                ...this.defaultOptions,
                plugins: {
                    ...this.defaultOptions.plugins,
                    legend: {
                        ...this.defaultOptions.plugins.legend,
                        position: 'right'
                    }
                },
                cutout: '60%',
                elements: {
                    arc: {
                        borderJoinStyle: 'round'
                    }
                }
            }
        });

        this.charts.set(canvasId, chart);
        return chart;
    }

    createBarChart(canvasId, data, labels, options = {}) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;

        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: options.label || 'Values',
                    data: data,
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false,
                    hoverBackgroundColor: 'rgba(37, 99, 235, 0.2)',
                    hoverBorderColor: 'rgba(37, 99, 235, 1)',
                    hoverBorderWidth: 3
                }]
            },
            options: {
                ...this.defaultOptions,
                plugins: {
                    ...this.defaultOptions.plugins,
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: {
                                family: 'Inter'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                family: 'Inter'
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        this.charts.set(canvasId, chart);
        return chart;
    }

    destroyChart(canvasId) {
        const chart = this.charts.get(canvasId);
        if (chart) {
            chart.destroy();
            this.charts.delete(canvasId);
        }
    }

    destroyAll() {
        this.charts.forEach((chart, id) => {
            chart.destroy();
        });
        this.charts.clear();
    }
}

// Data Processing Utilities
class DataProcessor {
    static formatCurrency(amount, currency = 'EUR') {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }).format(amount);
    }

    static formatNumber(number, decimals = 0) {
        return new Intl.NumberFormat('it-IT', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    }

    static formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('it-IT') + ' ' + 
               date.toLocaleTimeString('it-IT', {
                   hour: '2-digit',
                   minute: '2-digit'
               });
    }

    static formatRelativeTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) return 'Ora';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} min fa`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} ore fa`;
        return `${Math.floor(diffInSeconds / 86400)} giorni fa`;
    }

    static aggregateSeverityData(alerts) {
        const counts = { CRITICO: 0, ALTO: 0, MEDIO: 0 };
        alerts.forEach(alert => {
            if (counts.hasOwnProperty(alert.severity)) {
                counts[alert.severity]++;
            }
        });
        return counts;
    }

    static aggregateTechnicianData(alerts) {
        const counts = {};
        alerts.forEach(alert => {
            counts[alert.tecnico] = (counts[alert.tecnico] || 0) + 1;
        });
        return counts;
    }

    static calculateKPIs(alerts) {
        const total = alerts.length;
        const critical = alerts.filter(a => a.severity === 'CRITICO').length;
        const totalCost = alerts.reduce((sum, a) => sum + (a.estimated_cost || 0), 0);
        
        return {
            total_alerts: total,
            critical_alerts: critical,
            critical_percentage: total > 0 ? (critical / total * 100) : 0,
            estimated_losses: totalCost,
            avg_confidence: total > 0 ? 
                alerts.reduce((sum, a) => sum + (a.confidence_score || 0), 0) / total : 0
        };
    }
}

// File Upload Handler
class FileUploadHandler {
    constructor() {
        this.allowedTypes = ['.csv', 'text/csv'];
        this.maxFileSize = 10 * 1024 * 1024; // 10MB
        this.requiredFiles = [
            'attivita.csv',
            'timbrature.csv', 
            'teamviewer_bait.csv',
            'auto.csv',
            'permessi.csv'
        ];
    }

    validateFile(file) {
        const errors = [];

        // Check file type
        if (!file.name.toLowerCase().endsWith('.csv')) {
            errors.push('File must be a CSV');
        }

        // Check file size
        if (file.size > this.maxFileSize) {
            errors.push('File too large (max 10MB)');
        }

        // Check if file is empty
        if (file.size === 0) {
            errors.push('File is empty');
        }

        return {
            valid: errors.length === 0,
            errors: errors
        };
    }

    validateFileSet(files) {
        const fileNames = Array.from(files).map(f => f.name.toLowerCase());
        const missingRequired = this.requiredFiles.filter(
            required => !fileNames.includes(required.toLowerCase())
        );

        return {
            complete: missingRequired.length === 0,
            missing: missingRequired,
            hasFiles: files.length > 0
        };
    }

    async processFiles(files, onProgress = null) {
        const results = [];
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const validation = this.validateFile(file);
            
            if (onProgress) {
                onProgress({
                    current: i + 1,
                    total: files.length,
                    fileName: file.name,
                    status: validation.valid ? 'processing' : 'error'
                });
            }

            if (validation.valid) {
                try {
                    const content = await this.readFileContent(file);
                    results.push({
                        fileName: file.name,
                        success: true,
                        content: content,
                        size: file.size
                    });
                } catch (error) {
                    results.push({
                        fileName: file.name,
                        success: false,
                        error: error.message
                    });
                }
            } else {
                results.push({
                    fileName: file.name,
                    success: false,
                    error: validation.errors.join(', ')
                });
            }
        }

        return results;
    }

    readFileContent(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            
            reader.onload = (e) => resolve(e.target.result);
            reader.onerror = () => reject(new Error('Failed to read file'));
            
            reader.readAsText(file, 'utf-8');
        });
    }
}

// Export Utilities
class ExportManager {
    static toCSV(data, filename = 'export.csv') {
        if (!data || data.length === 0) return;

        const headers = Object.keys(data[0]);
        const csvContent = [
            headers.join(','),
            ...data.map(row => 
                headers.map(field => {
                    let value = row[field] || '';
                    // Escape commas and quotes
                    if (typeof value === 'string' && (value.includes(',') || value.includes('"'))) {
                        value = `"${value.replace(/"/g, '""')}"`;
                    }
                    return value;
                }).join(',')
            )
        ].join('\n');

        this.downloadFile(csvContent, filename, 'text/csv');
    }

    static toJSON(data, filename = 'export.json') {
        const jsonContent = JSON.stringify(data, null, 2);
        this.downloadFile(jsonContent, filename, 'application/json');
    }

    static downloadFile(content, filename, mimeType) {
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        
        a.href = url;
        a.download = filename;
        a.style.display = 'none';
        
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        
        URL.revokeObjectURL(url);
    }
}

// Notification System
class NotificationManager {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        // Create notification container if it doesn't exist
        this.container = document.getElementById('notification-container');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'notification-container';
            this.container.className = 'position-fixed top-0 end-0 p-3';
            this.container.style.zIndex = '1080';
            document.body.appendChild(this.container);
        }
    }

    show(message, type = 'info', duration = 5000) {
        const id = 'notification-' + Date.now();
        const typeClasses = {
            success: 'bg-success text-white',
            error: 'bg-danger text-white',
            warning: 'bg-warning text-dark',
            info: 'bg-primary text-white'
        };

        const typeIcons = {
            success: 'bi-check-circle',
            error: 'bi-x-circle',
            warning: 'bi-exclamation-triangle',
            info: 'bi-info-circle'
        };

        const notification = document.createElement('div');
        notification.id = id;
        notification.className = `toast align-items-center border-0 ${typeClasses[type]}`;
        notification.setAttribute('role', 'alert');
        notification.innerHTML = `
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center">
                    <i class="bi ${typeIcons[type]} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;

        this.container.appendChild(notification);

        const toast = new bootstrap.Toast(notification, {
            delay: duration
        });
        
        toast.show();

        // Remove element after toast is hidden
        notification.addEventListener('hidden.bs.toast', () => {
            notification.remove();
        });

        return toast;
    }

    success(message, duration) {
        return this.show(message, 'success', duration);
    }

    error(message, duration) {
        return this.show(message, 'error', duration);
    }

    warning(message, duration) {
        return this.show(message, 'warning', duration);
    }

    info(message, duration) {
        return this.show(message, 'info', duration);
    }
}

// Cache Manager
class CacheManager {
    constructor() {
        this.prefix = 'bait_dashboard_';
        this.defaultTTL = 5 * 60 * 1000; // 5 minutes
    }

    set(key, data, ttl = this.defaultTTL) {
        const item = {
            data: data,
            timestamp: Date.now(),
            ttl: ttl
        };
        
        try {
            localStorage.setItem(this.prefix + key, JSON.stringify(item));
        } catch (e) {
            console.warn('LocalStorage not available:', e);
        }
    }

    get(key) {
        try {
            const item = localStorage.getItem(this.prefix + key);
            if (!item) return null;

            const parsed = JSON.parse(item);
            const age = Date.now() - parsed.timestamp;

            if (age > parsed.ttl) {
                this.remove(key);
                return null;
            }

            return parsed.data;
        } catch (e) {
            console.warn('Cache read error:', e);
            return null;
        }
    }

    remove(key) {
        try {
            localStorage.removeItem(this.prefix + key);
        } catch (e) {
            console.warn('Cache remove error:', e);
        }
    }

    clear() {
        try {
            Object.keys(localStorage).forEach(key => {
                if (key.startsWith(this.prefix)) {
                    localStorage.removeItem(key);
                }
            });
        } catch (e) {
            console.warn('Cache clear error:', e);
        }
    }
}

// Performance optimized debounce
function debounce(func, wait, immediate = false) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            timeout = null;
            if (!immediate) func(...args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func(...args);
    };
}

// Throttle function
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Initialize global utilities
window.chartManager = new ChartManager();
window.fileUploadHandler = new FileUploadHandler();
window.notificationManager = new NotificationManager();
window.cacheManager = new CacheManager();

// Global performance measurement
window.addEventListener('load', () => {
    perfMonitor.mark('page-loaded');
    
    // Measure Time to Interactive
    setTimeout(() => {
        perfMonitor.mark('interactive');
        perfMonitor.logMetrics();
    }, 100);
});

// Error boundary for unhandled errors
window.addEventListener('error', (event) => {
    console.error('Global error:', event.error);
    if (window.notificationManager) {
        window.notificationManager.error('Si è verificato un errore imprevisto');
    }
});

// Export utilities for external use
window.BAITDashboard = {
    ChartManager,
    DataProcessor,
    FileUploadHandler,
    ExportManager,
    NotificationManager,
    CacheManager,
    debounce,
    throttle,
    perfMonitor
};