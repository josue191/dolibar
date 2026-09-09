<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * ApcRequisition — Requisition / Bon de sortie magasin
 * Decleche une SORTIE de stock sur validation.
 */

if (! defined('DOL_VERSION')) die('');

require_once __DIR__ . '/ApcObjectBase.class.php';
require_once __DIR__ . '/ApcStock.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

class ApcRequisition extends ApcObjectBase
{
    public $table_element = 'apclogistics_requisition';
    public $element       = 'req';
    public $picto         = 'apclogistics@apclogistics';
    public $prefix_ref_const   = 'APCLOGISTICS_PREFIX_REQ';
    public $ref_prefix_default = 'REQ-';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_requisition) */
    public $fields = array(
        'rowid'                    => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'                   => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'ref'                      => array('type'=>'varchar(64)', 'enabled'=>1, 'index'=>true, 'unique'=>true),
        'ref_ext'                  => array('type'=>'varchar(128)'),
        'date_demande'             => array('type'=>'date', 'enabled'=>1),
        'date_sortie'              => array('type'=>'date'),
        'objet'                    => array('type'=>'varchar(255)'),
        'fk_etatbesoin'            => array('type'=>'integer', 'index'=>true),
        'status'                   => array('type'=>'smallint', 'enabled'=>1, 'default'=>0),
        'note_public'              => array('type'=>'text'),
        'note_private'             => array('type'=>'text'),
        'fk_user_demandeur'        => array('type'=>'integer'),
        'demandeur_nom'            => array('type'=>'varchar(128)'),
        'demandeur_fonction'       => array('type'=>'varchar(128)'),
        'date_signature_demandeur' => array('type'=>'datetime'),
        'fk_user_magasinier'       => array('type'=>'integer'),
        'magasinier_nom'           => array('type'=>'varchar(128)'),
        'magasinier_fonction'      => array('type'=>'varchar(128)'),
        'date_signature_magasinier'=> array('type'=>'datetime'),
        'extraparams'              => array('type'=>'text'),
        'date_creation'            => array('type'=>'datetime'),
        'tms'                      => array('type'=>'timestamp'),
        'fk_user_creat'            => array('type'=>'integer'),
        'fk_user_modif'            => array('type'=>'integer'),
    );

    public $lines = array();   // ApcRequisitionLine[]
    public $fk_etatbesoin;

    public function __construct(DoliDB $db) { $this->db = $db; }

    public function fetchLines()
    {
        $this->lines = ApcRequisitionLine::fetchAllForParent($this->db, (int)$this->id);
        foreach ($this->lines as $l) {
            if (empty($l->ecart_qte)) {
                $l->ecart_qte = (float)$l->qte_demandee - (float)$l->qte_sortie;
            }
        }
        return $this->lines;
    }

    /**
     * Applique la signature Demandeur (level 0) — preparation avant validation stock.
     * Appelé depuis requisition_card.php action=sign&level=0.
     * @param int $level 0=Demandeur
     * @param User $user
     * @param string|null $nom
     * @param string|null $fonction
     * @return int
     */
    public function sign($level, User $user, $nom = null, $fonction = null, $notrigger = 0)
    {
        $level = (int)$level;
        if ($level !== 0) return -1;
        $this->fk_user_demandeur = (int)$user->id;
        $this->demandeur_nom = $nom ?: $user->getFullName($GLOBALS['langs']);
        $this->demandeur_fonction = $fonction;
        $this->date_signature_demandeur = dol_now();
        $this->status = max($this->status, self::STATUS_PENDING);
        $res = $this->update($user, $notrigger);
        if ($res > 0) {
            ApcAuditLog::log($this->element, (int)$this->id, 'SIGN', $user->id,
                null, null, 'Signature demandeur (level 0)');
        }
        return $res;
    }

    /**
     * Valide la sortie : enregistre les mouvements de stock (SORTIE)
     * et applique la signature Magasinier (level 1).
     * Doit être appelée APRES la signature Demandeur (level 0) — mais pas strict.
     * @param User $userDemandeur  ignoré, gardé pour rétro-compatibilité
     * @param User $userMagasinier celui qui signe la validation + applique stock
     * @param string|null $nom     Nom signataire
     * @param string|null $fonction Fonction signataire
     * @return int
     */
    public function validateAndProcessStock(User $userDemandeur, User $userMagasinier, $nom = null, $fonction = null, $notrigger = 0)
    {
        $this->fetchLines();

        // Signature Magasinier
        $this->fk_user_magasinier = (int)$userMagasinier->id;
        $this->magasinier_nom = $nom ?: $userMagasinier->getFullName($GLOBALS['langs']);
        $this->magasinier_fonction = $fonction;
        $this->date_signature_magasinier = dol_now();
        $this->date_sortie = dol_now();

        $this->status = self::STATUS_VALIDATED;
        $this->lockRef();
        $ok = $this->update($userMagasinier, $notrigger);
        if ($ok <= 0) return $ok;

        $countStock = 0;
        foreach ($this->lines as $line) {
            if (!empty($line->fk_product) && (float)$line->qte_sortie > 0) {
                $stock = new ApcStock($this->db);
                $res = $stock->loadOrCreateForProduct((int)$line->fk_product, $line->unite, $userMagasinier);
                if ($res >= 0) {
                    $stock->addMovement('REQ', (int)$this->id, $this->ref,
                        $line->unite,
                        0,
                        (float)$line->qte_sortie,
                        $userMagasinier,
                        'Sortie magasin via Requisition ' . $this->ref,
                        $notrigger);
                    $countStock++;
                }
            }
        }
        ApcAuditLog::log($this->element, (int)$this->id, 'VALIDATE', $userMagasinier->id,
            null, null, 'Signature magasinier + traitement stock effectue (' . $countStock . ' / ' . count($this->lines) . ' lignes avec stock)');
        return $ok;
    }
}

class ApcRequisitionLine extends ApcTableLineBase
{
    public $table_element    = 'apclogistics_requisition_lines';
    public $element          = 'req_line';
    public $fk_parent_column = 'fk_requisition';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_requisition_lines) */
    public $fields = array(
        'rowid'            => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'           => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'fk_requisition'   => array('type'=>'integer', 'enabled'=>1, 'index'=>true),
        'no_ligne'         => array('type'=>'integer', 'enabled'=>1, 'default'=>0),
        'date_mouvement'   => array('type'=>'date'),
        'description'      => array('type'=>'varchar(255)', 'enabled'=>1),
        'fk_product'       => array('type'=>'integer', 'index'=>true),
        'unite'            => array('type'=>'varchar(32)'),
        'qte_demandee'     => array('type'=>'decimal(16,4)', 'enabled'=>1, 'default'=>0),
        'qte_sortie'       => array('type'=>'decimal(16,4)', 'enabled'=>1, 'default'=>0),
        'ecart_qte'        => array('type'=>'decimal(16,4)', 'default'=>0),
        'extraparams'      => array('type'=>'text'),
        'date_creation'    => array('type'=>'datetime'),
        'tms'              => array('type'=>'timestamp'),
        'fk_user_creat'    => array('type'=>'integer'),
        'fk_user_modif'    => array('type'=>'integer'),
    );

    public $date_mouvement;
    public $description;
    public $fk_product;
    public $unite;
    public $qte_demandee;
    public $qte_sortie;
    public $ecart_qte;

    public function __construct(DoliDB $db) { $this->db = $db; }
}
