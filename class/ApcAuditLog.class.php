<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * ApcAuditLog : journalisation generique CREATE / UPDATE / DELETE / SIGN ...
 * Insere chaque action dans llx_apclogistics_auditlog ; possibilite de
 * recuperer l'historique d'une entite.
 */

if (! defined('DOL_VERSION')) die('');

class ApcAuditLog
{
    const TABLE = 'apclogistics_auditlog';

    /**
     * Ajoute une entree dans le journal d'audit.
     *
     * @param string      $entityType   ex. 'eb', 'req', 'dp', 'cot', 'bc', 'br', 'stock', 'dav', 'jav', 'dpai', 'token', 'setup'
     * @param int         $entityId     rowid
     * @param string      $actionType   CREATE/UPDATE/DELETE/VALIDATE/SIGN/SEND/PRINT/LINK/CONSUME/DOWNLOAD/EXPORT/SETUP
     * @param int         $fkUser       id dolibarr user (0 si fournisseur externe)
     * @param string|null $oldValuesJSON
     * @param string|null $newValuesJSON
     * @param string|null $details      detail supplementaire
     * @return int|bool                  rowid insere sinon false
     */
    public static function log($entityType, $entityId, $actionType, $fkUser = 0,
                                $oldValuesJSON = null, $newValuesJSON = null, $details = null)
    {
        global $db, $user;

        if (!is_object($db)) return false;

        if (empty($fkUser) && is_object($user) && !empty($user->id)) {
            $fkUser = (int)$user->id;
        }

        $login = '';
        if (!empty($fkUser)) {
            static $cachedLogins = array();
            if (isset($cachedLogins[$fkUser])) {
                $login = $cachedLogins[$fkUser];
            } else {
                $q = "SELECT login FROM " . MAIN_DB_PREFIX . "user WHERE rowid = " . (int)$fkUser;
                $r = $db->query($q);
                if ($r && $o = $db->fetch_object($r)) {
                    $login = $o->login;
                }
                $cachedLogins[$fkUser] = $login;
            }
        }

        $ip = self::getClientIp();
        $ua = empty($_SERVER['HTTP_USER_AGENT']) ? null : substr($_SERVER['HTTP_USER_AGENT'], 0, 254);
        $now = date('Y-m-d H:i:s');

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . self::TABLE
            . " (entity, entity_type, entity_id, action_type, action_details,"
            . " old_values_json, new_values_json, fk_user, user_login,"
            . " ip_address, http_user_agent, date_action) VALUES ("
            . " 1,"
            . " '" . $db->escape($entityType) . "',"
            . " " . (int)$entityId . ","
            . " '" . $db->escape($actionType) . "',"
            . ($details ? " '" . $db->escape($details) . "'," : " NULL,")
            . ($oldValuesJSON ? " '" . $db->escape($oldValuesJSON) . "'," : " NULL,")
            . ($newValuesJSON ? " '" . $db->escape($newValuesJSON) . "'," : " NULL,")
            . " " . (int)$fkUser . ","
            . " '" . $db->escape($login) . "',"
            . " '" . $db->escape($ip) . "',"
            . " '" . $db->escape($ua) . "',"
            . " '" . $now . "'"
            . ")";
        $res = $db->query($sql);
        if (!$res) {
            dol_syslog("APCLOGISTICS ApcAuditLog::log() DB error : " . $db->lasterror(), LOG_WARNING);
            return false;
        }
        return (int)$db->last_insert_id(MAIN_DB_PREFIX . self::TABLE);
    }

    /**
     * Retourne toutes les entrees pour une entite, plus recent d'abord.
     * @param DoliDB $db
     * @param string $entityType
     * @param int    $entityId
     * @param int    $limit
     * @return array[]
     */
    public static function fetchForEntity($db, $entityType, $entityId, $limit = 500)
    {
        $rows = array();
        $sql = "SELECT rowid, entity_type, entity_id, action_type, action_details,"
             . " old_values_json, new_values_json, fk_user, user_login,"
             . " ip_address, http_user_agent, date_action"
             . " FROM " . MAIN_DB_PREFIX . self::TABLE
             . " WHERE entity_type = '" . $db->escape($entityType) . "'"
             . " AND entity_id = " . (int)$entityId
             . " ORDER BY date_action DESC, rowid DESC"
             . " LIMIT " . (int)$limit;
        $res = $db->query($sql);
        if (!$res) return $rows;
        while ($o = $db->fetch_object($res)) $rows[] = $o;
        return $rows;
    }

    /**
     * Format l'action en libelle lisible.
     */
    public static function formatAction($type)
    {
        global $langs;
        $langs->load('apclogistics@apclogistics');
        $map = array(
            'CREATE'   => $langs->trans('AUDCreate'),
            'UPDATE'   => $langs->trans('AUDUpdate'),
            'DELETE'   => $langs->trans('AUDDelete'),
            'VALIDATE' => $langs->trans('AUDValidate'),
            'SIGN'     => $langs->trans('AUDSignature'),
            'SEND'     => $langs->trans('AUDSend'),
            'PRINT'    => $langs->trans('AUDPdf'),
            'LINK'     => $langs->trans('AUDLink'),
            'CONSUME'  => $langs->trans('AUDTokenConsume'),
        );
        return isset($map[$type]) ? $map[$type] : $type;
    }

    /** @return string */
    public static function getClientIp()
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']))     return $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))      return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        if (!empty($_SERVER['HTTP_X_REAL_IP']))             return $_SERVER['HTTP_X_REAL_IP'];
        if (!empty($_SERVER['REMOTE_ADDR']))                return $_SERVER['REMOTE_ADDR'];
        return '0.0.0.0';
    }
}
