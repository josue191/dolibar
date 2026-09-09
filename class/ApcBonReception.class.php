<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * ApcBonReception — Bon de Reception (transformation 1-clic depuis BC)
 * Decleche une ENTREE de stock sur validation.
 * Champs alignes sur le schema SQL 001_create_schema.sql
 */

if (! defined('DOL_VERSION')) die('');

require_once __DIR__ . '/ApcObjectBase.class.php';
require_once __DIR__ . '/ApcBonCommande.class.php';
require_once __DIR__ . '/ApcStock.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

class ApcBonReception extends ApcObjectBase
{
    public $table_element = 'apclogistics_bonreception';
    public $element       = 'br';
    public $picto         = 'apclogistics@apclogistics';
    public $prefix_ref_const   = 'APCLOGISTICS_PREFIX_BR';
    public $ref_prefix_default = 'BR-';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_bonreception) */
    public $fields = array(
        'rowid'                  => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'                 => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'ref'                    => array('type'=>'varchar(64)', 'enabled'=>1, 'index'=>true, 'unique'=>true),
        'ref_ext'                => array('type'=>'varchar(128)'),
        'fk_boncommande'         => array('type'=>'integer', 'enabled'=>1, 'index'=>true),
        'fk_cotation'            => array('type'=>'integer'),
        'fk_societe'             => array('type'=>'integer'),
        'fournisseur_nom'        => array('type'=>'varchar(255)'),
        'ref_bdl'                => array('type'=>'varchar(128)'),
        'ref_facture'            => array('type'=>'varchar(128)'),
        'date_reception'         => array('type'=>'date', 'enabled'=>1),
        'date_bl'                => array('type'=>'date'),
        'total_ht'               => array('type'=>'decimal(24,8)'),
        'total_tva'              => array('type'=>'decimal(24,8)'),
        'total_ttc'              => array('type'=>'decimal(24,8)'),
        'devise'                 => array('type'=>'varchar(3)', 'default'=>'CDF'),
        'total_qte_commandee'    => array('type'=>'decimal(16,4)', 'default'=>0),
        'total_qte_recue'        => array('type'=>'decimal(16,4)', 'default'=>0),
        'total_ecart'            => array('type'=>'decimal(16,4)', 'default'=>0),
        'status'                 => array('type'=>'smallint', 'enabled'=>1, 'default'=>0),
        'note_public'            => array('type'=>'text'),
        'note_private'           => array('type'=>'text'),
        'fk_user_receptionniste' => array('type'=>'integer'),
        'reception_nom'          => array('type'=>'varchar(128)'),
        'reception_fonction'     => array('type'=>'varchar(128)'),
        'reception_date_sig'     => array('type'=>'datetime'),
        'fk_user_livreur'        => array('type'=>'integer'),
        'livraison_nom'          => array('type'=>'varchar(128)'),
        'livraison_fonction'     => array('type'=>'varchar(128)'),
        'livraison_date_sig'     => array('type'=>'datetime'),
        'livraison_cni'          => array('type'=>'varchar(64)'),
        'stock_integre'          => array('type'=>'smallint', 'default'=>0),
        'date_integration_stock' => array('type'=>'datetime'),
        'extraparams'            => array('type'=>'text'),
        'date_creation'          => array('type'=>'datetime'),
        'tms'                    => array('type'=>'timestamp'),
        'fk_user_creat'          => array('type'=>'integer'),
        'fk_user_modif'          => array('type'=>'integer'),
    );

    // --- Champs entete (alignes sur SQL schema) ---
    public $fk_boncommande;
    public $fk_cotation;
    public $fk_societe;
    public $fournisseur_nom;
    /** @var string  Ref bon de livraison fournisseur (SQL: ref_bdl) */
    public $ref_bdl;
    /** @var string  Ref facture fournisseur (SQL: ref_facture) */
    public $ref_facture;
    public $date_reception;
    /** @var string  Date du bon de livraison fournisseur (SQL: date_bl) */
    public $date_bl;
    public $total_ht;
    public $total_tva;
    public $total_ttc;
    public $devise;
    public $total_qte_commandee;
    public $total_qte_recue;
    public $total_ecart;
    /** @var string  Observations (SQL: note_public) */
    public $note_public;

    // --- Reception APC (SQL: reception_nom, reception_fonction, reception_date_sig) ---
    public $reception_nom;
    public $reception_fonction;
    public $reception_date_sig;
    public $fk_user_receptionniste;

    // --- Livraison Fournisseur (SQL: livraison_nom, livraison_fonction, livraison_date_sig, livraison_cni) ---
    public $livraison_nom;
    public $livraison_fonction;
    public $livraison_date_sig;
    public $livraison_cni;
    public $fk_user_livreur;

    /** @var int  0/1 — stock integre (SQL: stock_integre) */
    public $stock_integre;
    /** @var string  Date d'integration stock (SQL: date_integration_stock) */
    public $date_integration_stock;

    public $lines = array();

    public function __construct(DoliDB $db) { $this->db = $db; }

    public function fetchLines()
    {
        $this->lines = ApcBonReceptionLine::fetchAllForParent($this->db, (int)$this->id);
        return $this->lines;
    }

    public function calculateTotals()
    {
        $this->fetchLines();
        $qteC = 0;
        $qteR = 0;
        $ht = 0;
        foreach ($this->lines as $l) {
            $l->ecart_qte = (float)$l->qte_recue - (float)$l->qte_commandee;
            $l->prix_total_ligne = round((float)$l->prix_unitaire * (float)$l->qte_recue, 2);
            $qteC += (float)$l->qte_commandee;
            $qteR += (float)$l->qte_recue;
            $ht += (float)$l->prix_total_ligne;
        }
        $this->total_qte_commandee = $qteC;
        $this->total_qte_recue    = $qteR;
        $this->total_ecart        = $qteR - $qteC;
        $this->total_ht           = round($ht, 2);
        $this->total_tva          = 0;
        $this->total_ttc          = round($ht, 2);
        return $this->total_ecart;
    }

    /**
     * Cree un BR depuis un BC (pre-remplissage).
     * @param ApcBonCommande $bc
     * @param User $u
     * @return ApcBonReception|false
     */
    public static function createFromBonCommande(ApcBonCommande $bc, User $u, $notrigger = 0)
    {
        $db = $bc->db;
        $br = new self($db);
        $br->fk_boncommande = $bc->id;
        $br->fk_cotation    = $bc->fk_cotation;
        $br->fk_societe     = $bc->fk_societe;
        $br->fournisseur_nom = $bc->fournisseur_nom;
        $br->date_reception = dol_now();

        $res = $br->create($u, $notrigger);
        if ($res <= 0) return false;

        $bc->fetchLines();
        $noLigne = 1;
        foreach ($bc->lines as $bcl) {
            $brl = new ApcBonReceptionLine($db);
            $brl->fk_bonreception  = $br->id;
            $brl->no_ligne         = $noLigne++;
            $brl->fk_boncommande_line = $bcl->id;
            $brl->fk_product       = $bcl->fk_product;
            $brl->description      = $bcl->description;
            $brl->unite            = $bcl->unite;
            $brl->qte_commandee    = $bcl->quantite;
            $brl->qte_recue        = $bcl->quantite;
            $brl->prix_unitaire    = $bcl->prix_unitaire;
            $brl->prix_total_ligne = round((float)$bcl->prix_unitaire * (float)$bcl->quantite, 2);
            $brl->create($u, $notrigger);
        }
        $br->calculateTotals();
        $br->update($u, $notrigger);
        ApcAuditLog::log($br->element, $br->id, 'CREATE_FROM_BC', $u->id,
            null, null, 'Creation depuis Bon de Commande ' . $bc->ref);
        return $br;
    }

    /**
     * Valide le BR, appose signatures, et declare les ENTREES de stock.
     * @return int
     */
    public function validateAndStockIn(User $userRecepteur, $livreurNom, $livreurFct, $livreurCni, $notrigger = 0)
    {
        $this->fetchLines();
        $this->calculateTotals();
        $this->status = self::STATUS_VALIDATED;
        $this->lockRef();

        $this->reception_nom      = $userRecepteur->getFullName($GLOBALS['langs']);
        $this->reception_fonction = $userRecepteur->poste;
        $this->reception_date_sig = dol_now();
        $this->fk_user_receptionniste = $userRecepteur->id;

        $this->livraison_nom      = $livreurNom;
        $this->livraison_fonction = $livreurFct;
        $this->livraison_date_sig = dol_now();
        $this->livraison_cni      = $livreurCni;

        $ok = $this->update($userRecepteur, $notrigger);
        if ($ok <= 0) return $ok;

        $nbMvt = 0;
        foreach ($this->lines as $line) {
            if (!empty($line->fk_product) && (float)$line->qte_recue > 0) {
                $stock = new ApcStock($this->db);
                $res = $stock->loadOrCreateForProduct((int)$line->fk_product, $line->unite, $userRecepteur);
                if ($res >= 0) {
                    $stock->addMovement('BR', (int)$this->id, $this->ref,
                        $line->unite,
                        (float)$line->qte_recue,
                        0,
                        $userRecepteur,
                        'Entree magasin via Bon Reception ' . $this->ref,
                        $notrigger);
                    $line->stock_processed = 1;
                    $line->update($userRecepteur, $notrigger);
                    $nbMvt++;
                }
            }
        }

        $this->stock_integre = 1;
        $this->date_integration_stock = dol_now();
        $this->update($userRecepteur, $notrigger);

        ApcAuditLog::log($this->element, (int)$this->id, 'VALIDATE_STOCK_IN', $userRecepteur->id,
            null, null, 'Validation BR + stock in (' . $nbMvt . ' mouvements)');
        return $ok;
    }
}

