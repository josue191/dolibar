<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * stock_card.php — Fiche de stock APC
 * Onglets : Fiche (info article + tableau mouvements) | Historique
 * Actions : Générer PDF fiche stock, modifier gestionnaire
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
$res = 0; if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php"; if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php"; if (!$res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once __DIR__ . '/class/ApcStock.class.php';
require_once __DIR__ . '/class/ApcAuditLog.class.php';
require_once __DIR__ . '/class/apc_links.lib.php';
require_once __DIR__ . '/class/pdf/pdf_fichestock_apc.modules.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');
$langs->load('products');

$id      = (int)GETPOST('id', 'int');
$action  = GETPOST('action', 'aZ');
$confirm = GETPOST('confirm', 'alpha');
$token   = GETPOST('token', 'alpha');

$object = new ApcStock($db);
$prod = new Product($db);

if ($id > 0) {
    $res = $object->fetch($id);
    if ($res <= 0) { dol_print_error('', 'ErrorLoadFailed'); exit; }
    if ($object->fk_product > 0) $prod->fetch($object->fk_product);
}

$permissiontoread   = !empty($user->rights->apclogistics->stock->read);
$permissiontoedit   = !empty($user->rights->apclogistics->stock->edit);
$permissiontoexport = !empty($user->rights->apclogistics->stock->read);
if (empty($permissiontoread)) accessforbidden();

$form = new Form($db);
$formother = new FormOther($db);

if ($action === 'update_gest' && $permissiontoedit && $id > 0 && $token) {
    $object->fk_user_gestionnaire    = (int)GETPOST('fk_user_gestionnaire', 'int');
    $object->fk_user_resp_logistique = (int)GETPOST('fk_user_resp_logistique', 'int');
    $object->unite                   = GETPOST('unite', 'alphanohtml');
    $object->seuil_alerte            = (float)str_replace(',', '.', GETPOST('seuil_alerte', 'alpha'));
    $object->last_stock_update       = $db->idate(dol_now());
    $res = $object->update($user);
    if ($res > 0) { setEventMessages($langs->trans('Saved'), null, 'mesgs'); }
    else setEventMessages($object->error, $object->errors, 'errors');
    header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/stock_card.php?id=' . $id);
    exit;
}

if ($action === 'generate_pdf' && $id > 0) {
    $g = new pdf_fichestock_apc($db);
    $g->write_file($object, $prod, $langs, '', 'I');
    exit;
}

$title = $prod->ref ? ('Stock ' . $prod->ref) : $langs->trans('StockTitle');
llxHeader('', $title, '', '', 0, 0, array('/custom/apclogistics/js/apclogistics.js'), array('/custom/apclogistics/css/apclogistics.css'));

$head = array();
$head[0][0] = DOL_URL_ROOT . '/custom/apclogistics/stock_card.php?id=' . ($object->id ?: 0);
$head[0][1] = $langs->trans('TabCard');
$head[0][2] = 'tabcard';
$head[1][0] = DOL_URL_ROOT . '/custom/apclogistics/stock_card.php?id=' . ($object->id ?: 0) . '&action=links';
$head[1][1] = $langs->trans('TabLinks');
$head[1][2] = 'tablinks';
$head[2][0] = DOL_URL_ROOT . '/custom/apclogistics/stock_card.php?id=' . ($object->id ?: 0) . '&action=audit';
$head[2][1] = $langs->trans('TabHistory');
$head[2][2] = 'tabhistory';

dol_fiche_head($head, 'tabcard', $title, -1, 'stock');

if ($id <= 0) {
    print '<div class="opacitymedium">' . $langs->trans('NoRecord') . '</div>';
    dol_fiche_end();
    llxFooter();
    $db->close();
    exit;
}

$editing = ($action === 'edit');
$seuil = !empty($conf->global->APCLOGISTICS_STOCK_ALERT) ? (float)$conf->global->APCLOGISTICS_STOCK_ALERT : 1;

if ($editing) {
    print '<form action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '" method="POST">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="update_gest">';
    print '<table class="border centpercent">';
} else {
    print '<table class="border centpercent">';
}

print '<tr><td class="titlefield">' . $langs->trans('ProductId') . '</td><td>';
if ($object->fk_product > 0) {
    print '<a href="' . DOL_URL_ROOT . '/product/card.php?id=' . $object->fk_product . '">' . img_picto('', 'product') . ' ' . dol_escape_htmltag($prod->ref ?: '#'.$object->fk_product) . '</a>';
} else print '<span class="opacitymedium">—</span>';
print '</td><td class="titlefield">' . $langs->trans('Label') . '</td><td>' . dol_escape_htmltag($prod->label ?: '-') . '</td></tr>';

