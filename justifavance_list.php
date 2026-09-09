<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 * justifavance_list.php — Listing Justifications d'Avance (JAV)
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
$res = 0; if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php"; if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php"; if (!$res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/class/ApcJustifAvance.class.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

if (empty($user->rights->apclogistics->justifavance->read)) accessforbidden();

$search_ref    = trim(GETPOST('search_ref', 'alpha'));
$search_status = GETPOST('search_status', 'int');
$search_objet  = trim(GETPOST('search_objet', 'alpha'));
$search_fk_dav = (int)GETPOST('search_fk_dav', 'int');
$search_date_start = dol_mktime(0,0,0, GETPOST('date_startmonth','int'), GETPOST('date_startday','int'), GETPOST('date_startyear','int'));
$search_date_end   = dol_mktime(23,59,59, GETPOST('date_endmonth','int'),   GETPOST('date_endday','int'),   GETPOST('date_endyear','int'));

$limit  = GETPOST('limit','int') ?: $conf->liste_limit;
$page   = GETPOST('page','int');
$sortfield = GETPOST('sortfield','aZ') ?: 'jav.date_creation';
$sortorder = GETPOST('sortorder','aZ') ?: 'DESC';
if ($page < 0) $page = 0;
$offset = $page * $limit;

$title = $langs->trans('APCLogisticsMenu_justifavance');
llxHeader('', $title, '', '', 0, 0, array(), array('/custom/apclogistics/css/apclogistics.css'));
print load_fiche_titre($title, '', 'apclogistics@apclogistics', 0);

$sql = "SELECT jav.rowid, jav.ref, jav.objet, jav.fk_demande_avance, jav.total_depense, jav.prise_avance, jav.ecart, jav.status, jav.date_jav, dav.ref as dav_ref";
$sql_count = "SELECT COUNT(jav.rowid)";
$sql_from = " FROM " . MAIN_DB_PREFIX . "apclogistics_justifavance jav";
$sql_from .= " LEFT JOIN " . MAIN_DB_PREFIX . "apclogistics_demandeavance dav ON dav.rowid = jav.fk_demande_avance";
$sql_where = " WHERE jav.entity IN (0," . getEntity('apclogistics_justifavance') . ")";

if ($search_ref)    $sql_where .= " AND (jav.ref LIKE '%" . $db->escape($search_ref) . "%' OR dav.ref LIKE '%" . $db->escape($search_ref) . "%')";
if ($search_objet)  $sql_where .= " AND jav.objet LIKE '%" . $db->escape($search_objet) . "%'";
if ($search_fk_dav > 0) $sql_where .= " AND jav.fk_demande_avance = " . $search_fk_dav;
if ($search_status !== '' && $search_status != -1) $sql_where .= " AND jav.status = " . (int)$search_status;
if ($search_date_start > 0) $sql_where .= " AND jav.date_jav >= '" . $db->idate($search_date_start) . "'";
if ($search_date_end > 0)   $sql_where .= " AND jav.date_jav <= '" . $db->idate($search_date_end) . "'";
$sql_order = " ORDER BY " . $sortfield . " " . $sortorder;

$resql = $db->query($sql . $sql_from . $sql_where . $sql_order . $db->plimit($limit+1, $offset));
$rescount = $db->query($sql_count . $sql_from . $sql_where);
$nbtotal = $rescount ? (int)$db->fetch_array($rescount)[0] : 0;
$num = $resql ? $db->num_rows($resql) : 0;
$formother = new FormOther($db);
$davInstance = new ApcJustifAvance($db);

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'"><input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'"><input type="hidden" name="sortorder" value="'.$sortorder.'"><input type="hidden" name="page" value="'.$page.'">';
print '<div class="div-table-responsive-no-min liste-filter-apc"><table class="noborder centpercent liste"><tr class="liste_titre_filter">';
print '<td><input type="text" class="flat width100" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" placeholder="'.$langs->trans('FieldRef').' JAV / DAV"></td>';
print '<td><input type="text" class="flat width100" name="search_objet" value="'.dol_escape_htmltag($search_objet).'" placeholder="'.$langs->trans('FieldObjet').'"></td>';
$statusList = $davInstance->getStatusList();
$sel = '<select class="flat width100" name="search_status"><option value="-1">'.$langs->trans('All').'</option>';
foreach ($statusList as $c=>$l) { $s = ($search_status!=='' && (int)$search_status===(int)$c)?' selected':''; $sel.='<option value="'.$c.'"'.$s.'>'.$l.'</option>'; }
$sel .= '</select>';
print '<td>'.$sel.'</td>';
print '<td>'.$formother->select_date($search_date_start?:-1,'date_start',0,0,1,'',1,0).'</td>';
print '<td>'.$formother->select_date($search_date_end?:-1,'date_end',0,0,1,'',1,0).'</td>';
print '<td><input type="submit" class="button small" name="button_search_x" value="'.$langs->trans('Search').'"> <input type="submit" class="button small" name="button_removefilter_x" value="'.$langs->trans('Reset').'"></td>';
print '</tr></table></div></form>';

$param = 'search_ref='.urlencode($search_ref).'&search_objet='.urlencode($search_objet).'&search_status='.urlencode($search_status);
print_barre_liste($langs->trans('JAVTitle').' ('.$nbtotal.')', $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotal, 'title_generic.png', 0, '', '', $limit);

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent liste"><tr class="liste_titre">';
print getTitleFieldOfList('','',$_SERVER["PHP_SELF"],'','','',$sortfield,$sortorder,'center width30')."\n";
print getTitleFieldOfList($langs->trans('FieldRef'),0,$_SERVER["PHP_SELF"],'jav.ref','','',$sortfield,$sortorder,'',1)."\n";
print getTitleFieldOfList($langs->trans('JAVDate'),0,$_SERVER["PHP_SELF"],'jav.date_jav','','',$sortfield,$sortorder,'',1)."\n";
print getTitleFieldOfList($langs->trans('JAVFromDAV'),0,$_SERVER["PHP_SELF"],'dav.ref','','',$sortfield,$sortorder,'',1)."\n";
print getTitleFieldOfList($langs->trans('FieldObjet'),0,$_SERVER["PHP_SELF"],'jav.objet','','',$sortfield,$sortorder,'',1)."\n";
print getTitleFieldOfList($langs->trans('JAVTotalDepense'),0,$_SERVER["PHP_SELF"],'jav.total_depense','','',$sortfield,$sortorder,'right ',1)."\n";
print getTitleFieldOfList($langs->trans('JAVPriseAvance'),0,$_SERVER["PHP_SELF"],'jav.prise_avance','','',$sortfield,$sortorder,'right ',1)."\n";
print getTitleFieldOfList($langs->trans('JAVEcart'),0,$_SERVER["PHP_SELF"],'jav.ecart','','',$sortfield,$sortorder,'right ',1)."\n";
print getTitleFieldOfList($langs->trans('FieldStatus'),0,$_SERVER["PHP_SELF"],'jav.status','','',$sortfield,$sortorder,'center ',1)."\n";
print '</tr>';

$i = 0;
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $jav = new ApcJustifAvance($db);
        $jav->id = $obj->rowid; $jav->ref = $obj->ref; $jav->status = $obj->status;
        print '<tr class="oddeven">';
        print '<td class="center">'.++$i.'</td>';
        print '<td>'.$jav->getNomUrl(1).'</td>';
        print '<td>'.dol_print_date($db->jdate($obj->date_jav),'day').'</td>';
        if (!empty($obj->dav_ref)) {
            $dav = new ApcDemandeAvance($db);
            $dav->fetch($obj->fk_demande_avance);
            print '<td>'.$dav->getNomUrl(1).'</td>';
        } else {
            print '<td><span class="opacitymedium">-</span></td>';
        }
        print '<td>'.dol_escape_htmltag($obj->objet).'</td>';
        print '<td class="right apc-money">'.price((float)$obj->total_depense,0,$langs,0,0,-1,$conf->currency).'</td>';
        print '<td class="right apc-money">'.price((float)$obj->prise_avance,0,$langs,0,0,-1,$conf->currency).'</td>';
        $ec = (float)$obj->ecart;
        $ecCls = ($ec < -0.0001) ? 'apc-money-negative' : (($ec > 0.0001) ? 'apc-money-positive' : '');
        print '<td class="right '.$ecCls.'">'.price($ec,0,$langs,0,0,-1,$conf->currency).'</td>';
        print '<td class="center">'.$jav->getStatusBadge().'</td>';
        print '</tr>';
    }
    $db->free($resql);
}
if ($i === 0) {
    print '<tr><td colspan="9" class="opacitymedium center">'.$langs->trans('NoRecord').'</td></tr>';
}
print '</table></div>';

$permissiontocreate = !empty($user->rights->apclogistics->justifavance->create);
if ($permissiontocreate) {
    print '<div class="tabsAction" style="margin-top:18px;">';
    print '<a class="butActionNew" href="'.DOL_URL_ROOT.'/custom/apclogistics/justifavance_card.php?action=create"><span class="fa fa-plus-circle"></span> '.$langs->trans('NewJAV').'</a>';
    print '</div>';
}

llxFooter();
$db->close();
