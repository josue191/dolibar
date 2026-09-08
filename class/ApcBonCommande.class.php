<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * ApcBonCommande — Bon de Commande APC (transforme 1-clic depuis une Cotation retenue)
 * Champs alignes sur le schema SQL 001_create_schema.sql
 */

if (! defined('DOL_VERSION')) die('');

require_once __DIR__ . '/ApcObjectBase.class.php';
require_once __DIR__ . '/ApcCotation.class.php';

class ApcBonCommande extends ApcObjectBase
{
    public $table_element = 'apclogistics_boncommande';
    public $element       = 'bc';
    public $picto         = 'apclogistics@apclogistics';
    public $prefix_ref_const   = 'APCLOGISTICS_PREFIX_BC';
    public $ref_prefix_default = 'BC-';

    const STATUS_ORDERED       = 2;
    const STATUS_PARTIAL_REC   = 3;
    const STATUS_FULLY_REC     = 4;
    const STATUS_CLOSED        = 5;

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_boncommande) */
    public $fields = array(
        'rowid'                  => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'                 => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'ref'                    => array('type'=>'varchar(64)', 'enabled'=>1, 'index'=>true, 'unique'=>true),
        'ref_ext'                => array('type'=>'varchar(128)'),
        'date_cmde'              => array('type'=>'date', 'enabled'=>1),
        'fk_cotation'            => array('type'=>'integer', 'index'=>true),
        'fk_demandeprix'         => array('type'=>'integer'),
        'fk_societe'             => array('type'=>'integer', 'index'=>true),
        'fournisseur_nom'        => array('type'=>'varchar(255)'),
        'fournisseur_adresse'    => array('type'=>'text'),
        'fournisseur_tel'        => array('type'=>'varchar(32)'),
        'fournisseur_email'      => array('type'=>'varchar(128)'),
        'fournisseur_contact'    => array('type'=>'varchar(128)'),
        'lieu_livraison'         => array('type'=>'varchar(255)'),
        'date_livraison'         => array('type'=>'date'),
        'conditions_paiement'    => array('type'=>'varchar(128)'),
        'delai_reglement_jours'  => array('type'=>'integer'),
        'taux_tva_applicable'    => array('type'=>'decimal(6,3)', 'default'=>0),
        'total_ht'               => array('type'=>'decimal(24,8)'),
        'total_tva'              => array('type'=>'decimal(24,8)'),
        'total_ttc'              => array('type'=>'decimal(24,8)'),
        'devise'                 => array('type'=>'varchar(3)', 'default'=>'CDF'),
        'status'                 => array('type'=>'smallint', 'enabled'=>1, 'default'=>0),
        'note_public'            => array('type'=>'text'),
        'note_private'           => array('type'=>'text'),
        'fk_user_logisticien'    => array('type'=>'integer'),
        'logisticien_nom'        => array('type'=>'varchar(128)'),
        'logisticien_fonction'   => array('type'=>'varchar(128)'),
        'date_signature_log'     => array('type'=>'datetime'),
        'fk_user_coordinateur'   => array('type'=>'integer'),
        'coordinateur_nom'       => array('type'=>'varchar(128)'),
        'coordinateur_fonction'  => array('type'=>'varchar(128)'),
        'date_signature_coord'   => array('type'=>'datetime'),
        'fournisseur_sig_nom'    => array('type'=>'varchar(128)'),
        'fournisseur_sig_fct'    => array('type'=>'varchar(128)'),
        'fournisseur_sig_date'   => array('type'=>'date'),
        'fournisseur_sig_ip'     => array('type'=>'varchar(64)'),
        'extraparams'            => array('type'=>'text'),
        'date_creation'          => array('type'=>'datetime'),
        'tms'                    => array('type'=>'timestamp'),
        'fk_user_creat'          => array('type'=>'integer'),
        'fk_user_modif'          => array('type'=>'integer'),
    );

    // --- Champs entete (alignes sur SQL schema) ---
    public $fk_cotation;
    public $fk_demandeprix;
    public $fk_societe;
    public $fournisseur_nom;
    public $fournisseur_adresse;
    public $fournisseur_tel;
    public $fournisseur_email;
    public $fournisseur_contact;

    /** @var string  Date de commande (SQL: date_cmde) */
    public $date_cmde;
    /** @var string  Date livraison prevue (SQL: date_livraison) */
    public $date_livraison;
    public $lieu_livraison;
    /** @var string  Conditions de paiement (SQL: conditions_paiement) */
    public $conditions_paiement;
    public $delai_reglement_jours;
    public $taux_tva_applicable;
    public $total_ht;
    public $total_tva;
    public $total_ttc;
    public $devise;

    // --- Logisticien (SQL: logisticien_nom, logisticien_fonction, date_signature_log) ---
    public $logisticien_nom;
    public $logisticien_fonction;
    public $date_signature_log;
    public $fk_user_logisticien;

    // --- Coordinateur (SQL: coordinateur_nom, coordinateur_fonction, date_signature_coord) ---
    public $coordinateur_nom;
    public $coordinateur_fonction;
    public $date_signature_coord;
    public $fk_user_coordinateur;

    // --- Fournisseur (SQL: fournisseur_sig_nom, fournisseur_sig_date) ---
    public $fournisseur_sig_nom;
    public $fournisseur_sig_date;

    public $lines = array();

    public function __construct(DoliDB $db) { $this->db = $db; }

    public function getStatusList()
    {
        global $langs;
        $langs->load('apclogistics@apclogistics');
        return array(
            self::STATUS_DRAFT       => $langs->trans('StatusDraft'),
            self::STATUS_ORDERED     => $langs->trans('BcStatusOrdered'),
            self::STATUS_PARTIAL_REC => $langs->trans('BcStatusPartialRec'),
            self::STATUS_FULLY_REC   => $langs->trans('BcStatusFullyRec'),
            self::STATUS_CLOSED      => $langs->trans('StatusClosed'),
            self::STATUS_CANCELLED   => $langs->trans('StatusCancelled'),
        );
    }

    public function fetchLines()
    {
        $this->lines = ApcBonCommandeLine::fetchAllForParent($this->db, (int)$this->id);
        return $this->lines;
    }

    public function calculateTotals()
    {
        $this->fetchLines();
        $ht = 0;
        $tva = 0;
        $txTva = (float)$this->taux_tva_applicable;
        foreach ($this->lines as $l) {
            $l->prix_total_ligne = round((float)$l->prix_unitaire * (float)$l->quantite, 2);
            $ht += $l->prix_total_ligne;
        }
        $this->total_ht = round($ht, 2);
        $this->total_tva = ($txTva > 0) ? round($this->total_ht * ($txTva / 100), 2) : 0;
        $this->total_ttc = round($this->total_ht + $this->total_tva, 2);
        return $this->total_ttc;
    }

    /**
     * Transformation 1-clic : cree un BC VALIDE depuis une cotation retenue.
     * @param ApcCotation $cot
     * @param User        $userLog  Logisticien signataire
     * @param User        $userCoord  Coordinateur signataire (optionnel)
     * @return ApcBonCommande|false
     */
    public static function createFromCotation(ApcCotation $cot, User $userLog, User $userCoord = null)
    {
        global $user, $conf, $langs;
        $db = $cot->db;
        $bc = new self($db);

        $bc->fk_cotation         = $cot->id;
        $bc->fk_demandeprix      = $cot->fk_demandeprix;
        $bc->fk_societe          = $cot->fk_societe;
        $bc->fournisseur_nom     = $cot->fournisseur_nom;
        $bc->fournisseur_adresse = $cot->fournisseur_adresse;
        $bc->fournisseur_tel     = $cot->fournisseur_tel;
        $bc->fournisseur_email   = $cot->fournisseur_email;
        $bc->fournisseur_contact = $cot->fournisseur_contact;
        $bc->date_cmde           = $db->idate(dol_now());
        $bc->date_livraison      = $cot->date_validite_offre;
        $bc->lieu_livraison      = $cot->lieu_livraison;
        $bc->conditions_paiement = $cot->conditions_reglement;
        $bc->taux_tva_applicable = $cot->taux_tva_applicable;

        $cot->fetchLines();

        $res = $bc->create($userLog);
        if ($res <= 0) return false;

        $noLigne = 1;
        foreach ($cot->lines as $cl) {
            $bl = new ApcBonCommandeLine($db);
            $bl->fk_boncommande     = $bc->id;
            $bl->no_ligne           = $noLigne++;
            $bl->fk_cotation_line   = $cl->id;
            $bl->fk_product         = $cl->fk_product;
            $bl->description        = $cl->description;
            $bl->unite              = $cl->unite;
            $bl->quantite           = $cl->quantite;
            $bl->qte_restante       = $cl->quantite;
            $bl->prix_unitaire      = $cl->prix_unitaire_ht;
            $bl->prix_total_ligne   = $cl->total_ht;
            $bl->tva_tx             = $cl->tva_tx;
            $bl->create($userLog);
        }

        $bc->calculateTotals();
        $bc->status = self::STATUS_ORDERED;
        $bc->lockRef();

        $bc->logisticien_nom      = $userLog->getFullName($langs);
        $bc->logisticien_fonction = $userLog->poste;
        $bc->date_signature_log   = $db->idate(dol_now());
        $bc->fk_user_logisticien  = $userLog->id;

        if ($userCoord) {
            $bc->coordinateur_nom      = $userCoord->getFullName($langs);
            $bc->coordinateur_fonction = $userCoord->poste;
            $bc->date_signature_coord  = $db->idate(dol_now());
            $bc->fk_user_coordinateur  = $userCoord->id;
        }

        $bc->update($userLog);

        ApcAuditLog::log($bc->element, $bc->id, 'CREATE_FROM_COT', $userLog->id,
            null, null, 'Transformation depuis Cotation ' . $cot->ref);
        return $bc;
    }
}

