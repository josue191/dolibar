<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * Classe abstraite commune a toutes les entites "tetes" du module APC Logistics.
 * Factorise : ref unique, status standards, audit log auto,
 *              blocage ref apres validation, toJSON.
 */

if (! defined('DOL_VERSION')) die('');

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';
require_once __DIR__ . '/ApcAuditLog.class.php';

abstract class ApcObjectBase extends CommonObject
{
    /** @var string  Nom de la table SQL (sans prefixe llx_), e.g. apclogistics_etatbesoin */
    public $table_element;
    /** @var string  Identifiant metier court, ex 'eb' */
    public $element;
    /** @var string  Clé prefixe constantes Dolibarr, ex 'EB' */
    public $prefix_ref_const = '';
    /** @var string  Valeur du prefixe numerotation par defaut, ex 'EB-' */
    public $ref_prefix_default = '';

    // Status standards (valeurs)
    const STATUS_DRAFT        = 0;
    const STATUS_PENDING      = 1;
    const STATUS_VALIDATED    = 2;
    const STATUS_SENT         = 3;
    const STATUS_EXECUTED     = 4;
    const STATUS_CLOSED       = 5;
    const STATUS_CANCELLED    = 9;

    /** @var bool Verrouillage de $ref : une fois verrouille, plus modifiable */
    protected $refLocked = false;

    /**
     * Retourne le tableau des status possibles, traduits.
     * @return array  [code => libelle]
     */
    public function getStatusList()
    {
        global $langs;
        $langs->load('apclogistics@apclogistics');
        return array(
            self::STATUS_DRAFT     => $langs->trans('StatusDraft'),
            self::STATUS_PENDING   => $langs->trans('StatusPending'),
            self::STATUS_VALIDATED => $langs->trans('StatusValidated'),
            self::STATUS_SENT      => $langs->trans('StatusSent'),
            self::STATUS_EXECUTED  => $langs->trans('StatusExecuted'),
            self::STATUS_CLOSED    => $langs->trans('StatusClosed'),
            self::STATUS_CANCELLED => $langs->trans('StatusCancelled'),
        );
    }

    /**
     * Retourne HTML d'une pastille de statut.
     * @param int|null $s  si null, utilise $this->status
     * @return string
     */
    public function getStatusBadge($s = null)
    {
        $list = $this->getStatusList();
        $v = ($s === null) ? (int)$this->status : (int)$s;
        $label = isset($list[$v]) ? $list[$v] : (string)$v;
        $mapClass = array(
            self::STATUS_DRAFT     => 'brouillon',
            self::STATUS_PENDING   => 'attente',
            self::STATUS_VALIDATED => 'valide',
            self::STATUS_SENT      => 'envoye',
            self::STATUS_EXECUTED  => 'execute',
            self::STATUS_CLOSED    => 'solde',
            self::STATUS_CANCELLED => 'annule',
        );
        $cls = isset($mapClass[$v]) ? $mapClass[$v] : 'brouillon';
        return sprintf('<span class="apc-status apc-status--%s">%s</span>', $cls, $label);
    }

    /**
     * Verrouille la reference : a appeler juste avant STATUS_VALIDATED.
     * @return void
     */
    public function lockRef()
    {
        $this->refLocked = true;
    }

    /**
     * Surcharge CommonObject pour :
     *  - ne pas accepter modification de $ref si verrouille
     *  - logguer audit log auto pour create/update/delete
     */
    public function create($user, $notrigger = 0)
    {
        global $conf;

        if (empty($this->ref)) {
            $this->ref = ApcNumbering::computeNextRef($this, $this->table_element);
        }

        // Champ 'annee' (exercice) : si la classe le declare et qu'il n'est pas renseigne,
        // on l'aligne sur l'annee courante (coherent avec ApcNumbering::computeNextRef).
        if (array_key_exists('annee', $this->fields) && empty($this->annee)) {
            $this->annee = (int)date('Y');
        }

        $oldJson = null;

        $res = parent::create($user, $notrigger);
        if ($res > 0) {
            ApcAuditLog::log(
                $this->element,
                (int)$this->id,
                'CREATE',
                ($user ? (int)$user->id : 0),
                null,
                json_encode($this->toArray(), JSON_UNESCAPED_UNICODE)
            );
        }
        return $res;
    }

