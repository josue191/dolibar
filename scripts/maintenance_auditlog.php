<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * maintenance_auditlog.php — Script de maintenance pour l'audit log
 * Rotation et archivage des logs pour éviter la croissance infinie de la table
 *
 * Usage : php scripts/maintenance_auditlog.php [options]
 * Options :
 *   --retention=365   : Nombre de jours de rétention (défaut : 365)
 *   --dry-run          : Simule les actions sans exécuter
 *   --archive          : Archive les anciens logs au lieu de les supprimer
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (! defined('NOREQUIREMENU'))  define('NOREQUIREMENU', '1');
if (! defined('NOREQUIREUSER')) define('NOREQUIREUSER', '1');
if (! defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (! defined('NOLOGIN')) define('NOLOGIN', '1');

require dirname(__DIR__) . '/main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

// Analyse des arguments
$retentionDays = 365;
$dryRun = false;
$archive = false;

foreach ($argv as $arg) {
    if (preg_match('/--retention=(\d+)/', $arg, $matches)) {
        $retentionDays = (int)$matches[1];
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } elseif ($arg === '--archive') {
        $archive = true;
    }
}

global $db, $conf, $langs;

$logs = $langs->load('apclogistics@apclogistics');

echo "=== Maintenance Audit Log APC Logistics ===\n";
echo "Rétention : $retentionDays jours\n";
echo "Mode : " . ($dryRun ? "DRY-RUN (simulation)" : "PRODUCTION") . "\n";
echo "Action : " . ($archive ? "ARCHIVAGE" : "SUPPRESSION") . "\n\n";

// Calculer la date limite
$cutoffDate = date('Y-m-d H:i:s', time() - ($retentionDays * 86400));
echo "Date limite : $cutoffDate\n\n";

$tbl = MAIN_DB_PREFIX . 'apclogistics_auditlog';

// Compter les enregistrements à traiter
$sqlCount = "SELECT COUNT(*) AS nb FROM " . $tbl . " WHERE date_action < '" . $db->escape($cutoffDate) . "'";
$resCount = $db->query($sqlCount);
$toProcess = 0;
if ($resCount) {
    $obj = $db->fetch_object($resCount);
    $toProcess = (int)$obj->nb;
}

echo "Enregistrements à traiter : $toProcess\n\n";

if ($toProcess === 0) {
    echo "Aucun enregistrement à traiter. Fin.\n";
    exit(0);
}

if ($archive) {
    // Mode archivage : créer une table d'archive
    $archiveTbl = MAIN_DB_PREFIX . 'apclogistics_auditlog_archive';
    
    // Vérifier si la table d'archive existe
    $sqlCheck = "SHOW TABLES LIKE '" . $archiveTbl . "'";
    $resCheck = $db->query($sqlCheck);
    $archiveExists = ($resCheck && $db->num_rows($resCheck) > 0);
    
    if (!$archiveExists) {
        echo "Création de la table d'archive...\n";
        if (!$dryRun) {
            $sqlCreate = "CREATE TABLE " . $archiveTbl . " LIKE " . $tbl;
            $db->query($sqlCreate);
        }
        echo "Table d'archive créée.\n\n";
    }
    
    // Déplacer les enregistrements vers l'archive
    echo "Archivage des enregistrements...\n";
    $sqlArchive = "INSERT INTO " . $archiveTbl . " SELECT * FROM " . $tbl 
                . " WHERE date_action < '" . $db->escape($cutoffDate) . "'";
    
    if (!$dryRun) {
        $resArchive = $db->query($sqlArchive);
        if ($resArchive) {
            $archived = $db->affected_rows();
            echo "$archived enregistrements archivés.\n";
            
            // Supprimer les enregistrements archivés
            $sqlDelete = "DELETE FROM " . $tbl . " WHERE date_action < '" . $db->escape($cutoffDate) . "'";
            $resDelete = $db->query($sqlDelete);
            if ($resDelete) {
                $deleted = $db->affected_rows();
                echo "$deleted enregistrements supprimés de la table principale.\n";
            }
        } else {
            echo "Erreur lors de l'archivage : " . $db->lasterror() . "\n";
            exit(1);
        }
    } else {
        echo "[DRY-RUN] Archivage simulé de $toProcess enregistrements.\n";
    }
    
} else {
    // Mode suppression directe
    echo "Suppression des enregistrements...\n";
    $sqlDelete = "DELETE FROM " . $tbl . " WHERE date_action < '" . $db->escape($cutoffDate) . "'";
    
    if (!$dryRun) {
        $resDelete = $db->query($sqlDelete);
        if ($resDelete) {
            $deleted = $db->affected_rows();
            echo "$deleted enregistrements supprimés.\n";
        } else {
            echo "Erreur lors de la suppression : " . $db->lasterror() . "\n";
            exit(1);
        }
    } else {
        echo "[DRY-RUN] Suppression simulée de $toProcess enregistrements.\n";
    }
}

// Optimiser la table après suppression importante
if (!$dryRun && $toProcess > 1000) {
    echo "\nOptimisation de la table...\n";
    $sqlOptimize = "OPTIMIZE TABLE " . $tbl;
    $db->query($sqlOptimize);
    echo "Table optimisée.\n";
}

echo "\n=== Maintenance terminée ===\n";
exit(0);
