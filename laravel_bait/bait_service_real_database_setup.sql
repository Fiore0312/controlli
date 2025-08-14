-- =====================================================
-- BAIT SERVICE ENTERPRISE DATABASE SCHEMA
-- Database: bait_service_real
-- Created: 2025-08-12
-- Purpose: Production MySQL schema for real business data
-- =====================================================

-- Drop and recreate database
DROP DATABASE IF EXISTS bait_service_real;
CREATE DATABASE bait_service_real 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE bait_service_real;

-- =====================================================
-- 1. CORE ENTITIES TABLES
-- =====================================================

-- Tecnici (Staff/Technicians)
CREATE TABLE tecnici (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100) NOT NULL,
    nome_completo VARCHAR(200) GENERATED ALWAYS AS (CONCAT(nome, ' ', cognome)) STORED,
    email VARCHAR(255) UNIQUE,
    telefono VARCHAR(50),
    codice_dipendente VARCHAR(50) UNIQUE,
    attivo BOOLEAN DEFAULT TRUE,
    ore_standard_giornaliere DECIMAL(4,2) DEFAULT 8.00,
    tipo_contratto ENUM('full_time', 'part_time', 'consulente') DEFAULT 'full_time',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_nome_completo (nome_completo),
    INDEX idx_attivo (attivo),
    INDEX idx_codice_dipendente (codice_dipendente)
) ENGINE=InnoDB;

-- Clienti (Clients)
CREATE TABLE clienti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    nome_normalizzato VARCHAR(200) GENERATED ALWAYS AS (UPPER(TRIM(nome))) STORED,
    indirizzo VARCHAR(500),
    citta VARCHAR(100),
    provincia VARCHAR(10),
    cap VARCHAR(10),
    telefono VARCHAR(50),
    email VARCHAR(255),
    codice_gestionale VARCHAR(50),
    tipo_cliente ENUM('azienda', 'privato', 'ente_pubblico') DEFAULT 'azienda',
    attivo BOOLEAN DEFAULT TRUE,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_nome_normalizzato (nome_normalizzato),
    INDEX idx_citta_provincia (citta, provincia),
    INDEX idx_attivo (attivo),
    INDEX idx_codice_gestionale (codice_gestionale),
    FULLTEXT idx_search_cliente (nome, indirizzo, citta)
) ENGINE=InnoDB;

-- Auto aziendali (Company Vehicles)
CREATE TABLE auto_aziendali (
    id INT AUTO_INCREMENT PRIMARY KEY,
    targa VARCHAR(20) NOT NULL UNIQUE,
    modello VARCHAR(100) NOT NULL,
    marca VARCHAR(50),
    anno_immatricolazione YEAR,
    carburante ENUM('benzina', 'diesel', 'ibrida', 'elettrica') DEFAULT 'benzina',
    km_attuali INT DEFAULT 0,
    attiva BOOLEAN DEFAULT TRUE,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_targa (targa),
    INDEX idx_attiva (attiva),
    INDEX idx_modello (modello)
) ENGINE=InnoDB;

-- =====================================================
-- 2. ACTIVITY TRACKING TABLES
-- =====================================================

-- Timbrature (Time Tracking)
CREATE TABLE timbrature (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tecnico_id INT NOT NULL,
    cliente_id INT NOT NULL,
    data_timbratura DATE NOT NULL,
    ora_inizio DATETIME NOT NULL,
    ora_fine DATETIME,
    ore_calcolate DECIMAL(5,2),
    ore_arrotondate DECIMAL(5,2),
    ore_centesimi DECIMAL(5,2),
    ore_nette_pause DECIMAL(5,2),
    pausa_minuti INT DEFAULT 0,
    indirizzo_start VARCHAR(500),
    citta_start VARCHAR(100),
    provincia_start VARCHAR(10),
    indirizzo_end VARCHAR(500),
    citta_end VARCHAR(100),
    provincia_end VARCHAR(10),
    descrizione_attivita TEXT,
    stato_timbratura ENUM('in_corso', 'completata', 'sospesa', 'annullata') DEFAULT 'completata',
    timbratura_id_esterno VARCHAR(50),
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tecnico_id) REFERENCES tecnici(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE,
    
    INDEX idx_tecnico_data (tecnico_id, data_timbratura),
    INDEX idx_cliente_data (cliente_id, data_timbratura),
    INDEX idx_ora_inizio_fine (ora_inizio, ora_fine),
    INDEX idx_stato (stato_timbratura),
    INDEX idx_data_timbratura (data_timbratura),
    INDEX idx_timbratura_esterno (timbratura_id_esterno),
    
    -- Performance index for overlap detection
    INDEX idx_overlap_detection (tecnico_id, ora_inizio, ora_fine),
    INDEX idx_cliente_overlap (cliente_id, ora_inizio, ora_fine)
) ENGINE=InnoDB;

