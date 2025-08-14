-- =====================================================
-- IMPORT CSV DATA TO MYSQL BAIT_SERVICE_REAL
-- Script automatico per caricamento dati reali
-- Created: 2025-08-12
-- =====================================================

USE bait_service_real;

-- Temporary disable foreign key checks for import
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = '';

-- =====================================================
-- 1. POPOLAMENTO TECNICI (TECHNICIANS)
-- =====================================================

-- Inserimento tecnici reali da CSV
INSERT INTO tecnici (nome, cognome, email, codice_dipendente, attivo) VALUES
('Arlind', 'Hoxha', 'arlind.hoxha@baitservice.it', 'AH001', TRUE),
('Davide', 'Cestone', 'davide.cestone@baitservice.it', 'DC001', TRUE),
('Gabriele', 'De Palma', 'gabriele.depalma@baitservice.it', 'GDP001', TRUE),
('Franco', 'Fiorellino', 'franco.fiorellino@baitservice.it', 'FF001', TRUE),
('Alex', 'Ferrario', 'alex.ferrario@baitservice.it', 'AF001', TRUE),
('Matteo', 'Di Salvo', 'matteo.disalvo@baitservice.it', 'MDS001', TRUE),
('Matteo', 'Signo', 'matteo.signo@baitservice.it', 'MS001', TRUE),
('Info', 'User', 'info@baitservice.it', 'INFO001', TRUE),
('Marco', 'Birocchi', 'marco.birocchi@baitservice.it', 'MB001', TRUE)
ON DUPLICATE KEY UPDATE
nome = VALUES(nome),
cognome = VALUES(cognome),
email = VALUES(email);

-- =====================================================
-- 2. POPOLAMENTO CLIENTI (CLIENTS) 
-- =====================================================

-- Inserimento clienti reali normalizzati da CSV
INSERT INTO clienti (nome, indirizzo, citta, provincia, tipo_cliente, attivo) VALUES
-- Da timbrature.csv
('A&B Consulting Srl', 'Via Regina Elena', 'Cerro Maggiore', 'MI', 'azienda', TRUE),
('ITX Italia', 'Largo Corsia dei Servi 32', 'Milano', 'MI', 'azienda', TRUE),

-- Da teamviewer_bait.csv
('Acquisti', NULL, NULL, NULL, 'azienda', TRUE),
('Liliana Aguilar', NULL, NULL, NULL, 'privato', TRUE),
('User', NULL, NULL, NULL, 'privato', TRUE),
('Sa', NULL, NULL, NULL, 'azienda', TRUE),
('Utente1', NULL, NULL, NULL, 'privato', TRUE),
('Commerciale', NULL, NULL, NULL, 'azienda', TRUE),
('Valerio', NULL, NULL, NULL, 'privato', TRUE),
('Maristella Maffi', NULL, NULL, NULL, 'privato', TRUE),

-- Da auto.csv
('Be.Co/Garibaldina Saronno', NULL, 'Saronno', NULL, 'azienda', TRUE),
('Garibaldina Corbetta', NULL, 'Corbetta', NULL, 'azienda', TRUE),

-- Altri clienti comuni
('Electraline', NULL, NULL, NULL, 'azienda', TRUE),
('Comune Lentate', NULL, 'Lentate', NULL, 'ente_pubblico', TRUE),
('Spolidoro', NULL, NULL, NULL, 'azienda', TRUE),
('OR.VE.CA', NULL, NULL, NULL, 'azienda', TRUE),
('FGB Studio', NULL, NULL, NULL, 'azienda', TRUE),
('BAIT Service Srl', 'Via della Sede', 'Milano', 'MI', 'azienda', TRUE)
ON DUPLICATE KEY UPDATE
nome = VALUES(nome),
indirizzo = VALUES(indirizzo),
citta = VALUES(citta),
provincia = VALUES(provincia);

-- =====================================================
-- 3. POPOLAMENTO AUTO AZIENDALI (VEHICLES)
-- =====================================================