class ApcBonReceptionLine extends ApcTableLineBase
{
    public $table_element    = 'apclogistics_bonreception_lines';
    public $element          = 'br_line';
    public $fk_parent_column = 'fk_bonreception';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_bonreception_lines) */
    public $fields = array(
        'rowid'            => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'           => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'fk_bonreception'  => array('type'=>'integer', 'enabled'=>1, 'index'=>true),
        'fk_boncommande_line' => array('type'=>'integer', 'index'=>true),
        'no_ligne'         => array('type'=>'integer', 'enabled'=>1, 'default'=>0),
        'description'      => array('type'=>'varchar(255)', 'enabled'=>1),
        'fk_product'       => array('type'=>'integer', 'index'=>true),
        'unite'            => array('type'=>'varchar(32)'),
        'qte_commandee'    => array('type'=>'decimal(16,4)', 'enabled'=>1, 'default'=>0),
        'qte_recue'        => array('type'=>'decimal(16,4)', 'enabled'=>1, 'default'=>0),
        'ecart_qte'        => array('type'=>'decimal(16,4)', 'default'=>0),
        'prix_unitaire'    => array('type'=>'decimal(24,8)', 'enabled'=>1, 'default'=>0),
        'prix_total_ligne' => array('type'=>'decimal(24,8)', 'enabled'=>1, 'default'=>0),
        'motif_ecart'      => array('type'=>'varchar(255)'),
        'stock_processed'  => array('type'=>'smallint', 'default'=>0),
        'extraparams'      => array('type'=>'text'),
        'date_creation'    => array('type'=>'datetime'),
        'tms'              => array('type'=>'timestamp'),
        'fk_user_creat'    => array('type'=>'integer'),
        'fk_user_modif'    => array('type'=>'integer'),
    );

    public $fk_boncommande_line;
    public $fk_product;
    public $description;
    public $unite;
    public $qte_commandee;
    public $qte_recue;
    public $ecart_qte;
    /** @var float  Prix unitaire HT (SQL: prix_unitaire) */
    public $prix_unitaire;
    /** @var float  Total ligne (SQL: prix_total_ligne) */
    public $prix_total_ligne;
    public $motif_ecart;
    /** @var int  0/1 — ligne integree en stock (SQL: stock_processed) */
    public $stock_processed;

    public function __construct(DoliDB $db) { $this->db = $db; }
}