-- TeamViewer Sessions (Remote Sessions)
CREATE TABLE teamviewer_sessioni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tecnico_id INT NOT NULL,
    cliente_id INT NOT NULL,
    codice_sessione VARCHAR(50) NOT NULL,
    tipo_sessione VARCHAR(100) DEFAULT 'Controllo remoto',
    gruppo VARCHAR(100),
    inizio DATETIME NOT NULL,
    fine DATETIME,
    durata_minuti INT,
    durata_ore DECIMAL(5,2),
    note TEXT,
    classificazione VARCHAR(100),
    commenti_cliente TEXT,
    stato ENUM('attiva', 'completata', 'interrotta') DEFAULT 'completata',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tecnico_id) REFERENCES tecnici(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE,
    
    INDEX idx_tecnico_data (tecnico_id, DATE(inizio)),
    INDEX idx_cliente_data (cliente_id, DATE(inizio)),
    INDEX idx_codice_sessione (codice_sessione),
    INDEX idx_inizio_fine (inizio, fine),
    INDEX idx_durata (durata_ore),
    INDEX idx_stato (stato),
    
    -- Performance index for overlap detection with timbrature
    INDEX idx_overlap_timbrature (tecnico_id, inizio, fine)
) ENGINE=InnoDB;

-- Utilizzo Auto (Vehicle Usage)
CREATE TABLE utilizzo_auto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tecnico_id INT NOT NULL,
    auto_id INT NOT NULL,
    cliente_id INT,
    data_utilizzo DATE NOT NULL,
    ora_presa DATETIME NOT NULL,
    ora_riconsegna DATETIME,
    km_partenza INT,
    km_arrivo INT,
    km_percorsi INT GENERATED ALWAYS AS (km_arrivo - km_partenza) STORED,
    destinazione VARCHAR(500),
    scopo_viaggio TEXT,
    carburante_litri DECIMAL(6,2),
    costo_carburante DECIMAL(8,2),
    note TEXT,
    stato ENUM('in_uso', 'riconsegnata', 'non_riconsegnata') DEFAULT 'riconsegnata',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tecnico_id) REFERENCES tecnici(id) ON DELETE CASCADE,
    FOREIGN KEY (auto_id) REFERENCES auto_aziendali(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE SET NULL,
    
    INDEX idx_tecnico_data (tecnico_id, data_utilizzo),
    INDEX idx_auto_data (auto_id, data_utilizzo),
    INDEX idx_cliente_data (cliente_id, data_utilizzo),
    INDEX idx_ora_presa_riconsegna (ora_presa, ora_riconsegna),
    INDEX idx_stato (stato),
    
    -- Performance index for overlap detection with timbrature
    INDEX idx_overlap_timbrature (tecnico_id, ora_presa, ora_riconsegna)
) ENGINE=InnoDB;

-- Permessi e Ferie (Leaves and Permissions)
CREATE TABLE permessi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tecnico_id INT NOT NULL,
    tipo ENUM('ferie', 'permesso', 'malattia', 'permessi_ex_festivita', 'donazione_sangue', 'altro') NOT NULL,
    data_richiesta DATETIME NOT NULL,
    data_inizio DATE,
    data_fine DATE,
    ore_permesso DECIMAL(4,2), -- Per permessi di ore
    stato ENUM('da_approvare', 'approvata', 'rifiutata', 'annullata') DEFAULT 'da_approvare',
    note TEXT,
    approvatore VARCHAR(100),
    data_approvazione DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tecnico_id) REFERENCES tecnici(id) ON DELETE CASCADE,
    
    INDEX idx_tecnico_data (tecnico_id, data_inizio, data_fine),
    INDEX idx_tipo (tipo),
    INDEX idx_stato (stato),
    INDEX idx_data_richiesta (data_richiesta),
    INDEX idx_periodo (data_inizio, data_fine)
) ENGINE=InnoDB;

