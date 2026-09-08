<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * ApcCotation — Reponse fournisseur a une Demande de Prix
 *   Enregistree depuis le portail public (token).
 */

if (! defined('DOL_VERSION')) die('');

require_once __DIR__ . '/ApcObjectBase.class.php';

class ApcCotation extends ApcObjectBase
{
    public $table_element = 'apclogistics_cotation';
    public $element       = 'cot';
    public $picto         = 'apclogistics@apclogistics';
    public $prefix_ref_const   = 'APCLOGISTICS_PREFIX_COT';
    public $ref_prefix_default = 'COT-';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_cotation, etendu) */
    public $fields = array(
        'rowid'                      => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'                     => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'ref'                        => array('type'=>'varchar(64)', 'enabled'=>1, 'index'=>true, 'unique'=>true),
        'fk_demandeprix'             => array('type'=>'integer', 'enabled'=>1, 'index'=>true),
        'fk_token'                   => array('type'=>'integer'),
        'fournisseur_nom'            => array('type'=>'varchar(255)', 'enabled'=>1),
        'fournisseur_adresse'        => array('type'=>'text'),
        'fournisseur_tel'            => array('type'=>'varchar(32)'),
        'fournisseur_email'          => array('type'=>'varchar(128)'),
        'fournisseur_contact'        => array('type'=>'varchar(128)'),
        'fk_societe_match'           => array('type'=>'integer', 'index'=>true),
        'fk_societe'                 => array('type'=>'integer'),
        'date_cotation'              => array('type'=>'date', 'enabled'=>1),
        'lieu_livraison'             => array('type'=>'varchar(255)'),
        'date_livraison'             => array('type'=>'date'),
        'date_validite_offre'        => array('type'=>'date'),
        'delai_livraison_jours'      => array('type'=>'integer'),
        'delai_livraison'            => array('type'=>'varchar(128)'),
        'conditions_paiement'        => array('type'=>'varchar(128)'),
        'conditions_reglement'       => array('type'=>'varchar(128)'),
        'conditions_acceptation'     => array('type'=>'varchar(8)'),
        'taux_tva_applicable'        => array('type'=>'decimal(6,3)', 'default'=>0),
        'total_ht'                   => array('type'=>'decimal(24,8)', 'enabled'=>1, 'default'=>0),
        'total_tva'                  => array('type'=>'decimal(24,8)', 'default'=>0),
        'total_ttc'                  => array('type'=>'decimal(24,8)', 'default'=>0),
        'devise'                     => array('type'=>'varchar(3)', 'default'=>'CDF'),
        'conditions_checkbox'        => array('type'=>'smallint', 'default'=>0),
        'signature_nom'              => array('type'=>'varchar(128)'),
        'signature_date'             => array('type'=>'date'),
        'signature_fournisseur_nom'  => array('type'=>'varchar(128)'),
        'signature_fournisseur_fct'  => array('type'=>'varchar(128)'),
        'signature_fournisseur_date' => array('type'=>'date'),
        'signature_fournisseur_ip'   => array('type'=>'varchar(45)'),
        'ecriture_date'              => array('type'=>'datetime'),
        'ip_soumission'              => array('type'=>'varchar(45)'),
        'user_agent_soumission'      => array('type'=>'varchar(255)'),
        'status'                     => array('type'=>'smallint', 'enabled'=>1, 'default'=>0),
        'note_public'                => array('type'=>'text'),
        'note_private'               => array('type'=>'text'),
        'extraparams'                => array('type'=>'text'),
        'date_creation'              => array('type'=>'datetime'),
        'tms'                        => array('type'=>'timestamp'),
        'fk_user_creat'              => array('type'=>'integer'),
        'fk_user_modif'              => array('type'=>'integer'),
    );

    const STATUS_RECEIVED   = 1;
    const STATUS_REVIEWED   = 2;
    const STATUS_RETAINED   = 3;
    const STATUS_REJECTED   = 8;

    public $fk_demandeprix;
    public $fk_token;
    public $fk_societe;
    public $fournisseur_nom;
    public $fournisseur_adresse;
    public $fournisseur_tel;
    public $fournisseur_email;
    public $fournisseur_contact;
    public $date_cotation;
    public $date_validite_offre;
    public $conditions_reglement;
    public $delai_livraison;
    public $lieu_livraison;
    public $conditions_acceptation;
    public $signature_fournisseur_nom;
    public $signature_fournisseur_fct;
    public $signature_fournisseur_date;
    public $signature_fournisseur_ip;
    public $taux_tva_applicable;
    public $total_ht;
    public $total_tva;
    public $total_ttc;
    public $fk_user_review;
    public $note_review;

    public $lines = array();

    public function __construct(DoliDB $db) { $this->db = $db; }

    public function getStatusList()
    {
        global $langs;
        $langs->load('apclogistics@apclogistics');
        return array(
            self::STATUS_DRAFT     => $langs->trans('StatusDraft'),
            self::STATUS_RECEIVED  => $langs->trans('CotStatusReceived'),
            self::STATUS_REVIEWED  => $langs->trans('CotStatusReviewed'),
            self::STATUS_RETAINED  => $langs->trans('CotStatusRetained'),
            self::STATUS_REJECTED  => $langs->trans('CotStatusRejected'),
        );
    }

    public function fetchLines()
    {
        $this->lines = ApcCotationLine::fetchAllForParent($this->db, (int)$this->id);
        return $this->lines;
    }

    public function calculateTotals()
    {
        $this->fetchLines();
        $ht = 0;
        $tva = 0;
        $txTva = (float)$this->taux_tva_applicable;
        foreach ($this->lines as $l) {
            $pu = (float)$l->prix_unitaire_ht;
            $qte = (float)$l->quantite;
            $remise = (float)$l->remise_pct;
            $sousTotal = $pu * $qte;
            if ($remise > 0) $sousTotal = $sousTotal * (1 - ($remise / 100));
            $l->total_ht = round($sousTotal, 2);
            $ht += $l->total_ht;
        }
        $this->total_ht = round($ht, 2);
        if ($txTva > 0) {
            $this->total_tva = round($this->total_ht * ($txTva / 100), 2);
        } else {
            $this->total_tva = 0;
        }
        $this->total_ttc = round($this->total_ht + $this->total_tva, 2);
        return $this->total_ttc;
    }
}