-- Auto aziendali da auto.csv
INSERT INTO auto_aziendali (targa, modello, marca, attiva) VALUES
('UNKNOWN_PEUGEOT', 'Peugeot', 'Peugeot', TRUE),
('UNKNOWN_FIESTA', 'Fiesta', 'Ford', TRUE)
ON DUPLICATE KEY UPDATE
modello = VALUES(modello),
marca = VALUES(marca);

-- =====================================================
-- 4. IMPORT TIMBRATURE (TIME TRACKING)
-- =====================================================

-- Preparazione tabella temporanea per import timbrature
CREATE TEMPORARY TABLE temp_timbrature (
    dipendente_nome VARCHAR(100),
    dipendente_cognome VARCHAR(100),
    cliente_nome VARCHAR(200),
    ora_inizio VARCHAR(50),
    ora_fine VARCHAR(50),
    ore DECIMAL(10,8),
    indirizzo_start VARCHAR(500),
    citta_start VARCHAR(100),
    provincia_start VARCHAR(10),
    indirizzo_end VARCHAR(500),
    citta_end VARCHAR(100),
    provincia_end VARCHAR(10),
    descrizione_attivita TEXT,
    ore_centesimi DECIMAL(10,8),
    ore_arrotondate DECIMAL(10,8),
    ore_nette_pause DECIMAL(10,8),
    timbratura_id_esterno VARCHAR(50)
);

-- Import dati reali da clean_timbrature.csv tramite LOAD DATA LOCAL INFILE
-- NOTA: Adatta il path per la tua configurazione
LOAD DATA LOCAL INFILE '/mnt/c/xampp/htdocs/controlli/data/processed/clean_timbrature.csv'
INTO TABLE temp_timbrature
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(dipendente_nome, dipendente_cognome, @dummy, cliente_nome, @dummy2, @dummy3, @dummy4, @dummy5, 
 ora_inizio, ora_fine, ore, indirizzo_start, citta_start, provincia_start, @dummy6,
 indirizzo_end, citta_end, provincia_end, @dummy7, descrizione_attivita, @dummy8,
 ore_centesimi, @dummy9, @dummy10, @dummy11, ore_nette_pause, @dummy12, @dummy13, @dummy14, @dummy15, @dummy16, @dummy17, @dummy18, timbratura_id_esterno);

-- Inserimento timbrature nel database con JOIN per gli ID
INSERT INTO timbrature (
    tecnico_id, cliente_id, data_timbratura, ora_inizio, ora_fine, 
    ore_calcolate, ore_centesimi, ore_nette_pause,
    indirizzo_start, citta_start, provincia_start,
    indirizzo_end, citta_end, provincia_end,
    descrizione_attivita, timbratura_id_esterno, stato_timbratura
)
SELECT 
    t.id as tecnico_id,
    c.id as cliente_id,
    DATE(STR_TO_DATE(tt.ora_inizio, '%Y-%m-%d %H:%i:%s')) as data_timbratura,
    STR_TO_DATE(tt.ora_inizio, '%Y-%m-%d %H:%i:%s') as ora_inizio,
    CASE 
        WHEN tt.ora_fine IS NOT NULL AND tt.ora_fine != '' 
        THEN STR_TO_DATE(tt.ora_fine, '%Y-%m-%d %H:%i:%s')
        ELSE NULL 
    END as ora_fine,
    tt.ore as ore_calcolate,
    tt.ore_centesimi,
    tt.ore_nette_pause,
    tt.indirizzo_start,
    tt.citta_start,
    tt.provincia_start,
    tt.indirizzo_end,
    tt.citta_end,
    tt.provincia_end,
    tt.descrizione_attivita,
    tt.timbratura_id_esterno,
    'completata' as stato_timbratura
FROM temp_timbrature tt
JOIN tecnici t ON CONCAT(t.nome, ' ', t.cognome) = CONCAT(tt.dipendente_nome, ' ', tt.dipendente_cognome)
JOIN clienti c ON c.nome_normalizzato = UPPER(TRIM(tt.cliente_nome))
WHERE tt.ora_inizio IS NOT NULL AND tt.ora_inizio != '';