-- =====================================================
-- 3. BUSINESS INTELLIGENCE TABLES
-- =====================================================

-- Sovrapposizioni Rilevate (Detected Overlaps)
CREATE TABLE sovrapposizioni_rilevate (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tecnico_id INT NOT NULL,
    data_conflitto DATE NOT NULL,
    tipo_conflitto ENUM('stesso_cliente', 'clienti_diversi', 'timbratura_teamviewer', 'timbratura_auto', 'teamviewer_auto') NOT NULL,
    gravita ENUM('critica', 'alta', 'media', 'bassa') NOT NULL,
    
    -- Riferimenti alle attività in conflitto
    timbratura_1_id INT,
    timbratura_2_id INT,
    teamviewer_id INT,
    auto_id INT,
    
    descrizione_conflitto TEXT NOT NULL,
    ore_sovrapposte DECIMAL(5,2),
    fatturazione_impatto ENUM('perdita_ricavi', 'doppia_fatturazione', 'nessun_impatto') DEFAULT 'nessun_impatto',
    
    stato_risoluzione ENUM('nuovo', 'in_verifica', 'risolto', 'ignorato') DEFAULT 'nuovo',
    risoluzione_note TEXT,
    risolto_da VARCHAR(100),
    risolto_il DATETIME,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tecnico_id) REFERENCES tecnici(id) ON DELETE CASCADE,
    FOREIGN KEY (timbratura_1_id) REFERENCES timbrature(id) ON DELETE CASCADE,
    FOREIGN KEY (timbratura_2_id) REFERENCES timbrature(id) ON DELETE CASCADE,
    FOREIGN KEY (teamviewer_id) REFERENCES teamviewer_sessioni(id) ON DELETE CASCADE,
    FOREIGN KEY (auto_id) REFERENCES utilizzo_auto(id) ON DELETE CASCADE,
    
    INDEX idx_tecnico_data_gravita (tecnico_id, data_conflitto, gravita),
    INDEX idx_tipo_gravita (tipo_conflitto, gravita),
    INDEX idx_stato (stato_risoluzione),
    INDEX idx_fatturazione_impatto (fatturazione_impatto),
    INDEX idx_data_conflitto (data_conflitto)
) ENGINE=InnoDB;

-- KPI Giornalieri (Daily KPIs)
CREATE TABLE kpi_giornalieri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_calcolo DATE NOT NULL,
    tecnico_id INT,
    
    -- KPI Operativi
    ore_lavorate DECIMAL(5,2) DEFAULT 0,
    ore_fatturabili DECIMAL(5,2) DEFAULT 0,
    ore_teamviewer DECIMAL(5,2) DEFAULT 0,
    ore_trasferta DECIMAL(5,2) DEFAULT 0,
    
    -- KPI Efficienza
    percentuale_produttivita DECIMAL(5,2) DEFAULT 0,
    numero_clienti_serviti INT DEFAULT 0,
    numero_sovrapposizioni INT DEFAULT 0,
    
    -- KPI Qualità
    tempo_medio_per_cliente DECIMAL(5,2) DEFAULT 0,
    distanza_media_trasferte DECIMAL(8,2) DEFAULT 0,
    
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tecnico_id) REFERENCES tecnici(id) ON DELETE CASCADE,
    
    UNIQUE KEY idx_unique_daily_kpi (data_calcolo, tecnico_id),
    INDEX idx_data_calcolo (data_calcolo),
    INDEX idx_tecnico_data (tecnico_id, data_calcolo),
    INDEX idx_produttivita (percentuale_produttivita),
    INDEX idx_sovrapposizioni (numero_sovrapposizioni)
) ENGINE=InnoDB;

