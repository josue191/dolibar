<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * demandeprix_list.php — Listing paginé des Demandes de Prix (DP)
 * Filtres : statut, date début/fin, mot-clé dans ref/fournisseur/lieu livraison
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
$res = 0; if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php"; if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php"; if (!$res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/class/ApcDemandePrix.class.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

if (empty($user->rights->apclogistics->demandeprix->read)) accessforbidden();

$action     = GETPOST('action', 'aZ');
$massaction = GETPOST('massaction', 'alpha');
$toselect   = GETPOST('toselect', 'array');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'apclogisticsdemandeprixlist';

$search_ref    = trim(GETPOST('search_ref', 'alpha'));
$search_status = GETPOST('search_status', 'int');
$search_date_start = dol_mktime(0,0,0, GETPOST('date_startmonth', 'int'), GETPOST('date_startday', 'int'), GETPOST('date_startyear', 'int'));
$search_date_end   = dol_mktime(23,59,59, GETPOST('date_endmonth', 'int'), GETPOST('date_endday', 'int'), GETPOST('date_endyear', 'int'));

$limit  = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$page   = GETPOST('page', 'int');
$sortfield = GETPOST('sortfield', 'aZ');
$sortorder = GETPOST('sortorder', 'aZ');
if (empty($sortfield)) $sortfield = 'dp.date_creation';
if (empty($sortorder)) $sortorder = 'DESC';
if ($page < 0) $page = 0;
$offset = $page * $limit;

$title = $langs->trans('APCLogisticsMenu_demandeprix');
llxHeader('', $title, '', '', 0, 0, array(), array('/custom/apclogistics/css/apclogistics.css'));
print load_fiche_titre($title, '', 'apclogistics@apclogistics', 0);

$sql = "SELECT dp.rowid, dp.ref, dp.date_dp, dp.fournisseur_nom, dp.fournisseur_tel, dp.lieu_livraison, dp.date_livraison, dp.status, dp.date_envoi, dp.fk_user_envoi";
$sql_count = "SELECT COUNT(dp.rowid)";
$sql_from = " FROM " . MAIN_DB_PREFIX . "apclogistics_demandeprix dp";
$sql_where = " WHERE dp.entity IN (0," . getEntity('apclogistics_demandeprix') . ")";

if ($search_ref) $sql_where .= " AND (dp.ref LIKE '%" . $db->escape($search_ref) . "%' OR dp.fournisseur_nom LIKE '%" . $db->escape($search_ref) . "%' OR dp.lieu_livraison LIKE '%" . $db->escape($search_ref) . "%')";
if ($search_status !== '' && $search_status != -1) $sql_where .= " AND dp.status = " . (int)$search_status;
if ($search_date_start > 0) $sql_where .= " AND dp.date_dp >= '" . $db->idate($search_date_start) . "'";
if ($search_date_end > 0)   $sql_where .= " AND dp.date_dp <= '" . $db->idate($search_date_end) . "'";

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
print '<td><input type="text" class="flat width100" name="search_ref" value="' . dol_escape_htmltag($search_ref) . '" placeholder="' . dol_escape_htmltag($langs->trans('DPRef') . ' / ' . $langs->trans('DPFournisseur') . ' / ' . $langs->trans('DPLieuLiv')) . '"></td>';
$statusList = (new ApcDemandePrix($db))->getStatusList();
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

$param = '';
if (!empty($search_ref)) $param .= '&search_ref=' . urlencode($search_ref);
if ($search_status !== '' && $search_status != -1) $param .= '&search_status=' . (int)$search_status;
if ($search_date_start > 0) { $param .= '&date_startmonth=' . GETPOST('date_startmonth', 'int') . '&date_startday=' . GETPOST('date_startday', 'int') . '&date_startyear=' . GETPOST('date_startyear', 'int'); }
if ($search_date_end > 0)   { $param .= '&date_endmonth=' . GETPOST('date_endmonth', 'int') . '&date_endday=' . GETPOST('date_endday', 'int') . '&date_endyear=' . GETPOST('date_endyear', 'int'); }

print_barre_liste($langs->trans('DPTitle') . ' (' . $nbtotal . ')', $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotal, 'title_generic.png', 0, '', '', $limit);

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent liste">';
print '<tr class="liste_titre">';
print getTitleFieldOfList('', 0, $_SERVER["PHP_SELF"], '', '', '', $sortfield, $sortorder, 'center width30') . "\n";
print getTitleFieldOfList($langs->trans('DPRef'), 0, $_SERVER["PHP_SELF"], 'dp.ref', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('DPDate'), 0, $_SERVER["PHP_SELF"], 'dp.date_dp', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('DPFournisseur'), 0, $_SERVER["PHP_SELF"], 'dp.fournisseur_nom', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('DPTel'), 0, $_SERVER["PHP_SELF"], 'dp.fournisseur_tel', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('DPLieuLiv'), 0, $_SERVER["PHP_SELF"], 'dp.lieu_livraison', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('DPDateLiv'), 0, $_SERVER["PHP_SELF"], 'dp.date_livraison', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('FieldStatus'), 0, $_SERVER["PHP_SELF"], 'dp.status', '', '', $sortfield, $sortorder, 'center ', 1) . "\n";
print '</tr>';

$dptmp = new ApcDemandePrix($db);
if ($num) {
    $i = 0;
    while ($i < min($num, $limit)) {
        $obj = $db->fetch_object($resql);
        if (!$obj) break;
        $dptmp->id = $obj->rowid; $dptmp->ref = $obj->ref; $dptmp->status = $obj->status;
        print '<tr class="oddeven">';
        print '<td class="center"><input type="checkbox" class="flat checkforselect" name="toselect[]" value="' . $obj->rowid . '"></td>';
        print '<td>' . $dptmp->getNomUrl(1) . '</td>';
        print '<td>' . dol_print_date($db->jdate($obj->date_dp), 'day') . '</td>';
        print '<td>' . dol_escape_htmltag($obj->fournisseur_nom) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->fournisseur_tel) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->lieu_livraison) . '</td>';
        print '<td>' . ($obj->date_livraison ? dol_print_date($db->jdate($obj->date_livraison), 'day') : '<span class="opacitymedium">—</span>') . '</td>';
        print '<td class="center">' . $dptmp->getStatusBadge() . '</td>';
        print '</tr>';
        $i++;
    }
} else {
    print '<tr class="oddeven"><td colspan="8" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
}
print '</table></div>';

if ($user->rights->apclogistics->demandeprix->create) {
    print '<div class="tabsAction" style="margin-top:16px;">';
    print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/demandeprix_card.php?action=create">' . $langs->trans('NewDemandePrix') . '</a>';
    print '</div>';
}

llxFooter();
$db->close();
