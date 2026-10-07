-- NEWS VOY - struttura logica della tabella in uso
-- Non contiene dati né credenziali.

CREATE TABLE voy_news (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    poster VARCHAR(255) NOT NULL,
    date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    news LONGTEXT NOT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    teaser VARCHAR(500) NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_date (date)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