class ApcCotationLine extends ApcTableLineBase
{
    public $table_element    = 'apclogistics_cotation_lines';
    public $element          = 'cot_line';
    public $fk_parent_column = 'fk_cotation';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_cotation_lines, etendu) */
    public $fields = array(
        'rowid'               => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'              => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'fk_cotation'         => array('type'=>'integer', 'enabled'=>1, 'index'=>true),
        'fk_demandeprix_line' => array('type'=>'integer', 'enabled'=>1, 'index'=>true),
        'no_ligne'            => array('type'=>'integer', 'enabled'=>1, 'default'=>0),
        'fk_product'          => array('type'=>'integer', 'index'=>true),
        'description'         => array('type'=>'varchar(255)'),
        'unite'               => array('type'=>'varchar(32)'),
        'quantite'            => array('type'=>'decimal(16,4)', 'enabled'=>1, 'default'=>0),
        'prix_unitaire_ht'    => array('type'=>'decimal(24,8)', 'enabled'=>1, 'default'=>0),
        'remise_pct'          => array('type'=>'decimal(6,3)', 'default'=>0),
        'total_ht'            => array('type'=>'decimal(24,8)', 'enabled'=>1, 'default'=>0),
        'prix_total_partiel'  => array('type'=>'decimal(24,8)', 'default'=>0),
        'remarque'            => array('type'=>'varchar(255)'),
        'tva_tx'              => array('type'=>'decimal(6,3)', 'default'=>0),
        'extraparams'         => array('type'=>'text'),
        'date_creation'       => array('type'=>'datetime'),
        'tms'                 => array('type'=>'timestamp'),
        'fk_user_creat'       => array('type'=>'integer'),
        'fk_user_modif'       => array('type'=>'integer'),
    );

    public $fk_demandeprix_line;
    public $fk_product;
    public $description;
    public $unite;
    public $quantite;
    public $prix_unitaire_ht;
    public $remise_pct;
    public $total_ht;
    public $tva_tx;
    public $remarque;

    public function __construct(DoliDB $db) { $this->db = $db; }
}
