<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * ApcDemandeAvance — Demande d'avance APC (3 signatures: Demandeur → Verificateur → Approbateur)
 * Lien vers : Justification d'avance (1-n, mais 1-1 dans les usages APC)
 */

if (! defined('DOL_VERSION')) die('');

require_once __DIR__ . '/ApcObjectBase.class.php';
require_once __DIR__ . '/ApcAuditLog.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

class ApcDemandeAvance extends ApcObjectBase
{
    public $table_element = 'apclogistics_demandeavance';
    public $element       = 'dav';
    public $picto         = 'apclogistics@apclogistics';
    public $prefix_ref_const   = 'APCLOGISTICS_PREFIX_DAV';
    public $ref_prefix_default = 'DAV-';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_demandeavance) */
    public $fields = array(
        'rowid'                    => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'                   => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'ref'                      => array('type'=>'varchar(64)', 'enabled'=>1, 'index'=>true, 'unique'=>true),
        'ref_ext'                  => array('type'=>'varchar(128)'),
        'date_dav'                 => array('type'=>'date', 'enabled'=>1),
        'objet'                    => array('type'=>'varchar(255)', 'enabled'=>1),
        'compte'                   => array('type'=>'varchar(32)'),
        'mode_paiement'            => array('type'=>'smallint', 'enabled'=>1, 'default'=>1),
        'coord_banque_nom'         => array('type'=>'varchar(255)'),
        'coord_banque_iban'        => array('type'=>'varchar(64)'),
        'coord_banque_banque'      => array('type'=>'varchar(128)'),
        'coord_banque_swift'       => array('type'=>'varchar(16)'),
        'total_ht'                 => array('type'=>'decimal(24,8)', 'enabled'=>1, 'default'=>0),
        'total'                    => array('type'=>'decimal(24,8)', 'enabled'=>1, 'default'=>0),
        'devise'                   => array('type'=>'varchar(3)', 'default'=>'CDF'),
        'status'                   => array('type'=>'smallint', 'enabled'=>1, 'default'=>0),
        'note_public'              => array('type'=>'text'),
        'note_private'             => array('type'=>'text'),
        'fk_user_demandeur'        => array('type'=>'integer'),
        'demandeur_nom'            => array('type'=>'varchar(128)'),
        'demandeur_fonction'       => array('type'=>'varchar(128)'),
        'date_signature_demandeur' => array('type'=>'datetime'),
        'fk_user_verificateur'     => array('type'=>'integer'),
        'verif_nom'                => array('type'=>'varchar(128)'),
        'verif_fonction'           => array('type'=>'varchar(128)'),
        'date_signature_verif'     => array('type'=>'datetime'),
        'fk_user_approbateur'      => array('type'=>'integer'),
        'approb_nom'               => array('type'=>'varchar(128)'),
        'approb_fonction'          => array('type'=>'varchar(128)'),
        'date_signature_approb'    => array('type'=>'datetime'),
        'date_decaissement'        => array('type'=>'date'),
        'extraparams'              => array('type'=>'text'),
        'date_creation'            => array('type'=>'datetime'),
        'tms'                      => array('type'=>'timestamp'),
        'fk_user_creat'            => array('type'=>'integer'),
        'fk_user_modif'            => array('type'=>'integer'),
    );

    public $date_dav;
    public $objet;
    public $compte;
    public $mode_paiement; // 1=caisse, 2=banque
    public $coord_banque_nom;
    public $coord_banque_iban;
    public $coord_banque_banque;
    public $coord_banque_swift;
    public $total_ht;
    public $total;
    public $devise;
    public $date_decaissement;

    public $fk_user_demandeur;
    public $demandeur_nom;
    public $demandeur_fonction;
    public $date_signature_demandeur;

    public $fk_user_verificateur;
    public $verif_nom;
    public $verif_fonction;
    public $date_signature_verif;

    public $fk_user_approbateur;
    public $approb_nom;
    public $approb_fonction;
    public $date_signature_approb;

    public $lines = array();

    public function __construct(DoliDB $db) { $this->db = $db; }

    public function fetchLines()
    {
        $this->lines = ApcDemandeAvanceLine::fetchAllForParent($this->db, (int)$this->id);
        $this->calculateTotals();
        return $this->lines;
    }

    public function calculateTotals()
    {
        $sum = 0.0;
        foreach ($this->lines as $l) { $sum += (float)$l->montant; }
        $this->total_ht = $sum;
        $this->total    = $sum;
        return $sum;
    }

    /**
     * Applique une signature : $level = 0 (Demandeur) / 1 (Vérificateur) / 2 (Approbateur)
     * @return int >0 OK
     */
    public function sign($level, User $user, $nom = null, $fonction = null, $date = null, $notrigger = 0)
    {
        if ($date === null) $date = dol_now();
        $map = array(
            0 => array('id'=>'fk_user_demandeur','date'=>'date_signature_demandeur','nom'=>'demandeur_nom','fct'=>'demandeur_fonction'),
            1 => array('id'=>'fk_user_verificateur','date'=>'date_signature_verif','nom'=>'verif_nom','fct'=>'verif_fonction'),
            2 => array('id'=>'fk_user_approbateur','date'=>'date_signature_approb','nom'=>'approb_nom','fct'=>'approb_fonction'),
        );
        if (!isset($map[$level])) return -1;
        $m = $map[$level];
        $this->{$m['id']}   = (int)$user->id;
        $this->{$m['date']} = $date;
        $this->{$m['nom']}  = $nom ?: $user->getFullName($GLOBALS['langs']);
        $this->{$m['fct']}  = $fonction;

        $this->status = max($this->status, self::STATUS_PENDING);
        if ($level === 2) {
            $this->status = self::STATUS_VALIDATED;
            $this->lockRef();
        }
        $res = $this->update($user, $notrigger);
        if ($res > 0) {
            ApcAuditLog::log($this->element, (int)$this->id, 'SIGN', $user->id,
                null, null, 'Signature niveau ' . $level . ' (' . $m['nom'] . ')');
        }
        return $res;
    }

    /** Retourne les Justifications liées */
    public function fetchJustifs()
    {
        $rows = array();
        $sql = "SELECT rowid, ref, date_jav, total_depense, prise_avance, ecart, status"
             . " FROM " . MAIN_DB_PREFIX . "apclogistics_justifavance"
             . " WHERE fk_demande_avance = " . (int)$this->id
             . " ORDER BY date_jav DESC";
        $res = $this->db->query($sql);
        if (!$res) return $rows;
        while ($o = $this->db->fetch_object($res)) $rows[] = $o;
        return $rows;
    }
}

class ApcDemandeAvanceLine extends ApcTableLineBase
{
    public $table_element    = 'apclogistics_demandeavance_lines';
    public $element          = 'dav_line';
    public $fk_parent_column = 'fk_demandeavance';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_demandeavance_lines) */
    public $fields = array(
        'rowid'            => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'           => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'fk_demandeavance' => array('type'=>'integer', 'enabled'=>1, 'index'=>true),
        'no_ligne'         => array('type'=>'integer', 'enabled'=>1, 'default'=>0),
        'depense_label'    => array('type'=>'varchar(255)', 'enabled'=>1),
        'projet'           => array('type'=>'varchar(255)'),
        'budget'           => array('type'=>'varchar(128)'),
        'montant'          => array('type'=>'decimal(24,8)', 'enabled'=>1, 'default'=>0),
        'extraparams'      => array('type'=>'text'),
        'date_creation'    => array('type'=>'datetime'),
        'tms'              => array('type'=>'timestamp'),
        'fk_user_creat'    => array('type'=>'integer'),
        'fk_user_modif'    => array('type'=>'integer'),
    );

    public $depense_label;
    public $projet;
    public $budget;
    public $montant;

    public function __construct(DoliDB $db) { $this->db = $db; }
}
