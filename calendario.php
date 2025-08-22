<?php
/**
 * CALENDARIO - Visualizzazione calendario.csv
 * Gestione calendario appuntamenti e pianificazione
 */

header('Content-Type: text/html; charset=utf-8');

// Parser per file .ics (iCalendar format)
function parseIcsFile($icsContent) {
    $events = [];
    $currentEvent = [];
    $inEvent = false;
    
    // Split per righe
    $lines = explode("\n", $icsContent);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if ($line === "BEGIN:VEVENT") {
            $inEvent = true;
            $currentEvent = [];
            continue;
        }
        
        if ($line === "END:VEVENT") {
            $inEvent = false;
            if (!empty($currentEvent)) {
                $events[] = $currentEvent;
            }
            continue;
        }
        
        if ($inEvent && !empty($line)) {
            // Parse proprietà ICS con gestione completa parametri
            if (strpos($line, ":") !== false) {
                list($propertyPart, $value) = explode(":", $line, 2);
                
                // Gestisci proprietà con parametri (es. DTSTART;VALUE=DATE:20250818)
                $property = $propertyPart;
                if (strpos($propertyPart, ";") !== false) {
                    $parts = explode(";", $propertyPart);
                    $property = $parts[0];
                    
                    // Conserva parametri per debug se necessario
                    $parameters = array_slice($parts, 1);
                    $currentEvent[$property . '_PARAMS'] = $parameters;
                }
                
                $currentEvent[$property] = $value;
            }
        }
    }
    
    return $events;
}

// Converte data ICS in formato leggibile - VERSIONE CORRETTA
function parseIcsDateTime($icsDateTime) {
    // Rimuovi eventuali parametri dalla data (es. TZID=)
    $cleanDateTime = $icsDateTime;
    if (strpos($icsDateTime, ':') !== false) {
        $parts = explode(':', $icsDateTime);
        $cleanDateTime = end($parts);
    }
    
    $cleanDateTime = trim($cleanDateTime);
    
    // Pattern per data ISO (con o senza time) - VERSIONE ROBUSTA
    if (preg_match('/(\d{4})(\d{2})(\d{2})(?:T(\d{2})(\d{2})(\d{2}))?/', $cleanDateTime, $matches)) {
        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $day = (int)$matches[3];
        $hour = isset($matches[4]) ? (int)$matches[4] : 0;
        $minute = isset($matches[5]) ? (int)$matches[5] : 0;
        $second = isset($matches[6]) ? (int)$matches[6] : 0;
        
        // VALIDAZIONE DATE - Previene timestamp = 0 (01/01/1970)
        if ($year >= 2020 && $year <= 2030 && $month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
            $timestamp = mktime($hour, $minute, $second, $month, $day, $year);
            
            return [
                "timestamp" => $timestamp,
                "formatted" => sprintf("%02d/%02d/%04d %02d:%02d", $day, $month, $year, $hour, $minute),
                "date" => sprintf("%02d/%02d/%04d", $day, $month, $year),
                "time" => sprintf("%02d:%02d", $hour, $minute),
                "is_all_day" => ($hour == 0 && $minute == 0 && !isset($matches[4]))
            ];
        }
    }
    
    return null;
}

