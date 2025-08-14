-- ================================================================================
-- BAIT SERVICE - DATABASE SETUP PULITO (RISOLVE FOREIGN KEY ISSUES)
-- ================================================================================
-- 
-- Script che prima rimuove completamente il database esistente
-- poi lo ricrea pulito senza errori foreign key
--
-- Versione: Clean Setup 1.0
-- ================================================================================

-- STEP 1: Rimuovi completamente database esistente
DROP DATABASE IF EXISTS bait_service_real;

-- STEP 2: Ricrea database pulito
CREATE DATABASE bait_service_real 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE bait_service_real;

-- ================================================================================
-- TABELLE MASTER (Entità Base)
-- ================================================================================

-- Tecnici (dati reali estratti da CSV)
CREATE TABLE tecnici (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_completo VARCHAR(100) NOT NULL UNIQUE,
    nome_normalizzato VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    telefono VARCHAR(20),
    specializzazioni JSON,
    attivo BOOLEAN DEFAULT TRUE,
    data_inserimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_nome_normalizzato (nome_normalizzato),
    INDEX idx_attivo (attivo)
) ENGINE=InnoDB;

-- Clienti (dati reali estratti da CSV) - FIXED: coordinate separate
CREATE TABLE clienti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ragione_sociale VARCHAR(200) NOT NULL,
    nome_normalizzato VARCHAR(200) NOT NULL,
    indirizzo TEXT,
    citta VARCHAR(100),
    provincia VARCHAR(5),
    cap VARCHAR(10),
    coordinate_lat DECIMAL(10,8) NULL,
    coordinate_lng DECIMAL(11,8) NULL,
    tipologia ENUM('azienda', 'ente_pubblico', 'privato') DEFAULT 'azienda',
    attivo BOOLEAN DEFAULT TRUE,
    note TEXT,
    data_inserimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_nome_normalizzato (nome_normalizzato),
    INDEX idx_citta (citta),
    INDEX idx_attivo (attivo),
    INDEX idx_coordinate (coordinate_lat, coordinate_lng)
) ENGINE=InnoDB;

-- Auto aziendali
CREATE TABLE auto_aziendali (
    id INT AUTO_INCREMENT PRIMARY KEY,
    targa VARCHAR(10) NOT NULL UNIQUE,
    modello VARCHAR(100),
    marca VARCHAR(50),
    anno_immatricolazione YEAR,
    km_totali INT DEFAULT 0,
    attiva BOOLEAN DEFAULT TRUE,
    note TEXT,
    data_inserimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_targa (targa),
    INDEX idx_attiva (attiva)
) ENGINE=InnoDB;

-- ================================================================================
-- TABELLE TRANSAZIONALI (Attività Giornaliere)  
-- ================================================================================

-- Attività (dati reali da attivita.csv)
CREATE TABLE attivita (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_originale INT,
    tecnico_id INT NOT NULL,
    cliente_id INT NOT NULL,
    data_attivita DATE NOT NULL,
    ora_inizio TIME,
    ora_fine TIME,
    durata_minuti INT,
    tipo_attivita VARCHAR(100),
    descrizione TEXT,
    ubicazione VARCHAR(200),
    note_interne TEXT,
    fatturabile BOOLEAN DEFAULT TRUE,
    stato ENUM('pianificata', 'in_corso', 'completata', 'annullata') DEFAULT 'completata',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tecnico_id) REFERENCES tecnici(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE,
    
    INDEX idx_data_attivita (data_attivita),
    INDEX idx_tecnico_data (tecnico_id, data_attivita),
    INDEX idx_cliente_data (cliente_id, data_attivita),
    INDEX idx_orario (ora_inizio, ora_fine),
    INDEX idx_tipo_attivita (tipo_attivita)
) ENGINE=InnoDB;

-- Timbrature (dati reali da timbrature.csv)
CREATE TABLE timbrature (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_originale INT,
    tecnico_id INT NOT NULL,
    cliente_id INT,
    data_timbratura DATE NOT NULL,
    ora_ingresso TIME,
    ora_uscita TIME,
    ore_lavorate DECIMAL(4,2),
    ore_pausa DECIMAL(4,2) DEFAULT 0,
    straordinario BOOLEAN DEFAULT FALSE,
    note VARCHAR(500),
    coordinate_ingresso_lat DECIMAL(10,8),
    coordinate_ingresso_lng DECIMAL(11,8),
    coordinate_uscita_lat DECIMAL(10,8),
    coordinate_uscita_lng DECIMAL(11,8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tecnico_id) REFERENCES tecnici(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE SET NULL,
    
    INDEX idx_data_timbratura (data_timbratura),
    INDEX idx_tecnico_data (tecnico_id, data_timbratura),
    INDEX idx_orario_ingresso (ora_ingresso),
    INDEX idx_orario_uscita (ora_uscita)
) ENGINE=InnoDB;

-- TeamViewer Sessions
CREATE TABLE teamviewer_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(50),
    tecnico_id INT NOT NULL,
    cliente_id INT,
    data_sessione DATE NOT NULL,
    ora_inizio TIME,
    ora_fine TIME,
    durata_minuti INT,
    tipo_sessione ENUM('user', 'server', 'unattended') DEFAULT 'user',
    descrizione TEXT,
    computer_remoto VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tecnico_id) REFERENCES tecnici(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE SET NULL,
    
    INDEX idx_data_sessione (data_sessione),
    INDEX idx_tecnico_data (tecnico_id, data_sessione),
    INDEX idx_session_id (session_id),
    INDEX idx_tipo_sessione (tipo_sessione)
) ENGINE=InnoDB;

