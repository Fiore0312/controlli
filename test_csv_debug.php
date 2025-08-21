<?php
/**
 * Test dei percorsi CSV per debugging
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Test Percorsi CSV</h1>";

// Test TeamViewer files
$csvBaitPath = __DIR__ . '/data/input/teamviewer_bait.csv';
$csvGruppoPath = __DIR__ . '/data/input/teamviewer_gruppo.csv';
$calendarioPath = __DIR__ . '/data/input/calendario.csv';

echo "<h2>TeamViewer Files</h2>";
echo "Path BAIT: " . $csvBaitPath . "<br>";
echo "Exists: " . (file_exists($csvBaitPath) ? "✅ YES" : "❌ NO") . "<br>";
if (file_exists($csvBaitPath)) {
    echo "Size: " . filesize($csvBaitPath) . " bytes<br>";
    echo "Modified: " . date('Y-m-d H:i:s', filemtime($csvBaitPath)) . "<br>";
}

echo "<br>Path GRUPPO: " . $csvGruppoPath . "<br>";
echo "Exists: " . (file_exists($csvGruppoPath) ? "✅ YES" : "❌ NO") . "<br>";
if (file_exists($csvGruppoPath)) {
    echo "Size: " . filesize($csvGruppoPath) . " bytes<br>";
    echo "Modified: " . date('Y-m-d H:i:s', filemtime($csvGruppoPath)) . "<br>";
}

echo "<h2>Calendario File</h2>";
echo "Path: " . $calendarioPath . "<br>";
echo "Exists: " . (file_exists($calendarioPath) ? "✅ YES" : "❌ NO") . "<br>";
if (file_exists($calendarioPath)) {
    echo "Size: " . filesize($calendarioPath) . " bytes<br>";
    echo "Modified: " . date('Y-m-d H:i:s', filemtime($calendarioPath)) . "<br>";
}

echo "<h2>Directory Permissions</h2>";
$inputDir = __DIR__ . '/data/input/';
echo "Input Dir: " . $inputDir . "<br>";
echo "Readable: " . (is_readable($inputDir) ? "✅ YES" : "❌ NO") . "<br>";
echo "Writable: " . (is_writable($inputDir) ? "✅ YES" : "❌ NO") . "<br>";

echo "<h2>All CSV Files in Input</h2>";
$csvFiles = glob($inputDir . '*.csv');
foreach ($csvFiles as $file) {
    $basename = basename($file);
    $size = round(filesize($file) / 1024, 1) . ' KB';
    echo "📄 $basename - $size<br>";
}
?>