-- Alert e Notifiche (Alerts and Notifications)
CREATE TABLE alert_notifiche (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('sovrapposizione', 'orario_anomalo', 'auto_non_riconsegnata', 'teamviewer_lungo', 'permesso_scaduto') NOT NULL,
    gravita ENUM('critica', 'alta', 'media', 'bassa') NOT NULL,
    tecnico_id INT,
    cliente_id INT,
    
    titolo VARCHAR(255) NOT NULL,
    messaggio TEXT NOT NULL,
    
    -- Riferimenti alle entità correlate
    timbratura_id INT,
    teamviewer_id INT,
    auto_id INT,
    permesso_id INT,
    
    stato ENUM('nuovo', 'letto', 'in_gestione', 'risolto', 'ignorato') DEFAULT 'nuovo',
    priorita INT DEFAULT 5, -- 1=massima, 10=minima
    
    -- Gestione notifiche email
    email_inviata BOOLEAN DEFAULT FALSE,
    email_inviata_il DATETIME,
    destinatari_email TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tecnico_id) REFERENCES tecnici(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE,
    FOREIGN KEY (timbratura_id) REFERENCES timbrature(id) ON DELETE CASCADE,
    FOREIGN KEY (teamviewer_id) REFERENCES teamviewer_sessioni(id) ON DELETE CASCADE,
    FOREIGN KEY (auto_id) REFERENCES utilizzo_auto(id) ON DELETE CASCADE,
    FOREIGN KEY (permesso_id) REFERENCES permessi(id) ON DELETE CASCADE,
    
    INDEX idx_tipo_gravita (tipo, gravita),
    INDEX idx_stato (stato),
    INDEX idx_tecnico_data (tecnico_id, created_at),
    INDEX idx_priorita (priorita),
    INDEX idx_email_inviata (email_inviata)
) ENGINE=InnoDB;

-- =====================================================
-- 4. CONFIGURATION AND SYSTEM TABLES
-- =====================================================

-- Configurazioni Sistema (System Configuration)
CREATE TABLE configurazioni_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chiave VARCHAR(100) NOT NULL UNIQUE,
    valore TEXT,
    tipo ENUM('string', 'integer', 'decimal', 'boolean', 'json') DEFAULT 'string',
    descrizione TEXT,
    categoria VARCHAR(50) DEFAULT 'generale',
    modificabile BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_chiave (chiave),
    INDEX idx_categoria (categoria)
) ENGINE=InnoDB;