-- =====================================================
-- 5. IMPORT TEAMVIEWER SESSIONS
-- =====================================================

-- Preparazione tabella temporanea per TeamViewer
CREATE TEMPORARY TABLE temp_teamviewer (
    assegnatario VARCHAR(200),
    nome_cliente VARCHAR(200),
    codice VARCHAR(50),
    tipo_sessione VARCHAR(100),
    gruppo VARCHAR(100),
    inizio VARCHAR(50),
    fine VARCHAR(50),
    durata VARCHAR(20),
    note TEXT,
    classificazione VARCHAR(100),
    commenti_cliente TEXT,
    durata_minuti DECIMAL(10,2),
    durata_ore DECIMAL(10,6)
);

-- Import TeamViewer data
LOAD DATA LOCAL INFILE '/mnt/c/xampp/htdocs/controlli/data/processed/clean_teamviewer_bait.csv'
INTO TABLE temp_teamviewer
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(assegnatario, nome_cliente, codice, tipo_sessione, gruppo, inizio, fine, durata, 
 note, classificazione, commenti_cliente, @dummy, @dummy2, durata_minuti, durata_ore);

-- Inserimento sessioni TeamViewer
INSERT INTO teamviewer_sessioni (
    tecnico_id, cliente_id, codice_sessione, tipo_sessione, gruppo,
    inizio, fine, durata_minuti, durata_ore, note, classificazione, 
    commenti_cliente, stato
)
SELECT 
    t.id as tecnico_id,
    c.id as cliente_id,
    tt.codice as codice_sessione,
    tt.tipo_sessione,
    tt.gruppo,
    STR_TO_DATE(tt.inizio, '%Y-%m-%d %H:%i:%s') as inizio,
    CASE 
        WHEN tt.fine IS NOT NULL AND tt.fine != '' 
        THEN STR_TO_DATE(tt.fine, '%Y-%m-%d %H:%i:%s')
        ELSE NULL 
    END as fine,
    tt.durata_minuti,
    tt.durata_ore,
    tt.note,
    tt.classificazione,
    tt.commenti_cliente,
    'completata' as stato
FROM temp_teamviewer tt
JOIN tecnici t ON t.nome_completo = tt.assegnatario
JOIN clienti c ON c.nome_normalizzato = UPPER(TRIM(tt.nome_cliente))
WHERE tt.inizio IS NOT NULL AND tt.inizio != '';

-- =====================================================
-- 6. IMPORT UTILIZZO AUTO (VEHICLE USAGE)
-- =====================================================

-- Preparazione tabella temporanea per utilizzo auto
CREATE TEMPORARY TABLE temp_auto (
    dipendente VARCHAR(200),
    data_utilizzo VARCHAR(20),
    auto VARCHAR(100),
    presa_data_ora VARCHAR(50),
    riconsegna_data_ora VARCHAR(50),
    cliente VARCHAR(200)
);

-- Import dati auto
LOAD DATA LOCAL INFILE '/mnt/c/xampp/htdocs/controlli/data/processed/clean_auto.csv'
INTO TABLE temp_auto
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(dipendente, data_utilizzo, auto, presa_data_ora, riconsegna_data_ora, cliente, @dummy, @dummy2, @dummy3, @dummy4, @dummy5);

-- Inserimento utilizzo auto
INSERT INTO utilizzo_auto (
    tecnico_id, auto_id, cliente_id, data_utilizzo, ora_presa, ora_riconsegna, 
    destinazione, stato
)
SELECT 
    t.id as tecnico_id,
    a.id as auto_id,
    c.id as cliente_id,
    STR_TO_DATE(ta.data_utilizzo, '%d/%m/%Y') as data_utilizzo,
    STR_TO_DATE(ta.presa_data_ora, '%Y-%m-%d %H:%i:%s') as ora_presa,
    CASE 
        WHEN ta.riconsegna_data_ora IS NOT NULL AND ta.riconsegna_data_ora != '' 
        THEN STR_TO_DATE(ta.riconsegna_data_ora, '%Y-%m-%d %H:%i:%s')
        ELSE NULL 
    END as ora_riconsegna,
    ta.cliente as destinazione,
    CASE 
        WHEN ta.riconsegna_data_ora IS NOT NULL AND ta.riconsegna_data_ora != '' 
        THEN 'riconsegnata'
        ELSE 'in_uso' 
    END as stato
