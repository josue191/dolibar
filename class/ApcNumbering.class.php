<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * ApcNumbering — numerotation annuelle automatique :
 *   [prefixe constant] + [annee sur 4 chiffres] + - + [numero 4 chiffres]
 *   Exemple :  EB-2026-0001, BC-2026-0123
 * Persiste dans la table llx_apclogistics_numbering (doc_type, annee, last_number),
 * en utilisant SELECT ... FOR UPDATE si InnoDB pour eviter les doublons.
 */

if (! defined('DOL_VERSION')) die('');

class ApcNumbering
{
    const TABLE = 'apclogistics_numbering';

    /** DocType -> cle de configuration prefixe dans llx_const (APCLOGISTICS_PREFIX_XXX) */
    public static $mapPrefixConst = array(
        'eb'   => 'APCLOGISTICS_PREFIX_EB',
        'req'  => 'APCLOGISTICS_PREFIX_REQ',
        'dp'   => 'APCLOGISTICS_PREFIX_DP',
        'cot'  => 'APCLOGISTICS_PREFIX_COT',
        'bc'   => 'APCLOGISTICS_PREFIX_BC',
        'br'   => 'APCLOGISTICS_PREFIX_BR',
        'dav'  => 'APCLOGISTICS_PREFIX_DAV',
        'jav'  => 'APCLOGISTICS_PREFIX_JAV',
        'dpai' => 'APCLOGISTICS_PREFIX_DPAI',
    );

    /** DocType -> prefixe par defaut si constante absente */
    public static $mapPrefixDefault = array(
        'eb'   => 'EB-',
        'req'  => 'REQ-',
        'dp'   => 'DP-',
        'cot'  => 'COT-',
        'bc'   => 'BC-',
        'br'   => 'BR-',
        'dav'  => 'DAV-',
        'jav'  => 'JAV-',
        'dpai' => 'DPAI-',
    );

    /** Associe le nom de table SQL au docType */
    public static $mapTableDocType = array(
        'apclogistics_etatbesoin'         => 'eb',
        'apclogistics_requisition'        => 'req',
        'apclogistics_demandeprix'        => 'dp',
        'apclogistics_cotation'           => 'cot',
        'apclogistics_boncommande'        => 'bc',
        'apclogistics_bonreception'       => 'br',
        'apclogistics_demandeavance'      => 'dav',
        'apclogistics_justifavance'       => 'jav',
        'apclogistics_demandepaiement'    => 'dpai',
    );

    /**
     * Calcule la reference SUIVANTE pour un type de document.
     * Incremente atomiquement le compteur en BDD.
     *
     * @param CommonObject|string $objectOrTable  instance de ApcObjectBase (->element donne le type) OU nom table
     * @param string|null         $fallbackTable   pour compatibilite ancienne signature
     * @param int|null            $year            annee (si null, utilise l'annee courante)
     * @return string|null         reference generee ex. EB-2026-0001, ou null si echec
     */
    public static function computeNextRef($objectOrTable, $fallbackTable = null, $year = null)
    {
        global $db, $conf;

        if ($objectOrTable instanceof ApcObjectBase) {
            $docType = $objectOrTable->element;
            $prefixDefault = isset($objectOrTable->ref_prefix_default) ? $objectOrTable->ref_prefix_default : '';
            if (isset(self::$mapPrefixConst[$docType])) {
                $constKey = self::$mapPrefixConst[$docType];
                $prefix   = empty($conf->global->$constKey) ? $prefixDefault : $conf->global->$constKey;
            } else {
                $prefix = $prefixDefault;
            }
        } else {
            $tableName = (string)$fallbackTable ?: (string)$objectOrTable;
            $shortName = str_replace(MAIN_DB_PREFIX, '', $tableName);
            if (!isset(self::$mapTableDocType[$shortName])) {
                dol_syslog("APC Numbering : table non supportee : " . $shortName, LOG_WARNING);
                return null;
            }
            $docType = self::$mapTableDocType[$shortName];
            $constKey = self::$mapPrefixConst[$docType];
            $prefixDefault = self::$mapPrefixDefault[$docType];
            $prefix = empty($conf->global->$constKey) ? $prefixDefault : $conf->global->$constKey;
        }

        if (empty($year)) $year = (int)date('Y');

        $db->begin();

        try {
            $tbl = MAIN_DB_PREFIX . self::TABLE;
            $sqlLock = "SELECT last_number FROM " . $tbl
                     . " WHERE doc_type = '" . $db->escape($docType) . "'"
                     . " AND annee = " . (int)$year
                     . " AND entity = 1"
                     . " FOR UPDATE";
            $res = $db->query($sqlLock);
            $next = 1;
            if ($res && $o = $db->fetch_object($res)) {
                $next = (int)$o->last_number + 1;
                $upd = "UPDATE " . $tbl . " SET last_number = " . $next
                     . " WHERE doc_type = '" . $db->escape($docType) . "'"
                     . " AND annee = " . (int)$year . " AND entity = 1";
                $db->query($upd);
            } else {
                $ins = "INSERT INTO " . $tbl
                     . " (entity, doc_type, annee, last_number)"
                     . " VALUES (1, '" . $db->escape($docType) . "', " . (int)$year . ", 1)";
                $db->query($ins);
                $next = 1;
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            dol_syslog('APC Numbering exception : ' . $e->getMessage(), LOG_ERR);
            return null;
        }

        $ref = $prefix . $year . '-' . sprintf('%04d', $next);
        return $ref;
    }
}
