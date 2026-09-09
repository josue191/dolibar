<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * stock_exportcsv.php — Export CSV du stock actuel + mouvements sur periode
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
$res = 0; if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php"; if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php"; if (!$res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once __DIR__ . '/class/ApcStock.class.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

if (empty($user->rights->apclogistics->stock->read)) accessforbidden();

$type = GETPOST('type', 'aZ') ?: 'stock';
$date_start = dol_mktime(0,0,0, GETPOST('date_startmonth', 'int'), GETPOST('date_startday', 'int'), GETPOST('date_startyear', 'int'));
$date_end   = dol_mktime(23,59,59, GETPOST('date_endmonth', 'int'), GETPOST('date_endday', 'int'), GETPOST('date_endyear', 'int'));

if ($type === 'stock') {
    $sql = "SELECT s.rowid, s.fk_product, s.unite, s.stock_actuel, s.seuil_alerte, s.last_stock_update, p.ref, p.label"
         . " FROM " . MAIN_DB_PREFIX . "apclogistics_stock s"
         . " LEFT JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = s.fk_product"
         . " WHERE s.entity IN (0," . getEntity('apclogistics_stock') . ")"
         . " ORDER BY p.ref ASC";
    $resql = $db->query($sql);
    if (!$resql) { dol_print_error('', $db->lasterror()); exit; }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="apc_stock_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array(
        $langs->trans('Ref'), $langs->trans('Label'), $langs->trans('StockUnit'),
        $langs->trans('StockCurrent'), $langs->trans('StockAlert'), $langs->trans('StockLastUpd')
    ), ';');
    while ($obj = $db->fetch_object($resql)) {
        fputcsv($out, array(
            $obj->ref ?: '#'.$obj->fk_product,
            $obj->label,
            $obj->unite,
            (float)$obj->stock_actuel,
            (float)$obj->seuil_alerte,
            $obj->last_stock_update ? dol_print_date($db->jdate($obj->last_stock_update), 'dayhourrfc') : '',
        ), ';');
    }
    fclose($out);
    exit;
}

if ($type === 'mouvements') {
    $where = '';
    if ($date_start > 0) $where .= " AND m.date_movement >= '" . $db->idate($date_start) . "'";
    if ($date_end > 0)   $where .= " AND m.date_movement <= '" . $db->idate($date_end) . "'";
    $sql = "SELECT m.date_movement, m.ref_doc_type, m.ref_doc_id, m.unite, m.entree, m.sortie, m.stock_apres,"
         . " p.ref AS prod_ref, p.label AS prod_label"
         . " FROM " . MAIN_DB_PREFIX . "apclogistics_stock_movements m"
         . " LEFT JOIN " . MAIN_DB_PREFIX . "apclogistics_stock s ON s.rowid = m.fk_stock"
         . " LEFT JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = s.fk_product"
         . " WHERE m.entity IN (0," . getEntity('apclogistics_stock_movements') . ")"
         . $where
         . " ORDER BY m.date_movement ASC, m.rowid ASC";
    $resql = $db->query($sql);
    if (!$resql) { dol_print_error('', $db->lasterror()); exit; }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="apc_stock_mouvements_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array(
        'Date', 'Type doc', 'Ref doc', 'Produit', 'Unite', 'Entree', 'Sortie', 'Stock apres'
    ), ';');
    while ($obj = $db->fetch_object($resql)) {
        fputcsv($out, array(
            $obj->date_movement ? dol_print_date($db->jdate($obj->date_movement), 'dayhourrfc') : '',
            $obj->ref_doc_type,
            (int)$obj->ref_doc_id,
            ($obj->prod_ref ?: '#').' '.($obj->prod_label ?: ''),
            $obj->unite,
            (float)$obj->entree,
            (float)$obj->sortie,
            (float)$obj->stock_apres,
        ), ';');
    }
    fclose($out);
    exit;
}

llxHeader('', $langs->trans('StockExportCSV'));
print load_fiche_titre($langs->trans('StockExportCSV'));
print '<h3>' . $langs->trans('ExportCSV') . '</h3>';
print '<div class="tabsAction"><a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/stock_exportcsv.php?type=stock">' . $langs->trans('StockCurrent') . ' (CSV)</a>';
print ' <a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/stock_exportcsv.php?type=mouvements">' . $langs->trans('StockMovs') . ' (CSV)</a></div>';

$formother = new FormOther($db);
print '<form method="GET" action="' . DOL_URL_ROOT . '/custom/apclogistics/stock_exportcsv.php" style="margin-top:16px;">';
print '<input type="hidden" name="type" value="mouvements">';
print '<table class="border centpercent" style="max-width:640px;">';
print '<tr><td>' . $langs->trans('DateStart') . '</td><td>' . $formother->select_date($date_start ? $date_start : -1, 'date_start', 0, 0, 1, '', 1, 0) . '</td></tr>';
print '<tr><td>' . $langs->trans('DateEnd') . '</td><td>' . $formother->select_date($date_end ? $date_end : -1, 'date_end', 0, 0, 1, '', 1, 0) . '</td></tr>';
print '</table>';
print '<div style="margin-top:10px;"><input type="submit" class="button" value="' . $langs->trans('StockExportCSV') . '"></div>';
print '</form>';

print '<p><a class="button button-cancel" href="' . DOL_URL_ROOT . '/custom/apclogistics/stock_list.php">' . $langs->trans('BackToList') . '</a></p>';
llxFooter();
$db->close();
