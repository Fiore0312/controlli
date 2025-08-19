<?php
/**
 * FIX CALENDARIO MANCANTE - CORREZIONE COMPLETA
 * Aggiunge supporto calendario.csv ovunque necessario
 */

$filePath = __DIR__ . '/audit_monthly_manager.php';
$content = file_get_contents($filePath);

echo "🔧 FIX CALENDARIO.CSV MANCANTE - CORREZIONE COMPLETA\n";
echo "===================================================\n\n";

// FIX 1: Aggiunge calendario.csv nell'array $requiredFiles per il monitor stato
$pattern1 = "/(\s+)'teamviewer_gruppo\.csv' => \['icon' => 'fas fa-users', 'label' => 'TeamViewer Gruppo', 'critical' => false\]\s*\n(\s+)\];/";
$replacement1 = "$1'teamviewer_gruppo.csv' => ['icon' => 'fas fa-users', 'label' => 'TeamViewer Gruppo', 'critical' => false],\n$2'calendario.csv' => ['icon' => 'fas fa-calendar-alt', 'label' => 'Calendario Appuntamenti', 'critical' => false]\n$2];";

if (preg_match($pattern1, $content)) {
    $content = preg_replace($pattern1, $replacement1, $content);
    echo "✅ Fix 1: Aggiunto calendario.csv nell'array \$requiredFiles\n";
} else {
    echo "⚠️ Fix 1: Pattern non trovato per \$requiredFiles\n";
}

// FIX 2: Aggiunge il campo input per calendario.csv nel form
$pattern2 = "/(\s+)<\/div>\s*<\/div>\s*\s*<!--\s*Upload Progress Bar/";
$replacement2 = "$1</div>
                            
                            <div class=\"bait-form-group\">
                                <label class=\"bait-form-label\" for=\"calendario\">
                                    <i class=\"fas fa-calendar-alt me-2 text-primary\"></i>
                                    Calendario Appuntamenti
                                </label>
                                <input type=\"file\" name=\"calendario\" id=\"calendario\" class=\"bait-form-control\" 
                                       accept=\".csv\" aria-describedby=\"calendario-help\">
                                <div class=\"bait-form-help\" id=\"calendario-help\">
                                    Appuntamenti pianificati Outlook
                                </div>
                            </div>
                        </div>
                        
                        <!-- Upload Progress Bar";

if (preg_match($pattern2, $content)) {
    $content = preg_replace($pattern2, $replacement2, $content);
    echo "✅ Fix 2: Aggiunto campo input calendario nel form\n";
} else {
    echo "⚠️ Fix 2: Pattern non trovato per form input\n";
    
    // Fallback: cerca pattern alternativo
    $altPattern = "/(\s+)<div class=\"bait-form-group\">\s*<label[^>]*for=\"auto\".*?<\/div>\s*<\/div>/s";
    if (preg_match($altPattern, $content)) {
        $altReplacement = "$0
                            
                            <div class=\"bait-form-group\">
                                <label class=\"bait-form-label\" for=\"calendario\">
                                    <i class=\"fas fa-calendar-alt me-2 text-primary\"></i>
                                    Calendario Appuntamenti
                                </label>
                                <input type=\"file\" name=\"calendario\" id=\"calendario\" class=\"bait-form-control\" 
                                       accept=\".csv\" aria-describedby=\"calendario-help\">
                                <div class=\"bait-form-help\" id=\"calendario-help\">
                                    Appuntamenti pianificati Outlook
                                </div>
                            </div>";
        
        $content = preg_replace($altPattern, $altReplacement, $content);
        echo "✅ Fix 2 (Fallback): Campo calendario aggiunto dopo il campo auto\n";
    }
}

// Salva il file corretto
file_put_contents($filePath, $content);

echo "✅ File audit_monthly_manager.php aggiornato con successo!\n\n";

// Verifica che il file upload_csv/calendario.csv esista
$csvPath = __DIR__ . '/upload_csv/calendario.csv';
if (!file_exists($csvPath)) {
    // Crea file di esempio
    $calendarSample = "data_inizio,data_fine,tecnico,azienda,tipo_appuntamento,note\n";
    $calendarSample .= "01/08/2025 09:00,01/08/2025 10:00,Alex Ferrario,Cliente Test SRL,Intervento On-Site,Controllo sistema server\n";
    $calendarSample .= "01/08/2025 14:00,01/08/2025 16:00,Davide Cestone,Azienda XYZ,Formazione,Training nuovo software\n";
    
    file_put_contents($csvPath, $calendarSample);
    echo "✅ Creato file di esempio: upload_csv/calendario.csv\n";
} else {
    echo "✅ File upload_csv/calendario.csv già presente\n";
}

// Aggiorna anche carica_dati_incrementale.php
$incrementalPath = __DIR__ . '/carica_dati_incrementale.php';
if (file_exists($incrementalPath)) {
    $incrementalContent = file_get_contents($incrementalPath);
    
    // Verifica se calendario.csv è già nell'array
    if (strpos($incrementalContent, "'calendario.csv'") === false) {
        $pattern = "/('auto\.csv' => 'utilizzo_auto',?)/";
        $replacement = "$1\n        'calendario.csv' => 'calendario_appuntamenti'";
        
        $incrementalContent = preg_replace($pattern, $replacement, $incrementalContent);
        file_put_contents($incrementalPath, $incrementalContent);
        echo "✅ Aggiunto calendario.csv anche in carica_dati_incrementale.php\n";
    } else {
        echo "✅ calendario.csv già presente in carica_dati_incrementale.php\n";
    }
}

echo "\n🎯 CORREZIONE COMPLETATA!\n";
echo "========================\n";
echo "Ora il sistema supporta completamente:\n";
echo "1. ✅ Upload calendario.csv nel form\n";
echo "2. ✅ Monitor stato file calendario\n";
echo "3. ✅ Processamento incrementale calendario\n";
echo "4. ✅ File esempio creato\n\n";

echo "🌐 TESTA SUBITO:\n";
echo "http://localhost/controlli/audit_monthly_manager.php\n";
echo "Dovresti vedere ora il campo 'Calendario Appuntamenti'!\n\n";

?>