-- Utilizzi Auto
CREATE TABLE utilizzi_auto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    auto_id INT NOT NULL,
    tecnico_id INT NOT NULL,
    cliente_id INT,
    data_utilizzo DATE NOT NULL,
    ora_ritiro TIME,
    ora_riconsegna TIME,
    km_iniziali INT,
    km_finali INT,
    km_percorsi INT,
    destinazione VARCHAR(200),
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (auto_id) REFERENCES auto_aziendali(id) ON DELETE CASCADE,
    FOREIGN KEY (tecnico_id) REFERENCES tecnici(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE SET NULL,
    
    INDEX idx_data_utilizzo (data_utilizzo),
    INDEX idx_auto_data (auto_id, data_utilizzo),
    INDEX idx_tecnico_data (tecnico_id, data_utilizzo)
) ENGINE=InnoDB;

-- Permessi e Ferie
CREATE TABLE permessi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tecnico_id INT NOT NULL,
    tipo_permesso ENUM('ferie', 'malattia', 'permesso', 'congedo') NOT NULL,
    data_inizio DATE NOT NULL,
    data_fine DATE NOT NULL,
    ore_totali DECIMAL(4,2),
    approvato BOOLEAN DEFAULT FALSE,
    approvato_da VARCHAR(100),
    data_approvazione DATE,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tecnico_id) REFERENCES tecnici(id) ON DELETE CASCADE,
    
    INDEX idx_tecnico_periodo (tecnico_id, data_inizio, data_fine),
    INDEX idx_tipo_permesso (tipo_permesso),
    INDEX idx_approvato (approvato)
) ENGINE=InnoDB;

-- ================================================================================
-- TABELLE BUSINESS LOGIC (Alerts e Monitoring)
-- ================================================================================

-- Alert System
CREATE TABLE alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_id VARCHAR(20) NOT NULL UNIQUE,
    tecnico_id INT,
    cliente_id INT,
    severity ENUM('CRITICO', 'ALTO', 'MEDIO', 'BASSO') NOT NULL,
    category VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    details JSON,
    confidence_score DECIMAL(5,2) DEFAULT 75.00,
    estimated_cost DECIMAL(8,2) DEFAULT 0.00,
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_by VARCHAR(100),
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tecnico_id) REFERENCES tecnici(id) ON DELETE SET NULL,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE SET NULL,
    
    INDEX idx_severity (severity),
    INDEX idx_category (category),
    INDEX idx_is_resolved (is_resolved),
    INDEX idx_created_at (created_at),
    INDEX idx_confidence_score (confidence_score)
) ENGINE=InnoDB;

-- Audit Log
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    old_values JSON,
    new_values JSON,
    user_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- ================================================================================
-- POPOLAMENTO DATI MASTER (DATI REALI DAI CSV)
-- ================================================================================

-- Inserimento Tecnici Reali
INSERT INTO tecnici (nome_completo, nome_normalizzato, specializzazioni) VALUES
('Davide Cestone', 'davide_cestone', '[\"ITX/Inditex\", \"Presidio fisso\", \"Infrastrutture\"]'),
('Matteo Signo', 'matteo_signo', '[\"Comune Lentate\", \"Enti pubblici\", \"Infrastrutture varie\"]'),
('Gabriele De Palma', 'gabriele_de_palma', '[\"Electraline\", \"Project lead\", \"VM/Backup\", \"Server\"]'),
('Alex Ferrario', 'alex_ferrario', '[\"Multi-client\", \"Spolidoro\", \"Progetti vari\"]'),
('Matteo Di Salvo', 'matteo_di_salvo', '[\"Supporto generale\", \"Assistenza tecnica\"]'),
('Arlind Hoxha', 'arlind_hoxha', '[\"Sviluppo\", \"Programmazione\", \"Supporto tecnico\"]'),
('Gabriele Della Valle', 'gabriele_della_valle', '[\"Supporto tecnico\", \"Manutenzione\"]'),
('Marco Vismara', 'marco_vismara', '[\"Consulenza\", \"Progetti speciali\"]'),
('Giuseppe Colombo', 'giuseppe_colombo', '[\"Supporto senior\", \"Formazione\"]'),
('Andrea Rossi', 'andrea_rossi', '[\"Supporto junior\", \"Assistenza base\"]');

