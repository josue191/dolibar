<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * stock_list.php — Listing fiches stock APC
 * Filtres : ref produit, catégorie, stock < seuil, gestionnaire
 * Export CSV bouton
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
$res = 0; if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php"; if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php"; if (!$res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once __DIR__ . '/class/ApcStock.class.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');
$langs->load('products');

if (empty($user->rights->apclogistics->stock->read)) accessforbidden();

$action     = GETPOST('action', 'aZ');
$search_ref = trim(GETPOST('search_ref', 'alpha'));
$search_cat = trim(GETPOST('search_cat', 'alpha'));
$search_gest = (int)GETPOST('search_gest', 'int');
$search_low  = (int)GETPOST('search_low', 'int');

$limit  = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$page   = GETPOST('page', 'int');
$sortfield = GETPOST('sortfield', 'aZ');
$sortorder = GETPOST('sortorder', 'aZ');
if (empty($sortfield)) $sortfield = 'p.ref';
if (empty($sortorder)) $sortorder = 'ASC';
if ($page < 0) $page = 0;
$offset = $page * $limit;

$title = $langs->trans('APCLogisticsMenu_stock');
llxHeader('', $title, '', '', 0, 0, array(), array('/custom/apclogistics/css/apclogistics.css'));
print load_fiche_titre($title, '', 'apclogistics@apclogistics', 0);

$formother = new FormOther($db);
$form = new Form($db);

$sql = "SELECT s.rowid, s.fk_product, s.unite, s.stock_actuel, s.seuil_alerte, s.fk_user_gestionnaire, s.fk_user_resp_logistique, s.last_stock_update,"
     . " p.ref, p.label, p.description, p.tosell, p.tobuy, p.price";
$sql_count = "SELECT COUNT(s.rowid)";
$sql_from = " FROM " . MAIN_DB_PREFIX . "apclogistics_stock s"
    . " LEFT JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = s.fk_product";
$sql_where = " WHERE s.entity IN (0," . getEntity('apclogistics_stock') . ")";

if ($search_ref) $sql_where .= " AND (p.ref LIKE '%" . $db->escape($search_ref) . "%' OR p.label LIKE '%" . $db->escape($search_ref) . "%')";
if ($search_cat) $sql_where .= " AND p.description LIKE '%" . $db->escape($search_cat) . "%'";
if ($search_gest > 0) $sql_where .= " AND s.fk_user_gestionnaire = " . $search_gest;
if ($search_low) $sql_where .= " AND s.stock_actuel <= s.seuil_alerte";

$sql_order = " ORDER BY " . $sortfield . " " . $sortorder;

$resql = $db->query($sql . $sql_from . $sql_where . $sql_order . $db->plimit($limit + 1, $offset));
$rescount = $db->query($sql_count . $sql_from . $sql_where);
$nbtotal = $rescount ? (int)$db->fetch_array($rescount)[0] : 0;
$num = $resql ? $db->num_rows($resql) : 0;

$seuil = !empty($conf->global->APCLOGISTICS_STOCK_ALERT) ? (float)$conf->global->APCLOGISTICS_STOCK_ALERT : 1;

print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="sortfield" value="' . $sortfield . '">';
print '<input type="hidden" name="sortorder" value="' . $sortorder . '">';
print '<input type="hidden" name="page" value="' . $page . '">';
print '<div class="div-table-responsive-no-min liste-filter-apc">';
print '<table class="noborder centpercent liste">';
print '<tr class="liste_titre_filter">';
print '<td><input type="text" class="flat width100" name="search_ref" value="' . dol_escape_htmltag($search_ref) . '" placeholder="' . dol_escape_htmltag($langs->trans('Ref') . ' / ' . $langs->trans('Label')) . '"></td>';
print '<td><input type="text" class="flat width100" name="search_cat" value="' . dol_escape_htmltag($search_cat) . '" placeholder="' . dol_escape_htmltag($langs->trans('Description')) . '"></td>';
print '<td><select class="flat width100" name="search_low"><option value="0">' . $langs->trans('All') . '</option><option value="1"' . ($search_low ? ' selected' : '') . '>' . $langs->trans('StockOnlyLow') . '</option></select></td>';
print '<td>' . $form->select_dolusers($search_gest, 'search_gest', 1, '', 0, '', '', 0, 0, 0, '', 0, '', 'minwidth150') . '</td>';
print '<td><input type="submit" class="button small" name="button_search_x" value="' . dol_escape_htmltag($langs->trans('Search')) . '"> ';
print '<input type="submit" class="button small" name="button_removefilter_x" value="' . dol_escape_htmltag($langs->trans('Reset')) . '"></td>';
print '</tr></table></div>';
print '</form>';

