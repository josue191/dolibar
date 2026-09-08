<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * ApcEtatBesoin — Etat de besoin APC (planification budgetaire + 3 signatures)
 */

if (! defined('DOL_VERSION')) die('');

require_once __DIR__ . '/ApcObjectBase.class.php';
require_once __DIR__ . '/ApcNumbering.class.php';
require_once __DIR__ . '/ApcAuditLog.class.php';

class ApcEtatBesoin extends ApcObjectBase
{
    public $table_element = 'apclogistics_etatbesoin';
    public $element       = 'eb';
    public $picto         = 'apclogistics@apclogistics';
    public $prefix_ref_const   = 'APCLOGISTICS_PREFIX_EB';
    public $ref_prefix_default = 'EB-';

    public $fields = array(
        'rowid'        => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'       => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'ref'          => array('type'=>'varchar(64)', 'enabled'=>1, 'index'=>true, 'unique'=>true),
        'ref_ext'      => array('type'=>'varchar(128)'),
        'date_eb'      => array('type'=>'date',    'enabled'=>1),
        'objet'        => array('type'=>'varchar(255)', 'enabled'=>1),
        'total_ht'     => array('type'=>'decimal(24,8)'),
        'total_ttc'    => array('type'=>'decimal(24,8)'),
        'status'       => array('type'=>'smallint', 'enabled'=>1, 'default'=>0),
        'note_public'  => array('type'=>'text'),
        'note_private' => array('type'=>'text'),

        'fk_user_demandeur' => array('type'=>'integer'),
        'date_signature_demandeur' => array('type'=>'datetime'),
        'signataire_nom_d'  => array('type'=>'varchar(128)'),
        'signataire_fonction_d' => array('type'=>'varchar(128)'),

        'fk_user_verificateur' => array('type'=>'integer'),
        'date_signature_verif' => array('type'=>'datetime'),
        'signataire_nom_v'  => array('type'=>'varchar(128)'),
        'signataire_fonction_v' => array('type'=>'varchar(128)'),

        'fk_user_approbateur' => array('type'=>'integer'),
        'date_signature_approb' => array('type'=>'datetime'),
        'signataire_nom_a'  => array('type'=>'varchar(128)'),
        'signataire_fonction_a' => array('type'=>'varchar(128)'),
        'extraparams'       => array('type'=>'text'),
        'date_creation'     => array('type'=>'datetime'),
        'tms'               => array('type'=>'timestamp'),
        'fk_user_creat'     => array('type'=>'integer'),
        'fk_user_modif'     => array('type'=>'integer'),
    );

    public $lines = array();   // ApcEtatBesoinLine[]

    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /** Calcule et met a jour $this->total_ht = sum(montant lignes) */
    public function calculateTotals()
    {
        $sum = 0.0;
        foreach ($this->lines as $l) { $sum += (float)$l->montant; }
        $this->total_ht  = $sum;
        $this->total_ttc = $sum;
        return $sum;
    }

    /** Charge les lignes associées */
    public function fetchLines()
    {
        $this->lines = ApcEtatBesoinLine::fetchAllForParent($this->db, (int)$this->id);
        $this->calculateTotals();
        return $this->lines;
    }

    /** Applique la signature Demandeur, Verificateur OU Approbateur selon $level (0/1/2) */
    public function sign($level, User $user, $nom = null, $fonction = null, $date = null)
    {
        if ($date === null) $date = dol_now();
        $mapUser = array(
            0 => array('id'=>'fk_user_demandeur',   'date'=>'date_signature_demandeur', 'nom'=>'signataire_nom_d',  'fct'=>'signataire_fonction_d'),
            1 => array('id'=>'fk_user_verificateur','date'=>'date_signature_verif',     'nom'=>'signataire_nom_v',  'fct'=>'signataire_fonction_v'),
            2 => array('id'=>'fk_user_approbateur', 'date'=>'date_signature_approb',    'nom'=>'signataire_nom_a',  'fct'=>'signataire_fonction_a'),
        );
        if (!isset($mapUser[$level])) return -1;
        $m = $mapUser[$level];
        $this->{$m['id']}   = (int)$user->id;
        $this->{$m['date']} = $date;
        $this->{$m['nom']}  = $nom ?: $user->getFullName($langs);
        $this->{$m['fct']}  = $fonction;

        $this->status = max($this->status, self::STATUS_PENDING);
        if ($level === 2) {
            $this->status = self::STATUS_VALIDATED;
            $this->lockRef();
        }
        $res = $this->update($user);
        if ($res > 0) {
            ApcAuditLog::log($this->element, (int)$this->id, 'SIGN', $user->id,
                null, null, 'Signature niveau ' . $level . ' (' . $m['nom'] . ')');
        }
        return $res;
    }
}


/**
 * Ligne d'Etat de besoin
 */
class ApcEtatBesoinLine extends ApcTableLineBase
{
    public $table_element    = 'apclogistics_etatbesoin_lines';
    public $element          = 'eb_line';
    public $fk_parent_column = 'fk_etatbesoin';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_etatbesoin_lines) */
    public $fields = array(
        'rowid'            => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'           => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'fk_etatbesoin'    => array('type'=>'integer', 'enabled'=>1, 'index'=>true),
        'no_ligne'         => array('type'=>'integer', 'enabled'=>1, 'default'=>0),
        'depense'          => array('type'=>'varchar(255)', 'enabled'=>1),
        'projet_or_budget' => array('type'=>'varchar(255)'),
        'budget_code'      => array('type'=>'varchar(64)'),
        'compte'           => array('type'=>'varchar(32)'),
        'montant'          => array('type'=>'decimal(24,8)', 'enabled'=>1, 'default'=>0),
        'fk_product'       => array('type'=>'integer', 'index'=>true),
        'extraparams'      => array('type'=>'text'),
        'date_creation'    => array('type'=>'datetime'),
        'tms'              => array('type'=>'timestamp'),
        'fk_user_creat'    => array('type'=>'integer'),
        'fk_user_modif'    => array('type'=>'integer'),
    );

    public $depense;
    public $projet_or_budget;
    public $budget_code;
    public $compte;
    public $montant;
    public $fk_product;

    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }
}