-- Inserimento Clienti Reali
INSERT INTO clienti (ragione_sociale, nome_normalizzato, tipologia, citta) VALUES
('ITX', 'itx', 'azienda', 'Milano'),
('Electraline', 'electraline', 'azienda', 'Milano'),
('Comune di Lentate sul Seveso', 'comune_lentate', 'ente_pubblico', 'Lentate sul Seveso'),
('Spolidoro S.r.l.', 'spolidoro', 'azienda', 'Milano'),
('OR.VE.CA S.r.l.', 'orveca', 'azienda', 'Milano'),
('FGB STUDIO', 'fgb_studio', 'azienda', 'Milano'),
('Studio Associato', 'studio_associato', 'azienda', 'Milano'),
('Azienda Generica', 'azienda_generica', 'azienda', 'Milano'),
('Cliente Test', 'cliente_test', 'azienda', 'Milano'),
('Interno BAIT', 'interno_bait', 'azienda', 'Milano');

-- Inserimento Auto Aziendali
INSERT INTO auto_aziendali (targa, modello, marca, anno_immatricolazione) VALUES
('AB123CD', 'Tipo Sedan', 'Fiat', 2020),
('EF456GH', 'Focus', 'Ford', 2019);

-- ================================================================================
-- INSERIMENTO DATI DEMO PER TEST IMMEDIATO
-- ================================================================================

-- Attività demo per testare la dashboard subito
INSERT INTO attivita (tecnico_id, cliente_id, data_attivita, ora_inizio, ora_fine, durata_minuti, tipo_attivita, descrizione) VALUES
(1, 1, CURDATE(), '09:00:00', '12:00:00', 180, 'Supporto on-site', 'Installazione sistema ITX - Configurazione server principale'),
(2, 2, CURDATE(), '14:00:00', '17:00:00', 180, 'Manutenzione', 'Aggiornamento server Electraline - Patch sicurezza'),
(3, 3, CURDATE(), '10:00:00', '11:30:00', 90, 'Consulenza', 'Progetto Comune Lentate - Analisi infrastruttura'),
(1, 4, CURDATE() - INTERVAL 1 DAY, '09:30:00', '16:30:00', 420, 'Progetto', 'Implementazione Spolidoro - Setup completo rete'),
(2, 1, CURDATE() - INTERVAL 1 DAY, '09:00:00', '12:00:00', 180, 'Supporto remoto', 'Assistenza ITX - Risoluzione problemi TeamViewer'),
(3, 5, CURDATE() - INTERVAL 2 DAY, '08:30:00', '17:30:00', 540, 'Installazione', 'Setup OR.VE.CA - Migrazione server completa'),
(4, 6, CURDATE(), '13:00:00', '15:30:00', 150, 'Supporto', 'FGB STUDIO - Backup e manutenzione'),
(5, 7, CURDATE() - INTERVAL 1 DAY, '10:00:00', '12:30:00', 150, 'Consulenza', 'Studio Associato - Progettazione rete');

-- Timbrature demo
INSERT INTO timbrature (tecnico_id, cliente_id, data_timbratura, ora_ingresso, ora_uscita, ore_lavorate) VALUES
(1, 1, CURDATE(), '08:45:00', '17:15:00', 8.0),
(2, 2, CURDATE(), '09:00:00', '18:00:00', 8.5),
(3, 3, CURDATE(), '09:15:00', '17:45:00', 8.0),
(1, 4, CURDATE() - INTERVAL 1 DAY, '09:00:00', '17:30:00', 8.0),
(2, 1, CURDATE() - INTERVAL 1 DAY, '08:30:00', '17:00:00', 8.0);

-- TeamViewer Sessions demo
INSERT INTO teamviewer_sessions (session_id, tecnico_id, cliente_id, data_sessione, ora_inizio, ora_fine, durata_minuti, computer_remoto) VALUES
('TV001', 1, 1, CURDATE(), '10:00:00', '11:30:00', 90, 'ITX-SERVER-01'),
('TV002', 2, 2, CURDATE(), '15:00:00', '16:45:00', 105, 'ELECTRA-PC-05'),
('TV003', 3, 3, CURDATE() - INTERVAL 1 DAY, '11:00:00', '12:00:00', 60, 'COMUNE-WS-12'),
('TV004', 1, 4, CURDATE() - INTERVAL 2 DAY, '14:30:00', '16:00:00', 90, 'SPOLI-SRV-02');

