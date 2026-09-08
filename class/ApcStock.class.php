<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * ApcStock — Fiche stock par produit + mouvements
 * addMovement() met a jour stock_actuel et cree ligne movement.
 * Champs alignes sur le schema SQL 001_create_schema.sql
 */

if (! defined('DOL_VERSION')) die('');

require_once __DIR__ . '/ApcObjectBase.class.php';

class ApcStock extends ApcObjectBase
{
    public $table_element = 'apclogistics_stock';
    public $element       = 'stock';
    public $picto         = 'apclogistics@apclogistics';
    public $prefix_ref_const   = 'APCLOGISTICS_PREFIX_STOCK';
    public $ref_prefix_default = 'STK-';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_stock) */
    public $fields = array(
        'rowid'                  => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'                 => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'fk_product'             => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'unique'=>true),
        'designation'            => array('type'=>'varchar(255)'),
        'unite'                  => array('type'=>'varchar(32)'),
        'stock_actuel'           => array('type'=>'decimal(16,4)', 'enabled'=>1, 'default'=>0),
        'seuil_alerte'           => array('type'=>'decimal(16,4)', 'default'=>1),
        'emplacement'            => array('type'=>'varchar(128)'),
        'fk_user_gestionnaire'   => array('type'=>'integer'),
        'fk_user_resp_logistique'=> array('type'=>'integer'),
        'last_movement_date'     => array('type'=>'datetime'),
        'last_stock_update'      => array('type'=>'datetime'),
        'extraparams'            => array('type'=>'text'),
        'date_creation'          => array('type'=>'datetime'),
        'tms'                    => array('type'=>'timestamp'),
        'fk_user_creat'          => array('type'=>'integer'),
        'fk_user_modif'          => array('type'=>'integer'),
    );

    public $fk_product;
    /** @var string  Designation produit (SQL: designation) */
    public $designation;
    public $unite;
    public $stock_actuel;
    public $seuil_alerte;
    public $emplacement;
    public $fk_user_gestionnaire;
    public $fk_user_resp_logistique;
    /** @var string  Date du dernier mouvement (SQL: last_movement_date) */
    public $last_movement_date;
    /** @var string  Date de la derniere mise a jour stock (SQL: last_stock_update) */
    public $last_stock_update;

    public $movements = array();

    public function __construct(DoliDB $db) { $this->db = $db; }

    public function fetchLines()
    {
        $this->movements = ApcStockMovement::fetchAllForParent($this->db, (int)$this->id);
        return $this->movements;
    }

    public function fetchMovements($limit = 500)
    {
        $rows = array();
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "apclogistics_stock_movements"
             . " WHERE fk_stock = " . (int)$this->id
             . " ORDER BY date_movement ASC, rowid ASC"
             . " LIMIT " . (int)$limit;
        $res = $this->db->query($sql);
        if (!$res) return $rows;
        while ($o = $this->db->fetch_object($res)) {
            $m = new ApcStockMovement($this->db);
            if ($m->fetch($o->rowid) > 0) $rows[] = $m;
        }
        $this->movements = $rows;
        return $rows;
    }

    /**
     * Charge ou cree une fiche stock pour un produit Dolibarr.
     * @return int  rowid si ok, -1 si erreur
     */
    public function loadOrCreateForProduct($fkProduct, $unite, User $userCreator)
    {
        global $conf, $langs;
        $fkProduct = (int)$fkProduct;
        if ($fkProduct > 0) {
            $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . $this->table_element
                 . " WHERE fk_product = " . $fkProduct . " LIMIT 1";
            $res = $this->db->query($sql);
            if ($res && $o = $this->db->fetch_object($res)) {
                return $this->fetch($o->rowid);
            }
        }
        if (empty($this->ref)) {
            $this->ref = ApcNumbering::computeNextRef($this, $this->table_element);
        }
        $this->fk_product = $fkProduct;
        $this->unite = $unite;
        if ($fkProduct > 0) {
            $p = new Product($this->db);
            if ($p->fetch($fkProduct) > 0) {
                $this->designation = $p->label;
            }
        } else {
            $this->designation = 'Produit externe';
        }
        if (!isset($this->stock_actuel)) $this->stock_actuel = 0;
        $seuilConf = isset($conf->global->APCLOGISTICS_STOCK_ALERT) ? (int)$conf->global->APCLOGISTICS_STOCK_ALERT : 5;
        if (!isset($this->seuil_alerte)) $this->seuil_alerte = $seuilConf;
        return $this->create($userCreator);
    }

    /**
     * Ajoute un mouvement de stock (IN ou OUT) et met a jour stock_actuel.
     * @param string $typeMvt  'BR'|'REQ'|'INV'|'AJUST'
     * @param int    $fkDoc    rowid du document origine
     * @param string $refDoc   reference du document
     * @param string $unite
     * @param float  $qteIn    quantite entree (0 si sortie)
     * @param float  $qteOut   quantite sortie (0 si entree)
     * @param User   $user
     * @param string $motif
     * @return int|false
     */
    public function addMovement($typeMvt, $fkDoc, $refDoc, $unite, $qteIn, $qteOut, User $user, $motif = '')
    {
        $qteIn  = (float)$qteIn;
        $qteOut = (float)$qteOut;
        $before = (float)$this->stock_actuel;
        $after  = $before + $qteIn - $qteOut;

        $mvt = new ApcStockMovement($this->db);
        $mvt->fk_stock        = $this->id;
        $mvt->fk_product      = $this->fk_product;
        $mvt->date_movement   = dol_now();
        $mvt->ref_doc_type    = $typeMvt;
        $mvt->ref_doc_id      = (int)$fkDoc;
        $mvt->ref_doc_label   = $refDoc;
        $mvt->unite           = $unite;
        $mvt->entree          = $qteIn;
        $mvt->sortie          = $qteOut;
        $mvt->stock_apres     = $after;
        $mvt->motif           = $motif;
        $mvt->fk_user         = $user->id;

        $resMvt = $mvt->create($user);
        if ($resMvt <= 0) {
            $this->error = $mvt->error;
            return false;
        }

        $this->stock_actuel = $after;
        $this->last_movement_date = dol_now();
        $this->last_stock_update  = dol_now();
        $res = $this->update($user);

        ApcAuditLog::log($this->element, $this->id, 'MVT_' . $typeMvt, $user->id,
            null, null,
            'Stock avant=' . $before . ' apres=' . $after . ' IN=' . $qteIn . ' OUT=' . $qteOut . ' (' . $refDoc . ')');
        return $res;
    }

    public function isLowStock()
    {
        return (float)$this->stock_actuel <= (float)$this->seuil_alerte;
    }

    public static function fetchAllLowStock($db)
    {
        $rows = array();
        $sql = "SELECT s.rowid FROM " . MAIN_DB_PREFIX . "apclogistics_stock s"
             . " WHERE s.stock_actuel <= s.seuil_alerte"
             . " ORDER BY s.stock_actuel ASC";
        $res = $db->query($sql);
        if (!$res) return $rows;
        while ($o = $db->fetch_object($res)) {
            $st = new self($db);
            if ($st->fetch($o->rowid) > 0) $rows[] = $st;
        }
        return $rows;
    }
}

