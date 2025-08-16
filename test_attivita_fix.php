<?php
/**
 * TEST ATTIVITÀ DEEPSER - Verifica CSV parsing
 */
header('Content-Type: text/html; charset=utf-8');

// Funzione corretta per leggere CSV
function readCSVFile($filepath) {
    if (!file_exists($filepath)) {
        return ['headers' => [], 'data' => []];
    }
    
    $csvContent = file_get_contents($filepath);
    
    // Remove BOM if present
    $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);
    
    // Detect encoding and convert to UTF-8
    $encoding = mb_detect_encoding($csvContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $csvContent = mb_convert_encoding($csvContent, 'UTF-8', $encoding);
    }
    
    // Parse CSV using proper delimiter detection
    $lines = array_map('trim', explode("\n", $csvContent));
    $lines = array_filter($lines, function($line) { return !empty($line); });
    
    if (empty($lines)) return ['headers' => [], 'data' => []];
    
    // Parse header line - try comma first, then semicolon
    $headerLine = array_shift($lines);
    $delimiter = ',';
    if (substr_count($headerLine, ',') < substr_count($headerLine, ';')) {
        $delimiter = ';';
    }
    
    $headers = str_getcsv($headerLine, $delimiter);
    
    // Clean headers
    $headers = array_map(function($h) {
        return trim(str_replace(['"', "'"], '', $h));
    }, $headers);
    
    $data = [];
    foreach ($lines as $line) {
        if (trim($line) && !empty($line)) {
            $row = str_getcsv($line, $delimiter);
            
            // Clean row data
            $row = array_map(function($cell) {
                return trim(str_replace(['"', "'"], '', $cell));
            }, $row);
            
            // Pad array to match headers count
            while (count($row) < count($headers)) {
                $row[] = '';
            }
            
            // Trim to match headers count
            $row = array_slice($row, 0, count($headers));
            
            $data[] = $row;
        }
    }
    
    return ['headers' => $headers, 'data' => $data];
}

$csvPath = __DIR__ . '/data/input/attivita.csv';
$csvData = readCSVFile($csvPath);

echo "<h1>🔧 Test Parsing CSV Attività</h1>";
echo "<p><strong>File:</strong> $csvPath</p>";
echo "<p><strong>File exists:</strong> " . (file_exists($csvPath) ? 'Sì' : 'No') . "</p>";
echo "<p><strong>Headers trovati:</strong> " . count($csvData['headers']) . "</p>";
echo "<p><strong>Record trovati:</strong> " . count($csvData['data']) . "</p>";

echo "<h2>📋 Headers</h2>";
echo "<ol>";
foreach ($csvData['headers'] as $i => $header) {
    echo "<li><strong>$i:</strong> " . htmlspecialchars($header) . "</li>";
}
echo "</ol>";

echo "<h2>🔍 Primi 3 Record (Debug)</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<thead><tr>";
foreach ($csvData['headers'] as $header) {
    echo "<th style='padding: 8px; background: #f0f0f0;'>" . htmlspecialchars($header) . "</th>";
}
echo "</tr></thead>";
echo "<tbody>";

for ($i = 0; $i < min(3, count($csvData['data'])); $i++) {
    echo "<tr>";
    foreach ($csvData['data'][$i] as $cell) {
        $displayCell = htmlspecialchars(mb_substr($cell, 0, 50));
        if (mb_strlen($cell) > 50) $displayCell .= '...';
        echo "<td style='padding: 8px; border: 1px solid #ccc;'>$displayCell</td>";
    }
    echo "</tr>";
}

echo "</tbody></table>";

if (count($csvData['data']) > 0) {
    echo "<p style='color: green;'><strong>✅ CSV parsing riuscito!</strong></p>";
    echo "<p><a href='attivita_deepser.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔗 Vai alla pagina Attività Deepser</a></p>";
} else {
    echo "<p style='color: red;'><strong>❌ Nessun dato trovato nel CSV</strong></p>";
}
?>