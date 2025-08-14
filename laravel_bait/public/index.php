<?php
/**
 * BAIT Service Enterprise Dashboard
 * 
 * Fallback to standalone PHP if Laravel not available
 */

// Try Laravel first
if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    require __DIR__.'/../vendor/autoload.php';

    define('LARAVEL_START', microtime(true));

    if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
        require $maintenance;
    }

    try {
        $app = require_once __DIR__.'/../bootstrap/app.php';
        $kernel = $app->make('Illuminate\Contracts\Http\Kernel');
        $response = $kernel->handle($request = \Illuminate\Http\Request::capture())->send();
        $kernel->terminate($request, $response);
    } catch (Exception $e) {
        // Laravel failed, fallback to standalone
        require __DIR__.'/index_standalone.php';
    }
} else {
    // Laravel not available, use standalone
    require __DIR__.'/index_standalone.php';
}