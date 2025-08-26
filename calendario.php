<?php
/**
 * CALENDARIO FIXED - Versione corretta con estrazione dipendenti
 * Gestione calendario appuntamenti con parsing ATTENDEE corretto
 */

header('Content-Type: text/html; charset=utf-8');

// Parser ICS migliorato per estrarre ATTENDEE correttamente
function parseIcsFileEnhanced($icsContent) {
    $events = [];
    $currentEvent = [];
    $inEvent = false;
    
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
            // Gestione linee multi-linea (continuazione con TAB o spazio)
            if (preg_match('/^\s/', $line)) {
                // Continua la proprietà precedente
                $keys = array_keys($currentEvent);
                if (!empty($keys)) {
                    $lastKey = end($keys);
                    $currentEvent[$lastKey] .= trim($line);
                }
                continue;
            }
            
            if (strpos($line, ":") !== false) {
                list($propertyPart, $value) = explode(":", $line, 2);
                
                // Gestione ATTENDEE con parsing del CN (Common Name)
                if (strpos($propertyPart, "ATTENDEE") === 0) {
                    // Estrai il nome dal CN="Nome Cognome"
                    if (preg_match('/CN=([^;]+)/', $propertyPart, $matches)) {
                        $attendeeName = trim($matches[1], '"');
                        
                        // Se non esiste già, inizializza array attendees
                        if (!isset($currentEvent['ATTENDEES'])) {
                            $currentEvent['ATTENDEES'] = [];
                        }
                        
                        $currentEvent['ATTENDEES'][] = $attendeeName;
                        $currentEvent['ATTENDEE_FULL'] = $line; // Mantieni anche la riga completa
                    }
                } else {
                    // Altre proprietà
                    $property = $propertyPart;
                    if (strpos($propertyPart, ";") !== false) {
                        $parts = explode(";", $propertyPart);
                        $property = $parts[0];
                    }
                    
                    $currentEvent[$property] = $value;
                }
            }
        }
    }
    
    return $events;
}