class ApcBonCommandeLine extends ApcTableLineBase
{
    public $table_element    = 'apclogistics_boncommande_lines';
    public $element          = 'bc_line';
    public $fk_parent_column = 'fk_boncommande';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_boncommande_lines) */
    public $fields = array(
        'rowid'            => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'           => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'fk_boncommande'   => array('type'=>'integer', 'enabled'=>1, 'index'=>true),
        'no_ligne'         => array('type'=>'integer', 'enabled'=>1, 'default'=>0),
        'description'      => array('type'=>'varchar(255)', 'enabled'=>1),
        'fk_product'       => array('type'=>'integer', 'index'=>true),
        'unite'            => array('type'=>'varchar(32)'),
        'quantite'         => array('type'=>'decimal(16,4)', 'enabled'=>1, 'default'=>0),
        'prix_unitaire'    => array('type'=>'decimal(24,8)', 'enabled'=>1, 'default'=>0),
        'prix_total_ligne' => array('type'=>'decimal(24,8)', 'enabled'=>1, 'default'=>0),
        'tva_tx'           => array('type'=>'decimal(6,3)', 'default'=>0),
        'qte_restante'     => array('type'=>'decimal(16,4)', 'default'=>0),
        'fk_cotation_line' => array('type'=>'integer'),
        'extraparams'      => array('type'=>'text'),
        'date_creation'    => array('type'=>'datetime'),
        'tms'              => array('type'=>'timestamp'),
        'fk_user_creat'    => array('type'=>'integer'),
        'fk_user_modif'    => array('type'=>'integer'),
    );

    public $fk_cotation_line;
    public $fk_product;
    public $description;
    public $unite;
    public $quantite;
    public $qte_restante;
    /** @var float  Prix unitaire HT (SQL: prix_unitaire) */
    public $prix_unitaire;
    /** @var float  Total ligne (SQL: prix_total_ligne) */
    public $prix_total_ligne;
    public $tva_tx;

    public function __construct(DoliDB $db) { $this->db = $db; }
}
