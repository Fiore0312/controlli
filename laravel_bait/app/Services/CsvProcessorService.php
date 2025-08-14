<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Technician;
use App\Models\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use League\Csv\Statement;
use Carbon\Carbon;

/**
 * BAIT Service - CSV Processor Service
 * 
 * Robust CSV processing with encoding detection and validation
 * Migrated from Python BAITEnterpriseController CSV processing logic
 */
class CsvProcessorService
{
    // Required CSV files with expected columns (from Python)
    private array $requiredFiles = [
        'attivita.csv' => ['Contratto', 'Id Ticket', 'Iniziata il', 'Conclusa il', 'Azienda', 'Tipologia Attività', 'Descrizione', 'Durata', 'Creato da'],
        'timbrature.csv' => ['tecnico', 'cliente', 'ora inizio', 'ora fine', 'ore'],
        'teamviewer_bait.csv' => ['tecnico', 'cliente', 'Inizio', 'Fine', 'durata_minuti'],
        'teamviewer_gruppo.csv' => ['tecnico', 'cliente', 'Inizio', 'Fine'],
        'permessi.csv' => ['tecnico', 'tipo', 'data_inizio', 'data_fine'],
        'auto.csv' => ['tecnico', 'veicolo', 'data', 'ora_presa', 'ora_riconsegna'],
        'calendario.csv' => ['tecnico', 'cliente', 'data', 'ora_inizio', 'ora_fine']
    ];

    // Encoding fallbacks (from Python config)
    private array $encodingFallbacks = ['utf-8', 'cp1252', 'latin1', 'iso-8859-1'];
    private array $separators = [';', ',', '\t'];
    private int $maxFileSize = 100 * 1024 * 1024; // 100MB

    // Data directories
    private string $uploadDir;
    private string $inputDir;
    private string $processedDir;
    private string $backupDir;

