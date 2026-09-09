<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 * demandeavance_list.php — Listing Demandes d'Avance (DAV)
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
$res = 0; if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php"; if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php"; if (!$res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/class/ApcDemandeAvance.class.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

if (empty($user->rights->apclogistics->demandeavance->read)) accessforbidden();

$search_ref    = trim(GETPOST('search_ref', 'alpha'));
$search_status = GETPOST('search_status', 'int');
$search_objet  = trim(GETPOST('search_objet', 'alpha'));
$search_date_start = dol_mktime(0,0,0, GETPOST('date_startmonth','int'), GETPOST('date_startday','int'), GETPOST('date_startyear','int'));
$search_date_end   = dol_mktime(23,59,59, GETPOST('date_endmonth','int'),   GETPOST('date_endday','int'),   GETPOST('date_endyear','int'));

$limit  = GETPOST('limit','int') ?: $conf->liste_limit;
$page   = GETPOST('page','int');
$sortfield = GETPOST('sortfield','aZ') ?: 'dav.date_creation';
$sortorder = GETPOST('sortorder','aZ') ?: 'DESC';
if ($page < 0) $page = 0;
$offset = $page * $limit;

$title = $langs->trans('APCLogisticsMenu_demandeavance');
llxHeader('', $title, '', '', 0, 0, array(), array('/custom/apclogistics/css/apclogistics.css'));
print load_fiche_titre($title, '', 'apclogistics@apclogistics', 0);

$sql = "SELECT dav.rowid, dav.ref, dav.objet, dav.compte, dav.mode_paiement, dav.total, dav.status, dav.date_demande, dav.fk_user_demandeur";
$sql_count = "SELECT COUNT(dav.rowid)";
$sql_from = " FROM " . MAIN_DB_PREFIX . "apclogistics_demandeavance dav";
$sql_where = " WHERE dav.entity IN (0," . getEntity('apclogistics_demandeavance') . ")";

if ($search_ref)    $sql_where .= " AND (dav.ref LIKE '%" . $db->escape($search_ref) . "%' OR dav.compte LIKE '%" . $db->escape($search_ref) . "%')";
if ($search_objet)  $sql_where .= " AND dav.objet LIKE '%" . $db->escape($search_objet) . "%'";
if ($search_status !== '' && $search_status != -1) $sql_where .= " AND dav.status = " . (int)$search_status;
if ($search_date_start > 0) $sql_where .= " AND dav.date_demande >= '" . $db->idate($search_date_start) . "'";
if ($search_date_end > 0)   $sql_where .= " AND dav.date_demande <= '" . $db->idate($search_date_end) . "'";
$sql_order = " ORDER BY " . $sortfield . " " . $sortorder;

$resql = $db->query($sql . $sql_from . $sql_where . $sql_order . $db->plimit($limit+1, $offset));
$rescount = $db->query($sql_count . $sql_from . $sql_where);
$nbtotal = $rescount ? (int)$db->fetch_array($rescount)[0] : 0;
$num = $resql ? $db->num_rows($resql) : 0;
$formother = new FormOther($db);

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'"><input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'"><input type="hidden" name="sortorder" value="'.$sortorder.'"><input type="hidden" name="page" value="'.$page.'">';
print '<div class="div-table-responsive-no-min liste-filter-apc"><table class="noborder centpercent liste"><tr class="liste_titre_filter">';
print '<td><input type="text" class="flat width100" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" placeholder="'.$langs->trans('FieldRef').' / Compte"></td>';
print '<td><input type="text" class="flat width100" name="search_objet" value="'.dol_escape_htmltag($search_objet).'" placeholder="'.$langs->trans('FieldObjet').'"></td>';
$statusList = (new ApcDemandeAvance($db))->getStatusList();
$sel = '<select class="flat width100" name="search_status"><option value="-1">'.$langs->trans('All').'</option>';
foreach ($statusList as $c=>$l) { $s = ($search_status!=='' && (int)$search_status===(int)$c)?' selected':''; $sel.='<option value="'.$c.'"'.$s.'>'.$l.'</option>'; }
$sel .= '</select>';
print '<td>'.$sel.'</td>';
print '<td>'.$formother->select_date($search_date_start?:-1,'date_start',0,0,1,'',1,0).'</td>';
print '<td>'.$formother->select_date($search_date_end?:-1,'date_end',0,0,1,'',1,0).'</td>';
print '<td><input type="submit" class="button small" name="button_search_x" value="'.$langs->trans('Search').'"> <input type="submit" class="button small" name="button_removefilter_x" value="'.$langs->trans('Reset').'"></td>';
print '</tr></table></div></form>';

$param = 'search_ref='.urlencode($search_ref).'&search_objet='.urlencode($search_objet).'&search_status='.urlencode($search_status);
print_barre_liste($langs->trans('DAVTitle').' ('.$nbtotal.')', $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotal, 'title_generic.png', 0, '', '', $limit);

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent liste"><tr class="liste_titre">';
print getTitleFieldOfList('','',$_SERVER["PHP_SELF"],'','','',$sortfield,$sortorder,'center width30')."\n";
print getTitleFieldOfList($langs->trans('FieldRef'),0,$_SERVER["PHP_SELF"],'dav.ref','','',$sortfield,$sortorder,'',1)."\n";
print getTitleFieldOfList($langs->trans('DAVDate'),0,$_SERVER["PHP_SELF"],'dav.date_demande','','',$sortfield,$sortorder,'',1)."\n";
print getTitleFieldOfList($langs->trans('FieldObjet'),0,$_SERVER["PHP_SELF"],'dav.objet','','',$sortfield,$sortorder,'',1)."\n";
print getTitleFieldOfList($langs->trans('DAVCompte'),0,$_SERVER["PHP_SELF"],'dav.compte','','',$sortfield,$sortorder,'',1)."\n";
print getTitleFieldOfList($langs->trans('DAVMode'),0,$_SERVER["PHP_SELF"],'dav.mode_paiement','','',$sortfield,$sortorder,'',1)."\n";
print getTitleFieldOfList($langs->trans('DAVTotal'),0,$_SERVER["PHP_SELF"],'dav.total','','',$sortfield,$sortorder,'right ',1)."\n";
print getTitleFieldOfList($langs->trans('FieldStatus'),0,$_SERVER["PHP_SELF"],'dav.status','','',$sortfield,$sortorder,'center ',1)."\n";
print '</tr>';

$tmp = new ApcDemandeAvance($db);
if ($num) {
    $i=0;
    while ($i < min($num, $limit)) {
        $obj = $db->fetch_object($resql);
        if (!$obj) break;
        $tmp->id = $obj->rowid; $tmp->ref = $obj->ref; $tmp->status = $obj->status;
        $mode = ((int)$obj->mode_paiement === 2) ? $langs->trans('DAVModeBanque') : $langs->trans('DAVModeCaisse');
        print '<tr class="oddeven"><td class="center"><input type="checkbox" class="flat checkforselect" name="toselect[]" value="'.$obj->rowid.'"></td>';
        print '<td>'.$tmp->getNomUrl(1).'</td>';
        print '<td>'.($obj->date_demande?dol_print_date($db->jdate($obj->date_demande),'day'):'-').'</td>';
        print '<td>'.dol_escape_htmltag($obj->objet).'</td>';
        print '<td>'.dol_escape_htmltag($obj->compte).'</td>';
        print '<td>'.dol_escape_htmltag($mode).'</td>';
        print '<td class="apc-money right">'.price((float)$obj->total, 0, $langs, 0, 0, -1, $conf->currency).'</td>';
        print '<td class="center">'.$tmp->getStatusBadge().'</td></tr>';
        $i++;
    }
} else print '<tr class="oddeven"><td colspan="8" class="opacitymedium">'.$langs->trans('NoRecord').'</td></tr>';
print '</table></div>';

if ($user->rights->apclogistics->demandeavance->create) {
    print '<div class="tabsAction" style="margin-top:16px;">';
    print '<a class="butAction" href="'.DOL_URL_ROOT.'/custom/apclogistics/demandeavance_card.php?action=create">'.$langs->trans('NewDAV').'</a>';
    print '</div>';
}
llxFooter();
$db->close();
