<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * boncommande_list.php — Listing paginé des Bons de Commande
 * Filtres : statut, date début/fin, mot-clé dans ref/fournisseur/lieu
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/class/ApcBonCommande.class.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

if (empty($user->rights->apclogistics->boncommande->read)) accessforbidden();

$action     = GETPOST('action', 'aZ');
$massaction = GETPOST('massaction', 'alpha');
$toselect   = GETPOST('toselect', 'array');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'apclogisticsboncommandelist';

$search_ref    = trim(GETPOST('search_ref', 'alpha'));
$search_status = GETPOST('search_status', 'int');
$search_date_start = dol_mktime(0,0,0, GETPOST('date_startmonth', 'int'), GETPOST('date_startday', 'int'), GETPOST('date_startyear', 'int'));
$search_date_end   = dol_mktime(23,59,59, GETPOST('date_endmonth', 'int'), GETPOST('date_endday', 'int'), GETPOST('date_endyear', 'int'));

$limit  = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$page   = GETPOST('page', 'int');
$sortfield = GETPOST('sortfield', 'aZ');
$sortorder = GETPOST('sortorder', 'aZ');
if (empty($sortfield)) $sortfield = 'bc.date_creation';
if (empty($sortorder)) $sortorder = 'DESC';
if ($page < 0) $page = 0;
$offset = $page * $limit;

$title = $langs->trans('APCLogisticsMenu_boncommande');
llxHeader('', $title, '', '', 0, 0, array(), array('/custom/apclogistics/css/apclogistics.css'));
print load_fiche_titre($title, '', 'apclogistics@apclogistics', 0);

$sql = "SELECT bc.rowid, bc.ref, bc.date_cmde, bc.fournisseur_nom, bc.lieu_livraison, bc.date_livraison, bc.total_ht, bc.total_ttc, bc.status, bc.logisticien_nom, bc.coordinateur_nom, bc.fk_cotation";
$sql_count = "SELECT COUNT(bc.rowid)";
$sql_from = " FROM " . MAIN_DB_PREFIX . "apclogistics_boncommande bc";
$sql_where = " WHERE bc.entity IN (0," . getEntity('apclogistics_boncommande') . ")";

if ($search_ref) $sql_where .= " AND (bc.ref LIKE '%" . $db->escape($search_ref) . "%' OR bc.fournisseur_nom LIKE '%" . $db->escape($search_ref) . "%' OR bc.lieu_livraison LIKE '%" . $db->escape($search_ref) . "%')";
if ($search_status !== '' && $search_status != -1) $sql_where .= " AND bc.status = " . (int)$search_status;
if ($search_date_start > 0) $sql_where .= " AND bc.date_cmde >= '" . $db->idate($search_date_start) . "'";
if ($search_date_end > 0)   $sql_where .= " AND bc.date_cmde <= '" . $db->idate($search_date_end) . "'";

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
print '<td><input type="text" class="flat width100" name="search_ref" value="' . dol_escape_htmltag($search_ref) . '" placeholder="' . dol_escape_htmltag($langs->trans('FieldRef') . ' / ' . $langs->trans('BCFournisseur') . ' / ' . $langs->trans('BCLieuLiv')) . '"></td>';
$statusList = (new ApcBonCommande($db))->getStatusList();
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

$param = 'search_ref=' . urlencode($search_ref) . '&search_status=' . urlencode($search_status);
if ($search_date_start > 0) $param .= '&date_startmonth=' . date('m', $search_date_start) . '&date_startday=' . date('d', $search_date_start) . '&date_startyear=' . date('Y', $search_date_start);
if ($search_date_end > 0)   $param .= '&date_endmonth=' . date('m', $search_date_end) . '&date_endday=' . date('d', $search_date_end) . '&date_endyear=' . date('Y', $search_date_end);

print_barre_liste($langs->trans('BCTitle') . ' (' . $nbtotal . ')', $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotal, 'title_generic.png', 0, '', '', $limit);

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent liste">';
print '<tr class="liste_titre">';
print getTitleFieldOfList('', 0, $_SERVER["PHP_SELF"], '', '', '', $sortfield, $sortorder, 'center width30') . "\n";
print getTitleFieldOfList($langs->trans('FieldRef'), 0, $_SERVER["PHP_SELF"], 'bc.ref', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('BCDate'), 0, $_SERVER["PHP_SELF"], 'bc.date_cmde', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('BCFournisseur'), 0, $_SERVER["PHP_SELF"], 'bc.fournisseur_nom', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('BCLieuLiv'), 0, $_SERVER["PHP_SELF"], 'bc.lieu_livraison', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('BCDateLivPrev'), 0, $_SERVER["PHP_SELF"], 'bc.date_livraison', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('BCTotalHT'), 0, $_SERVER["PHP_SELF"], 'bc.total_ht', '', '', $sortfield, $sortorder, 'right ', 1) . "\n";
print getTitleFieldOfList($langs->trans('BCTotalTTC'), 0, $_SERVER["PHP_SELF"], 'bc.total_ttc', '', '', $sortfield, $sortorder, 'right ', 1) . "\n";
print getTitleFieldOfList($langs->trans('FieldStatus'), 0, $_SERVER["PHP_SELF"], 'bc.status', '', '', $sortfield, $sortorder, 'center ', 1) . "\n";
print '</tr>';

$bctmp = new ApcBonCommande($db);
if ($num) {
    $i = 0;
    while ($i < min($num, $limit)) {
        $obj = $db->fetch_object($resql);
        if (!$obj) break;
        $bctmp->id = $obj->rowid; $bctmp->ref = $obj->ref; $bctmp->status = $obj->status;
        print '<tr class="oddeven">';
        print '<td class="center"><input type="checkbox" class="flat checkforselect" name="toselect[]" value="' . $obj->rowid . '"></td>';
        print '<td>' . $bctmp->getNomUrl(1) . '</td>';
        print '<td>' . dol_print_date($db->jdate($obj->date_cmde), 'day') . '</td>';
        print '<td>' . dol_escape_htmltag($obj->fournisseur_nom) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->lieu_livraison) . '</td>';
        print '<td>' . ($obj->date_livraison ? dol_print_date($db->jdate($obj->date_livraison), 'day') : '<span class="opacitymedium">—</span>') . '</td>';
        print '<td class="apc-money right">' . price($obj->total_ht, 0, $langs, 0, 0, -1, $conf->currency) . '</td>';
        print '<td class="apc-money right">' . price($obj->total_ttc, 0, $langs, 0, 0, -1, $conf->currency) . '</td>';
        print '<td class="center">' . $bctmp->getStatusBadge() . '</td>';
        print '</tr>';
        $i++;
    }
} else {
    print '<tr class="oddeven"><td colspan="9" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
}
print '</table></div>';

if ($user->rights->apclogistics->boncommande->create) {
    print '<div class="tabsAction" style="margin-top:16px;">';
    print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/boncommande_card.php?action=create">' . $langs->trans('NewBonCommande') . '</a>';
    print '</div>';
}

llxFooter();
$db->close();
