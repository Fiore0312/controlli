<?php
/**
 * BAIT Service - CSV Type Detector
 * 
 * Classe intelligente per riconoscimento automatico del tipo di file CSV
 * basato sull'analisi dell'header e del contenuto, indipendentemente dal nome file.
 * 
 * SOLUZIONE AL PROBLEMA:
 * Il sistema può ora accettare file con nomi personalizzati (es: "rapportini_agosto.csv")
 * e riconoscerli automaticamente come tipo "attivita.csv" analizzando le colonne.
 * 
 * @author BAIT Service Integration Expert
 * @version 2.0
 * @date 2025-08-19
 */

class CSVTypeDetector {
    
    /**
     * Firme identificative per ogni tipo di file CSV
     * Ogni tipo ha colonne chiave che lo identificano univocamente
     */
    private $signatures = [
        'attivita.csv' => [
            'required' => ['ID Ticket', 'Creato da', 'Tipologia attività'],
            'optional' => ['Durata', 'Descrizione', 'Azienda', 'Contratto'],
            'description' => 'File attività/rapportini tecnici'
        ],
        
        'timbrature.csv' => [
            'required' => ['Dipendente', 'Data'],
            'optional' => ['Ora ingresso', 'Ora uscita', 'Pause', 'Ore lavoro'],
            'description' => 'File timbrature presenze'
        ],
        
        'auto.csv' => [
            'required' => ['Tecnico', 'Data utilizzo'],
            'optional' => ['Veicolo', 'Km', 'Destinazione', 'Note'],
            'description' => 'File utilizzo auto aziendali'
        ],
        
        'teamviewer_bait.csv' => [
            'required' => ['Session ID', 'Tecnico', 'Cliente'],
            'optional' => ['Durata', 'Start time', 'End time'],
            'description' => 'File sessioni TeamViewer individuali'
        ],
        
        'teamviewer_gruppo.csv' => [
            'required' => ['Session ID', 'Group'],
            'optional' => ['Participants', 'Duration'],
            'description' => 'File sessioni TeamViewer di gruppo'
        ],
        
        'permessi.csv' => [
            'required' => ['Dipendente', 'Tipo permesso'],
            'optional' => ['Data inizio', 'Data fine', 'Stato', 'Note'],
            'description' => 'File permessi e ferie'
        ],
        
        'calendario.csv' => [
            'required' => ['Data', 'Evento'],
            'optional' => ['Ora', 'Tecnico', 'Cliente', 'Descrizione'],
            'description' => 'File calendario appuntamenti'
        ]
    ];
    