print '<tr><td>' . $langs->trans('StockCurrent') . '</td><td class="valeur">';
$low = (float)$object->stock_actuel <= ($object->seuil_alerte > 0 ? (float)$object->seuil_alerte : $seuil);
$cls = $low ? 'apc-neg' : 'apc-pos';
print '<span class="' . $cls . '"><b>' . price((float)$object->stock_actuel, 0, $langs, 0, 0, 0, '') . '</b></span> ' . dol_escape_htmltag($object->unite ?: '');
if ($low) print ' &nbsp; <span class="apc-status apc-status--annule">' . $langs->trans('StockLow') . '</span>';
print '</td>';

if ($editing) {
    print '<td>' . $langs->trans('StockUnit') . '</td><td><input type="text" name="unite" size="14" value="' . dol_escape_htmltag($object->unite) . '"></td></tr>';
    print '<tr><td>' . $langs->trans('StockAlert') . '</td><td><input type="text" name="seuil_alerte" size="14" value="' . ($object->seuil_alerte > 0 ? (float)$object->seuil_alerte : $seuil) . '"></td>';
    print '<td>' . $langs->trans('StockGest') . '</td><td>' . $form->select_dolusers($object->fk_user_gestionnaire, 'fk_user_gestionnaire', 1, '', 0, '', '', 0, 0, 0, '', 0, '', 'minwidth200') . '</td></tr>';
    print '<tr><td>' . $langs->trans('StockRespLog') . '</td><td>' . $form->select_dolusers($object->fk_user_resp_logistique, 'fk_user_resp_logistique', 1, '', 0, '', '', 0, 0, 0, '', 0, '', 'minwidth200') . '</td>';
    print '<td>' . $langs->trans('StockLastUpd') . '</td><td>' . ($object->last_stock_update ? dol_print_date($db->jdate($object->last_stock_update), 'dayhour') : '-') . '</td></tr>';
} else {
    print '<td>' . $langs->trans('StockUnit') . '</td><td>' . dol_escape_htmltag($object->unite ?: '-') . '</td></tr>';
    print '<tr><td>' . $langs->trans('StockAlert') . '</td><td>' . price($object->seuil_alerte > 0 ? (float)$object->seuil_alerte : $seuil, 0, $langs, 0, 0, 0, '') . '</td>';
    $u = new User($db); $gName = '-';
    if ($object->fk_user_gestionnaire > 0 && $u->fetch($object->fk_user_gestionnaire) > 0) $gName = $u->getFullName($langs);
    print '<td>' . $langs->trans('StockGest') . '</td><td>' . dol_escape_htmltag($gName) . '</td></tr>';
    $u2 = new User($db); $rName = '-';
    if ($object->fk_user_resp_logistique > 0 && $u2->fetch($object->fk_user_resp_logistique) > 0) $rName = $u2->getFullName($langs);
    print '<tr><td>' . $langs->trans('StockRespLog') . '</td><td>' . dol_escape_htmltag($rName) . '</td>';
    print '<td>' . $langs->trans('StockLastUpd') . '</td><td>' . ($object->last_stock_update ? dol_print_date($db->jdate($object->last_stock_update), 'dayhour') : '-') . '</td></tr>';
}
print '</table>';

if ($editing) {
    print '<div class="tabsAction" style="margin-top:16px;">';
    print '<input type="submit" class="button" value="' . $langs->trans('Save') . '">';
    print ' <a class="button button-cancel" href="' . DOL_URL_ROOT . '/custom/apclogistics/stock_card.php?id=' . $object->id . '">' . $langs->trans('Cancel') . '</a>';
    print '</div></form>';
}

$movements = $object->fetchMovements(500);

print '<h3 style="margin-top:22px;">' . $langs->trans('StockMovs') . ' (' . count($movements) . ')</h3>';
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<thead><tr class="liste_titre">';
print '<th class="center">' . $langs->trans('StockMovDate') . '</th>';
print '<th class="center">' . $langs->trans('StockMovType') . '</th>';
print '<th>' . $langs->trans('StockMovRef') . '</th>';
print '<th class="center">' . $langs->trans('StockUnit') . '</th>';
print '<th class="right">' . $langs->trans('StockMovIn') . '</th>';
print '<th class="right">' . $langs->trans('StockMovOut') . '</th>';
print '<th class="right"><b>' . $langs->trans('StockCurrent') . '</b></th>';
print '<th>' . $langs->trans('User') . '</th>';
print '</tr></thead><tbody>';