// Funzione di parsing date ICS 
function parseIcsDateTime($icsDateTime) {
    $cleanDateTime = $icsDateTime;
    if (strpos($icsDateTime, ':') !== false) {
        $parts = explode(':', $icsDateTime);
        $cleanDateTime = end($parts);
    }
    
    $cleanDateTime = trim($cleanDateTime);
    
    if (preg_match('/(\d{4})(\d{2})(\d{2})(?:T(\d{2})(\d{2})(\d{2}))?/', $cleanDateTime, $matches)) {
        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $day = (int)$matches[3];
        $hour = isset($matches[4]) ? (int)$matches[4] : 0;
        $minute = isset($matches[5]) ? (int)$matches[5] : 0;
        $second = isset($matches[6]) ? (int)$matches[6] : 0;
        
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

// Converte eventi ICS con estrazione corretta dipendenti
function convertIcsToCalendarFormatFixed($icsEvents) {
    $parsedData = [];
    $totalHours = 0;
    $employees = [];
    $clients = [];
    $locations = [];
    
    foreach ($icsEvents as $event) {
        $summary = $event["SUMMARY"] ?? "";
        $location = $event["LOCATION"] ?? "";
        $description = $event["DESCRIPTION"] ?? "";
        
        // Parse date
        $startParsed = parseIcsDateTime($event["DTSTART"] ?? "");
        $endParsed = parseIcsDateTime($event["DTEND"] ?? "");
        
        $dtstart = $startParsed ? $startParsed["formatted"] : "";
        $dtend = $endParsed ? $endParsed["formatted"] : "";
        
        // NUOVO: Estrai dipendente dai campi ATTENDEES
        $dipendente = "";
        if (isset($event['ATTENDEES']) && !empty($event['ATTENDEES'])) {
            // Prendi il primo attendee (solitamente il dipendente assegnato)
            $dipendente = $event['ATTENDEES'][0];
            
            // Filtra nomi speciali (non dipendenti)
            if ($dipendente === 'Punto' || $dipendente === 'Tecnico') {
                $dipendente = "";
            }
        }
        
        // Fallback: se non c'è ATTENDEE, prova a estrarre dal SUMMARY
        if (empty($dipendente)) {
            $technicians = [
                "Matteo Signo", "Davide Cestone", "Arlind Hoxha", 
                "Gabriele De Palma", "Marco Birocchi", "Alex Ferrario", 
                "Franco Fiorellino", "Veronica Totta", "Matteo Di Salvo"
            ];
            
            foreach ($technicians as $tech) {
                if (stripos($summary, $tech) !== false) {
                    $dipendente = $tech;
                    break;
                }
            }
        }
        
        // Se ancora vuoto, usa il summary per identificare
        if (empty($dipendente)) {
            if (stripos($summary, "ferie") !== false) {
                // Prova a estrarre nome dalle ferie
                if (preg_match('/(Matteo|Davide|Arlind|Gabriele|Marco|Alex|Franco|Veronica|Niccolò)/i', $summary, $matches)) {
                    $firstName = $matches[1];
                    // Mappa nomi → cognomi completi
                    $nameMap = [
                        'Matteo' => 'Matteo Signo',
                        'Davide' => 'Davide Cestone', 
                        'Arlind' => 'Arlind Hoxha',
                        'Gabriele' => 'Gabriele De Palma',
                        'Marco' => 'Marco Birocchi',
                        'Alex' => 'Alex Ferrario',
                        'Franco' => 'Franco Fiorellino',
                        'Veronica' => 'Veronica Totta',
                        'Niccolò' => 'Niccolò Ragusa'
                    ];
                    $dipendente = $nameMap[$firstName] ?? $firstName;
                }
            }
        }
        
        // Fallback finale - lascia vuoto invece di email placeholder
        if (empty($dipendente)) {
            $dipendente = "Non assegnato";
        }
        
        // Calcola ore totali
        $oreTotali = 0;
        if ($startParsed && $endParsed) {
            $diffSeconds = $endParsed["timestamp"] - $startParsed["timestamp"];
            
            if ($startParsed["is_all_day"] || $endParsed["is_all_day"]) {
                $diffDays = ceil($diffSeconds / (24 * 60 * 60));
                $oreTotali = $diffDays;
            } else {
                $diffHours = $diffSeconds / 3600;
                $oreTotali = $diffHours;
            }
        }
        
        // Estrai cliente dal summary
        $cliente = $summary;
        if (preg_match('/^([^-]+)/', $summary, $matches)) {
            $cliente = trim($matches[1]);
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
        
        // Raccogli statistiche
        if (!empty($dipendente) && $dipendente !== "Non assegnato") $employees[$dipendente] = true;
        if (!empty($cliente)) $clients[$cliente] = true;
        if (!empty($location)) $locations[$location] = true;
        $totalHours += $oreTotali;
        
        $parsedData[] = [
            'dipendente' => $dipendente,
            'cliente' => $cliente,
            'dove' => $location,
            'data_inizio' => $dtstart,
            'data_fine' => $dtend,
            'ore_totali' => $oreTotali,
            'note' => $notes,
            'is_all_day' => ($startParsed && $startParsed["is_all_day"]) || ($endParsed && $endParsed["is_all_day"])
        ];
    }
    
    return [
        'events' => $parsedData,
        'total_hours' => $totalHours,
        'employees' => $employees,
        'clients' => $clients,
        'locations' => $locations,
        'parsing_status' => 'ics_fixed_success',
        'events_parsed' => count($parsedData)
    ];
}

// Leggi e processa il file calendario - UNIFICATO su upload_csv
$csvPath = __DIR__ . '/upload_csv/calendario.csv';
$hasCSV = file_exists($csvPath);

if ($hasCSV) {
    $icsContent = file_get_contents($csvPath);
    
    // Detect encoding and convert to UTF-8
    $encoding = mb_detect_encoding($icsContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $icsContent = mb_convert_encoding($icsContent, 'UTF-8', $encoding);
    }
    
    $events = parseIcsFileEnhanced($icsContent);
    $calendarData = convertIcsToCalendarFormatFixed($events);
} else {
    $calendarData = [
        'events' => [],
        'total_hours' => 0,
        'employees' => [],
        'clients' => [],
        'locations' => [],
        'parsing_status' => 'file_not_found'
    ];
}

$totalRecords = count($calendarData['events']);
$totalHours = $calendarData['total_hours'];
$employees = $calendarData['employees'];
$clients = $calendarData['clients'];
$uniqueEmployees = count($employees);
$uniqueClients = count($clients);
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
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
        
        .badge-employee {
            background-color: #28a745;
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
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
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-location {
            background-color: #fd7e14;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .badge-hours {
            background-color: #28a745;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .badge-days {
            background-color: #6f42c1;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        .improvement-alert {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 1px solid #28a745;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="main-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-calendar-check me-3"></i>Calendario
                    </h1>
                    <p class="mb-0">✅ Versione con estrazione dipendenti corretta</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="laravel_bait/public/index_standalone.php" class="btn btn-light btn-lg">
                        <i class="fas fa-arrow-left me-2"></i>Dashboard Principale
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">

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
        <div class="table-container" style="background: white; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.1); overflow: hidden;">
            <div class="table-header" style="background: linear-gradient(135deg, #495057 0%, #343a40 100%); color: white; padding: 1rem 1.5rem;">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-0">
                            <i class="fas fa-list-alt me-2"></i>Eventi Calendario (Con Dipendenti)
                        </h4>
                        <small>File: upload_csv/calendario.csv - Parser ICS migliorato</small>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-success">
                            <i class="fas fa-check-circle me-1"></i>Attendees Estratti
                        </span>
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
                                <?php if (!empty($event['dipendente']) && $event['dipendente'] !== 'Non assegnato'): ?>
                                    <?php if (strpos($event['cliente'], 'Ferie') !== false || strpos($event['cliente'], 'FERIE') !== false): ?>
                                        <span class="badge-vacation"><?= htmlspecialchars($event['dipendente']) ?></span>
                                    <?php else: ?>
                                        <span class="badge-employee"><?= htmlspecialchars($event['dipendente']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Non assegnato</span>
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
                            <td><?= htmlspecialchars($event['data_inizio']) ?></td>
                            <td><?= htmlspecialchars($event['data_fine']) ?></td>
                            <td>
                                <?php if ($event['ore_totali'] > 0): ?>
                                    <?php if ($event['is_all_day'] && $event['ore_totali'] >= 1): ?>
                                        <span class="badge-days"><?= number_format($event['ore_totali'], 0) ?> giorni</span>
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
                
                <!-- Dipendenti Identificati -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="alert alert-success">
                            <h6><i class="fas fa-users me-2"></i>Dipendenti Identificati (<?= count($employees) ?>)</h6>
                            <?php foreach (array_keys($employees) as $employee): ?>
                                <span class="badge-employee me-2 mb-2"><?= htmlspecialchars($employee) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <?php else: ?>
                <div class="text-center p-5">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h4>File non trovato</h4>
                    <p class="text-muted">Il file upload_csv/calendario.csv non è disponibile.</p>
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

    <script>
        $(document).ready(function() {
            <?php if ($hasCSV && !empty($calendarData['events'])): ?>
            $('#calendarioTable').DataTable({
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