FROM temp_auto ta
JOIN tecnici t ON t.nome_completo = ta.dipendente
LEFT JOIN auto_aziendali a ON (
    (ta.auto = 'Peugeot' AND a.modello = 'Peugeot') OR
    (ta.auto = 'Fiesta' AND a.modello = 'Fiesta')
)
LEFT JOIN clienti c ON c.nome_normalizzato = UPPER(TRIM(ta.cliente))
WHERE ta.presa_data_ora IS NOT NULL AND ta.presa_data_ora != ''
AND ta.dipendente IS NOT NULL AND ta.dipendente != '';

-- =====================================================
-- 7. IMPORT PERMESSI (LEAVES AND PERMISSIONS)
-- =====================================================

-- Preparazione tabella temporanea per permessi
CREATE TEMPORARY TABLE temp_permessi (
    data_richiesta VARCHAR(50),
    dipendente VARCHAR(200),
    tipo VARCHAR(100),
    data_inizio VARCHAR(20),
    data_fine VARCHAR(20),
    stato VARCHAR(50),
    note TEXT
);

-- Import permessi
LOAD DATA LOCAL INFILE '/mnt/c/xampp/htdocs/controlli/data/processed/clean_permessi.csv'
INTO TABLE temp_permessi
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(data_richiesta, dipendente, tipo, data_inizio, data_fine, stato, note);

-- Normalizzazione nomi dipendenti per match con tecnici
UPDATE temp_permessi 
SET dipendente = REPLACE(REPLACE(dipendente, '"', ''), 'De Palma, Gabriele', 'Gabriele De Palma');
UPDATE temp_permessi 
SET dipendente = REPLACE(dipendente, 'Cestone, Davide', 'Davide Cestone');
UPDATE temp_permessi 
SET dipendente = REPLACE(dipendente, 'Hoxha, Arlind', 'Arlind Hoxha');
UPDATE temp_permessi 
SET dipendente = REPLACE(dipendente, 'Ferrario, Alex', 'Alex Ferrario');
UPDATE temp_permessi 
SET dipendente = REPLACE(dipendente, 'Di Salvo, Matteo', 'Matteo Di Salvo');
UPDATE temp_permessi 
SET dipendente = REPLACE(dipendente, 'Birocchi, Marco', 'Marco Birocchi');

-- Inserimento permessi
INSERT INTO permessi (
    tecnico_id, tipo, data_richiesta, data_inizio, data_fine, stato, note
)
SELECT 
    t.id as tecnico_id,
    CASE 
        WHEN LOWER(tp.tipo) LIKE '%ferie%' THEN 'ferie'
        WHEN LOWER(tp.tipo) LIKE '%permess%' THEN 'permesso'
        WHEN LOWER(tp.tipo) LIKE '%malatt%' THEN 'malattia'
        WHEN LOWER(tp.tipo) LIKE '%festivit%' THEN 'permessi_ex_festivita'
        WHEN LOWER(tp.tipo) LIKE '%donazione%' THEN 'donazione_sangue'
        ELSE 'altro'
    END as tipo,
    STR_TO_DATE(tp.data_richiesta, '%Y-%m-%d %H:%i:%s') as data_richiesta,
    CASE 
        WHEN tp.data_inizio IS NOT NULL AND tp.data_inizio != '' 
        THEN STR_TO_DATE(tp.data_inizio, '%Y-%m-%d')
        ELSE NULL 
    END as data_inizio,
    CASE 
        WHEN tp.data_fine IS NOT NULL AND tp.data_fine != '' 
        THEN STR_TO_DATE(tp.data_fine, '%Y-%m-%d')
        ELSE NULL 
    END as data_fine,
    CASE 
        WHEN LOWER(tp.stato) LIKE '%approv%' THEN 'approvata'
        WHEN LOWER(tp.stato) LIKE '%rifiut%' THEN 'rifiutata'
        WHEN LOWER(tp.stato) LIKE '%annull%' THEN 'annullata'
        ELSE 'da_approvare'
    END as stato,
    tp.note