$stkCourant = 0;
$totalDelta = 0;
foreach ($movements as $m) { $totalDelta += (float)$m->entree - (float)$m->sortie; }
$stkCourant = (float)$object->stock_actuel - $totalDelta;
foreach ($movements as $m) {
    $stkCourant += (float)$m->entree - (float)$m->sortie;
    print '<tr class="oddeven">';
    print '<td class="center">' . dol_print_date($db->jdate($m->date_movement), 'dayhour') . '</td>';
    $typeBadge = (float)$m->entree > 0 ? '<span class="apc-status apc-status--valide">ENTREE</span>' : '<span class="apc-status apc-status--annule">SORTIE</span>';
    print '<td class="center">' . $typeBadge . '</td>';
    $doc = $m->ref_doc_type . ' #' . (int)$m->ref_doc_id;
    if ($m->ref_doc_type === 'BC') $doc = '<a href="' . DOL_URL_ROOT . '/custom/apclogistics/boncommande_card.php?id=' . (int)$m->ref_doc_id . '">' . dol_escape_htmltag($m->ref_doc_label ?: ('BC #' . (int)$m->ref_doc_id)) . '</a>';
    if ($m->ref_doc_type === 'BR') $doc = '<a href="' . DOL_URL_ROOT . '/custom/apclogistics/bonreception_card.php?id=' . (int)$m->ref_doc_id . '">' . dol_escape_htmltag($m->ref_doc_label ?: ('BR #' . (int)$m->ref_doc_id)) . '</a>';
    if ($m->ref_doc_type === 'REQ') $doc = '<a href="' . DOL_URL_ROOT . '/custom/apclogistics/requisition_card.php?id=' . (int)$m->ref_doc_id . '">' . dol_escape_htmltag($m->ref_doc_label ?: ('REQ #' . (int)$m->ref_doc_id)) . '</a>';
    print '<td>' . $doc . '</td>';
    print '<td class="center">' . dol_escape_htmltag($m->unite ?: '-') . '</td>';
    print '<td class="right apc-pos">' . ((float)$m->entree > 0 ? '+' . price((float)$m->entree, 0, $langs, 0, 0, 0, '') : '') . '</td>';
    print '<td class="right apc-neg">' . ((float)$m->sortie > 0 ? '-' . price((float)$m->sortie, 0, $langs, 0, 0, 0, '') : '') . '</td>';
    print '<td class="right apc-money"><b>' . price($stkCourant, 0, $langs, 0, 0, 0, '') . '</b></td>';
    $uName = '-';
    if (!empty($m->fk_user)) { $uu = new User($db); if ($uu->fetch($m->fk_user) > 0) $uName = $uu->getFullName($langs); }
    print '<td>' . dol_escape_htmltag($uName) . '</td>';
    print '</tr>';
}
if (empty($movements)) {
    print '<tr><td colspan="8" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
}
print '</tbody></table></div>';

print '<div class="tabsAction" style="margin-top:24px;">';
print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/stock_card.php?id=' . $object->id . '&action=generate_pdf&token=' . newToken() . '">' . $langs->trans('PDFGenerateBtn') . '</a>';
if ($permissiontoedit && !$editing) print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/stock_card.php?id=' . $object->id . '&action=edit">' . $langs->trans('Modify') . '</a>';
print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/stock_list.php">' . $langs->trans('BackToList') . '</a>';
print '</div>';

dol_fiche_end();

if ($action === 'links' && $object->id > 0) {
    apcPrintLinksTab($db, $langs, $conf, $object);
}

if ($action === 'audit' && $object->id > 0) {
    $audits = ApcAuditLog::fetchForEntity($db, 'stock', $object->id, 200);
    print '<h3 style="margin-top:12px;">' . $langs->trans('TabHistory') . '</h3>';
    print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
    print '<tr class="liste_titre"><th>' . $langs->trans('AUDColDate') . '</th><th>' . $langs->trans('AUDColUser') . '</th><th>' . $langs->trans('AUDColAction') . '</th><th>' . $langs->trans('AUDColIp') . '</th><th>' . $langs->trans('AUDColDetails') . '</th></tr>';
    foreach ($audits as $a) {
        print '<tr class="oddeven">';
        print '<td>' . dol_print_date($db->jdate($a->date_action), 'dayhour') . '</td>';
        print '<td>' . dol_escape_htmltag($a->user_login) . '</td>';
        print '<td>' . ApcAuditLog::formatAction($a->action_type) . '</td>';
        print '<td>' . dol_escape_htmltag($a->ip_address) . '</td>';
        print '<td>' . dol_escape_htmltag($a->action_details) . '</td>';
        print '</tr>';
    }
    if (empty($audits)) print '<tr><td colspan="5" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
    print '</table></div>';
}

llxFooter();
$db->close();
