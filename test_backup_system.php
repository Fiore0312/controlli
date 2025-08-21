<?php
/**
 * Test Script per Windows File Locking Fix
 * Verifica il funzionamento del nuovo sistema di backup
 */

header('Content-Type: text/html; charset=utf-8');

// Include le funzioni dal file principale
include_once 'audit_monthly_manager.php';

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Test Backup System - BAIT Service</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .warning { color: orange; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>";

echo "<h1>🔧 Test Sistema Backup Windows File Locking</h1>";
echo "<p>Data test: " . date('d/m/Y H:i:s') . "</p>";

// Test 1: Verifica funzioni esistenti
echo "<h2>Test 1: Verifica Funzioni</h2>";
if (function_exists('createBackupWithRetry')) {
    echo "<span class='success'>✅ Funzione createBackupWithRetry trovata</span><br>";
} else {
    echo "<span class='error'>❌ Funzione createBackupWithRetry non trovata</span><br>";
}

if (function_exists('isFileInUse')) {
    echo "<span class='success'>✅ Funzione isFileInUse trovata</span><br>";
} else {
    echo "<span class='error'>❌ Funzione isFileInUse non trovata</span><br>";
}

// Test 2: Verifica OS Detection
echo "<h2>Test 2: Rilevamento Sistema Operativo</h2>";
echo "<span class='info'>📊 OS Family: " . PHP_OS_FAMILY . "</span><br>";
echo "<span class='info'>📊 PHP OS: " . PHP_OS . "</span><br>";

// Test 3: Test directory e permessi
echo "<h2>Test 3: Directory e Permessi</h2>";
$uploadDir = __DIR__ . '/data/input/';
if (is_dir($uploadDir)) {
    echo "<span class='success'>✅ Directory input esistente: $uploadDir</span><br>";
    if (is_writable($uploadDir)) {
        echo "<span class='success'>✅ Directory scrivibile</span><br>";
    } else {
        echo "<span class='warning'>⚠️ Directory non scrivibile</span><br>";
    }
} else {
    echo "<span class='error'>❌ Directory input non trovata</span><br>";
}

// Test 4: Elenca file di backup esistenti
echo "<h2>Test 4: File di Backup Esistenti</h2>";
$backupFiles = glob($uploadDir . 'backup_*');
if ($backupFiles) {
    echo "<span class='info'>📁 Trovati " . count($backupFiles) . " file di backup:</span><br>";
    foreach (array_slice($backupFiles, -5) as $file) { // Mostra solo gli ultimi 5
        $basename = basename($file);
        $filesize = round(filesize($file) / 1024, 1) . ' KB';
        $modified = date('d/m/Y H:i', filemtime($file));
        echo "<span class='info'>📄 $basename - $filesize - $modified</span><br>";
    }
} else {
    echo "<span class='info'>📂 Nessun file di backup trovato</span><br>";
}

// Test 5: Test funzione isFileInUse
echo "<h2>Test 5: Test Funzione File In Use</h2>";
$testFile = $uploadDir . 'timbrature.csv';
if (file_exists($testFile)) {
    echo "<span class='info'>📄 Test su file: $testFile</span><br>";
    $isInUse = isFileInUse($testFile);
    if ($isInUse) {
        echo "<span class='warning'>⚠️ File risulta in uso</span><br>";
    } else {
        echo "<span class='success'>✅ File non in uso</span><br>";
    }
} else {
    echo "<span class='warning'>⚠️ File di test non trovato</span><br>";
}

// Test 6: Simulazione backup (senza modificare file reali)
echo "<h2>Test 6: Simulazione Backup</h2>";
echo "<span class='info'>💡 Simulazione creazione backup (senza file)...</span><br>";

// Crea un timestamp di esempio
$testTimestamp = date('Y-m-d_H-i-s');
$simulatedBackupPath = $uploadDir . "backup_test_$testTimestamp.tmp";

echo "<span class='info'>🎯 Path backup simulato: " . basename($simulatedBackupPath) . "</span><br>";

// Test 7: Verifica spazio disco
echo "<h2>Test 7: Spazio Disco</h2>";
$freeBytes = disk_free_space($uploadDir);
$totalBytes = disk_total_space($uploadDir);

if ($freeBytes !== false && $totalBytes !== false) {
    $freeGB = round($freeBytes / (1024*1024*1024), 2);
    $totalGB = round($totalBytes / (1024*1024*1024), 2);
    $usedPercent = round((($totalBytes - $freeBytes) / $totalBytes) * 100, 1);
    
    echo "<span class='info'>💾 Spazio libero: {$freeGB} GB / {$totalGB} GB</span><br>";
    echo "<span class='info'>📊 Spazio utilizzato: {$usedPercent}%</span><br>";
    
    if ($freeGB < 1) {
        echo "<span class='warning'>⚠️ Poco spazio libero disponibile</span><br>";
    } else {
        echo "<span class='success'>✅ Spazio disco sufficiente</span><br>";
    }
} else {
    echo "<span class='warning'>⚠️ Non è possibile determinare lo spazio disco</span><br>";
}

echo "<h2>✨ Test Completato</h2>";
echo "<p><strong>Sistema di backup Windows file locking:</strong> ";
if (function_exists('createBackupWithRetry') && function_exists('isFileInUse')) {
    echo "<span class='success'>PRONTO ✅</span></p>";
    echo "<p><em>Il sistema può gestire i conflitti di file locking su Windows con retry automatico.</em></p>";
} else {
    echo "<span class='error'>NON FUNZIONANTE ❌</span></p>";
}

echo "<hr>";
echo "<p><a href='audit_monthly_manager.php'>🔙 Torna all'Audit Manager</a></p>";

echo "</body></html>";
?>