<?php
/**
 * apc_init.php — Auto-create missing APC tables on page load.
 * Include from any card/list page that needs APC tables.
 *
 * @param DoliDB $db
 * @return array  errors (empty = OK)
 */
if (!defined('DOL_VERSION')) die('');

function apc_ensure_tables($db)
{
    $prefix = MAIN_DB_PREFIX;
    $errors = array();

    // 1) ENTETE ETAT DE BESOIN
    $sql0 = "CREATE TABLE IF NOT EXISTS " . $prefix . "apclogistics_etatbesoin (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        entity INT DEFAULT 1 NOT NULL,
        ref VARCHAR(64) NOT NULL UNIQUE,
        ref_ext VARCHAR(128) DEFAULT NULL,
        date_eb DATE NOT NULL,
        objet VARCHAR(255) NOT NULL,
        total_ht DECIMAL(24,8) DEFAULT 0,
        total_ttc DECIMAL(24,8) DEFAULT 0,
        status SMALLINT NOT NULL DEFAULT 0,
        note_public TEXT DEFAULT NULL,
        note_private TEXT DEFAULT NULL,
        fk_user_demandeur INT DEFAULT NULL,
        date_signature_demandeur DATETIME DEFAULT NULL,
        signataire_nom_d VARCHAR(128) DEFAULT NULL,
        signataire_fonction_d VARCHAR(128) DEFAULT NULL,
        fk_user_verificateur INT DEFAULT NULL,
        date_signature_verif DATETIME DEFAULT NULL,
        signataire_nom_v VARCHAR(128) DEFAULT NULL,
        signataire_fonction_v VARCHAR(128) DEFAULT NULL,
        fk_user_approbateur INT DEFAULT NULL,
        date_signature_approb DATETIME DEFAULT NULL,
        signataire_nom_a VARCHAR(128) DEFAULT NULL,
        signataire_fonction_a VARCHAR(128) DEFAULT NULL,
        extraparams TEXT DEFAULT NULL,
        date_creation DATETIME DEFAULT NULL,
        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        fk_user_creat INT DEFAULT NULL,
        fk_user_modif INT DEFAULT NULL,
        KEY idx_apclog_eb_status (status),
        KEY idx_apclog_eb_date (date_eb),
        KEY idx_apclog_eb_demandeur (fk_user_demandeur),
        KEY idx_apclog_eb_entity (entity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$db->query($sql0)) $errors[] = 'etatbesoin: ' . $db->lasterror();

    // 2) LIGNES ETAT DE BESOIN
    $sql1 = "CREATE TABLE IF NOT EXISTS " . $prefix . "apclogistics_etatbesoin_lines (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        entity INT DEFAULT 1 NOT NULL,
        fk_etatbesoin INT NOT NULL,
        no_ligne INT NOT NULL DEFAULT 0,
        depense VARCHAR(255) NOT NULL,
        projet_or_budget VARCHAR(255) DEFAULT NULL,
        budget_code VARCHAR(64) DEFAULT NULL,
        compte VARCHAR(32) DEFAULT NULL,
        montant DECIMAL(24,8) NOT NULL DEFAULT 0,
        fk_product INT DEFAULT NULL,
        extraparams TEXT DEFAULT NULL,
        date_creation DATETIME DEFAULT NULL,
        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        fk_user_creat INT DEFAULT NULL,
        fk_user_modif INT DEFAULT NULL,
        KEY idx_apclog_ebl_main (fk_etatbesoin),
        KEY idx_apclog_ebl_prod (fk_product)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$db->query($sql1)) $errors[] = 'et Lines: ' . $db->lasterror();

    // 3) AUDIT LOG
    $sql2 = "CREATE TABLE IF NOT EXISTS " . $prefix . "apclogistics_auditlog (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        entity INT DEFAULT 1 NOT NULL,
        entity_type VARCHAR(32) NOT NULL,
        entity_id INT NOT NULL,
        action_type VARCHAR(32) NOT NULL,
        action_details TEXT DEFAULT NULL,
        old_values_json TEXT DEFAULT NULL,
        new_values_json TEXT DEFAULT NULL,
        fk_user INT DEFAULT NULL,
        user_login VARCHAR(64) DEFAULT NULL,
        ip_address VARCHAR(64) DEFAULT NULL,
        http_user_agent VARCHAR(255) DEFAULT NULL,
        date_action DATETIME NOT NULL,
        KEY idx_apclog_aud_type (entity_type),
        KEY idx_apclog_aud_id (entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$db->query($sql2)) $errors[] = 'auditlog: ' . $db->lasterror();

    // 4) COMPTEUR DE NUMEROTATION (indispensable : sans lui, computeNextRef()
    //    echoue silencieusement et toutes les creations apres la 1ere ont
    //    une reference en double -> erreur "Duplicate entry")
    $sql3 = "CREATE TABLE IF NOT EXISTS " . $prefix . "apclogistics_numbering (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        entity INT DEFAULT 1 NOT NULL,
        doc_type VARCHAR(8) NOT NULL,
        annee INT NOT NULL,
        last_number INT NOT NULL DEFAULT 0,
        UNIQUE KEY uk_apclog_num (doc_type, annee, entity),
        KEY idx_apclog_num_entity (entity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$db->query($sql3)) $errors[] = 'numbering: ' . $db->lasterror();

    return $errors;
}
