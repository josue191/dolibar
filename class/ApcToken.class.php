<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * ApcToken — generation / validation / consommation de tokens UNIQUES
 * pour les portails fournisseurs de reponses aux Demandes de Prix.
 *
 * Format :
 *   - cote clair (token envoye) : 64 caracteres hexadecimaux aleatoires
 *   - en base : hash SHA-256 du token (128 hexa) + salt
 * Durée de validité lue depuis : $conf->global->APCLOGISTICS_TOKEN_DAYS (defaut 30, min 7 max 60)
 */

if (! defined('DOL_VERSION')) die('');

require_once __DIR__ . '/ApcAuditLog.class.php';

class ApcToken
{
    const TABLE = 'apclogistics_tokens';

    /** @var int Taille (octets) du token aleatoire */
    const TOKEN_LENGTH_BYTES = 32;   // => 64 hex

    /** @var int Taille max (octets) salt */
    const SALT_LENGTH_BYTES = 16;

    /**
     * Genere un NOUVEAU token pour une Demande de Prix.
     * Sauvegarde en base (SHA-256 + salt) et retourne la version claire.
     *
     * @param DoliDB $db
     * @param int    $fkDemandePrix
     * @param int    $validityDays   si null, valeur conf (defaut 30, min 7, max 60)
     * @return string|false          token CLAIR 64 caracteres, ou false
     */
    public static function generate($db, $fkDemandePrix, $validityDays = null)
    {
        global $conf;
        if (empty($conf->global->APCLOGISTICS_TOKEN_DAYS)) {
            $conf->global->APCLOGISTICS_TOKEN_DAYS = 30;
        }
        if ($validityDays === null) {
            $validityDays = (int)$conf->global->APCLOGISTICS_TOKEN_DAYS;
        }
        if ($validityDays < 7)  $validityDays = 7;
        if ($validityDays > 60) $validityDays = 60;

        $clear  = bin2hex(random_bytes(self::TOKEN_LENGTH_BYTES));   // 64
        $salt   = bin2hex(random_bytes(self::SALT_LENGTH_BYTES));   // 32
        $hash   = hash('sha256', $clear . $salt);                   // 64 hexa -> stocke 128 ? SHA256 64 chars
        $now    = date('Y-m-d H:i:s');
        $exp    = date('Y-m-d H:i:s', time() + $validityDays * 86400);

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . self::TABLE
            . " (entity, fk_demandeprix, token_hash, token_salt,"
            . " date_creation, date_expiration, used, attempts_counter)"
            . " VALUES (1,"
            . (int)$fkDemandePrix . ","
            . " '" . $db->escape($hash) . "',"
            . " '" . $db->escape($salt) . "',"
            . " '" . $now . "',"
            . " '" . $exp . "',"
            . " 0, 0)";
        $res = $db->query($sql);
        if (!$res) {
            dol_syslog('APC Token generate DB error ' . $db->lasterror(), LOG_ERR);
            return false;
        }
        return $clear;
    }

    /**
     * Valide un token clair :
     *   - compare le hash SHA256(token + salt) stocke
     *   - verifie date_expiration > now
     *   - verifie used = 0
     *   - limite le nombre d'essais (rate limit bruteforce) : echec si > 100 tentatives
     *
     * @param DoliDB   $db
     * @param string   $clearToken  64 hexa
     * @param object   &$outToken   sortie : ligne base si valide (rowid, fk_demandeprix, date_expiration)
     * @return string    'ok' | 'invalid' | 'expired' | 'used' | 'ratelimited'
     */
    public static function validate($db, $clearToken, &$outToken = null)
    {
        $outToken = null;
        if (!is_string($clearToken) || strlen($clearToken) !== 64 || !ctype_xdigit($clearToken)) {
            return 'invalid';
        }

        $tbl = MAIN_DB_PREFIX . self::TABLE;

        // Recupere tous les rows non consommes + hash candidates
        $sql = "SELECT rowid, fk_demandeprix, token_hash, token_salt,"
             . " date_creation, date_expiration, used, attempts_counter"
             . " FROM " . $tbl
             . " WHERE used = 0"
             . " ORDER BY date_creation DESC LIMIT 1000";
        $res = $db->query($sql);
        if (!$res) return 'invalid';

        $candidates = array();
        while ($o = $db->fetch_object($res)) $candidates[] = $o;

        $now = time();
        $matched  = null;
        foreach ($candidates as $r) {
            $computed = hash('sha256', $clearToken . $r->token_salt);
            if (hash_equals($computed, $r->token_hash)) {
                $matched = $r;
                break;
            }
        }

        if (!$matched) {
            // Rate limit : incremente tentative sur chaque token
            foreach ($candidates as $r) {
                $db->query("UPDATE " . $tbl . " SET attempts_counter = attempts_counter + 1 WHERE rowid = " . (int)$r->rowid);
            }
            return 'invalid';
        }

        if ($matched->attempts_counter > 100) {
            return 'ratelimited';
        }
        if (strtotime($matched->date_expiration) < $now) {
            return 'expired';
        }
        if ((int)$matched->used === 1) {
            return 'used';
        }

        $outToken = $matched;
        return 'ok';
    }

    /**
     * Marque le token comme utilise (apres soumission Cotation fournisseur).
     *
     * @param DoliDB $db
     * @param int    $rowid              rowid du token
     * @param int    $consumedById       fk_cotation (ou 0)
     * @param string $ip
     * @param string $ua
     * @return bool
     */
    public static function consume($db, $rowid, $consumedById = 0, $ip = '', $ua = '')
    {
        $tbl = MAIN_DB_PREFIX . self::TABLE;
        $sql = "UPDATE " . $tbl . " SET used = 1,"
             . " date_utilisation = '" . date('Y-m-d H:i:s') . "',"
             . " consumed_by_cotation_id = " . (int)$consumedById . ","
             . " ip_used = '" . $db->escape($ip) . "',"
             . " user_agent = '" . $db->escape(substr($ua, 0, 254)) . "'"
             . " WHERE rowid = " . (int)$rowid . " AND used = 0";
        $ok = (bool)$db->query($sql);
        if ($ok) {
            ApcAuditLog::log('token', (int)$rowid, 'CONSUME', 0, null, null,
                             'Consomme par cotation rowid=' . (int)$consumedById);
        }
        return $ok;
    }

    /**
     * Verifie si un token CLAIR est deja utilise / expire sans recuperer l'objet.
     * @return bool
     */
    public static function isConsumed($db, $clearToken)
    {
        $dummy = null;
        $status = self::validate($db, $clearToken, $dummy);
        return in_array($status, array('used', 'expired', 'invalid', 'ratelimited'), true);
    }
}