FROM temp_permessi tp
JOIN tecnici t ON t.nome_completo = tp.dipendente
WHERE tp.data_richiesta IS NOT NULL AND tp.data_richiesta != '';

-- =====================================================
-- 8. PULIZIA E OTTIMIZZAZIONE
-- =====================================================

-- Drop temporary tables
DROP TEMPORARY TABLE IF EXISTS temp_timbrature;
DROP TEMPORARY TABLE IF EXISTS temp_teamviewer;
DROP TEMPORARY TABLE IF EXISTS temp_auto;
DROP TEMPORARY TABLE IF EXISTS temp_permessi;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- 9. CALCOLO KPI INIZIALI
-- =====================================================

-- Calcolo KPI giornalieri per le date presenti
INSERT INTO kpi_giornalieri (data_calcolo, tecnico_id, ore_lavorate, ore_fatturabili, ore_teamviewer, numero_clienti_serviti, numero_sovrapposizioni)
SELECT 
    data_timbratura as data_calcolo,
    tecnico_id,
    COALESCE(SUM(ore_nette_pause), 0) as ore_lavorate,
    COALESCE(SUM(ore_nette_pause), 0) as ore_fatturabili,
    COALESCE(tv.ore_teamviewer, 0) as ore_teamviewer,
    COUNT(DISTINCT cliente_id) as numero_clienti_serviti,
    0 as numero_sovrapposizioni
FROM timbrature t
LEFT JOIN (
    SELECT 
        tecnico_id,
        DATE(inizio) as data_sessione,
        SUM(durata_ore) as ore_teamviewer
    FROM teamviewer_sessioni 
    GROUP BY tecnico_id, DATE(inizio)
) tv ON t.tecnico_id = tv.tecnico_id AND t.data_timbratura = tv.data_sessione
WHERE t.stato_timbratura = 'completata'
GROUP BY data_timbratura, tecnico_id
ON DUPLICATE KEY UPDATE
ore_lavorate = VALUES(ore_lavorate),
ore_fatturabili = VALUES(ore_fatturabili),
ore_teamviewer = VALUES(ore_teamviewer),
numero_clienti_serviti = VALUES(numero_clienti_serviti);

-- =====================================================
-- 10. VERIFY IMPORT SUCCESS
-- =====================================================

-- Report risultati import
SELECT 'IMPORT COMPLETATO - STATISTICHE:' as status;
SELECT 'Tecnici inseriti:' as tipo, COUNT(*) as totale FROM tecnici;
SELECT 'Clienti inseriti:' as tipo, COUNT(*) as totale FROM clienti;
SELECT 'Timbrature inserite:' as tipo, COUNT(*) as totale FROM timbrature;
SELECT 'Sessioni TeamViewer inserite:' as tipo, COUNT(*) as totale FROM teamviewer_sessioni;
SELECT 'Utilizzi auto inseriti:' as tipo, COUNT(*) as totale FROM utilizzo_auto;
SELECT 'Permessi inseriti:' as tipo, COUNT(*) as totale FROM permessi;
SELECT 'KPI giornalieri calcolati:' as tipo, COUNT(*) as totale FROM kpi_giornalieri;

-- Verifica date range dati
SELECT 
    'Range timbrature:' as info,
    MIN(data_timbratura) as data_min,
    MAX(data_timbratura) as data_max,
    COUNT(*) as totale_records
FROM timbrature;

SELECT 
    'Range TeamViewer:' as info,
    MIN(DATE(inizio)) as data_min,
    MAX(DATE(inizio)) as data_max,
    COUNT(*) as totale_records
FROM teamviewer_sessioni;

SELECT 'Database bait_service_real popolato con successo!' as final_status;