    /**
     * Analizza un file CSV e determina il tipo più probabile
     * 
     * @param string $filePath Percorso del file CSV da analizzare
     * @return array Risultato con tipo identificato, confidence e dettagli
     */
    public function detectType($filePath) {
        
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'error' => 'File non trovato',
                'file_path' => $filePath
            ];
        }
        
        // Analisi encoding e lettura header
        $encoding = $this->detectEncoding($filePath);
        $header = $this->readHeader($filePath, $encoding);
        
        if (!$header) {
            return [
                'success' => false,
                'error' => 'Impossibile leggere header CSV',
                'encoding' => $encoding
            ];
        }
        
        // Normalizza header (rimuovi BOM, spazi, case)
        $normalizedHeader = $this->normalizeHeader($header);
        
        // Calcola confidence per ogni tipo
        $scores = [];
        foreach ($this->signatures as $type => $signature) {
            $score = $this->calculateConfidence($normalizedHeader, $signature);
            if ($score > 0) {
                $scores[$type] = $score;
            }
        }
        
        // Ordina per confidence descrescente
        arsort($scores);
        
        $result = [
            'success' => count($scores) > 0,
            'detected_type' => null,
            'confidence' => 0,
            'all_scores' => $scores,
            'header' => $header,
            'normalized_header' => $normalizedHeader,
            'encoding' => $encoding,
            'analysis' => []
        ];
        
        if (count($scores) > 0) {
            $bestType = array_keys($scores)[0];
            $bestScore = $scores[$bestType];
            
            $result['detected_type'] = $bestType;
            $result['confidence'] = $bestScore;
            $result['description'] = $this->signatures[$bestType]['description'];
            
            // Analisi dettagliata per il tipo migliore
            $result['analysis'] = $this->analyzeMatch($normalizedHeader, $this->signatures[$bestType]);
        }
        
        return $result;
    }
    
    /**
     * Rileva encoding del file CSV
     */
    private function detectEncoding($filePath) {
        $sample = file_get_contents($filePath, false, null, 0, 1024);
        
        $encodings = ['UTF-8', 'UTF-16', 'Windows-1252', 'ISO-8859-1'];
        foreach ($encodings as $encoding) {
            if (mb_check_encoding($sample, $encoding)) {
                return $encoding;
            }
        }
        
        return 'UTF-8'; // Fallback
    }
    
    /**
     * Legge header del CSV gestendo encoding
     */
    private function readHeader($filePath, $encoding = 'UTF-8') {
        $handle = fopen($filePath, 'r');
        if (!$handle) return false;
        
        $header = fgetcsv($handle, 1000, ',');
        fclose($handle);
        
        if (!$header) return false;
        
        // Converti encoding se necessario
        if ($encoding !== 'UTF-8') {
            $header = array_map(function($col) use ($encoding) {
                return mb_convert_encoding($col, 'UTF-8', $encoding);
            }, $header);
        }
        
        return $header;
    }
    
    /**
     * Normalizza header per matching robusto
     */
    private function normalizeHeader($header) {
        return array_map(function($col) {
            // Rimuovi BOM UTF-8
            $col = str_replace("\xEF\xBB\xBF", '', $col);
            
            // Normalizza spazi e case
            $col = trim($col);
            $col = preg_replace('/\s+/', ' ', $col);
            
            return $col;
        }, $header);
    }
    
    /**
     * Calcola confidence score per un tipo specifico
     */
    private function calculateConfidence($header, $signature) {
        $requiredMatches = 0;
        $optionalMatches = 0;
        
        // Crea versioni normalizzate delle colonne attese
        $requiredNormalized = array_map([$this, 'normalizeColumnName'], $signature['required']);
        $optionalNormalized = array_map([$this, 'normalizeColumnName'], $signature['optional']);
        
        // Normalizza header per confronto
        $headerNormalized = array_map([$this, 'normalizeColumnName'], $header);
        
        // Conta match nelle colonne richieste
        foreach ($requiredNormalized as $required) {
            foreach ($headerNormalized as $col) {
                if ($this->columnsMatch($col, $required)) {
                    $requiredMatches++;
                    break;
                }
            }
        }
        
        // Conta match nelle colonne opzionali
        foreach ($optionalNormalized as $optional) {
            foreach ($headerNormalized as $col) {
                if ($this->columnsMatch($col, $optional)) {
                    $optionalMatches++;
                    break;
                }
            }
        }
        
        // Calcola confidence
        $totalRequired = count($signature['required']);
        $totalOptional = count($signature['optional']);
        
        if ($requiredMatches === 0) {
            return 0; // Nessuna colonna richiesta trovata
        }
        
        $requiredScore = ($requiredMatches / $totalRequired) * 70; // 70% peso per colonne richieste
        $optionalScore = $totalOptional > 0 ? ($optionalMatches / $totalOptional) * 30 : 0; // 30% peso per opzionali
        
        return round($requiredScore + $optionalScore, 1);
    }
    
    /**
     * Normalizza nome colonna per confronti robusti
     */
    private function normalizeColumnName($name) {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^\w\s]/', '', $name); // Rimuovi punteggiatura
        $name = preg_replace('/\s+/', ' ', $name);     // Normalizza spazi
        return $name;
    }
    
    /**
     * Verifica se due nomi colonna corrispondono (matching fuzzy)
     */
    private function columnsMatch($col1, $col2) {
        $col1 = $this->normalizeColumnName($col1);
        $col2 = $this->normalizeColumnName($col2);
        
        // Match esatto
        if ($col1 === $col2) return true;
        
        // Match parziale (una stringa contiene l'altra)
        if (strpos($col1, $col2) !== false || strpos($col2, $col1) !== false) {
            return true;
        }
        
        // Match tramite parole chiave comuni
        $keywords = [
            'id' => ['id', 'identificativo', 'codice'],
            'ticket' => ['ticket', 'richiesta'],
            'tecnico' => ['tecnico', 'operatore', 'creato da'],
            'data' => ['data', 'date', 'giorno'],
            'ora' => ['ora', 'orario', 'time'],
            'durata' => ['durata', 'duration', 'tempo'],
            'cliente' => ['cliente', 'azienda', 'client'],
            'auto' => ['auto', 'veicolo', 'macchina'],
            'permesso' => ['permesso', 'ferie', 'congedo']
        ];
        
        foreach ($keywords as $key => $synonyms) {
            $col1HasKey = false;
            $col2HasKey = false;
            
            foreach ($synonyms as $synonym) {
                if (strpos($col1, $synonym) !== false) $col1HasKey = true;
                if (strpos($col2, $synonym) !== false) $col2HasKey = true;
            }
            
            if ($col1HasKey && $col2HasKey) return true;
        }
        
        return false;
    }
    
    /**
     * Fornisce analisi dettagliata del matching
     */
    private function analyzeMatch($header, $signature) {
        $analysis = [
            'required_matches' => [],
            'optional_matches' => [],
            'unmatched_required' => [],
            'unmatched_optional' => [],
            'extra_columns' => []
        ];
        
        $headerNormalized = array_map([$this, 'normalizeColumnName'], $header);
        
        // Analizza colonne richieste
        foreach ($signature['required'] as $required) {
            $found = false;
            foreach ($header as $i => $col) {
                if ($this->columnsMatch($col, $required)) {
                    $analysis['required_matches'][] = [
                        'expected' => $required,
                        'found' => $col,
                        'position' => $i
                    ];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $analysis['unmatched_required'][] = $required;
            }
        }
        
        // Analizza colonne opzionali
        foreach ($signature['optional'] as $optional) {
            $found = false;
            foreach ($header as $i => $col) {
                if ($this->columnsMatch($col, $optional)) {
                    $analysis['optional_matches'][] = [
                        'expected' => $optional,
                        'found' => $col,
                        'position' => $i
                    ];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $analysis['unmatched_optional'][] = $optional;
            }
        }
        
        return $analysis;
    }
    
    /**
     * Ottieni tutti i tipi supportati con descrizioni
     */
    public function getSupportedTypes() {
        $types = [];
        foreach ($this->signatures as $type => $signature) {
            $types[$type] = [
                'description' => $signature['description'],
                'required_columns' => $signature['required'],
                'optional_columns' => $signature['optional']
            ];
        }
        return $types;
    }
    
    /**
     * Test rapido su un file
     */
    public function quickTest($filePath) {
        $result = $this->detectType($filePath);
        
        echo "<h3>🔍 CSV Type Detection Results</h3>";
        echo "<p><strong>File:</strong> " . basename($filePath) . "</p>";
        
        if (!$result['success']) {
            echo "<p class='text-danger'>❌ <strong>Errore:</strong> " . $result['error'] . "</p>";
            return;
        }
        
        echo "<p><strong>Tipo rilevato:</strong> <span class='badge bg-success'>" . $result['detected_type'] . "</span></p>";
        echo "<p><strong>Confidence:</strong> " . $result['confidence'] . "%</p>";
        echo "<p><strong>Descrizione:</strong> " . $result['description'] . "</p>";
        echo "<p><strong>Encoding:</strong> " . $result['encoding'] . "</p>";
        
        if ($result['confidence'] >= 70) {
            echo "<p class='text-success'>✅ <strong>Match Sicuro</strong> - Il file può essere processato automaticamente</p>";
        } elseif ($result['confidence'] >= 40) {
            echo "<p class='text-warning'>⚠️ <strong>Match Probabile</strong> - Richiede conferma utente</p>";
        } else {
            echo "<p class='text-danger'>❌ <strong>Match Incerto</strong> - Mapping manuale necessario</p>";
        }
        
        echo "<h4>Header trovato:</h4>";
        echo "<code>" . implode(' | ', $result['header']) . "</code>";
    }
}
?>