$param = 'search_ref=' . urlencode($search_ref) . '&search_cat=' . urlencode($search_cat) . '&search_low=' . urlencode($search_low) . '&search_gest=' . urlencode($search_gest);
print_barre_liste($langs->trans('StockTitle') . ' (' . $nbtotal . ')', $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotal, 'stock', 0, '', '', $limit);

print '<div class="tabsAction" style="margin:8px 0;">';
print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/stock_exportcsv.php">' . $langs->trans('StockExportCSV') . '</a>';
print '</div>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent liste">';
print '<tr class="liste_titre">';
print getTitleFieldOfList($langs->trans('Ref'), 0, $_SERVER["PHP_SELF"], 'p.ref', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('Label'), 0, $_SERVER["PHP_SELF"], 'p.label', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('StockUnit'), 0, $_SERVER["PHP_SELF"], 's.unite', '', '', $sortfield, $sortorder, 'center ', 1) . "\n";
print getTitleFieldOfList($langs->trans('StockCurrent'), 0, $_SERVER["PHP_SELF"], 's.stock_actuel', '', '', $sortfield, $sortorder, 'right ', 1) . "\n";
print getTitleFieldOfList($langs->trans('StockAlert'), 0, $_SERVER["PHP_SELF"], 's.seuil_alerte', '', '', $sortfield, $sortorder, 'right ', 1) . "\n";
print getTitleFieldOfList($langs->trans('StockGest'), 0, $_SERVER["PHP_SELF"], 's.fk_user_gestionnaire', '', '', $sortfield, $sortorder, '', 1) . "\n";
print getTitleFieldOfList($langs->trans('StockLastUpd'), 0, $_SERVER["PHP_SELF"], 's.last_stock_update', '', '', $sortfield, $sortorder, 'center ', 1) . "\n";
print '</tr>';

$stktmp = new ApcStock($db);
$prod = new Product($db);
$u = new User($db);
if ($num) {
    $i = 0;
    while ($i < min($num, $limit)) {
        $obj = $db->fetch_object($resql);
        if (!$obj) break;
        $stktmp->id = $obj->rowid;
        $low = (float)$obj->stock_actuel <= ($obj->seuil_alerte > 0 ? (float)$obj->seuil_alerte : $seuil);
        print '<tr class="oddeven' . ($low ? ' apc-low-stock-row' : '') . '">';
        $prod->id = $obj->fk_product;
        $prod->ref = $obj->ref;
        $linkRef = '<a href="' . DOL_URL_ROOT . '/custom/apclogistics/stock_card.php?id=' . $obj->rowid . '">' . img_picto('', 'product') . ' ' . dol_escape_htmltag($obj->ref ?: '#'.$obj->fk_product) . '</a>';
        print '<td>' . $linkRef . '</td>';
        print '<td>' . dol_escape_htmltag($obj->label ?: '-') . '</td>';
        print '<td class="center">' . dol_escape_htmltag($obj->unite ?: '-') . '</td>';
        $cls = $low ? 'apc-neg' : '';
        print '<td class="right apc-money ' . $cls . '"><b>' . price((float)$obj->stock_actuel, 0, $langs, 0, 0, 0, '') . '</b></td>';
        print '<td class="right">' . price(($obj->seuil_alerte > 0 ? (float)$obj->seuil_alerte : $seuil), 0, $langs, 0, 0, 0, '') . '</td>';
        $gestNom = '-';
        if ($obj->fk_user_gestionnaire > 0 && $u->fetch($obj->fk_user_gestionnaire) > 0) $gestNom = $u->getFullName($langs);
        print '<td>' . dol_escape_htmltag($gestNom) . '</td>';
        print '<td class="center">' . ($obj->last_stock_update ? dol_print_date($db->jdate($obj->last_stock_update), 'dayhour') : '-') . '</td>';
        print '</tr>';
        $i++;
    }
} else {
    print '<tr class="oddeven"><td colspan="7" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();
