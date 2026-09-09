<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * ApcDemandePrix — Demande de prix envoyee aux fournisseurs
 *   — Genere un token + lien public pour portail fournisseur de reponse
 */

if (! defined('DOL_VERSION')) die('');

require_once __DIR__ . '/ApcObjectBase.class.php';
require_once __DIR__ . '/ApcToken.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

class ApcDemandePrix extends ApcObjectBase
{
    public $table_element = 'apclogistics_demandeprix';
    public $element       = 'dp';
    public $picto         = 'apclogistics@apclogistics';
    public $prefix_ref_const   = 'APCLOGISTICS_PREFIX_DP';
    public $ref_prefix_default = 'DP-';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_demandeprix) */
    public $fields = array(
        'rowid'               => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'              => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'ref'                 => array('type'=>'varchar(64)', 'enabled'=>1, 'index'=>true, 'unique'=>true),
        'ref_ext'             => array('type'=>'varchar(128)'),
        'annee'               => array('type'=>'integer', 'enabled'=>1),
        'date_dp'             => array('type'=>'date', 'enabled'=>1),
        'fk_societe_cible'    => array('type'=>'integer', 'index'=>true),
        'fournisseur_nom'     => array('type'=>'varchar(255)'),
        'fournisseur_adresse' => array('type'=>'text'),
        'fournisseur_tel'     => array('type'=>'varchar(32)'),
        'fournisseur_email'   => array('type'=>'varchar(128)'),
        'fournisseur_contact' => array('type'=>'varchar(128)'),
        'apc_organisation'    => array('type'=>'varchar(255)', 'default'=>'APC ONG Agri-Peace and Child'),
        'apc_adresse'         => array('type'=>'varchar(255)'),
        'apc_contact_nom'     => array('type'=>'varchar(128)'),
        'lieu_livraison'      => array('type'=>'varchar(255)'),
        'date_livraison'      => array('type'=>'date'),
        'status'              => array('type'=>'smallint', 'enabled'=>1, 'default'=>0),
        'note_public'         => array('type'=>'text'),
        'note_private'        => array('type'=>'text'),
        'mention_legale'      => array('type'=>'text'),
        'date_envoi'          => array('type'=>'datetime'),
        'fk_user_envoi'       => array('type'=>'integer'),
        'extraparams'         => array('type'=>'text'),
        'date_creation'       => array('type'=>'datetime'),
        'tms'                 => array('type'=>'timestamp'),
        'fk_user_creat'       => array('type'=>'integer'),
        'fk_user_modif'       => array('type'=>'integer'),
    );

    public $annee;
    public $date_dp;
    public $fk_societe_cible;
    public $fournisseur_nom;
    public $fournisseur_adresse;
    public $fournisseur_tel;
    public $fournisseur_email;
    public $fournisseur_contact;
    public $lieu_livraison;
    public $date_livraison;
    public $mention_legale;

    public $lines = array();
    /** @var array stocke les token generes : [(int)token_rowid => (string)clear_token] */
    public $generatedTokens = array();

    public function __construct(DoliDB $db) { $this->db = $db; }

    public function fetchLines()
    {
        $this->lines = ApcDemandePrixLine::fetchAllForParent($this->db, (int)$this->id);
        return $this->lines;
    }

    /**
     * Genere un NOUVEAU token pour cette DP, retourne URL publique.
     * @param User $user
     * @param int  $validityDays  si null utilise conf
     * @return string|false  URL complete ex. https://site.org/custom/apclogistics/public/cotation.php?token=xxx
     */
    public function generateSupplierLink(User $user, $validityDays = null)
    {
        global $conf;
        $clear = ApcToken::generate($this->db, (int)$this->id, $validityDays);
        if (!$clear) return false;

        // Recupere rowid token (best effort)
        $q = "SELECT rowid FROM " . MAIN_DB_PREFIX . "apclogistics_tokens"
           . " WHERE fk_demandeprix = " . (int)$this->id . " ORDER BY rowid DESC LIMIT 1";
        $r = $this->db->query($q);
        $rowid = 0;
        if ($r && $o = $this->db->fetch_object($r)) $rowid = (int)$o->rowid;

        $this->generatedTokens[$rowid] = $clear;

        $this->status = self::STATUS_SENT;
        $this->date_envoi = dol_now();
        $this->fk_user_envoi = $user->id;
        if (empty($this->mention_legale)) {
            $this->mention_legale = empty($conf->global->APCLOGISTICS_DP_LEGAL_NOTICE)
                ? "Cette demande de prix n'oblige en rien APC a contracter, a acheter ou a consommer votre service"
                : $conf->global->APCLOGISTICS_DP_LEGAL_NOTICE;
        }
        $this->update($user);

        ApcAuditLog::log($this->element, (int)$this->id, 'SEND', $user->id, null, null,
            'Generation lien fournisseur (token_id=' . $rowid . ')');

        $url = DOL_MAIN_URL_ROOT . '/custom/apclogistics/public/cotation.php?token=' . $clear;

        // ======= ENVOI EMAIL CMailFile Dolibarr (TR-7.2) =======
        if (!class_exists('CMailFile')) {
            require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
        }
        global $langs;
        if (!is_object($langs)) {
            $langs = new Translate('', $conf);
            $langs->setDefaultLang($conf->global->MAIN_LANG_DEFAULT ? $conf->global->MAIN_LANG_DEFAULT : 'fr_FR');
        }
        $langs->load('apclogistics@apclogistics');

        $fromName  = 'APC ONG Agri-Peace and Child';
        $fromEmail = !empty($conf->global->MAIN_INFO_SOCIETE_MAIL) ? $conf->global->MAIN_INFO_SOCIETE_MAIL : 'noreply@apc-ong.org';
        $from = $fromName . ' <' . $fromEmail . '>';
        $objet = $langs->trans('DPEmailObjet', $this->ref);
        $lienDP = DOL_MAIN_URL_ROOT . '/custom/apclogistics/demandeprix_card.php?id=' . $this->id;

        // --- 1) Email au fournisseur ---
        if (!empty($this->fournisseur_email)) {
            $corps = '<html><body style="font-family:Arial,sans-serif; font-size:13px; color:#222;">';
            $corps .= '<p>' . $langs->trans('DPEmailSalutation', (!empty($this->fournisseur_contact) ? $this->fournisseur_contact : $this->fournisseur_nom)) . ',</p>';
            $corps .= '<p>' . $langs->trans('DPEmailIntro', $this->ref) . '</p>';
            $corps .= '<p><b>' . $langs->trans('DPEmailLien') . ' :</b><br>';
            $corps .= '<a href="' . $url . '" style="background:#0f623c; color:#fff; padding:10px 16px; border-radius:4px; text-decoration:none; display:inline-block;">' . $langs->trans('DPEmailLienCta') . '</a>';
            $corps .= '<br><span style="color:#666; font-size:12px;">' . $langs->trans('DPEmailLienAlt') . ' : <br><code>' . $url . '</code></span></p>';
            if (!empty($this->mention_legale)) {
                $corps .= '<p style="color:#555; font-style:italic; border-left:3px solid #0f623c; padding:4px 8px;">' . $this->mention_legale . '</p>';
            }
            $corps .= '<p>-- <br>' . $fromName . '<br>' . $fromEmail . '</p>';
            $corps .= '</body></html>';

            $mail = new CMailFile($objet, $this->fournisseur_email, $from, $corps,
                array(), array(), array(),
                '', '', 1, 1);
            $mail->sendfile();
        }

        // --- 2) Notification au logisticien / adresse APC ---
        if (!empty($conf->global->APCLOGISTICS_NOTIF_EMAIL)) {
            $corpsNotif = '<html><body style="font-family:Arial,sans-serif; font-size:13px;">';
            $corpsNotif .= '<p><b>' . $langs->trans('DPNotifTitre') . '</b></p>';
            $corpsNotif .= '<ul>';
            $corpsNotif .= '<li>' . $langs->trans('DPNotifRef') . ' : <b>' . $this->ref . '</b> (<a href="' . $lienDP . '">'. $langs->trans('DPNotifOpen') .'</a>)</li>';
            $corpsNotif .= '<li>' . $langs->trans('DPNotifFournisseur') . ' : ' . (!empty($this->fournisseur_nom) ? $this->fournisseur_nom : '') . (!empty($this->fournisseur_email) ? ' &lt;' . $this->fournisseur_email . '&gt;' : '') . '</li>';
            $corpsNotif .= '<li>' . $langs->trans('DPNotifEnvoyePar') . ' : ' . $user->getFullName($langs) . '</li>';
            $corpsNotif .= '<li>' . $langs->trans('DPNotifDate') . ' : ' . date('d/m/Y H:i') . '</li>';
            $corpsNotif .= '</ul></body></html>';

            $mailN = new CMailFile($langs->trans('DPNotifObjet', $this->ref),
                $conf->global->APCLOGISTICS_NOTIF_EMAIL, $from, $corpsNotif,
                array(), array(), array(),
                '', '', 1, 1);
            $mailN->sendfile();
        }
        dol_syslog(__METHOD__ . ' DP envoyée email fournisseur ' . $this->fournisseur_email . ' lien: ' . $url, LOG_DEBUG);

        return $url;
    }

    /** Retourne la liste des COTATIONS (reponses fournisseurs) pour cette DP */
    public function fetchCotations()
    {
        $rows = array();
        $sql = "SELECT rowid, ref, fournisseur_nom, date_cotation, total_ht, status"
             . " FROM " . MAIN_DB_PREFIX . "apclogistics_cotation"
             . " WHERE fk_demandeprix = " . (int)$this->id
             . " ORDER BY date_cotation DESC";
        $res = $this->db->query($sql);
        if (!$res) return $rows;
        while ($o = $this->db->fetch_object($res)) $rows[] = $o;
        return $rows;
    }
}

