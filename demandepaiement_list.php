<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 * demandepaiement_list.php — Listing Demandes de Paiement APC (DPAI)
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/class/ApcDemandePaiement.class.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

if (empty($user->rights->apclogistics->demandepaiement->read)) accessforbidden();

$search_ref     = trim(GETPOST('search_ref', 'alpha'));
$search_status  = GETPOST('search_status', 'int');
$search_objet   = trim(GETPOST('search_objet', 'alpha'));
$search_cas     = (int)GETPOST('search_cas', 'int');
$search_benef   = trim(GETPOST('search_benef', 'alpha'));
$search_date_start = dol_mktime(0,0,0, GETPOST('date_startmonth','int'), GETPOST('date_startday','int'), GETPOST('date_startyear','int'));
$search_date_end   = dol_mktime(23,59,59, GETPOST('date_endmonth','int'),   GETPOST('date_endday','int'),   GETPOST('date_endyear','int'));

$limit  = GETPOST('limit','int') ?: $conf->liste_limit;
$page   = GETPOST('page','int');
$sortfield = GETPOST('sortfield','aZ') ?: 'dpai.date_creation';
$sortorder = GETPOST('sortorder','aZ') ?: 'DESC';
if ($page < 0) $page = 0;
$offset = $page * $limit;

$title = $langs->trans('APCLogisticsMenu_demandepaiement');
llxHeader('', $title, '', '', 0, 0, array(), array('/custom/apclogistics/css/apclogistics.css'));
print load_fiche_titre($title, '', 'apclogistics@apclogistics', 0);

$sql = "SELECT dpai.rowid, dpai.ref, dpai.objet, dpai.cas_usage, dpai.mode_paiement, dpai.compte, dpai.beneficiaire_nom, dpai.total, dpai.status, dpai.date_dpai";
$sql_count = "SELECT COUNT(dpai.rowid)";
$sql_from = " FROM " . MAIN_DB_PREFIX . "apclogistics_demandepaiement dpai";
$sql_where = " WHERE dpai.entity IN (0," . getEntity('apclogistics_demandepaiement') . ")";

if ($search_ref)    $sql_where .= " AND (dpai.ref LIKE '%" . $db->escape($search_ref) . "%' OR dpai.compte LIKE '%" . $db->escape($search_ref) . "%')";
if ($search_objet)  $sql_where .= " AND dpai.objet LIKE '%" . $db->escape($search_objet) . "%'";
if ($search_benef)  $sql_where .= " AND dpai.beneficiaire_nom LIKE '%" . $db->escape($search_benef) . "%'";
if ($search_cas > 0) $sql_where .= " AND dpai.cas_usage = " . $search_cas;
if ($search_status !== '' && $search_status != -1) $sql_where .= " AND dpai.status = " . (int)$search_status;
if ($search_date_start > 0) $sql_where .= " AND dpai.date_dpai >= '" . $db->idate($search_date_start) . "'";
if ($search_date_end > 0)   $sql_where .= " AND dpai.date_dpai <= '" . $db->idate($search_date_end) . "'";
$sql_order = " ORDER BY " . $sortfield . " " . $sortorder;

$resql = $db->query($sql . $sql_from . $sql_where . $sql_order . $db->plimit($limit+1, $offset));
$rescount = $db->query($sql_count . $sql_from . $sql_where);
$nbtotal = $rescount ? (int)$db->fetch_array($rescount)[0] : 0;
$num = $resql ? $db->num_rows($resql) : 0;
$formother = new FormOther($db);
$dpaiInstance = new ApcDemandePaiement($db);

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'"><input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'"><input type="hidden" name="sortorder" value="'.$sortorder.'"><input type="hidden" name="page" value="'.$page.'">';
print '<div class="div-table-responsive-no-min liste-filter-apc"><table class="noborder centpercent liste"><tr class="liste_titre_filter">';
print '<td><input type="text" class="flat width100" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" placeholder="'.$langs->trans('FieldRef').' / Compte"></td>';
print '<td><input type="text" class="flat width100" name="search_objet" value="'.dol_escape_htmltag($search_objet).'" placeholder="'.$langs->trans('FieldObjet').'"></td>';
print '<td><input type="text" class="flat width100" name="search_benef" value="'.dol_escape_htmltag($search_benef).'" placeholder="'.$langs->trans('DPAIBeneficiaire').'"></td>';
$selCas = '<select class="flat width100" name="search_cas"><option value="0">'.$langs->trans('DPAICasUsageAll').'</option>';
for ($c = 1; $c <= 3; $c++) { $selCas .= '<option value="'.$c.'"'.($search_cas===$c?' selected':'').'>'.$langs->trans('DPAICas'.$c).'</option>'; }
$selCas .= '</select>';
print '<td>'.$selCas.'</td>';
$statusList = $dpaiInstance->getStatusList();
$sel = '<select class="flat width100" name="search_status"><option value="-1">'.$langs->trans('All').'</option>';
foreach ($statusList as $c=>$l) { $s = ($search_status!=='' && (int)$search_status===(int)$c)?' selected':''; $sel.='<option value="'.$c.'"'.$s.'>'.$l.'</option>'; }
$sel .= '</select>';
print '<td>'.$sel.'</td>';
print '<td>'.$formother->select_date($search_date_start?:-1,'date_start',0,0,1,'',1,0).'</td>';
print '<td>'.$formother->select_date($search_date_end?:-1,'date_end',0,0,1,'',1,0).'</td>';
print '<td><input type="submit" class="button small" name="button_search_x" value="'.$langs->trans('Search').'"> <input type="submit" class="button small" name="button_removefilter_x" value="'.$langs->trans('Reset').'"></td>';
print '</tr></table></div></form>';

