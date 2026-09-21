<?php
/**
 * TEST RAPIDE — A exécuter sur cPanel via le navigateur
 * URL : http://tondomaine.com/custom/apclogistics/scripts/test_eb_debug.php
 *
 * Vérifie : 1) DB connection  2) Tables manquantes  3) TCPDF  4) Création auto
 */

// Trouver main.inc.php
$found = false;
$searchPaths = array('../../main.inc.php', '../../../main.inc.php', '../../../../main.inc.php');
foreach ($searchPaths as $p) {
    if (file_exists($p)) { @include $p; $found = true; break; }
}
if (!$found) { echo "ERREUR: main.inc.php introuvable\n"; exit(1); }

global $db, $conf;

header('Content-Type: text/plain; charset=utf-8');
echo "====== TEST DEBUG ETAT DE BESOIN ======\n\n";

// 1) DB connection
echo "1) CONNEXION DB\n";
if (is_object($db)) {
    echo "   OK: connecte a " . $conf->db->host . "/" . $conf->db->name . "\n";
    echo "   Prefix: " . MAIN_DB_PREFIX . "\n";
} else {
    echo "   ERREUR: objet \$db non disponible\n";
    exit(1);
}

// 2) Check tables
echo "\n2) VERIFICATION TABLES\n";
$tables_to_check = array(
    'apclogistics_etatbesoin' => 'Entete EB',
    'apclogistics_etatbesoin_lines' => 'Lignes EB',
    'apclogistics_auditlog' => 'Audit Log',
);
foreach ($tables_to_check as $tblName => $label) {
    $fullTbl = MAIN_DB_PREFIX . $tblName;
    $res = $db->query("SHOW TABLES LIKE '" . $fullTbl . "'");
    if ($res && $db->num_rows($res) > 0) {
        // Count rows
        $r2 = $db->query("SELECT COUNT(*) as c FROM " . $fullTbl);
        $cnt = $r2 ? $db->fetch_object($r2)->c : '?';
        echo "   OK: $fullTbl ($label) — $cnt lignes\n";
    } else {
        echo "   MANQUANT: $fullTbl ($label)\n";
        echo "   -> Tentative de creation...\n";
        if ($tblName === 'apclogistics_etatbesoin_lines') {
            $sql = "CREATE TABLE IF NOT EXISTS " . $fullTbl . " (
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
        } elseif ($tblName === 'apclogistics_auditlog') {
            $sql = "CREATE TABLE IF NOT EXISTS " . $fullTbl . " (
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
        }
        if (!empty($sql)) {
            $res2 = $db->query($sql);
            if ($res2) {
                echo "   -> CREE OK\n";
            } else {
                echo "   -> ERREUR: " . $db->lasterror() . "\n";
            }
            unset($sql);
        }
    }
}

// 3) Check TCPDF
echo "\n3) VERIFICATION TCPDF\n";
$tcpdf_paths = array(
    DOL_DOCUMENT_ROOT . '/includes/tecnickcom/tcpdf/tcpdf.php',
    DOL_DOCUMENT_ROOT . '/includes/tcpdf/tcpdf.php',
);
$tcpdf_ok = false;
foreach ($tcpdf_paths as $tp) {
    if (file_exists($tp)) {
        echo "   OK: $tp\n";
        $tcpdf_ok = true;
        break;
    }
}
if (!$tcpdf_ok) {
    echo "   MANQUANT: Aucun TCPDF trouve\n";
    echo "   Cherche dans " . DOL_DOCUMENT_ROOT . "/includes/ :\n";
    $includes = @scandir(DOL_DOCUMENT_ROOT . '/includes/');
    if ($includes) {
        foreach ($includes as $f) {
            if (strpos($f, 'tcpdf') !== false || strpos($f, 'tecnickcom') !== false) {
                echo "     -> $f\n";
            }
        }
    }
    // Try to find any pdf library
    $libs = @scandir(DOL_DOCUMENT_ROOT . '/includes/');
    if ($libs) {
        foreach ($libs as $f) {
            if (preg_match('/pdf|fpdf|tcpdf|dompdf/i', $f)) {
                echo "     -> trouvable: $f\n";
            }
        }
    }
}

// 4) Test INSERT + SELECT on etatbesoin
echo "\n4) TEST INSERT/SELECT\n";
$tbl_eb = MAIN_DB_PREFIX . 'apclogistics_etatbesoin';
$res = $db->query("SHOW TABLES LIKE '" . $tbl_eb . "'");
if ($res && $db->num_rows($res) > 0) {
    // Test insert
    $sql = "INSERT INTO " . $tbl_eb . " (entity, ref, date_eb, objet, status, date_creation) VALUES (1, 'EB-TEST-0000', NOW(), 'Test debug', 0, NOW())";
    $res2 = $db->query($sql);
    if ($res2) {
        $newId = $db->last_insert_id($tbl_eb);
        echo "   INSERT OK: id=$newId\n";
        // Test insert line
        $tbl_lines = MAIN_DB_PREFIX . 'apclogistics_etatbesoin_lines';
        $res3 = $db->query("SHOW TABLES LIKE '" . $tbl_lines . "'");
        if ($res3 && $db->num_rows($res3) > 0) {
            $sql2 = "INSERT INTO " . $tbl_lines . " (entity, fk_etatbesoin, no_ligne, depense, montant, date_creation) VALUES (1, $newId, 1, 'Test ligne', 100.50, NOW())";
            $res4 = $db->query($sql2);
            if ($res4) {
                echo "   INSERT LINE OK\n";
                // Cleanup
                $db->query("DELETE FROM " . $tbl_lines . " WHERE fk_etatbesoin = " . $newId);
                $db->query("DELETE FROM " . $tbl_eb . " WHERE rowid = " . $newId);
                echo "   CLEANUP OK\n";
            } else {
                echo "   INSERT LINE ERREUR: " . $db->lasterror() . "\n";
            }
        } else {
            echo "   INSERT LINE SKIPPED: table lines manquante\n";
        }
    } else {
        echo "   INSERT ERREUR: " . $db->lasterror() . "\n";
    }
} else {
    echo "   SKIP: table entete manquante\n";
}

// 5) Check Dolibarr version
echo "\n5) DOLIBARR VERSION\n";
if (defined('DOL_VERSION')) {
    echo "   " . DOL_VERSION . "\n";
} else {
    echo "   Inconnue\n";
}

echo "\n====== FIN DU TEST ======\n";