// Converte eventi ICS in formato calendario standard
function convertIcsToCalendarFormat($icsEvents) {
    $headers = ["SUMMARY", "DTSTART", "DTEND", "NOTES", "ATTENDEE", "LOCATION"];
    $data = [];
    
    foreach ($icsEvents as $event) {
        $summary = $event["SUMMARY"] ?? "";
        $organizer = $event["ORGANIZER"] ?? "";
        $location = $event["LOCATION"] ?? "";
        $description = $event["DESCRIPTION"] ?? "";
        
        // Parse date
        $startParsed = parseIcsDateTime($event["DTSTART"] ?? "");
        $endParsed = parseIcsDateTime($event["DTEND"] ?? "");
        
        $dtstart = $startParsed ? $startParsed["formatted"] : "";
        $dtend = $endParsed ? $endParsed["formatted"] : "";
        
        // Estrai tecnico dall'organizer o dal summary
        $technician = "";
        if (!empty($organizer)) {
            $technician = $organizer;
        } else {
            $technicians = ["Davide Cestone", "Arlind Hoxha", "Gabriele De Palma", "Matteo Signo", "Marco Birocchi", "Alex Ferrario", "Franco Fiorellino", "Veronica Totta"];
            foreach ($technicians as $tech) {
                if (stripos($summary, $tech) !== false) {
                    $technician = $tech;
                    break;
                }
            }
        }
        
        // Note intelligenti
        $notes = "";
        if (!empty($description)) {
            $notes = $description;
        } elseif (stripos($summary, "ferie") !== false) {
            $notes = "Ferie";
        } elseif (preg_match('/TICKET#?(\d+)/i', $summary, $ticketMatch)) {
            $notes = "Ticket " . $ticketMatch[1];
        } elseif (stripos($summary, "remoto") !== false) {
            $notes = "Lavoro Remoto";
        }
        
        // BUGFIX: Se il parsing fallisce, usa date placeholder
        if (!$startParsed || $startParsed["timestamp"] <= 0) {
            $dtstart = "Data non valida";
        }
        if (!$endParsed || $endParsed["timestamp"] <= 0) {
            $dtend = "Data non valida";
        }
        
        $data[] = [
            $summary,     // SUMMARY
            $dtstart,     // DTSTART
            $dtend,       // DTEND
            $notes,       // NOTES
            $technician,  // ATTENDEE
            $location,    // LOCATION
            "start_parsed" => $startParsed ? $startParsed["timestamp"] : null,
            "end_parsed" => $endParsed ? $endParsed["timestamp"] : null
        ];
    }
    
    return ["headers" => $headers, "data" => $data, "format" => "ics_parsed"];
}

// Funzione specializzata per leggere formato Outlook Calendar CSV e ICS
function readOutlookCalendarCSV($filepath) {
    if (!file_exists($filepath)) {
        return ['headers' => [], 'data' => [], 'format' => 'not_found'];
    }
    
    $content = file_get_contents($filepath);
    
    // Detect encoding and convert to UTF-8
    $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }
    
    // Rimuovi BOM se presente
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    
    // Controlla se è un file .ics
    if (stripos($content, 'BEGIN:VCALENDAR') !== false && stripos($content, 'BEGIN:VEVENT') !== false) {
        // È un file .ics - usa parser ICS
        $icsEvents = parseIcsFile($content);
        return convertIcsToCalendarFormat($icsEvents);
    }
    
    // È un file CSV - continua con logica esistente
    $lines = explode("\n", trim($content));
    $lineCount = count($lines);
    
    if ($lineCount <= 2 && strlen($content) > 1000) {
        // Formato Outlook concatenato - tutto su una riga
        return parseOutlookConcatenatedFormat($content);
    }
    
    // Formato CSV standard
    $headers = str_getcsv(array_shift($lines), ',');
    $data = [];
    
    foreach ($lines as $line) {
        if (trim($line) && !strpos($line, 'Somma di Ore') && !strpos($line, 'Etichette')) {
            $row = str_getcsv($line, ',');
            if (count($row) >= 3 && !empty($row[0])) {
                while (count($row) < count($headers)) {
                    $row[] = '';
                }
                $data[] = array_slice($row, 0, count($headers));
            }
        }
    }
    
    return ['headers' => $headers, 'data' => $data, 'format' => 'standard'];
}