class ApcDemandePrixLine extends ApcTableLineBase
{
    public $table_element    = 'apclogistics_demandeprix_lines';
    public $element          = 'dp_line';
    public $fk_parent_column = 'fk_demandeprix';

    /** Champs alignes sur le schema SQL 001_create_schema.sql (table llx_apclogistics_demandeprix_lines) */
    public $fields = array(
        'rowid'            => array('type'=>'integer', 'enabled'=>1, 'index'=>true, 'comment'=>'Id'),
        'entity'           => array('type'=>'integer', 'enabled'=>1, 'default'=>1),
        'fk_demandeprix'   => array('type'=>'integer', 'enabled'=>1, 'index'=>true),
        'no_ligne'         => array('type'=>'integer', 'enabled'=>1, 'default'=>0),
        'specification'    => array('type'=>'text', 'enabled'=>1),
        'unite'            => array('type'=>'varchar(32)'),
        'quantite'         => array('type'=>'decimal(16,4)', 'enabled'=>1, 'default'=>0),
        'fk_product'       => array('type'=>'integer', 'index'=>true),
        'extraparams'      => array('type'=>'text'),
        'date_creation'    => array('type'=>'datetime'),
        'tms'              => array('type'=>'timestamp'),
        'fk_user_creat'    => array('type'=>'integer'),
        'fk_user_modif'    => array('type'=>'integer'),
    );

    public $specification;
    public $unite;
    public $quantite;
    public $fk_product;

    public function __construct(DoliDB $db) { $this->db = $db; }
}
