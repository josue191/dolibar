<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * bonreception_list.php — Listing paginé des Bons de Réception
 * Filtres : référence BC, date réception, statut, mot-clé ref/fournisseur/BL
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
$res = 0; if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php"; if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php"; if (!$res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/class/ApcBonReception.class.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

if (empty($user->rights->apclogistics->bonreception->read)) accessforbidden();

$action     = GETPOST('action', 'aZ');
$massaction = GETPOST('massaction', 'alpha');
$toselect   = GETPOST('toselect', 'array');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'apclogisticsbonreceptionlist';

$search_ref       = trim(GETPOST('search_ref', 'alpha'));
$search_bc_ref    = trim(GETPOST('search_bc_ref', 'alpha'));
$search_status    = GETPOST('search_status', 'int');
$search_date_start = dol_mktime(0,0,0, GETPOST('date_startmonth', 'int'), GETPOST('date_startday', 'int'), GETPOST('date_startyear', 'int'));
$search_date_end   = dol_mktime(23,59,59, GETPOST('date_endmonth', 'int'), GETPOST('date_endday', 'int'), GETPOST('date_endyear', 'int'));

$limit  = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$page   = GETPOST('page', 'int');
$sortfield = GETPOST('sortfield', 'aZ');
$sortorder = GETPOST('sortorder', 'aZ');
if (empty($sortfield)) $sortfield = 'br.date_creation';
if (empty($sortorder)) $sortorder = 'DESC';
if ($page < 0) $page = 0;
$offset = $page * $limit;

$title = $langs->trans('APCLogisticsMenu_br');
llxHeader('', $title, '', '', 0, 0, array(), array('/custom/apclogistics/css/apclogistics.css'));
print load_fiche_titre($title, '', 'apclogistics@apclogistics', 0);

$sql = "SELECT br.rowid, br.ref, br.date_reception, br.fournisseur_nom, br.ref_bdl, br.fk_boncommande, br.total_qte_commandee, br.total_qte_recue, br.total_ecart, br.status, bc.ref AS bc_ref";
$sql_count = "SELECT COUNT(br.rowid)";
$sql_from = " FROM " . MAIN_DB_PREFIX . "apclogistics_bonreception br"
    . " LEFT JOIN " . MAIN_DB_PREFIX . "apclogistics_boncommande bc ON bc.rowid = br.fk_boncommande";
$sql_where = " WHERE br.entity IN (0," . getEntity('apclogistics_bonreception') . ")";

if ($search_ref) $sql_where .= " AND (br.ref LIKE '%" . $db->escape($search_ref) . "%' OR br.fournisseur_nom LIKE '%" . $db->escape($search_ref) . "%' OR br.ref_bdl LIKE '%" . $db->escape($search_ref) . "%')";
if ($search_bc_ref) $sql_where .= " AND bc.ref LIKE '%" . $db->escape($search_bc_ref) . "%'";
if ($search_status !== '' && $search_status != -1) $sql_where .= " AND br.status = " . (int)$search_status;
if ($search_date_start > 0) $sql_where .= " AND br.date_reception >= '" . $db->idate($search_date_start) . "'";
if ($search_date_end > 0)   $sql_where .= " AND br.date_reception <= '" . $db->idate($search_date_end) . "'";

$sql_order = " ORDER BY " . $sortfield . " " . $sortorder;

$resql = $db->query($sql . $sql_from . $sql_where . $sql_order . $db->plimit($limit + 1, $offset));
$rescount = $db->query($sql_count . $sql_from . $sql_where);
$nbtotal = $rescount ? (int)$db->fetch_array($rescount)[0] : 0;
$num = $resql ? $db->num_rows($resql) : 0;

$formother = new FormOther($db);

print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="sortfield" value="' . $sortfield . '">';
print '<input type="hidden" name="sortorder" value="' . $sortorder . '">';
print '<input type="hidden" name="page" value="' . $page . '">';

print '<div class="div-table-responsive-no-min liste-filter-apc">';
print '<table class="noborder centpercent liste">';
print '<tr class="liste_titre_filter">';
print '<td><input type="text" class="flat width100" name="search_ref" value="' . dol_escape_htmltag($search_ref) . '" placeholder="' . dol_escape_htmltag($langs->trans('FieldRef') . ' / ' . $langs->trans('BRFournisseur') . ' / ' . $langs->trans('BRRefBL')) . '"></td>';
print '<td><input type="text" class="flat width100" name="search_bc_ref" value="' . dol_escape_htmltag($search_bc_ref) . '" placeholder="' . dol_escape_htmltag($langs->trans('BRFKBC')) . '"></td>';
$statusList = (new ApcBonReception($db))->getStatusList();
$select = '<select class="flat width100" name="search_status"><option value="-1">' . $langs->trans('All') . '</option>';
foreach ($statusList as $code => $lib) { $sel = ($search_status !== '' && (int)$search_status === (int)$code) ? ' selected' : ''; $select .= '<option value="' . $code . '"' . $sel . '>' . $lib . '</option>'; }
$select .= '</select>';
print '<td>' . $select . '</td>';
print '<td>' . $formother->select_date($search_date_start ? $search_date_start : -1, 'date_start', 0, 0, 1, '', 1, 0) . '</td>';
print '<td>' . $formother->select_date($search_date_end ? $search_date_end : -1, 'date_end', 0, 0, 1, '', 1, 0) . '</td>';
print '<td><input type="submit" class="button small" name="button_search_x" value="' . dol_escape_htmltag($langs->trans('Search')) . '"> ';
print '<input type="submit" class="button small" name="button_removefilter_x" value="' . dol_escape_htmltag($langs->trans('Reset')) . '"></td>';
print '</tr></table></div>';
print '</form>';

$param = 'search_ref=' . urlencode($search_ref) . '&search_bc_ref=' . urlencode($search_bc_ref) . '&search_status=' . urlencode($search_status);
if ($search_date_start > 0) $param .= '&date_startmonth=' . date('m', $search_date_start) . '&date_startday=' . date('d', $search_date_start) . '&date_startyear=' . date('Y', $search_date_start);
if ($search_date_end > 0)   $param .= '&date_endmonth=' . date('m', $search_date_end) . '&date_endday=' . date('d', $search_date_end) . '&date_endyear=' . date('Y', $search_date_end);

print_barre_liste($langs->trans('BRTitle') . ' (' . $nbtotal . ')', $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotal, 'title_generic.png', 0, '', '', $limit);

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent liste">';
print '<tr class="liste_titre">';
print getTitleFieldOfList('', 0, $_SERVER["PHP_SELF"], '', '', '', $sortfield, $sortorder, 'center width30') . "\n";
print getTitleFieldOfList($langs->trans('FieldRef'), 0, $_SERVER["PHP_SELF"], 'br.ref', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('BRFKBC'), 0, $_SERVER["PHP_SELF"], 'bc.ref', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('BRDateRec'), 0, $_SERVER["PHP_SELF"], 'br.date_reception', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('BRFournisseur'), 0, $_SERVER["PHP_SELF"], 'br.fournisseur_nom', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('BRRefBL'), 0, $_SERVER["PHP_SELF"], 'br.ref_bdl', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('BRTQC'), 0, $_SERVER["PHP_SELF"], 'br.total_qte_commandee', '', '', $sortfield, $sortorder, 'center ', 1) . "\n";
print getTitleFieldOfList($langs->trans('BRTQR'), 0, $_SERVER["PHP_SELF"], 'br.total_qte_recue', '', '', $sortfield, $sortorder, 'center ', 1) . "\n";
print getTitleFieldOfList($langs->trans('BRTEcart'), 0, $_SERVER["PHP_SELF"], 'br.total_ecart', '', '', $sortfield, $sortorder, 'center ', 1) . "\n";
print getTitleFieldOfList($langs->trans('FieldStatus'), 0, $_SERVER["PHP_SELF"], 'br.status', '', '', $sortfield, $sortorder, 'center ', 1) . "\n";
print '</tr>';

$brtmp = new ApcBonReception($db);
$bctmp = new ApcBonCommande($db);
if ($num) {
    $i = 0;
    while ($i < min($num, $limit)) {
        $obj = $db->fetch_object($resql);
        if (!$obj) break;
        $brtmp->id = $obj->rowid; $brtmp->ref = $obj->ref; $brtmp->status = $obj->status;
        $ecartCls = '';
        if ((float)$obj->total_ecart < 0) $ecartCls = 'apc-neg';
        if ((float)$obj->total_ecart > 0) $ecartCls = 'apc-pos';
        print '<tr class="oddeven">';
        print '<td class="center"><input type="checkbox" class="flat checkforselect" name="toselect[]" value="' . $obj->rowid . '"></td>';
        print '<td>' . $brtmp->getNomUrl(1) . '</td>';
        if (!empty($obj->fk_boncommande)) {
            $bctmp->id = $obj->fk_boncommande; $bctmp->ref = $obj->bc_ref;
            print '<td>' . $bctmp->getNomUrl(1) . '</td>';
        } else {
            print '<td><span class="opacitymedium">—</span></td>';
        }
        print '<td>' . dol_print_date($db->jdate($obj->date_reception), 'day') . '</td>';
        print '<td>' . dol_escape_htmltag($obj->fournisseur_nom) . '</td>';
        print '<td>' . ($obj->ref_bdl ? dol_escape_htmltag($obj->ref_bdl) : '<span class="opacitymedium">—</span>') . '</td>';
        print '<td class="center">' . price((float)$obj->total_qte_commandee, 0, $langs, 0, 0, 0, '') . '</td>';
        print '<td class="center">' . price((float)$obj->total_qte_recue, 0, $langs, 0, 0, 0, '') . '</td>';
        print '<td class="center ' . $ecartCls . '">' . price((float)$obj->total_ecart, 0, $langs, 0, 0, 0, '') . '</td>';
        print '<td class="center">' . $brtmp->getStatusBadge() . '</td>';
        print '</tr>';
        $i++;
    }
} else {
    print '<tr class="oddeven"><td colspan="10" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
}
print '</table></div>';

if ($user->rights->apclogistics->bonreception->create) {
    print '<div class="tabsAction" style="margin-top:16px;">';
    print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/bonreception_card.php?action=create">' . $langs->trans('NewBR') . '</a>';
    print '</div>';
}

llxFooter();
$db->close();