// Parser specializzato per formato Outlook concatenato - FIXED VERSION
function parseOutlookConcatenatedFormat($csvContent) {
    $headers = ['SUMMARY', 'DTSTART', 'DTEND', 'NOTES', 'ATTENDEE', 'LOCATION'];
    $data = [];
    
    // Parser date europeo corretto
    function parseEuropeanDateTime($dateStr) {
        if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4}) (\d{1,2}):(\d{2})/', $dateStr, $matches)) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];
            $hour = (int)$matches[4];
            $minute = (int)$matches[5];
            
            return mktime($hour, $minute, 0, $month, $day, $year);
        }
        return false;
    }
    
    // Pulisce titoli lunghi
    function cleanEventTitle($title) {
        $title = preg_replace('/https?:\/\/[^\s"]+/', '', $title);
        $title = preg_replace('/\s+/', ' ', $title);
        $title = preg_replace('/_+/', ' ', $title);
        if (strlen($title) > 80) {
            $title = substr($title, 0, 80) . '...';
        }
        return trim($title);
    }
    
    // Rimuovi header dalla content
    $content = preg_replace('/^SUMMARY,DTSTART,DTEND,.*?"/', '', $csvContent);
    
    // Pattern migliorato per catturare anche i campi aggiuntivi
    $pattern = '/"([^"]+)","(\d{1,2}\/\d{1,2}\/\d{4} \d{1,2}:\d{2})","(\d{1,2}\/\d{1,2}\/\d{4} \d{1,2}:\d{2})","?([^"]*?)"?,"?([^"]*?)"?,"?([^"]*?)"?/';
    
    preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
    
    // Fallback con pattern più semplice se necessario
    if (empty($matches)) {
        $simplePattern = '/"([^"]+)","(\d{1,2}\/\d{1,2}\/\d{4} \d{1,2}:\d{2})","(\d{1,2}\/\d{1,2}\/\d{4} \d{1,2}:\d{2})"/';
        preg_match_all($simplePattern, $content, $matches, PREG_SET_ORDER);
    }
    
    foreach ($matches as $match) {
        if (count($match) >= 4) {
            $rawSummary = $match[1];
            $rawDtstart = $match[2];
            $rawDtend = $match[3];
            $rawNotes = $match[4] ?? '';
            $rawAttendee = $match[5] ?? '';
            $rawLocation = $match[6] ?? '';
            
            // Parse date con parser europeo
            $startTime = parseEuropeanDateTime($rawDtstart);
            $endTime = parseEuropeanDateTime($rawDtend);
            
            // Pulizia summary
            $summary = cleanEventTitle($rawSummary);
            
            // Estrai tecnico
            $technician = '';
            if (!empty($rawAttendee) && $rawAttendee !== 'TRUE' && strlen($rawAttendee) < 50) {
                $technician = $rawAttendee;
            } else {
                $technicians = ['Davide Cestone', 'Arlind Hoxha', 'Gabriele De Palma', 'Matteo Signo', 'Marco Birocchi', 'Alex Ferrario', 'Franco Fiorellino', 'Veronica Totta'];
                foreach ($technicians as $tech) {
                    if (stripos($summary, $tech) !== false) {
                        $technician = $tech;
                        break;
                    }
                }
                // Fallback pattern
                if (empty($technician) && preg_match('/- ([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s*$/', $summary, $techMatch)) {
                    $technician = trim($techMatch[1]);
                }
            }
            
            // Location intelligente
            $location = '';
            if (!empty($rawLocation) && $rawLocation !== 'TRUE') {
                $location = $rawLocation;
            } elseif (stripos($summary, 'REMOTO') !== false) {
                $location = 'Remoto';
            } elseif (preg_match('/(INDITEX|BAIT|Comune|Lentate|Melzo|Milano|Spolidoro)/i', $summary, $locMatch)) {
                $location = trim($locMatch[1]);
            }
            
            // Note intelligenti
            $notes = '';
            if (!empty($rawNotes)) {
                $notes = $rawNotes;
            } elseif (stripos($summary, 'ferie') !== false) {
                $notes = 'Ferie';
            } elseif (preg_match('/TICKET#?(\d+)/i', $summary, $ticketMatch)) {
                $notes = 'Ticket ' . $ticketMatch[1];
            } elseif (stripos($summary, 'remoto') !== false) {
                $notes = 'Lavoro Remoto';
            } elseif (stripos($summary, 'manutenzione') !== false) {
                $notes = 'Manutenzione';
            }
            
            $event = [
                $summary,     // SUMMARY
                $rawDtstart,  // DTSTART  
                $rawDtend,    // DTEND
                $notes,       // NOTES
                $technician,  // ATTENDEE
                $location,    // LOCATION
                'start_parsed' => $startTime,
                'end_parsed' => $endTime
            ];
            $data[] = $event;
        }
    }
    
    return ['headers' => $headers, 'data' => $data, 'format' => 'outlook_concatenated_fixed', 'events_found' => count($data)];
}