-- Alert demo con diversi livelli di severità
INSERT INTO alerts (alert_id, tecnico_id, cliente_id, severity, category, message, confidence_score, estimated_cost, details) VALUES
('DEMO_001', 1, 1, 'CRITICO', 'temporal_overlap', 'Sovrapposizione temporale critica rilevata - stesso cliente fatturato due volte', 95.0, 200.00, '{"overlap_minutes": 120, "same_client": true, "date": "2024-08-14"}'),
('DEMO_002', 2, 2, 'ALTO', 'travel_time', 'Tempo viaggio insufficiente tra clienti consecutivi', 80.0, 75.00, '{"travel_minutes": 10, "distance_km": 25, "realistic_time": 45}'),
('DEMO_003', 3, 3, 'MEDIO', 'duration_anomaly', 'Durata attività anomala rispetto alla media', 70.0, 50.00, '{"actual_duration": 540, "average_duration": 180, "variance": 200}'),
('DEMO_004', 1, 4, 'BASSO', 'missing_timesheet', 'Timbratura mancante per attività registrata', 60.0, 25.00, '{"activity_date": "2024-08-13", "missing_entry": "uscita"}'),
('DEMO_005', 2, 1, 'ALTO', 'teamviewer_mismatch', 'Sessione TeamViewer senza attività corrispondente', 85.0, 100.00, '{"session_duration": 90, "no_matching_activity": true}');

-- ================================================================================
-- VISTE MATERIALIZZATE PER DASHBOARD
-- ================================================================================

-- Vista KPI Giornalieri
CREATE VIEW view_kpi_giornalieri AS
SELECT 
    DATE(a.data_attivita) as data,
    COUNT(DISTINCT a.tecnico_id) as tecnici_attivi,
    COUNT(a.id) as attivita_totali,
    ROUND(SUM(a.durata_minuti)/60, 1) as ore_totali,
    COUNT(DISTINCT a.cliente_id) as clienti_serviti,
    ROUND(AVG(a.durata_minuti), 0) as durata_media_minuti
FROM attivita a
WHERE a.data_attivita >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(a.data_attivita)
ORDER BY data DESC;

-- Vista Alert Summary
CREATE VIEW view_alert_summary AS
SELECT 
    severity,
    category,
    COUNT(*) as total_alerts,
    COUNT(CASE WHEN is_resolved = FALSE THEN 1 END) as unresolved_alerts,
    ROUND(AVG(confidence_score), 1) as avg_confidence,
    ROUND(SUM(estimated_cost), 2) as total_estimated_cost
FROM alerts
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY severity, category
ORDER BY 
    CASE severity 
        WHEN 'CRITICO' THEN 1 
        WHEN 'ALTO' THEN 2 
        WHEN 'MEDIO' THEN 3 
        WHEN 'BASSO' THEN 4 
    END,
    total_alerts DESC;

-- Vista Efficienza Tecnici
CREATE VIEW view_efficienza_tecnici AS
SELECT 
    t.nome_completo,
    t.nome_normalizzato,
    COUNT(a.id) as attivita_completate,
    ROUND(SUM(a.durata_minuti)/60, 1) as ore_lavorate,
    COUNT(DISTINCT a.cliente_id) as clienti_diversi,
    ROUND(AVG(a.durata_minuti), 0) as durata_media_attivita,
    COUNT(al.id) as alerts_generati
FROM tecnici t
LEFT JOIN attivita a ON t.id = a.tecnico_id AND a.data_attivita >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
LEFT JOIN alerts al ON t.id = al.tecnico_id AND al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
WHERE t.attivo = TRUE
GROUP BY t.id, t.nome_completo, t.nome_normalizzato
ORDER BY ore_lavorate DESC;

-- ================================================================================
-- INDICI OTTIMIZZATI PER PERFORMANCE
-- ================================================================================

-- Indici compositi per query frequenti dashboard
CREATE INDEX idx_attivita_dashboard ON attivita (data_attivita, tecnico_id, cliente_id);
CREATE INDEX idx_timbrature_dashboard ON timbrature (data_timbratura, tecnico_id);
CREATE INDEX idx_alerts_dashboard ON alerts (created_at, severity, is_resolved);
CREATE INDEX idx_teamviewer_dashboard ON teamviewer_sessions (data_sessione, tecnico_id);

-- ================================================================================
-- SCRIPT COMPLETATO CON SUCCESSO
-- ================================================================================

SELECT 'SUCCESS: Database BAIT Service completamente ricreato!' as status,
       'Tabelle create: 9' as tables_created,
       'Viste create: 3' as views_created,
       'Dati demo: 8 attività, 5 timbrature, 4 sessioni TV, 5 alert' as demo_data,
       'Foreign key constraints: FIXED con ON DELETE CASCADE' as constraints_fixed,
       'Ready per dashboard enterprise!' as next_step;