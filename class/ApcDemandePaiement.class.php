<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * ApcDemandePaiement — Demande de paiement APC
 * 3 cas d'usage : 1=Avance activites/TDR, 2=Remboursement pieces justif, 3=Paiement factures fournisseurs
 * 3 signatures : Demandeur → Verificateur → Approbateur (ou Ordonnateur)
 */

if (! defined('DOL_VERSION')) die('');

require_once __DIR__ . '/ApcObjectBase.class.php';
require_once __DIR__ . '/ApcAuditLog.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

class ApcDemandePaiement extends ApcObjectBase
{
    public $table_element = 'apclogistics_demandepaiement';
    public $element       = 'dpai';
    public $picto         = 'apclogistics@apclogistics';
    public $prefix_ref_const   = 'APCLOGISTICS_PREFIX_DPAI';
    public $ref_prefix_default = 'DPAI-';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_demandepaiement) */
    public $fields = array(
        'rowid'                    => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'                   => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'ref'                      => array('type'=>'varchar(64)', 'enabled'=>1, 'index'=>true, 'unique'=>true),
        'ref_ext'                  => array('type'=>'varchar(128)'),
        'date_dpai'                => array('type'=>'date', 'enabled'=>1),
        'objet'                    => array('type'=>'varchar(255)', 'enabled'=>1),
        'compte'                   => array('type'=>'varchar(32)'),
        'cas_usage'                => array('type'=>'smallint', 'enabled'=>1, 'default'=>1),
        'mode_paiement'            => array('type'=>'smallint', 'enabled'=>1, 'default'=>1),
        'coord_banque_nom'         => array('type'=>'varchar(255)'),
        'coord_banque_iban'        => array('type'=>'varchar(64)'),
        'coord_banque_banque'      => array('type'=>'varchar(128)'),
        'coord_banque_swift'       => array('type'=>'varchar(16)'),
        'beneficiaire_nom'         => array('type'=>'varchar(255)'),
        'beneficiaire_fk_societe'  => array('type'=>'integer'),
        'beneficiaire_fk_user'     => array('type'=>'integer'),
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
        'date_paiement_effectif'   => array('type'=>'date'),
        'extraparams'              => array('type'=>'text'),
        'date_creation'            => array('type'=>'datetime'),
        'tms'                      => array('type'=>'timestamp'),
        'fk_user_creat'            => array('type'=>'integer'),
        'fk_user_modif'            => array('type'=>'integer'),
    );

    public $date_dpai;
    public $objet;
    public $compte;
    public $cas_usage;   // 1,2,3
    public $mode_paiement; // 1=caisse, 2=banque
    public $coord_banque_nom;
    public $coord_banque_iban;
    public $coord_banque_banque;
    public $coord_banque_swift;
    public $beneficiaire_nom;
    public $beneficiaire_fk_societe;
    public $beneficiaire_fk_user;
    public $total;
    public $devise;
    public $date_paiement_effectif;

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
        $this->lines = ApcDemandePaiementLine::fetchAllForParent($this->db, (int)$this->id);
        $this->calculateTotals();
        return $this->lines;
    }

    public function calculateTotals()
    {
        $sum = 0.0;
        foreach ($this->lines as $l) { $sum += (float)$l->montant; }
        $this->total = $sum;
        return $sum;
    }

    /** Libellé cas d'usage */
    public function getCasUsageLabel()
    {
        global $langs;
        $langs->load('apclogistics@apclogistics');
        $map = array(
            1 => $langs->trans('DPAICas1'),
            2 => $langs->trans('DPAICas2'),
            3 => $langs->trans('DPAICas3'),
        );
        return isset($map[(int)$this->cas_usage]) ? $map[(int)$this->cas_usage] : (string)$this->cas_usage;
    }

    /** Signature $level 0 (Demandeur) / 1 (Verificateur) / 2 (Approbateur/Ordonnateur) */
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
                null, null, 'Signature niveau ' . $level . ' cas_usage=' . $this->cas_usage);
        }
        return $res;
    }
}

class ApcDemandePaiementLine extends ApcTableLineBase
{
    public $table_element    = 'apclogistics_demandepaiement_lines';
    public $element          = 'dpai_line';
    public $fk_parent_column = 'fk_demandepaiement';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_demandepaiement_lines) */
    public $fields = array(
        'rowid'               => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'              => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'fk_demandepaiement'  => array('type'=>'integer', 'enabled'=>1, 'index'=>true),
        'no_ligne'            => array('type'=>'integer', 'enabled'=>1, 'default'=>0),
        'depense_label'       => array('type'=>'varchar(255)', 'enabled'=>1),
        'projet'              => array('type'=>'varchar(255)'),
        'budget'              => array('type'=>'varchar(128)'),
        'montant'             => array('type'=>'decimal(24,8)', 'enabled'=>1, 'default'=>0),
        'extraparams'         => array('type'=>'text'),
        'date_creation'       => array('type'=>'datetime'),
        'tms'                 => array('type'=>'timestamp'),
        'fk_user_creat'       => array('type'=>'integer'),
        'fk_user_modif'       => array('type'=>'integer'),
    );

    public $depense_label;
    public $projet;
    public $budget;
    public $montant;

    public function __construct(DoliDB $db) { $this->db = $db; }
}