-- Log Attività Sistema (System Activity Log)
CREATE TABLE log_attivita_sistema (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    azione VARCHAR(100) NOT NULL,
    tabella_interessata VARCHAR(100),
    record_id INT,
    dettagli JSON,
    utente VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_azione (azione),
    INDEX idx_tabella_data (tabella_interessata, created_at),
    INDEX idx_utente_data (utente, created_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- 5. OPTIMIZED PERFORMANCE VIEWS
-- =====================================================

-- Vista Attività Giornaliere Complete
CREATE VIEW v_attivita_giornaliere AS
SELECT 
    t.id as tecnico_id,
    t.nome_completo as tecnico,
    c.nome as cliente,
    DATE(tim.ora_inizio) as data_attivita,
    'timbratura' as tipo_attivita,
    tim.ora_inizio,
    tim.ora_fine,
    tim.ore_nette_pause as ore_totali,
    tim.descrizione_attivita as descrizione,
    tim.stato_timbratura as stato
FROM timbrature tim
JOIN tecnici t ON tim.tecnico_id = t.id
JOIN clienti c ON tim.cliente_id = c.id
WHERE tim.stato_timbratura = 'completata'

UNION ALL

SELECT 
    t.id as tecnico_id,
    t.nome_completo as tecnico,
    c.nome as cliente,
    DATE(tv.inizio) as data_attivita,
    'teamviewer' as tipo_attivita,
    tv.inizio as ora_inizio,
    tv.fine as ora_fine,
    tv.durata_ore as ore_totali,
    CONCAT('TeamViewer - ', tv.codice_sessione) as descrizione,
    tv.stato
FROM teamviewer_sessioni tv
JOIN tecnici t ON tv.tecnico_id = t.id
JOIN clienti c ON tv.cliente_id = c.id
WHERE tv.stato = 'completata';

-- Vista KPI Dashboard Real-time
CREATE VIEW v_kpi_dashboard AS
SELECT 
    t.id as tecnico_id,
    t.nome_completo as tecnico,
    CURDATE() as data,
    
    -- Ore lavorate oggi
    COALESCE(SUM(tim.ore_nette_pause), 0) as ore_timbrature_oggi,
    COALESCE(SUM(tv.durata_ore), 0) as ore_teamviewer_oggi,
    
    -- Contatori attività
    COUNT(DISTINCT tim.cliente_id) as clienti_serviti_oggi,
    COUNT(DISTINCT tim.id) as timbrature_oggi,
    COUNT(DISTINCT tv.id) as sessioni_teamviewer_oggi,
    
    -- Efficienza
    CASE 
        WHEN COALESCE(SUM(tim.ore_nette_pause), 0) > 0 
        THEN ROUND((COALESCE(SUM(tim.ore_nette_pause), 0) / 8.0) * 100, 2)
        ELSE 0 
    END as percentuale_produttivita,
    
    -- Sovrapposizioni attive
    COALESCE(sovr.sovrapposizioni_attive, 0) as sovrapposizioni_attive
    
FROM tecnici t
LEFT JOIN timbrature tim ON t.id = tim.tecnico_id 
    AND DATE(tim.ora_inizio) = CURDATE()
    AND tim.stato_timbratura = 'completata'
LEFT JOIN teamviewer_sessioni tv ON t.id = tv.tecnico_id 
    AND DATE(tv.inizio) = CURDATE()
    AND tv.stato = 'completata'
LEFT JOIN (
    SELECT 
        tecnico_id,
        COUNT(*) as sovrapposizioni_attive
    FROM sovrapposizioni_rilevate 
    WHERE data_conflitto = CURDATE() 
    AND stato_risoluzione IN ('nuovo', 'in_verifica')
    GROUP BY tecnico_id
) sovr ON t.id = sovr.tecnico_id
WHERE t.attivo = TRUE
GROUP BY t.id, t.nome_completo;

-- =====================================================
-- 6. INITIAL CONFIGURATION DATA
-- =====================================================

-- Inserimento configurazioni di default
INSERT INTO configurazioni_sistema (chiave, valore, tipo, descrizione, categoria) VALUES
('orario_standard_inizio_mattino', '09:00', 'string', 'Orario standard inizio turno mattutino', 'orari'),
('orario_standard_fine_mattino', '13:00', 'string', 'Orario standard fine turno mattutino', 'orari'),
('orario_standard_inizio_pomeriggio', '14:00', 'string', 'Orario standard inizio turno pomeridiano', 'orari'),
('orario_standard_fine_pomeriggio', '18:00', 'string', 'Orario standard fine turno pomeridiano', 'orari'),
('ore_lavorative_giornaliere_standard', '8.00', 'decimal', 'Numero ore lavorative standard per giornata', 'orari'),
('soglia_sovrapposizione_minuti', '15', 'integer', 'Soglia minima in minuti per rilevare sovrapposizioni', 'business_rules'),
('email_notifiche_attive', 'true', 'boolean', 'Attivazione notifiche email automatiche', 'notifiche'),
('auto_backup_giorni', '30', 'integer', 'Giorni di retention per backup automatici', 'sistema'),
('dashboard_refresh_seconds', '30', 'integer', 'Intervallo refresh dashboard in secondi', 'ui');

-- =====================================================
-- GRANT PERMISSIONS FOR LARAVEL USER
-- =====================================================

-- Create specific user for Laravel application (adjust password)
-- CREATE USER 'laravel_bait'@'localhost' IDENTIFIED BY 'secure_password_here';
-- GRANT ALL PRIVILEGES ON bait_service_real.* TO 'laravel_bait'@'localhost';
-- FLUSH PRIVILEGES;

-- =====================================================
-- SCRIPT COMPLETION
-- =====================================================

SELECT 'Database bait_service_real created successfully!' as status;
SELECT COUNT(*) as total_tables FROM information_schema.tables WHERE table_schema = 'bait_service_real';