    public function __construct()
    {
        $this->uploadDir = storage_path('app/upload_csv');
        $this->inputDir = storage_path('app/data/input');
        $this->processedDir = storage_path('app/data/processed');
        $this->backupDir = storage_path('app/backup_csv');

        // Create directories if they don't exist
        foreach ([$this->uploadDir, $this->inputDir, $this->processedDir, $this->backupDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Process all CSV files and return structured data
     * Migrated from Python process_all_files logic
     */
    public function processAllFiles(): array
    {
        $startTime = microtime(true);
        $processingBatchId = uniqid('batch_', true);
        
        Log::info("🔄 Starting CSV processing [Batch: {$processingBatchId}]");

        try {
            $dataFrames = [];
            $totalRecords = 0;
            $filesProcessed = [];

            // Try upload directory first, then input directory
            foreach ([$this->uploadDir, $this->inputDir] as $sourceDir) {
                foreach ($this->requiredFiles as $filename => $expectedCols) {
                    $filePath = $sourceDir . DIRECTORY_SEPARATOR . $filename;
                    
                    if (file_exists($filePath)) {
                        Log::info("📄 Processing file: {$filename} from {$sourceDir}");
                        
                        $processedData = $this->loadCsvRobust($filePath, $expectedCols);
                        
                        if (!empty($processedData)) {
                            $key = str_replace('.csv', '', $filename);
                            $dataFrames[$key] = $processedData;
                            $totalRecords += count($processedData);
                            $filesProcessed[] = $filename;
                            
                            Log::info("✅ Loaded {$filename}: " . count($processedData) . " records");
                            
                            // Store in database if it's activities
                            if ($key === 'attivita') {
                                $this->storeActivitiesInDatabase($processedData, $processingBatchId);
                            }
                        }
                        
                        break; // Use first found file
                    }
                }
            }

            if (empty($dataFrames)) {
                Log::warning("⚠️ No data files found");
                return [
                    'success' => false,
                    'message' => 'No CSV files found to process',
                    'errors' => ['No files found in upload or input directories']
                ];
            }

            // Backup processed files
            $this->backupProcessedFiles($filesProcessed);

            $processingTime = microtime(true) - $startTime;
            Log::info("✅ CSV processing completed successfully in {$processingTime}s");
            Log::info("📊 Total records processed: {$totalRecords}");

            return [
                'success' => true,
                'data' => $dataFrames,
                'files_processed' => $filesProcessed,
                'total_records' => $totalRecords,
                'processing_time' => $processingTime,
                'batch_id' => $processingBatchId
            ];

        } catch (\Exception $e) {
            Log::error("❌ CSV processing failed: {$e->getMessage()}", [
                'exception' => $e,
                'batch_id' => $processingBatchId
            ]);

            return [
                'success' => false,
                'message' => 'CSV processing failed',
                'errors' => [$e->getMessage()],
                'batch_id' => $processingBatchId
            ];
        }
    }

    /**
     * Load CSV with robust error handling and validation
     * Migrated from Python load_csv_robust method
     */
    private function loadCsvRobust(string $filePath, array $expectedColumns = []): array
    {
        if (!file_exists($filePath)) {
            Log::warning("⚠️ File not found: {$filePath}");
            return [];
        }

        // Check file size
        $fileSize = filesize($filePath);
        if ($fileSize > $this->maxFileSize) {
            Log::error("❌ File too large: {$filePath} ({$fileSize} bytes)");
            return [];
        }

        // Detect encoding
        $encoding = $this->detectFileEncoding($filePath);
        Log::info("📄 " . basename($filePath) . ": encoding={$encoding}");

        // Try different separators and encodings
        foreach ($this->separators as $separator) {
            foreach ([$encoding] + $this->encodingFallbacks as $tryEncoding) {
                try {
                    $csv = Reader::createFromPath($filePath, 'r');
                    $csv->setDelimiter($separator);
                    
                    // Convert encoding if necessary
                    if (strtolower($tryEncoding) !== 'utf-8') {
                        $csv->addStreamFilter('convert.iconv.' . $tryEncoding . '/UTF-8');
                    }

                    $csv->setHeaderOffset(0);
                    $records = (new Statement())->process($csv);
                    $data = iterator_to_array($records);

                    // Validate structure
                    if (count($data) > 0 && count(array_keys($data[0])) > 1) {
                        Log::info("✅ Successfully loaded " . basename($filePath) . ": " . count($data) . " rows");
                        
                        // Validate expected columns if provided
                        if (!empty($expectedColumns)) {
                            $actualColumns = array_keys($data[0]);
                            $missingCols = array_diff($expectedColumns, $actualColumns);
                            if (!empty($missingCols)) {
                                Log::warning("⚠️ Missing columns in " . basename($filePath) . ": " . implode(', ', $missingCols));
                            }
                        }

                        // Clean data
                        $cleanedData = $this->cleanDataArray($data, basename($filePath));
                        return $cleanedData;
                    }

                } catch (\Exception $e) {
                    // Continue to next encoding/separator combination
                    continue;
                }
            }
        }

        Log::error("❌ Failed to load " . basename($filePath) . " with any encoding/separator combination");
        return [];
    }

    /**
     * Detect file encoding with confidence scoring
     * Migrated from Python detect_file_encoding method
     */
    private function detectFileEncoding(string $filePath): string
    {
        try {
            $handle = fopen($filePath, 'r');
            $sample = fread($handle, 10000); // Sample first 10KB
            fclose($handle);

            // Use PHP's mb_detect_encoding
            $detected = mb_detect_encoding($sample, $this->encodingFallbacks, true);
            
            if ($detected) {
                Log::debug("🔍 Detected encoding: {$detected} for " . basename($filePath));
                return $detected;
            }

        } catch (\Exception $e) {
            Log::warning("⚠️ Encoding detection failed for " . basename($filePath) . ": {$e->getMessage()}");
        }

        return 'utf-8'; // Fallback
    }

    /**
     * Clean and standardize data array
     * Migrated from Python _clean_dataframe method
     */
    private function cleanDataArray(array $data, string $sourceFile): array
    {
        $cleaned = [];

        foreach ($data as $row) {
            $cleanRow = [];
            
            foreach ($row as $key => $value) {
                // Clean key
                $cleanKey = trim($key);
                
                // Clean value
                $cleanValue = is_string($value) ? trim($value) : $value;
                
                // Replace 'nan' strings with null
                if ($cleanValue === 'nan' || $cleanValue === 'null' || $cleanValue === '') {
                    $cleanValue = null;
                }

                // Parse datetime columns
                $cleanValue = $this->parseDateTimeValue($cleanKey, $cleanValue);

                // Standardize technician names
                if (in_array($cleanKey, ['tecnico', 'Creato da'])) {
                    $cleanValue = $this->standardizeTechnicianName($cleanValue);
                }

                $cleanRow[$cleanKey] = $cleanValue;
            }

            // Only add rows that have at least some data
            if (array_filter($cleanRow, fn($v) => !is_null($v))) {
                $cleaned[] = $cleanRow;
            }
        }

        Log::info("🧹 Cleaned {$sourceFile}: " . count($cleaned) . " rows after cleaning");
        return $cleaned;
    }

    /**
     * Parse datetime value with multiple format attempts
     * Migrated from Python _parse_datetime_column method
     */
    private function parseDateTimeValue(string $columnName, $value)
    {
        if (is_null($value) || !is_string($value)) {
            return $value;
        }

        // Check if column might contain datetime
        $datetimePatterns = ['data', 'ora', 'inizio', 'fine', 'timestamp', 'iniziata', 'conclusa'];
        $isDatetimeColumn = false;
        
        foreach ($datetimePatterns as $pattern) {
            if (stripos($columnName, $pattern) !== false) {
                $isDatetimeColumn = true;
                break;
            }
        }

        if (!$isDatetimeColumn) {
            return $value;
        }

        // Try different datetime formats
        $formats = [
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd/m/Y',
            'Y-m-d',
            'm/d/Y H:i:s',
            'm/d/Y H:i'
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->toDateTimeString();
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Try Carbon's flexible parsing as last resort
        try {
            $date = Carbon::parse($value);
            return $date->toDateTimeString();
        } catch (\Exception $e) {
            // Return original value if can't parse
            return $value;
        }
    }

    /**
     * Standardize technician name
     */
    private function standardizeTechnicianName($name)
    {
        if (is_null($name) || !is_string($name)) {
            return $name;
        }

        // Title case and trim
        $standardized = trim(ucwords(strtolower($name)));
        
        // Handle common variations
        $replacements = [
            'Gabriele De Palma' => 'Gabriele De Palma',
            'Davide Cestone' => 'Davide Cestone',
            'Alex Ferrario' => 'Alex Ferrario',
            'Marco Birocchi' => 'Marco Birocchi',
            'Matteo Signo' => 'Matteo Signo'
        ];

        foreach ($replacements as $variation => $standard) {
            if (stripos($standardized, $variation) !== false) {
                return $standard;
            }
        }

        return $standardized;
    }

    /**
     * Store activities in database
     */
    private function storeActivitiesInDatabase(array $activities, string $batchId): void
    {
        Log::info("💾 Storing " . count($activities) . " activities in database");

        foreach ($activities as $activityData) {
            try {
                // Map CSV columns to database columns
                $mappedData = $this->mapActivityData($activityData, $batchId);
                
                if ($mappedData) {
                    // Create or update activity
                    Activity::updateOrCreate(
                        [
                            'id_ticket' => $mappedData['id_ticket'],
                            'creato_da' => $mappedData['creato_da']
                        ],
                        $mappedData
                    );

                    // Ensure technician exists
                    if (!empty($mappedData['creato_da'])) {
                        Technician::firstOrCreate(
                            ['name' => $mappedData['creato_da']],
                            ['active' => true]
                        );
                    }

                    // Ensure client exists
                    if (!empty($mappedData['azienda'])) {
                        Client::firstOrCreate(
                            ['name' => $mappedData['azienda']],
                            ['active' => true]
                        );
                    }
                }

            } catch (\Exception $e) {
                Log::error("❌ Failed to store activity: {$e->getMessage()}", [
                    'activity_data' => $activityData
                ]);
            }
        }

        Log::info("✅ Activities stored in database successfully");
    }

    /**
     * Map CSV activity data to database columns
     */
    private function mapActivityData(array $csvData, string $batchId): ?array
    {
        try {
            // Required fields check
            $requiredFields = ['Id Ticket', 'Creato da'];
            foreach ($requiredFields as $field) {
                if (empty($csvData[$field])) {
                    return null; // Skip this record
                }
            }

            return [
                'contratto' => $csvData['Contratto'] ?? null,
                'id_ticket' => $csvData['Id Ticket'],
                'iniziata_il' => !empty($csvData['Iniziata il']) ? Carbon::parse($csvData['Iniziata il']) : null,
                'conclusa_il' => !empty($csvData['Conclusa il']) ? Carbon::parse($csvData['Conclusa il']) : null,
                'azienda' => $csvData['Azienda'] ?? null,
                'tipologia_attivita' => $csvData['Tipologia Attività'] ?? null,
                'descrizione' => $csvData['Descrizione'] ?? null,
                'durata' => is_numeric($csvData['Durata'] ?? null) ? floatval($csvData['Durata']) : null,
                'creato_da' => $this->standardizeTechnicianName($csvData['Creato da']),
                'file_source' => 'attivita.csv',
                'processing_batch_id' => $batchId,
                'is_validated' => false
            ];

        } catch (\Exception $e) {
            Log::warning("⚠️ Failed to map activity data: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Backup processed files
     */
    private function backupProcessedFiles(array $filesProcessed): void
    {
        $backupSubDir = $this->backupDir . DIRECTORY_SEPARATOR . date('Y-m-d_H-i-s');
        
        if (!is_dir($backupSubDir)) {
            mkdir($backupSubDir, 0755, true);
        }

        foreach ($filesProcessed as $filename) {
            $sourcePaths = [
                $this->uploadDir . DIRECTORY_SEPARATOR . $filename,
                $this->inputDir . DIRECTORY_SEPARATOR . $filename
            ];

            foreach ($sourcePaths as $sourcePath) {
                if (file_exists($sourcePath)) {
                    $backupPath = $backupSubDir . DIRECTORY_SEPARATOR . $filename;
                    copy($sourcePath, $backupPath);
                    Log::info("💾 Backed up {$filename} to backup directory");
                    break;
                }
            }
        }
    }

    /**
     * Get processing statistics
     */
    public function getProcessingStats(): array
    {
        return [
            'total_activities' => Activity::count(),
            'last_processing' => Activity::latest('created_at')->first()?->created_at,
            'unique_technicians' => Activity::distinct('creato_da')->count(),
            'unique_clients' => Activity::distinct('azienda')->count(),
            'files_in_upload' => count(glob($this->uploadDir . '/*.csv')),
            'files_in_input' => count(glob($this->inputDir . '/*.csv')),
            'backup_count' => count(glob($this->backupDir . '/*', GLOB_ONLYDIR))
        ];
    }
}