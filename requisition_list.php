<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * requisition_list.php — Listing paginé des Réquisitions / Bons de sortie magasin
 * Filtres : statut, date début/fin, mot-clé dans ref/objet/demandeur
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/class/ApcRequisition.class.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

if (empty($user->rights->apclogistics->requisition->read)) accessforbidden();

$action     = GETPOST('action', 'aZ');
$massaction = GETPOST('massaction', 'alpha');
$toselect   = GETPOST('toselect', 'array');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'apclogisticsrequisitionlist';

$search_ref    = trim(GETPOST('search_ref', 'alpha'));
$search_status = GETPOST('search_status', 'int');
$search_date_start = dol_mktime(0,0,0, GETPOST('date_startmonth', 'int'), GETPOST('date_startday', 'int'), GETPOST('date_startyear', 'int'));
$search_date_end   = dol_mktime(23,59,59, GETPOST('date_endmonth', 'int'), GETPOST('date_endday', 'int'), GETPOST('date_endyear', 'int'));

$limit  = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$page   = GETPOST('page', 'int');
$sortfield = GETPOST('sortfield', 'aZ');
$sortorder = GETPOST('sortorder', 'aZ');
if (empty($sortfield)) $sortfield = 'rq.date_creation';
if (empty($sortorder)) $sortorder = 'DESC';
if ($page < 0) $page = 0;
$offset = $page * $limit;

$title = $langs->trans('APCLogisticsMenu_requisition');
llxHeader('', $title, '', '', 0, 0, array(), array('/custom/apclogistics/css/apclogistics.css'));
print load_fiche_titre($title, '', 'apclogistics@apclogistics', 0);

$sql = "SELECT rq.rowid, rq.ref, rq.date_demande, rq.date_sortie, rq.objet, rq.status,"
     . " rq.demandeur_nom, rq.magasinier_nom, rq.fk_etatbesoin";
$sql_count = "SELECT COUNT(rq.rowid)";
$sql_from = " FROM " . MAIN_DB_PREFIX . "apclogistics_requisition rq";
$sql_where = " WHERE rq.entity IN (0," . getEntity('apclogistics_requisition') . ")";

if ($search_ref) $sql_where .= " AND (rq.ref LIKE '%" . $db->escape($search_ref) . "%' OR rq.objet LIKE '%" . $db->escape($search_ref) . "%' OR rq.demandeur_nom LIKE '%" . $db->escape($search_ref) . "%')";
if ($search_status !== '' && $search_status != -1) $sql_where .= " AND rq.status = " . (int)$search_status;
if ($search_date_start > 0) $sql_where .= " AND rq.date_demande >= '" . $db->idate($search_date_start) . "'";
if ($search_date_end > 0)   $sql_where .= " AND rq.date_demande <= '" . $db->idate($search_date_end) . "'";

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
print '<td><input type="text" class="flat width100" name="search_ref" value="' . dol_escape_htmltag($search_ref) . '" placeholder="' . dol_escape_htmltag($langs->trans('FieldRef') . ' / Objet / Demandeur') . '"></td>';
$statusList = (new ApcRequisition($db))->getStatusList();
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

print_barre_liste($langs->trans('REQTitle') . ' (' . $nbtotal . ')', $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotal, 'title_generic.png', 0, '', '', $limit);

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent liste">';
print '<tr class="liste_titre">';
print getTitleFieldOfList('', 0, $_SERVER["PHP_SELF"], '', '', '', $sortfield, $sortorder, 'center width30') . "\n";
print getTitleFieldOfList($langs->trans('FieldRef'), 0, $_SERVER["PHP_SELF"], 'rq.ref', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('REQDateDemande'), 0, $_SERVER["PHP_SELF"], 'rq.date_demande', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('REQDateSortie'), 0, $_SERVER["PHP_SELF"], 'rq.date_sortie', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('FieldObjet'), 0, $_SERVER["PHP_SELF"], 'rq.objet', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('REQDemandeur'), 0, $_SERVER["PHP_SELF"], 'rq.demandeur_nom', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('REQMagasinier'), 0, $_SERVER["PHP_SELF"], 'rq.magasinier_nom', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('FieldStatus'), 0, $_SERVER["PHP_SELF"], 'rq.status', '', '', $sortfield, $sortorder, 'center ', 1) . "\n";
print '</tr>';

$rqtmp = new ApcRequisition($db);
if ($num) {
    $i = 0;
    while ($i < min($num, $limit)) {
        $obj = $db->fetch_object($resql);
        if (!$obj) break;
        $rqtmp->id = $obj->rowid; $rqtmp->ref = $obj->ref; $rqtmp->status = $obj->status;
        print '<tr class="oddeven">';
        print '<td class="center"><input type="checkbox" class="flat checkforselect" name="toselect[]" value="' . $obj->rowid . '"></td>';
        print '<td>' . $rqtmp->getNomUrl(1) . '</td>';
        print '<td>' . dol_print_date($db->jdate($obj->date_demande), 'day') . '</td>';
        print '<td>' . ($obj->date_sortie ? dol_print_date($db->jdate($obj->date_sortie), 'day') : '<span class="opacitymedium">—</span>') . '</td>';
        print '<td>' . dol_escape_htmltag($obj->objet) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->demandeur_nom) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->magasinier_nom) . '</td>';
        print '<td class="center">' . $rqtmp->getStatusBadge() . '</td>';
        print '</tr>';
        $i++;
    }
} else {
    print '<tr class="oddeven"><td colspan="8" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
}
print '</table></div>';

if ($user->rights->apclogistics->requisition->create) {
    print '<div class="tabsAction" style="margin-top:16px;">';
    print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/requisition_card.php?action=create">' . $langs->trans('NewRequisition') . '</a>';
    print '</div>';
}

llxFooter();
$db->close();
