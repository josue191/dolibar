<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * ApcJustifAvance — Justification d'avance APC (liée 1:1 à une Demande d'Avance)
 * Calcule automatiquement l'écart : Total Dépense vs Prise d'Avance
 */

if (! defined('DOL_VERSION')) die('');

require_once __DIR__ . '/ApcObjectBase.class.php';
require_once __DIR__ . '/ApcDemandeAvance.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

class ApcJustifAvance extends ApcObjectBase
{
    public $table_element = 'apclogistics_justifavance';
    public $element       = 'jav';
    public $picto         = 'apclogistics@apclogistics';
    public $prefix_ref_const   = 'APCLOGISTICS_PREFIX_JAV';
    public $ref_prefix_default = 'JAV-';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_justifavance) */
    public $fields = array(
        'rowid'                    => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'                   => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'ref'                      => array('type'=>'varchar(64)', 'enabled'=>1, 'index'=>true, 'unique'=>true),
        'ref_ext'                  => array('type'=>'varchar(128)'),
        'fk_demande_avance'        => array('type'=>'integer', 'enabled'=>1, 'index'=>true),
        'date_jav'                 => array('type'=>'date', 'enabled'=>1),
        'objet'                    => array('type'=>'varchar(255)'),
        'total_depense'            => array('type'=>'decimal(24,8)', 'enabled'=>1, 'default'=>0),
        'prise_avance'             => array('type'=>'decimal(24,8)', 'enabled'=>1, 'default'=>0),
        'ecart'                    => array('type'=>'decimal(24,8)', 'default'=>0),
        'sens_ecart'               => array('type'=>'smallint', 'default'=>0),
        'devise'                   => array('type'=>'varchar(3)', 'default'=>'CDF'),
        'status'                   => array('type'=>'smallint', 'enabled'=>1, 'default'=>0),
        'note_public'              => array('type'=>'text'),
        'note_private'             => array('type'=>'text'),
        'fk_user_auteur'           => array('type'=>'integer'),
        'justif_nom'               => array('type'=>'varchar(128)'),
        'justif_fonction'          => array('type'=>'varchar(128)'),
        'date_signature_justif'    => array('type'=>'datetime'),
        'fk_user_verificateur'     => array('type'=>'integer'),
        'verif_nom'                => array('type'=>'varchar(128)'),
        'verif_fonction'           => array('type'=>'varchar(128)'),
        'date_signature_verif'     => array('type'=>'datetime'),
        'fk_user_approbateur'      => array('type'=>'integer'),
        'approb_nom'               => array('type'=>'varchar(128)'),
        'approb_fonction'          => array('type'=>'varchar(128)'),
        'date_signature_approb'    => array('type'=>'datetime'),
        'extraparams'              => array('type'=>'text'),
        'date_creation'            => array('type'=>'datetime'),
        'tms'                      => array('type'=>'timestamp'),
        'fk_user_creat'            => array('type'=>'integer'),
        'fk_user_modif'            => array('type'=>'integer'),
    );

    public $fk_demande_avance;
    public $date_jav;
    public $total_depense;
    public $prise_avance;
    public $ecart;
    public $sens_ecart; // -1 solde a rendre, +1 du a APC, 0 equilibre
    public $devise;

    public $fk_user_auteur;
    public $justif_nom;
    public $justif_fonction;
    public $date_signature_justif;

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
        $this->lines = ApcJustifAvanceLine::fetchAllForParent($this->db, (int)$this->id);
        $this->calculateTotals();
        return $this->lines;
    }

    /**
     * Calcule total_depense, puis ecart = total_depense - prise_avance
     * et met à jour sens_ecart
     */
    public function calculateTotals()
    {
        $sum = 0.0;
        foreach ($this->lines as $l) { $sum += (float)$l->montant; }
        $this->total_depense = $sum;

        // Si prise_avance non renseignée, récupérer depuis la DAV liée
        if (empty($this->prise_avance) && !empty($this->fk_demande_avance)) {
            $dav = new ApcDemandeAvance($this->db);
            if ($dav->fetch($this->fk_demande_avance) > 0) {
                if (empty($this->prise_avance)) $this->prise_avance = (float)$dav->total;
            }
        }

        $this->ecart = (float)$this->total_depense - (float)$this->prise_avance;
        if ($this->ecart < -0.0001)      $this->sens_ecart = -1;
        elseif ($this->ecart > 0.0001)   $this->sens_ecart = 1;
        else                              $this->sens_ecart = 0;

        return $this->ecart;
    }

    /**
     * Crée une JAV depuis une DAV (1 clic) — pré-remplit prise_avance
     * @param ApcDemandeAvance $dav
     * @param User $u
     * @return ApcJustifAvance|false
     */
    public static function createFromDemandeAvance(ApcDemandeAvance $dav, User $u)
    {
        $db = $dav->db;
        $jav = new self($db);
        $jav->fk_demande_avance = $dav->id;
        $jav->date_jav          = dol_now();
        $jav->prise_avance      = (float)$dav->total;
        $jav->devise            = $dav->devise ?: 'CDF';
        $jav->objet             = $dav->objet;

        $res = $jav->create($u);
        if ($res <= 0) return false;

        ApcAuditLog::log($jav->element, $jav->id, 'CREATE_FROM_DAV', $u->id,
            null, null, 'Creation depuis Demande Avance ' . $dav->ref . ' (prise_avance=' . $jav->prise_avance . ')');
        return $jav;
    }

    /** Signature : $level 0 (Auteur/Justif) / 1 (Verificateur) / 2 (Approbateur) */
    public function sign($level, User $user, $nom = null, $fonction = null, $date = null)
    {
        if ($date === null) $date = dol_now();
        $map = array(
            0 => array('id'=>'fk_user_auteur','date'=>'date_signature_justif','nom'=>'justif_nom','fct'=>'justif_fonction'),
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
        $res = $this->update($user);
        if ($res > 0) {
            ApcAuditLog::log($this->element, (int)$this->id, 'SIGN', $user->id,
                null, null, 'Signature niveau ' . $level);
        }
        return $res;
    }
}

class ApcJustifAvanceLine extends ApcTableLineBase
{
    public $table_element    = 'apclogistics_justifavance_lines';
    public $element          = 'jav_line';
    public $fk_parent_column = 'fk_justifavance';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_justifavance_lines) */
    public $fields = array(
        'rowid'                    => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'                   => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'fk_justifavance'          => array('type'=>'integer', 'enabled'=>1, 'index'=>true),
        'no_ligne'                 => array('type'=>'integer', 'enabled'=>1, 'default'=>0),
        'depense_label'            => array('type'=>'varchar(255)', 'enabled'=>1),
        'projet'                   => array('type'=>'varchar(255)'),
        'budget'                   => array('type'=>'varchar(128)'),
        'compte'                   => array('type'=>'varchar(32)'),
        'montant'                  => array('type'=>'decimal(24,8)', 'enabled'=>1, 'default'=>0),
        'date_facture'             => array('type'=>'date'),
        'ref_piece_justificative'  => array('type'=>'varchar(128)'),
        'extraparams'              => array('type'=>'text'),
        'date_creation'            => array('type'=>'datetime'),
        'tms'                      => array('type'=>'timestamp'),
        'fk_user_creat'            => array('type'=>'integer'),
        'fk_user_modif'            => array('type'=>'integer'),
    );

    public $depense_label;
    public $projet;
    public $budget;
    public $compte;
    public $montant;
    public $date_facture;
    public $ref_piece_justificative;

    public function __construct(DoliDB $db) { $this->db = $db; }
}