$param = 'search_ref='.urlencode($search_ref).'&search_objet='.urlencode($search_objet).'&search_cas='.urlencode($search_cas).'&search_status='.urlencode($search_status);
print_barre_liste($langs->trans('DPAITitle').' ('.$nbtotal.')', $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotal, 'title_generic.png', 0, '', '', $limit);

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent liste"><tr class="liste_titre">';
print getTitleFieldOfList('','',$_SERVER["PHP_SELF"],'','','',$sortfield,$sortorder,'center width30')."\n";
print getTitleFieldOfList($langs->trans('FieldRef'),0,$_SERVER["PHP_SELF"],'dpai.ref','','',$sortfield,$sortorder,'',1)."\n";
print getTitleFieldOfList($langs->trans('DPAIDate'),0,$_SERVER["PHP_SELF"],'dpai.date_dpai','','',$sortfield,$sortorder,'',1)."\n";
print getTitleFieldOfList($langs->trans('DPAICasUsage'),0,$_SERVER["PHP_SELF"],'dpai.cas_usage','','',$sortfield,$sortorder,'',1)."\n";
print getTitleFieldOfList($langs->trans('FieldObjet'),0,$_SERVER["PHP_SELF"],'dpai.objet','','',$sortfield,$sortorder,'',1)."\n";
print getTitleFieldOfList($langs->trans('DPAIBeneficiaire'),0,$_SERVER["PHP_SELF"],'dpai.beneficiaire_nom','','',$sortfield,$sortorder,'',1)."\n";
print getTitleFieldOfList($langs->trans('DAVMode'),0,$_SERVER["PHP_SELF"],'dpai.mode_paiement','','',$sortfield,$sortorder,'',1)."\n";
print getTitleFieldOfList($langs->trans('DAVTotal'),0,$_SERVER["PHP_SELF"],'dpai.total','','',$sortfield,$sortorder,'right ',1)."\n";
print getTitleFieldOfList($langs->trans('FieldStatus'),0,$_SERVER["PHP_SELF"],'dpai.status','','',$sortfield,$sortorder,'center ',1)."\n";
print '</tr>';

$i = 0;
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $tmp = new ApcDemandePaiement($db);
        $tmp->id = $obj->rowid; $tmp->ref = $obj->ref; $tmp->status = $obj->status; $tmp->cas_usage = $obj->cas_usage;
        print '<tr class="oddeven">';
        print '<td class="center">'.++$i.'</td>';
        print '<td>'.$tmp->getNomUrl(1).'</td>';
        print '<td>'.dol_print_date($db->jdate($obj->date_dpai),'day').'</td>';
        print '<td>'.$tmp->getCasUsageLabel().'</td>';
        print '<td>'.dol_escape_htmltag($obj->objet).'</td>';
        print '<td>'.dol_escape_htmltag($obj->beneficiaire_nom).'</td>';
        $mpLbl = ((int)$obj->mode_paiement === 1) ? $langs->trans('DAVModeCaisse') : $langs->trans('DAVModeBanque');
        print '<td>'.$mpLbl.'</td>';
        print '<td class="right apc-money">'.price((float)$obj->total,0,$langs,0,0,-1,$conf->currency).'</td>';
        print '<td class="center">'.$tmp->getStatusBadge().'</td>';
        print '</tr>';
    }
    $db->free($resql);
}
if ($i === 0) {
    print '<tr><td colspan="9" class="opacitymedium center">'.$langs->trans('NoRecord').'</td></tr>';
}
print '</table></div>';

$permissiontocreate = !empty($user->rights->apclogistics->demandepaiement->create);
if ($permissiontocreate) {
    print '<div class="tabsAction" style="margin-top:18px;">';
    print '<a class="butActionNew" href="'.DOL_URL_ROOT.'/custom/apclogistics/demandepaiement_card.php?action=create"><span class="fa fa-plus-circle"></span> '.$langs->trans('NewDPAI').'</a>';
    print '</div>';
}

llxFooter();
$db->close();