class ApcStockMovement extends ApcTableLineBase
{
    public $table_element    = 'apclogistics_stock_movements';
    public $element          = 'stock_mvt';
    public $fk_parent_column = 'fk_stock';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_stock_movements) */
    public $fields = array(
        'rowid'            => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'           => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'fk_stock'         => array('type'=>'integer', 'enabled'=>1, 'index'=>true),
        'fk_product'       => array('type'=>'integer', 'enabled'=>1, 'index'=>true),
        'date_movement'    => array('type'=>'date', 'enabled'=>1),
        'ref_doc_type'     => array('type'=>'varchar(8)', 'enabled'=>1),
        'ref_doc_id'       => array('type'=>'integer'),
        'ref_doc_label'    => array('type'=>'varchar(64)'),
        'unite'            => array('type'=>'varchar(32)'),
        'entree'           => array('type'=>'decimal(16,4)', 'enabled'=>1, 'default'=>0),
        'sortie'           => array('type'=>'decimal(16,4)', 'enabled'=>1, 'default'=>0),
        'stock_apres'      => array('type'=>'decimal(16,4)', 'default'=>0),
        'motif'            => array('type'=>'varchar(255)'),
        'fk_user'          => array('type'=>'integer'),
        'extraparams'      => array('type'=>'text'),
        'date_creation'    => array('type'=>'datetime'),
        'tms'              => array('type'=>'timestamp'),
        'fk_user_creat'    => array('type'=>'integer'),
        'fk_user_modif'    => array('type'=>'integer'),
    );

    public $fk_product;
    /** @var string  Date du mouvement (SQL: date_movement) */
    public $date_movement;
    /** @var string  Type de document origine : BC|BR|REQ|INV|AJUST (SQL: ref_doc_type) */
    public $ref_doc_type;
    /** @var int  rowid du document origine (SQL: ref_doc_id) */
    public $ref_doc_id;
    /** @var string  Ref humaine du document origine (SQL: ref_doc_label) */
    public $ref_doc_label;
    public $unite;
    /** @var float  Quantite entree (SQL: entree) */
    public $entree;
    /** @var float  Quantite sortie (SQL: sortie) */
    public $sortie;
    public $stock_apres;
    public $motif;
    public $fk_user;

    public function __construct(DoliDB $db) { $this->db = $db; }
}