    public function update($user = 0, $notrigger = 0, $allowemptyref = 0)
    {
        if ($this->refLocked && $this->refHasChanged()) {
            $this->error = 'ErrorRefLockedAfterValidation';
            return -1;
        }

        // Garde-fou identique a create() : 'annee' ne doit jamais etre NULL (colonne NOT NULL).
        if (array_key_exists('annee', $this->fields) && empty($this->annee)) {
            $this->annee = (int)date('Y');
        }

        $old = null;
        try {
            $clone = clone $this;
            $clone->fetch($this->id);
            $old = json_encode($clone->toArray(), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) { $old = null; }

        $res = parent::update($user, $notrigger, $allowemptyref);
        if ($res > 0) {
            ApcAuditLog::log(
                $this->element,
                (int)$this->id,
                'UPDATE',
                ($user ? $user->id : 0),
                $old,
                json_encode($this->toArray(), JSON_UNESCAPED_UNICODE)
            );
        }
        return $res;
    }

    public function delete($user, $notrigger = 0)
    {
        $old = json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
        $type = $this->element;
        $id = (int)$this->id;

        $res = parent::delete($user, $notrigger);
        if ($res > 0) {
            ApcAuditLog::log($type, $id, 'DELETE', ($user ? (int)$user->id : 0), $old, null);
        }
        return $res;
    }

    /**
     * Retourne les champs principaux sous forme de array (pour JSON / Audit).
     * A surcharger pour les classes filles si besoin de champs supplementaires.
     * @return array
     */
    public function toArray()
    {
        $arr = array();
        foreach (array('rowid','id','entity','ref','ref_ext','status','date_creation','tms',
                       'fk_user_creat','fk_user_modif','total_ht','total_ttc','total',
                       'note_public','note_private') as $k) {
            if (isset($this->$k)) $arr[$k] = $this->$k;
        }
        return $arr;
    }

    /**
     * @return bool  true si $this->ref a ete modifie par rapport a la BDD
     */
    protected function refHasChanged()
    {
        if (empty($this->id)) return false;
        $sql = "SELECT ref FROM " . MAIN_DB_PREFIX . $this->table_element . " WHERE rowid = " . (int)$this->id;
        $res = $this->db->query($sql);
        if (!$res) return false;
        $obj = $this->db->fetch_object($res);
        if (!$obj) return false;
        return ($obj->ref !== $this->ref);
    }

    /**
     * Retourne l'URL vers la fiche de l'objet.
     * Conforme a Dolibarr conventions CommonObject.
     */
    public function getNomUrl($withpicto = 0, $option = '', $nourl = 0)
    {
        global $langs;
        $langs->load('apclogistics@apclogistics');

        $map = array(
            'eb'   => 'etatbesoin_card.php',
            'req'  => 'requisition_card.php',
            'dp'   => 'demandeprix_card.php',
            'cot'  => 'cotation_card.php',
            'bc'   => 'boncommande_card.php',
            'br'   => 'bonreception_card.php',
            'stock'=> 'stock_card.php',
            'dav'  => 'demandeavance_card.php',
            'jav'  => 'justifavance_card.php',
            'dpai' => 'demandepaiement_card.php',
        );
        $script = isset($map[$this->element]) ? $map[$this->element] : '#';
        $url = dol_buildpath('/custom/apclogistics/' . $script, 1) . '?id=' . $this->id;

        $label = $langs->trans('Show') . ' ' . ($this->ref ?: $this->id);
        $picto = img_picto($label, 'object_' . $this->element . '@apclogistics', 'class="pictofixedwidth"', false);
        if (empty($this->picto) || !$picto) {
            $picto = img_picto($label, 'apclogistics@apclogistics', 'class="pictofixedwidth"', false);
        }

        if ($nourl) return ($withpicto ? $picto . ' ' : '') . $this->ref;

        $out = '<a href="' . $url . '" title="' . dol_escape_htmltag($label, 1) . '">';
        if ($withpicto) $out .= $picto;
        if ($withpicto) $out .= ' ';
        $out .= $this->ref;
        $out .= '</a>';
        return $out;
    }
}


/**
 * Classe abstraite pour toutes les lignes associees a une entete
 * (ApcEtatBesoinLine, ApcRequisitionLine, ApcDemandePrixLine, etc.)
 */
abstract class ApcTableLineBase extends CommonObject
{
    public $table_element;
    public $element;
    /** @var string  Nom du champ FK vers la table entete */
    public $fk_parent_column;

    public $no_ligne;

    /**
     * Retourne toutes les lignes d'une entete, triees par no_ligne.
     * @param DoliDB $db
     * @param int    $parentId
     * @return static[]
     */
    public static function fetchAllForParent($db, $parentId)
    {
        $self = new static($db);
        $tbl = MAIN_DB_PREFIX . $self->table_element;
        $fk  = $self->fk_parent_column;
        $sql = "SELECT rowid FROM " . $tbl . " WHERE " . $fk . " = " . (int)$parentId . " ORDER BY no_ligne ASC, rowid ASC";
        $res = $db->query($sql);
        $lines = array();
        if ($res) {
            while ($obj = $db->fetch_object($res)) {
                $l = new static($db);
                if ($l->fetch($obj->rowid) > 0) $lines[] = $l;
            }
        }
        return $lines;
    }

    public function deleteAllForParent($user, $parentId)
    {
        $tbl = MAIN_DB_PREFIX . $this->table_element;
        $fk  = $this->fk_parent_column;
        $sql = "DELETE FROM " . $tbl . " WHERE " . $fk . " = " . (int)$parentId;
        if (!$this->db->query($sql)) {
            $this->error = $this->db->lasterror();
            return -1;
        }
        return 1;
    }
}
