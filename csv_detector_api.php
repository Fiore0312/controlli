<?php
/**
 * BAIT Service - CSV Detector API
 * API per testing e gestione del sistema di auto-detection CSV
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/CSVTypeDetector.php';

$action = $_GET['action'] ?? 'info';
$detector = new CSVTypeDetector();

try {
    switch ($action) {
        case 'supported_types':
            echo json_encode($detector->getSupportedTypes());
            break;
            
        case 'test_file':
            $file = $_GET['file'] ?? '';
            if (empty($file)) {
                throw new Exception('File parameter required');
            }
            
            $filePath = __DIR__ . '/upload_csv/' . $file;
            $result = $detector->detectType($filePath);
            echo json_encode($result);
            break;
            
        case 'test_existing':
            $uploadDir = __DIR__ . '/upload_csv/';
            $files = [];
            
            if (is_dir($uploadDir)) {
                $csvFiles = glob($uploadDir . '*.csv');
                
                foreach ($csvFiles as $filePath) {
                    $filename = basename($filePath);
                    $result = $detector->detectType($filePath);
                    
                    $files[] = [
                        'filename' => $filename,
                        'file_path' => $filePath,
                        'success' => $result['success'],
                        'detected_type' => $result['detected_type'] ?? null,
                        'confidence' => $result['confidence'] ?? 0,
                        'description' => $result['description'] ?? null,
                        'error' => $result['error'] ?? null,
                        'encoding' => $result['encoding'] ?? null,
                        'header_count' => count($result['header'] ?? [])
                    ];
                }
            }
            
            echo json_encode($files);
            break;
            
        case 'analyze':
            $file = $_POST['file'] ?? '';
            if (empty($file)) {
                throw new Exception('File data required');
            }
            
            // Salva file temporaneo per analisi
            $tempFile = tempnam(sys_get_temp_dir(), 'csv_analysis_');
            file_put_contents($tempFile, $file);
            
            $result = $detector->detectType($tempFile);
            unlink($tempFile); // Pulisci file temporaneo
            
            echo json_encode($result);
            break;
            
        case 'demo':
            // Crea file CSV demo per testing
            createDemoFiles();
            echo json_encode(['success' => true, 'message' => 'Demo files created']);
            break;
            
        case 'info':
        default:
            echo json_encode([
                'service' => 'BAIT CSV Auto-Detection API',
                'version' => '2.0',
                'endpoints' => [
                    'supported_types' => 'Get all supported CSV types',
                    'test_file' => 'Test detection on specific file (?file=name.csv)',
                    'test_existing' => 'Test all files in upload_csv/',
                    'analyze' => 'Analyze uploaded file content (POST)',
                    'demo' => 'Create demo CSV files for testing'
                ]
            ]);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage(),
        'action' => $action
    ]);
}

/**
 * Crea file CSV demo per testing
 */
function createDemoFiles() {
    $demoDir = __DIR__ . '/upload_csv/demo/';
    if (!is_dir($demoDir)) {
        mkdir($demoDir, 0755, true);
    }
    
    // File attività con nome personalizzato
    $attivitaContent = "ID Ticket,Creato da,Tipologia attività,Durata,Descrizione\n";
    $attivitaContent .= "BAIT001,Alex Ferrario,Assistenza Remota,120,Configurazione server\n";
    $attivitaContent .= "BAIT002,Davide Cestone,Assistenza On-Site,90,Installazione software\n";
    file_put_contents($demoDir . 'rapportini_agosto_2025.csv', $attivitaContent);
    
    // File timbrature con nome personalizzato
    $timbratureContent = "Dipendente,Data,Ora ingresso,Ora uscita,Pause\n";
    $timbratureContent .= "Alex Ferrario,2025-08-19,09:00,18:00,60\n";
    $timbratureContent .= "Davide Cestone,2025-08-19,08:30,17:30,60\n";
    file_put_contents($demoDir . 'presenze_team_bait.csv', $timbratureContent);
    
    // File auto con nome personalizzato
    $autoContent = "Tecnico,Data utilizzo,Veicolo,Destinazione,Km\n";
    $autoContent .= "Alex Ferrario,2025-08-19,Ford Transit,Milano Centro,45\n";
    $autoContent .= "Davide Cestone,2025-08-19,Fiat Ducato,Settala,25\n";
    file_put_contents($demoDir . 'registro_mezzi_agosto.csv', $autoContent);
    
    // File TeamViewer con nome personalizzato
    $tvContent = "Session ID,Tecnico,Cliente,Durata,Start time\n";
    $tvContent .= "TV123456,Alex Ferrario,Cliente ABC,1800,2025-08-19 09:00\n";
    $tvContent .= "TV789012,Davide Cestone,Cliente XYZ,2400,2025-08-19 14:00\n";
    file_put_contents($demoDir . 'sessioni_remote_agosto.csv', $tvContent);
    
    // File con contenuto ambiguo (test edge case)
    $ambiguousContent = "Nome,Data,Valore,Note\n";
    $ambiguousContent .= "Test 1,2025-08-19,100,Prova\n";
    $ambiguousContent .= "Test 2,2025-08-20,200,Prova 2\n";
    file_put_contents($demoDir . 'file_ambiguo.csv', $ambiguousContent);
}
?>