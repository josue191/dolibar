-- =========================================================================
-- FIX : Création de la table llx_apclogistics_etatbesoin_lines
-- Exécuter dans l'onglet SQL de phpMyAdmin sur iapbdruz_gestion_dolibarr
-- Si la table existe déjà, cette commande ne fait rien (IF NOT EXISTS).
-- =========================================================================

CREATE TABLE IF NOT EXISTS llx_apclogistics_etatbesoin_lines (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    fk_etatbesoin              INT NOT NULL,
    no_ligne                   INT NOT NULL DEFAULT 0,

    depense                    VARCHAR(255) NOT NULL,
    projet_or_budget           VARCHAR(255) DEFAULT NULL,
    budget_code                VARCHAR(64)  DEFAULT NULL,
    compte                     VARCHAR(32)  DEFAULT NULL,
    montant                    DECIMAL(24,8) NOT NULL DEFAULT 0,

    fk_product                 INT DEFAULT NULL,

    extraparams                TEXT DEFAULT NULL,
    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_ebl_main    (fk_etatbesoin),
    KEY idx_apclog_ebl_prod    (fk_product)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
