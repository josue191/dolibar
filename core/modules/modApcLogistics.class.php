<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 * Class modApcLogistics
 *
 * Description and activation class for module APC Logistics & Procurement
 * Custom module for Dolibarr ERP/CRM - Digitalisation logistique et achats APC ONG
 */
class modApcLogistics extends DolibarrModules
{
    /**
     * Constructor. Define names, constants, directories, boxes, permissions
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        // Id for module (must be unique).
        $this->numero = 500100;

        // Key text used to identify module (for permissions, menus, etc...)
        $this->rights_class = 'apclogistics';

        // Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic'
        // It's used to group modules in module setup page
        $this->family = 'products';

        // Module position in the family on 5 digits ('00001' to '99999')
        $this->module_position = '90';

        // Module label (no space allowed), used if translation string 'ModuleXXXName' not found
        $this->name = preg_replace('/^mod/i', '', get_class($this));

        // Module description (used if translation not found)
        $this->description = "Module natif Dolibarr de Digitalisation Logistique et Achats pour APC ONG. Dematerialisation du cycle documentaire : Etat de besoin, Requisition, Demande de prix, Cotation portail fournisseur, Bon de commande, Bon de reception, Stock, Demande/Justification d'Avance, Demande de paiement. Generation PDF conformes trame APC.";

        // Possible values for version are: 'development', 'experimental', 'dolibarr' or version
        $this->version = '1.0.0';

        // Key used in llx_const table to save module status enabled/disabled
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);

        // Name of image file used for this module (icon)
        $this->picto = 'apclogistics@apclogistics';

        // Define some features
        $this->module_parts = array(
            'triggers' => 0,
            'login' => 0,
            'substitutions' => 0,
            'menus' => 1,
            'tpl' => 0,
            'barcode' => 0,
            'models' => 0,
            'for' => 0,
            'theme' => 0,
            'invoicediscount' => 0,
            'dochandler' => 0,
        );

        // Data directories to create when module is enabled.
        $this->dirs = array('/apclogistics/temp', '/apclogistics/documents');

        // Config pages. Put here list of php page names stored in admin/ directory, to setup module.
        $this->config_page_url = array('apclogistics_setup.php@apclogistics');

        // Dependencies
        $this->hidden = false;
        $this->depends = array(
            'modProduct',
            'modStock',
            'modSociete',
            'modFournisseur',
        );
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array('apclogistics@apclogistics');
        $this->phpmin = array(8, 1);
        $this->need_dolibarr_version = array(17, 0);

        // Constants
        $this->const = array(
            1 => array(
                'APCLOGISTICS_TOKEN_DAYS',
                'chaine',
                '30',
                'Duree de validite (jours) du token portail fournisseur (min 7, max 60)',
                0,
                'current',
                1,
            ),
            2 => array(
                'APCLOGISTICS_NOTIF_EMAIL',
                'chaine',
                'logistique@apc-ong.org',
                'Email de notification logisticien pour nouvelles cotations',
                0,
                'current',
                1,
            ),
            3 => array(
                'APCLOGISTICS_DP_LEGAL_NOTICE',
                'chaine',
                "Cette demande de prix n'oblige en rien APC a contracter, a acheter ou a consommer votre service",
                'Mention legale de non-engagement affichee sur la Demande de prix',
                0,
                'current',
                1,
            ),
            4 => array(
                'APCLOGISTICS_PREFIX_EB',
                'chaine',
                'EB-',
                'Prefixe numerotation Etat de besoin',
                0,
                'current',
                1,
            ),
            5 => array(
                'APCLOGISTICS_PREFIX_REQ',
                'chaine',
                'REQ-',
                'Prefixe numerotation Requisition / Bon de sortie magasin',
                0,
                'current',
                1,
            ),
            6 => array(
                'APCLOGISTICS_PREFIX_DP',
                'chaine',
                'DP-',
                'Prefixe numerotation Demande de prix',
                0,
                'current',
                1,
            ),
            7 => array(
                'APCLOGISTICS_PREFIX_COT',
                'chaine',
                'COT-',
                'Prefixe numerotation Cotation fournisseur',
                0,
                'current',
                1,
            ),
            8 => array(
                'APCLOGISTICS_PREFIX_BC',
                'chaine',
                'BC-',
                'Prefixe numerotation Bon de commande',
                0,
                'current',
                1,
            ),
            9 => array(
                'APCLOGISTICS_PREFIX_BR',
                'chaine',
                'BR-',
                'Prefixe numerotation Bon de reception',
                0,
                'current',
                1,
            ),
            10 => array(
                'APCLOGISTICS_PREFIX_DAV',
                'chaine',
                'DAV-',
                'Prefixe numerotation Demande d\'avance',
                0,
                'current',
                1,
            ),
            11 => array(
                'APCLOGISTICS_PREFIX_JAV',
                'chaine',
                'JAV-',
                'Prefixe numerotation Justification d\'avance',
                0,
                'current',
                1,
            ),
            12 => array(
                'APCLOGISTICS_PREFIX_DPAI',
                'chaine',
                'DPAI-',
                'Prefixe numerotation Demande de paiement',
                0,
                'current',
                1,
            ),
            13 => array(
                'APCLOGISTICS_STOCK_ALERT_QTY',
                'chaine',
                '1',
                'Seuil d\'alerte stock critique (quantite minimale avant surlignage)',
                0,
                'current',
                1,
            ),
        );

        // Array to add new pages in new tabs
        $this->tabs = array();

        // Dictionaries
        $this->dictionaries = array();

        // Boxes / Widgets
        $this->boxes = array(
            0 => array(
                'file' => 'box_apclogistics_dashboard@apclogistics',
                'note' => 'Vue d\'ensemble APC Logistique',
                'enabledbydefaulton' => 'Home',
            ),
        );

        // Permissions provided by this module
        $this->rights = array();
        $r = 0;

        $perm_groups = array(
            'etatbesoin'     => 'Etats de besoin',
            'requisition'    => 'Requisitions / Sorties magasin',
            'demandeprix'    => 'Demandes de prix',
            'cotation'       => 'Cotations fournisseurs',
            'boncommande'    => 'Bons de commande',
            'bonreception'   => 'Bons de reception',
            'stock'          => 'Gestion des stocks',
            'demandeavance'  => 'Demandes d\'avance',
            'justifavance'   => 'Justifications d\'avance',
            'demandepaiement'=> 'Demandes de paiement',
        );

        foreach ($perm_groups as $perm_key => $perm_label) {
            $r++;
            $this->rights[$r][0] = 500100 + $r;
            $this->rights[$r][1] = 'Lire les ' . $perm_label;
            $this->rights[$r][2] = 'r';
            $this->rights[$r][3] = 0;
            $this->rights[$r][4] = $perm_key;
            $this->rights[$r][5] = 'read';

            $r++;
            $this->rights[$r][0] = 500100 + $r;
            $this->rights[$r][1] = 'Creer des ' . $perm_label;
            $this->rights[$r][2] = 'c';
            $this->rights[$r][3] = 0;
            $this->rights[$r][4] = $perm_key;
            $this->rights[$r][5] = 'create';

            $r++;
            $this->rights[$r][0] = 500100 + $r;
            $this->rights[$r][1] = 'Modifier les ' . $perm_label;
            $this->rights[$r][2] = 'm';
            $this->rights[$r][3] = 0;
            $this->rights[$r][4] = $perm_key;
            $this->rights[$r][5] = 'edit';

            $r++;
            $this->rights[$r][0] = 500100 + $r;
            $this->rights[$r][1] = 'Supprimer les ' . $perm_label;
            $this->rights[$r][2] = 'd';
            $this->rights[$r][3] = 0;
            $this->rights[$r][4] = $perm_key;
            $this->rights[$r][5] = 'delete';

            $r++;
            $this->rights[$r][0] = 500100 + $r;
            $this->rights[$r][1] = 'Valider / Approuver les ' . $perm_label;
            $this->rights[$r][2] = 'a';
            $this->rights[$r][3] = 0;
            $this->rights[$r][4] = $perm_key;
            $this->rights[$r][5] = 'validate';
        }

        // Main menu entries
        $this->menu = array();

        // Menu left entry (new top menu)
        $this->menu[] = array(
            'fk_menu' => '0',
            'type' => 'top',
            'titre' => 'APCLogisticsMenuTop',
            'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth valignmiddle"'),
            'mainmenu' => 'apclogistics',
            'url' => '/apclogistics/dashboard.php',
            'langs' => 'apclogistics@apclogistics',
            'position' => 100,
            'perms' => '$user->rights->apclogistics->etatbesoin->read || $user->rights->apclogistics->stock->read || $user->rights->apclogistics->boncommande->read',
            'target' => '',
            'user' => 0,
        );

        // Sub-menus under APC Logistique (left menu entries, each pointing to list page)
        $submenus = array(
            'dashboard'       => array('titre'=>'Tableau de bord APC', 'url'=>'/apclogistics/dashboard.php', 'perm'=>'etatbesoin->read'),
            'etatbesoin'      => array('titre'=>'Etats de besoin', 'url'=>'/apclogistics/etatbesoin_list.php', 'perm'=>'etatbesoin->read'),
            'requisition'     => array('titre'=>'Requisitions / Sorties', 'url'=>'/apclogistics/requisition_list.php', 'perm'=>'requisition->read'),
            'demandeprix'     => array('titre'=>'Demandes de prix', 'url'=>'/apclogistics/demandeprix_list.php', 'perm'=>'demandeprix->read'),
            'cotation'        => array('titre'=>'Cotations fournisseurs', 'url'=>'/apclogistics/cotation_list.php', 'perm'=>'cotation->read'),
            'boncommande'     => array('titre'=>'Bons de commande', 'url'=>'/apclogistics/boncommande_list.php', 'perm'=>'boncommande->read'),
            'bonreception'    => array('titre'=>'Bons de reception', 'url'=>'/apclogistics/bonreception_list.php', 'perm'=>'bonreception->read'),
            'stock'           => array('titre'=>'Stocks / Fiches article', 'url'=>'/apclogistics/stock_list.php', 'perm'=>'stock->read'),
            'demandeavance'   => array('titre'=>'Demandes d\'avance', 'url'=>'/apclogistics/demandeavance_list.php', 'perm'=>'demandeavance->read'),
            'justifavance'    => array('titre'=>'Justifications d\'avance', 'url'=>'/apclogistics/justifavance_list.php', 'perm'=>'justifavance->read'),
            'demandepaiement' => array('titre'=>'Demandes de paiement', 'url'=>'/apclogistics/demandepaiement_list.php', 'perm'=>'demandepaiement->read'),
        );

        $sub_position = 10;
        foreach ($submenus as $skey => $sval) {
            $this->menu[] = array(
                'fk_menu' => 'fk_mainmenu=apclogistics',
                'type' => 'left',
                'titre' => 'APCLogisticsMenu_' . $skey,
                'mainmenu' => 'apclogistics',
                'leftmenu' => 'apclogistics_' . $skey,
                'url' => $sval['url'],
                'langs' => 'apclogistics@apclogistics',
                'position' => $sub_position,
                'perms' => '$user->rights->apclogistics->' . $sval['perm'],
                'target' => '',
                'user' => 0,
            );
            $sub_position += 10;
        }

        // Exports profiles provided by this module
        $this->export_code_temp = array();

        // Imports profiles
        $this->import_code_temp = array();
    }

    /**
     * Function called when module is enabled.
     * The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
     * It also creates data directories AND runs SQL CREATE TABLE from scripts/001_create_schema.sql.
     *
     * @param string $options Options when enabling module ('', 'noboxes')
     * @return int                 1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        $sql = array();

        $sqlFile = __DIR__ . '/../../../scripts/001_create_schema.sql';
        if (@file_exists($sqlFile)) {
            $raw = @file_get_contents($sqlFile);
            if ($raw !== false) {
                $raw = preg_replace('/--[^\n]*\n/', "\n", $raw);
                $raw = preg_replace('/\/\*[\s\S]*?\*\//', '', $raw);
                $stms = array_filter(array_map('trim', explode(';', $raw)));
                foreach ($stms as $s) {
                    if (preg_match('/^(CREATE|ALTER|INSERT|DROP)\b/i', $s)) {
                        $sql[] = $s . ';';
                    }
                }
            }
        }

        return $this->_init($sql, $options);
    }

    /**
     * Function called when module is disabled.
     * Remove from database constants, boxes and permissions from Dolibarr database.
     * Data directories are not deleted.
     * If the disable option requests full data removal, this method can issue
     * DROP TABLE statements for all llx_apclogistics_* tables.
     *
     * @param string $options Options when enabling module ('', 'noboxes', 'deleteTables')
     * @return int                 1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        $sql = array();

        if (strpos($options, 'deleteTables') !== false) {
            $tables = array(
                'llx_apclogistics_demandepaiement_lines',
                'llx_apclogistics_demandepaiement',
                'llx_apclogistics_justifavance_lines',
                'llx_apclogistics_justifavance',
                'llx_apclogistics_demandeavance_lines',
                'llx_apclogistics_demandeavance',
                'llx_apclogistics_stock_movements',
                'llx_apclogistics_stock',
                'llx_apclogistics_bonreception_lines',
                'llx_apclogistics_bonreception',
                'llx_apclogistics_boncommande_lines',
                'llx_apclogistics_boncommande',
                'llx_apclogistics_cotation_lines',
                'llx_apclogistics_cotation',
                'llx_apclogistics_tokens',
                'llx_apclogistics_demandeprix_lines',
                'llx_apclogistics_demandeprix',
                'llx_apclogistics_requisition_lines',
                'llx_apclogistics_requisition',
                'llx_apclogistics_etatbesoin_lines',
                'llx_apclogistics_etatbesoin',
                'llx_apclogistics_auditlog',
                'llx_apclogistics_numbering',
            );
            foreach ($tables as $t) {
                $sql[] = "DROP TABLE IF EXISTS " . $t . ";";
            }
        }

        return $this->_remove($sql, $options);
    }
}