$csvPath = __DIR__ . '/data/input/calendario.csv';
$hasCSV = file_exists($csvPath);

// Debug info per troubleshooting
$debugInfo = [
    'file_path' => $csvPath,
    'file_exists' => $hasCSV,
    'input_dir_exists' => is_dir(__DIR__ . '/data/input/'),
    'input_dir_readable' => is_readable(__DIR__ . '/data/input/')
];
$csvData = $hasCSV ? readOutlookCalendarCSV($csvPath) : ['headers' => [], 'data' => []];

// Funzione robusta per parsare Outlook calendar - gestisce formati instabili
function parseOutlookCalendar($rawData) {
    // Controlla se il file ha un formato parsabile
    if (empty($rawData['data']) || count($rawData['data']) === 0) {
        return [
            'events' => [],
            'total_hours' => 0,
            'employees' => [],
            'clients' => [],
            'locations' => [],
            'parsing_status' => 'empty'
        ];
    }
    
    // Se il formato è ICS parsato, processa direttamente
    if (isset($rawData['format']) && $rawData['format'] === 'ics_parsed') {
        $parsedData = [];
        $totalHours = 0;
        $employees = [];
        $clients = [];
        $locations = [];
        
        foreach ($rawData['data'] as $row) {
            if (count($row) >= 6) {
                $summary = $row[0];     // SUMMARY
                $dtstart = $row[1];     // DTSTART
                $dtend = $row[2];       // DTEND
                $notes = $row[3];       // NOTES
                $attendee = $row[4];    // ATTENDEE
                $location = $row[5];    // LOCATION
                
                // Skip header row
                if ($summary === 'SUMMARY' || trim($summary) === '') {
                    continue;
                }
                
                // Calcola ore usando timestamp ICS precisi
                $ore = 0;
                if (isset($row['start_parsed']) && isset($row['end_parsed']) && $row['start_parsed'] && $row['end_parsed']) {
                    $start = $row['start_parsed'];
                    $end = $row['end_parsed'];
                    
                    if ($start && $end && $end > $start) {
                        $hoursDiff = ($end - $start) / 3600;
                        
                        // Per eventi all-day (00:00 - 00:00), converti in giorni
                        $startHour = date('H:i', $start);
                        $endHour = date('H:i', $end);
                        
                        if ($startHour === '00:00' && $endHour === '00:00') {
                            $ore = ($end - $start) / (24 * 3600); // Giorni
                        } else {
                            $ore = $hoursDiff;
                        }
                    }
                }
                
                // Estrai cliente dal summary
                $cliente = $summary;
                if (preg_match('/^([^-]+)/', $summary, $matches)) {
                    $cliente = trim($matches[1]);
                }
                
                if (!empty($attendee)) $employees[$attendee] = true;
                if (!empty($cliente)) $clients[$cliente] = true;
                if (!empty($location)) $locations[$location] = true;
                $totalHours += $ore;
                
                $parsedData[] = [
                    'dipendente' => $attendee,
                    'cliente' => $cliente,
                    'dove' => $location,
                    'data_inizio' => $dtstart,
                    'data_fine' => $dtend,
                    'ore_totali' => $ore,
                    'note' => $notes
                ];
            }
        }
        
        return [
            'events' => $parsedData,
            'total_hours' => $totalHours,
            'employees' => $employees,
            'clients' => $clients,
            'locations' => $locations,
            'parsing_status' => 'ics_parsed_success',
            'events_parsed' => count($parsedData)
        ];
    }
    
    // Se il formato è outlook_concatenated, è già stato parsato correttamente
    if (isset($rawData['format']) && $rawData['format'] === 'outlook_concatenated') {
        // Processa i dati già parsati dal formato concatenato
        $parsedData = [];
        $totalHours = 0;
        $employees = [];
        $clients = [];
        $locations = [];
        
        foreach ($rawData['data'] as $row) {
            if (count($row) >= 6) {
                $summary = $row[0];     // SUMMARY
                $dtstart = $row[1];     // DTSTART
                $dtend = $row[2];       // DTEND
                $notes = $row[3];       // NOTES
                $attendee = $row[4];    // ATTENDEE
                $location = $row[5];    // LOCATION
                
                // Skip header row
                if ($summary === 'SUMMARY' || trim($summary) === '') {
                    continue;
                }
                
                // Calcola ore con parser europeo migliorato
                $ore = 0;
                if (!empty($dtstart) && !empty($dtend)) {
                    try {
                        // Prima prova con timestamp parsati se disponibili
                        if (isset($row['start_parsed']) && isset($row['end_parsed']) && $row['start_parsed'] && $row['end_parsed']) {
                            $start = $row['start_parsed'];
                            $end = $row['end_parsed'];
                        } else {
                            // Fallback con parser date europeo
                            if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4}) (\d{1,2}):(\d{2})/', $dtstart, $matches)) {
                                $start = mktime((int)$matches[4], (int)$matches[5], 0, (int)$matches[2], (int)$matches[1], (int)$matches[3]);
                            } else {
                                $start = strtotime($dtstart);
                            }
                            
                            if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4}) (\d{1,2}):(\d{2})/', $dtend, $matches)) {
                                $end = mktime((int)$matches[4], (int)$matches[5], 0, (int)$matches[2], (int)$matches[1], (int)$matches[3]);
                            } else {
                                $end = strtotime($dtend);
                            }
                        }
                        
                        if ($start && $end && $end > $start) {
                            $hoursDiff = ($end - $start) / 3600;
                            
                            // Per eventi all-day (00:00 - 00:00), converti in giorni
                            $startHour = date('H:i', $start);
                            $endHour = date('H:i', $end);
                            
                            if ($startHour === '00:00' && $endHour === '00:00') {
                                $ore = ($end - $start) / (24 * 3600); // Giorni
                            } else {
                                $ore = $hoursDiff;
                            }
                        }
                    } catch (Exception $e) {
                        $ore = 0;
                    }
                }
                
                // Estrai cliente dal summary
                $cliente = $summary;
                if (preg_match('/^([^-]+)/', $summary, $matches)) {
                    $cliente = trim($matches[1]);
                }
                
                if (!empty($attendee)) $employees[$attendee] = true;
                if (!empty($cliente)) $clients[$cliente] = true;
                if (!empty($location)) $locations[$location] = true;
                $totalHours += $ore;
                
                $parsedData[] = [
                    'dipendente' => $attendee,
                    'cliente' => $cliente,
                    'dove' => $location,
                    'data_inizio' => $dtstart,
                    'data_fine' => $dtend,
                    'ore_totali' => $ore,
                    'note' => $notes
                ];
            }
        }
        
        return [
            'events' => $parsedData,
            'total_hours' => $totalHours,
            'employees' => $employees,
            'clients' => $clients,
            'locations' => $locations,
            'parsing_status' => 'outlook_concatenated_success',
            'events_parsed' => count($parsedData)
        ];
    }
    
    // Verifica se il formato sembra troppo complesso (fallback per formati non gestiti)
    $totalColumns = 0;
    foreach ($rawData['data'] as $row) {
        $totalColumns = max($totalColumns, count($row));
    }
    
    if ($totalColumns > 50) {
        return [
            'events' => [],
            'total_hours' => 0,
            'employees' => [],
            'clients' => [],
            'locations' => [],
            'parsing_status' => 'complex_outlook_format',
            'raw_data_available' => true,
            'total_columns' => $totalColumns,
            'suggestion' => 'Il file calendario.csv usa un formato Outlook specializzato. Per visualizzazione ottimale, esportare il calendario in formato CSV standard con righe separate per ogni evento.'
        ];
    }
    
    // Formato standard - procedi con parsing normale
    $parsedData = [];
    $totalHours = 0;
    $employees = [];
    $clients = [];
    $locations = [];
    
    foreach ($rawData['data'] as $row) {
        if (count($row) > 6 && !empty($row[0])) {
            $summary = $row[0] ?? '';
            $dtstart = $row[1] ?? '';
            $dtend = $row[2] ?? '';
            $attendee = $row[5] ?? '';
            $location = $row[6] ?? '';
            
            if ($summary === 'SUMMARY' || trim($summary) === '') {
                continue;
            }
            
            // Estrai dipendente
            $dipendente = '';
            if (!empty($attendee) && $attendee !== 'ATTENDEE') {
                $dipendente = $attendee;
            } elseif (preg_match('/- ([A-Za-z\s]+)$/', $summary, $matches)) {
                $dipendente = trim($matches[1]);
            } elseif (preg_match('/([A-Za-z]+\s+[A-Za-z]+)/', $summary, $matches)) {
                $dipendente = trim($matches[1]);
            }
            
            $cliente = $summary;
            if (preg_match('/^([^-]+)/', $summary, $matches)) {
                $cliente = trim($matches[1]);
            }
            
            $ore = 0;
            if (!empty($dtstart) && !empty($dtend)) {
                try {
                    $start = strtotime($dtstart);
                    $end = strtotime($dtend);
                    if ($start && $end && $end > $start) {
                        $ore = ($end - $start) / 3600;
                    }
                } catch (Exception $e) {
                    $ore = 0;
                }
            }
            
            if (!empty($dipendente)) $employees[$dipendente] = true;
            if (!empty($cliente)) $clients[$cliente] = true;
            if (!empty($location)) $locations[$location] = true;
            $totalHours += $ore;
            
            $parsedData[] = [
                'dipendente' => $dipendente,
                'cliente' => $cliente,
                'dove' => $location,
                'data_inizio' => $dtstart,
                'data_fine' => $dtend,
                'ore_totali' => $ore
            ];
        }
    }
    
    return [
        'events' => $parsedData,
        'total_hours' => $totalHours,
        'employees' => $employees,
        'clients' => $clients,
        'locations' => $locations,
        'parsing_status' => 'success'
    ];
}

