-- MFO: registro minimale dei programmi VaBase Activity
-- Script proposto e NON ancora eseguito sul database di produzione.
-- Nessun vincolo sul mese: periodi e sovrapposizioni sono gestiti dalla Activity VaBase.
CREATE TABLE IF NOT EXISTS voy_mfo_programs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    activity_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mfo_program_activity (activity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