// Parse Outlook calendar data
$calendarData = parseOutlookCalendar($csvData);
$totalRecords = count($calendarData['events']);
$totalHours = $calendarData['total_hours'];
$employees = $calendarData['employees'];
$clients = $calendarData['clients'];
$locations = $calendarData['locations'];

$uniqueEmployees = count($employees);
$uniqueClients = count($clients);
$uniqueLocations = count($locations);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📅 Calendario - BAIT Service</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-header {
            background: linear-gradient(135deg, #fd7e14 0%, #e66500 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .stats-card.events { border-left-color: #fd7e14; }
        .stats-card.hours { border-left-color: #28a745; }
        .stats-card.employees { border-left-color: #6f42c1; }
        .stats-card.clients { border-left-color: #17a2b8; }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-top: 2rem;
        }
        
        .table-header {
            background: linear-gradient(135deg, #495057 0%, #343a40 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .breadcrumb-nav {
            background: white;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        
        .badge-employee {
            background-color: #6f42c1;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .badge-client {
            background-color: #17a2b8;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .badge-vacation {
            background-color: #dc3545;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .badge-location {
            background-color: #fd7e14;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
        }
        
        .badge-hours {
            background-color: #28a745;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="main-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-calendar-alt me-3"></i>Calendario
                    </h1>
                    <p class="mb-0">Pianificazione appuntamenti e gestione calendario</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="laravel_bait/public/index_standalone.php" class="btn btn-light btn-lg">
                        <i class="fas fa-dashboard me-2"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Breadcrumb -->
        <nav class="breadcrumb-nav">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="laravel_bait/public/index_standalone.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="breadcrumb-item active">Calendario</li>
            </ol>
        </nav>

        <!-- Statistics Cards -->
        <div class="row stats-row mb-4">
            <div class="col-md-3">
                <div class="stats-card events">
                    <h3 class="stats-number text-warning"><?= number_format($totalRecords) ?></h3>
                    <p class="stats-label">Eventi Totali</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card hours">
                    <h3 class="stats-number text-success"><?= number_format($totalHours, 1) ?>h</h3>
                    <p class="stats-label">Ore Pianificate</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card employees">
                    <h3 class="stats-number text-primary"><?= $uniqueEmployees ?></h3>
                    <p class="stats-label">Dipendenti</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card clients">
                    <h3 class="stats-number text-info"><?= $uniqueClients ?></h3>
                    <p class="stats-label">Clienti/Eventi</p>
                </div>
            </div>
        </div>

        <!-- Main Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-0">
                            <i class="fas fa-list-alt me-2"></i>Eventi Calendario
                        </h4>
                        <small>File: <?= $hasCSV ? 'calendario.csv' : 'File non trovato' ?></small>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if ($hasCSV): ?>
                        <span class="badge bg-success">
                            <i class="fas fa-check-circle me-1"></i>Dati Caricati
                        </span>
                        <?php else: ?>
                        <span class="badge bg-danger">
                            <i class="fas fa-exclamation-circle me-1"></i>File Mancante
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="table-responsive p-3">
                <?php if ($hasCSV && !empty($calendarData['events'])): ?>
                <table id="calendarioTable" class="table table-striped table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Dipendente</th>
                            <th>Cliente/Evento</th>
                            <th>Dove</th>
                            <th>Data e Ora Inizio</th>
                            <th>Data e Ora Fine</th>
                            <th>Ore Totali</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calendarData['events'] as $index => $event): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td>
                                <?php if (!empty($event['dipendente'])): ?>
                                    <?php if (strpos($event['cliente'], 'Ferie') !== false): ?>
                                        <span class="badge-vacation"><?= htmlspecialchars($event['dipendente']) ?></span>
                                    <?php else: ?>
                                        <span class="badge-employee"><?= htmlspecialchars($event['dipendente']) ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($event['cliente'])): ?>
                                    <span class="badge-client"><?= htmlspecialchars($event['cliente']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($event['dove'])): ?>
                                    <span class="badge-location" title="<?= htmlspecialchars($event['dove']) ?>">
                                        <?= htmlspecialchars(mb_substr($event['dove'], 0, 30)) ?><?= mb_strlen($event['dove']) > 30 ? '...' : '' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if (!empty($event['data_inizio'])) {
                                    // Usa il formato già corretto dalle funzioni di parsing
                                    echo htmlspecialchars($event['data_inizio']);
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if (!empty($event['data_fine'])) {
                                    // Usa il formato già corretto dalle funzioni di parsing
                                    echo htmlspecialchars($event['data_fine']);
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($event['ore_totali'] > 0): ?>
                                    <?php 
                                    // Controlla se è un evento all-day dal formato della data
                                    $isAllDay = (strpos($event['data_inizio'], '00:00') !== false && 
                                                strpos($event['data_fine'], '00:00') !== false);
                                    ?>
                                    
                                    <?php if ($isAllDay && $event['ore_totali'] >= 1): ?>
                                        <span class="badge" style="background-color: #6f42c1; color: white;"><?= number_format($event['ore_totali'], 0) ?> giorni</span>
                                    <?php else: ?>
                                        <span class="badge-hours"><?= number_format($event['ore_totali'], 1) ?>h</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center p-5">
                    <?php if (isset($calendarData['parsing_status']) && $calendarData['parsing_status'] === 'complex_outlook_format'): ?>
                        <i class="fas fa-info-circle fa-3x text-warning mb-3"></i>
                        <h4>Formato Outlook Speciale Rilevato</h4>
                        <p class="text-muted">Il file calendario.csv usa un formato Outlook concatenato.</p>
                        <div class="alert alert-warning text-start mt-3">
                            <h6><i class="fas fa-lightbulb me-2"></i>Suggerimento:</h6>
                            <p class="mb-2"><?= $calendarData['suggestion'] ?></p>
                            <p class="mb-0"><strong>File rilevato:</strong> <?= $calendarData['total_columns'] ?> colonne in formato concatenato.</p>
                        </div>
                    <?php else: ?>
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h4>Nessun dato disponibile</h4>
                        <p class="text-muted">Il file calendario.csv non è stato trovato o è vuoto.</p>
                    <?php endif; ?>
                    
                    <!-- Debug Panel -->
                    <div class="alert alert-info text-start mt-4">
                        <h6><i class="fas fa-info-circle me-2"></i>Informazioni Debug:</h6>
                        <ul class="mb-0">
                            <li><strong>File:</strong> <?= $debugInfo['file_exists'] ? '✅ Trovato' : '❌ Mancante' ?> (<?= basename($debugInfo['file_path']) ?>)</li>
                            <li><strong>Directory Input:</strong> <?= $debugInfo['input_dir_exists'] ? '✅ Esiste' : '❌ Mancante' ?></li>
                            <li><strong>Directory Leggibile:</strong> <?= $debugInfo['input_dir_readable'] ? '✅ Sì' : '❌ No' ?></li>
                            <?php if (isset($csvData['format'])): ?>
                            <li><strong>Formato CSV:</strong> <?= $csvData['format'] ?></li>
                            <?php endif; ?>
                            <?php if (isset($csvData['events_found'])): ?>
                            <li><strong>Eventi Regex:</strong> <?= $csvData['events_found'] ?> trovati</li>
                            <?php endif; ?>
                            <li><strong>Parsing Status:</strong> <?= $calendarData['parsing_status'] ?? 'unknown' ?></li>
                            <?php if (isset($calendarData['events_parsed'])): ?>
                            <li><strong>Eventi Processati:</strong> <?= $calendarData['events_parsed'] ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <?php if ($debugInfo['file_exists'] && isset($csvData['format']) && $csvData['format'] === 'outlook_concatenated'): ?>
                    <div class="alert alert-warning text-start mt-3">
                        <h6><i class="fas fa-tools me-2"></i>Tentativo Parsing Outlook Concatenato:</h6>
                        <p class="mb-1">Il sistema ha rilevato un formato Outlook concatenato e ha estratto <strong><?= $csvData['events_found'] ?? 0 ?></strong> eventi tramite regex.</p>
                        <p class="mb-0">Se non vedi dati, il formato potrebbe richiedere ulteriori ottimizzazioni di parsing.</p>
                        <p class="mt-2 mb-0">
                            <a href="test_calendario_comprehensive.php" class="btn btn-warning btn-sm">
                                <i class="fas fa-bug me-1"></i>Test Parsing Dettagliato
                            </a>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="laravel_bait/public/index_standalone.php" class="btn btn-primary me-2">
                            <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                        </a>
                        <a href="audit_monthly_manager.php" class="btn btn-success">
                            <i class="fas fa-upload me-2"></i>Carica File CSV
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

    <script>
        $(document).ready(function() {
            <?php if ($hasCSV && !empty($csvData['data'])): ?>
            $('#calendarioTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel me-1"></i>Excel',
                        className: 'btn btn-success btn-sm'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf me-1"></i>PDF',
                        className: 'btn btn-danger btn-sm'
                    }
                ],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tutti"]],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/it-IT.json'
                },
                order: [[4, 'desc']], // Order by start date
                columnDefs: [
                    { orderable: false, targets: 0 } // Disable ordering on row number column
                ]
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>