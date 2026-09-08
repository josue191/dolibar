<?php
if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/class/ApcJustifAvance.class.php';
require_once __DIR__ . '/class/ApcDemandeAvance.class.php';
require_once __DIR__ . '/class/apc_links.lib.php';
require_once __DIR__ . '/class/pdf/pdf_justifavance_apc.modules.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

$id      = (int)GETPOST('id', 'int');
$ref     = GETPOST('ref', 'alpha');
$action  = GETPOST('action', 'aZ');
$confirm = GETPOST('confirm', 'alpha');
$token   = GETPOST('token', 'alpha');
$davId   = (int)GETPOST('dav_id', 'int');

$object = new ApcJustifAvance($db);
$extrafields = new ExtraFields($db);
$extralabels = $extrafields->fetch_name_optionals_label('apclogistics_justifavance');

if ($id > 0 || $ref) {
    $res = ($id > 0) ? $object->fetch($id) : $object->fetch(0, $ref);
    if ($res <= 0) { dol_print_error('', 'ErrorLoadFailed'); exit; }
    $object->fetchLines();
}

$permissiontoread     = !empty($user->rights->apclogistics->justifavance->read);
$permissiontocreate   = !empty($user->rights->apclogistics->justifavance->create);
$permissiontoedit     = !empty($user->rights->apclogistics->justifavance->edit);
$permissiontodelete   = !empty($user->rights->apclogistics->justifavance->delete);
$permissiontovalidate = !empty($user->rights->apclogistics->justifavance->validate);

if ($action === 'create' && $action !== 'create_from_dav' && !$permissiontocreate) accessforbidden();
if (empty($permissiontoread) && $action !== 'create' && $action !== 'create_from_dav') accessforbidden();

$error = 0;
$backtopage = DOL_URL_ROOT . '/custom/apclogistics/justifavance_list.php';
$form = new Form($db);
$formother = new FormOther($db);

$linkedDAV = null;
if ($davId > 0) {
    $linkedDAV = new ApcDemandeAvance($db);
    $linkedDAV->fetch($davId);
}
if (!empty($object->fk_demande_avance)) {
    $linkedDAV = new ApcDemandeAvance($db);
    $linkedDAV->fetch($object->fk_demande_avance);
}

if ($action === 'create_from_dav' && $permissiontocreate && $davId > 0 && $linkedDAV && $linkedDAV->id > 0) {
    $newJAV = ApcJustifAvance::createFromDemandeAvance($linkedDAV, $user);
    if ($newJAV && $newJAV->id > 0) {
        setEventMessages($langs->trans('JAVFromDAVOK') . ' — ' . $newJAV->ref, null, 'mesgs');
        header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/justifavance_card.php?id=' . $newJAV->id . '&action=edit');
        exit;
    } else {
        setEventMessages($newJAV ? $newJAV->error : 'Erreur creation JAV', null, 'errors');
    }
}

if ($action === 'add' && $permissiontocreate && !$error && $user->valid && $token && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dj = dol_mktime(12,0,0, GETPOST('date_javmonth', 'int'), GETPOST('date_javday', 'int'), GETPOST('date_javyear', 'int'));
    $object->date_jav    = $db->idate($dj);
    $object->objet       = GETPOST('objet', 'alphanohtml');
    $object->fk_demande_avance = (int)GETPOST('fk_demande_avance', 'int');
    $object->prise_avance = (float)str_replace(',', '.', GETPOST('prise_avance', 'alpha'));
    $object->devise      = GETPOST('devise', 'alpha') ?: 'CDF';
    $object->note_public = GETPOST('note_public', 'alphanohtml');

    if (empty($object->date_jav) || $object->date_jav === '1970-01-01') { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('JAVDate')), null, 'errors'); $error++; }

    if (!$error) {
        $res = $object->create($user);
        if ($res > 0) {
            $lignes = GETPOST('lines', 'array');
            $no = 1;
            if (is_array($lignes)) {
                foreach ($lignes as $line) {
                    if (empty($line['depense_label']) && (float)$line['montant'] <= 0) continue;
                    $ln = new ApcJustifAvanceLine($db);
                    $ln->fk_justifavance = $object->id;
                    $ln->no_ligne        = $no++;
                    $ln->depense_label   = $line['depense_label'];
                    $ln->projet          = $line['projet'];
                    $ln->budget          = $line['budget'];
                    $ln->compte          = $line['compte'];
                    $ln->montant         = (float)str_replace(',', '.', $line['montant']);
                    $ln->date_facture    = $line['date_facture'];
                    $ln->ref_piece_justificative = $line['ref_piece_justificative'];
                    $ln->create($user);
                }
            }
            $object->calculateTotals();
            $object->update($user);
            header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/justifavance_card.php?id=' . $object->id);
            exit;
        } else { setEventMessages($object->error, $object->errors, 'errors'); }
    }
}

if ($action === 'update' && $permissiontoedit && !$error && $user->valid && $token) {
    $dj = dol_mktime(12,0,0, GETPOST('date_javmonth', 'int'), GETPOST('date_javday', 'int'), GETPOST('date_javyear', 'int'));
    $object->date_jav    = $db->idate($dj);
    $object->objet       = GETPOST('objet', 'alphanohtml');
    $object->fk_demande_avance = (int)GETPOST('fk_demande_avance', 'int');
    $object->prise_avance = (float)str_replace(',', '.', GETPOST('prise_avance', 'alpha'));
    $object->devise      = GETPOST('devise', 'alpha') ?: 'CDF';
    $object->note_public = GETPOST('note_public', 'alphanohtml');
    $res = $object->update($user);
    if ($res > 0) {
        $ln = new ApcJustifAvanceLine($db);
        $ln->deleteAllForParent($user, $object->id);
        $lignes = GETPOST('lines', 'array');
        $no = 1;
        if (is_array($lignes)) {
            foreach ($lignes as $line) {
                if (empty($line['depense_label']) && (float)$line['montant'] <= 0) continue;
                $nl = new ApcJustifAvanceLine($db);
                $nl->fk_justifavance = $object->id;
                $nl->no_ligne        = $no++;
                $nl->depense_label   = $line['depense_label'];
                $nl->projet          = $line['projet'];
                $nl->budget          = $line['budget'];
                $nl->compte          = $line['compte'];
                $nl->montant         = (float)str_replace(',', '.', $line['montant']);
                $nl->date_facture    = $line['date_facture'];
                $nl->ref_piece_justificative = $line['ref_piece_justificative'];
                $nl->create($user);
            }
        }
        $object->calculateTotals();
        $object->update($user);
        header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/justifavance_card.php?id=' . $object->id);
        exit;
    } else { setEventMessages($object->error, $object->errors, 'errors'); }
}

if ($action === 'confirm_delete' && $confirm === 'yes' && $permissiontodelete && $token) {
    $res = $object->delete($user);
    if ($res > 0) { header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/justifavance_list.php'); exit; }
    else setEventMessages($object->error, $object->errors, 'errors');
}

if ($action === 'generate_pdf' && $id > 0) {
    $g = new pdf_justifavance_apc($db);
    $g->write_file($object, $langs, '', 'I');
    exit;
}

if ($action === 'sign' && $permissiontovalidate && $id > 0 && $token) {
    $level = (int)GETPOST('level', 'int');
    $res = $object->sign($level, $user,
        GETPOST('nom_sig', 'alphanohtml'),
        GETPOST('fct_sig', 'alphanohtml')
    );
    if ($res > 0) { setEventMessages($langs->trans('SignOK'), null, 'mesgs'); }
    else setEventMessages($object->error, $object->errors, 'errors');
    header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/justifavance_card.php?id=' . $object->id);
    exit;
}

$title = ($action === 'create' || $action === 'create_from_dav' ? $langs->trans('NewJAV') : ($object->ref ?: $langs->trans('JAVTitle')));
llxHeader('', $title, '', '', 0, 0, array('/custom/apclogistics/js/apclogistics.js'), array('/custom/apclogistics/css/apclogistics.css'));

$head = array();
$head[0][0] = DOL_URL_ROOT . '/custom/apclogistics/justifavance_card.php?id=' . ($object->id ?: 0) . '&action=' . ($action ?: 'view');
$head[0][1] = $langs->trans('TabCard');
$head[0][2] = 'tabcard';
$head[1][0] = DOL_URL_ROOT . '/custom/apclogistics/justifavance_card.php?id=' . ($object->id ?: 0) . '&action=links';
$head[1][1] = $langs->trans('TabLinks');
$head[1][2] = 'tablinks';
$head[2][0] = DOL_URL_ROOT . '/custom/apclogistics/justifavance_card.php?id=' . ($object->id ?: 0) . '&action=audit';
$head[2][1] = $langs->trans('TabHistory');
$head[2][2] = 'tabhistory';

$picto = 'apclogistics@apclogistics';
dol_fiche_head($head, 'tabcard', $title, -1, $picto);

if ($action === 'create' || $action === 'edit') {
    $editing = ($action === 'edit');
    $obj =& $object;
    if ($editing) $obj->fetchLines();
    if (!$editing && $linkedDAV && $linkedDAV->id > 0) {
        $obj->fk_demande_avance = $linkedDAV->id;
        $obj->prise_avance = (float)$linkedDAV->total;
        $obj->objet = $linkedDAV->objet;
        $obj->devise = $linkedDAV->devise;
    }
    $hiddentoken = '<input type="hidden" name="token" value="' . newToken() . '">';
    $act = ($editing ? 'update' : 'add');

    print '<form action="' . $_SERVER["PHP_SELF"] . ($editing ? '?id=' . $obj->id : '') . '" method="POST">';
    print $hiddentoken;
    print '<input type="hidden" name="action" value="' . $act . '">';

    print '<table class="border centpercent">';
    print '<tr><td class="titlefieldcreate fieldrequired">' . $langs->trans('JAVRef') . '</td><td>';
    if ($editing) print $obj->ref; else print '<span class="opacitymedium">(Généré automatiquement)</span>';
    print '</td>';
    print '<td class="titlefield">' . $langs->trans('FieldStatus') . '</td><td>'
        . ($editing ? $obj->getStatusBadge() : '<span class="opacitymedium">' . $langs->trans('StatusDraft') . '</span>') . '</td></tr>';

    print '<tr><td class="fieldrequired">' . $langs->trans('JAVDate') . '</td><td>'
        . $form->select_date($obj->date_jav ? $obj->date_jav : -1, 'date_jav', 0, 0, 1, '', 1, 0) . '</td>';
    print '<td>' . $langs->trans('DAVDevise') . '</td><td>'
        . '<input type="text" size="8" name="devise" value="' . dol_escape_htmltag($obj->devise ?: 'CDF') . '"></td></tr>';

    print '<tr><td class="fieldrequired" colspan="1">' . $langs->trans('FieldObjet') . '</td><td colspan="3">'
        . '<input type="text" size="100" name="objet" value="' . dol_escape_htmltag($obj->objet) . '"></td></tr>';

    print '<tr><td>' . $langs->trans('JAVFromDAV') . ' (optionnel)</td><td>';
    $davOpts = '<select class="flat" name="fk_demande_avance"><option value="0">-- '.$langs->trans('None').' --</option>';
    $resDAV = $db->query("SELECT rowid, ref, objet FROM ".MAIN_DB_PREFIX."apclogistics_demandeavance WHERE status >= 0 ORDER BY date_creation DESC LIMIT 100");
    while ($o = $db->fetch_object($resDAV)) {
        $sel = ($obj->fk_demande_avance == $o->rowid) ? ' selected' : '';
        $davOpts .= '<option value="'.$o->rowid.'"'.$sel.'>'.$o->ref.' — '.dol_escape_htmltag($o->objet).'</option>';
    }
    $davOpts .= '</select>';
    print $davOpts;
    print '</td>';
    print '<td>' . $langs->trans('JAVPriseAvance') . '</td><td>'
        . '<input type="text" size="18" name="prise_avance" value="' . dol_escape_htmltag($obj->prise_avance ? $obj->prise_avance : '0') . '"> <span class="opacitymedium">CDF</span></td></tr>';

    print '<tr><td class="tdtop">' . $langs->trans('FieldNotes') . '</td><td colspan="3">'
        . '<textarea rows="3" cols="100" name="note_public">' . dol_escape_htmltag($obj->note_public) . '</textarea></td></tr>';
    print '</table>';

    print '<h3 style="margin-top:18px;">' . $langs->trans('JAVColDepense') . 's — ' . $langs->trans('JAVJustificatifs') . '</h3>';
    print '<table class="noborder centpercent" id="apc-jav-lines-table">';
    print '<thead><tr class="liste_titre">';
    print '<th class="width40" style="width:40px;">N°</th>';
    print '<th style="min-width:200px;">' . $langs->trans('JAVColDepense') . ' *</th>';
    print '<th>' . $langs->trans('JAVColProjet') . '</th>';
    print '<th>' . $langs->trans('JAVColBudget') . '</th>';
    print '<th>' . $langs->trans('JAVColCompte') . '</th>';
    print '<th>' . $langs->trans('JAVColRefPiece') . '</th>';
    print '<th style="width:120px;" class="right">' . $langs->trans('JAVColMontant') . '</th>';
    print '<th style="width:40px;">&nbsp;</th>';
    print '</tr></thead>';
    print '<tbody id="apc-jav-lines-body">';

    $lines = $editing && !empty($obj->lines) ? $obj->lines : array((object)array());
    $idx = 0;
    foreach ($lines as $ln) {
        $dep = isset($ln->depense_label) ? $ln->depense_label : '';
        $prj = isset($ln->projet) ? $ln->projet : '';
        $bud = isset($ln->budget) ? $ln->budget : '';
        $cmp = isset($ln->compte) ? $ln->compte : '';
        $refp = isset($ln->ref_piece_justificative) ? $ln->ref_piece_justificative : '';
        $mnt = isset($ln->montant) ? (float)$ln->montant : '';
        print '<tr class="apc-line-row">';
        print '<td class="apc-jav-no center">' . ($idx+1) . '</td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][depense_label]" value="' . dol_escape_htmltag($dep) . '"></td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][projet]" value="' . dol_escape_htmltag($prj) . '"></td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][budget]" value="' . dol_escape_htmltag($bud) . '"></td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][compte]" value="' . dol_escape_htmltag($cmp) . '"></td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][ref_piece_justificative]" value="' . dol_escape_htmltag($refp) . '"></td>';
        print '<td><input type="text" class="flat width100 right apc-line-montant" name="lines[' . $idx . '][montant]" value="' . ($mnt !== '' ? dol_escape_htmltag($mnt) : '') . '"></td>';
        print '<td><button type="button" class="button apc-rm-line" style="padding:2px 6px;">-</button></td>';
        print '</tr>';
        $idx++;
    }
    print '</tbody>';
    print '<tfoot><tr class="liste_total"><td colspan="6" class="right"><b>' . $langs->trans('JAVTotalDepense') . '</b></td>'
        . '<td class="right apc-money" id="apc-jav-total">0,00</td><td>&nbsp;</td></tr></tfoot>';
    print '</table>';

    print '<div style="margin-top:10px;">';
    print '<button type="button" id="apc-add-line" class="button small">+ Ajouter une ligne de dépense</button>';
    print '</div>';

    print '<div class="tabsAction" style="margin-top:24px;">';
    print '<input type="submit" class="button" value="' . ($editing ? $langs->trans('Save') : $langs->trans('Create')) . '">';
    print ' <a class="button button-cancel" href="' . DOL_URL_ROOT . '/custom/apclogistics/justifavance_list.php">' . $langs->trans('Cancel') . '</a>';
    print '</div>';
    print '</form>';

    print '<script type="text/javascript">
    $(document).ready(function() {
        function calcTotal() {
            var sum = 0;
            $(".apc-line-montant").each(function(){
                var v = parseFloat($(this).val().replace(/\s/g,"").replace(",","."));
                if (!isNaN(v)) sum += v;
            });
            $("#apc-jav-total").text(APCNumFmt(sum));
        }
        function APCNumFmt(n){ n = Number(n||0).toFixed(2); var p = n.split("."); p[0]=p[0].replace(/\B(?=(\d{3})+(?!\d))/g," "); return p.join(","); }
        function renumber() { $(".apc-jav-no").each(function(i){ $(this).text(i+1); }); }
        $(document).on("input", ".apc-line-montant", calcTotal);
        $("#apc-add-line").on("click", function() {
            var n = $("#apc-jav-lines-body tr").length;
            var html = "<tr class=\"apc-line-row\">"
                + "<td class=\"apc-jav-no center\">"+(n+1)+"</td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][depense_label]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][projet]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][budget]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][compte]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][ref_piece_justificative]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100 right apc-line-montant\" name=\"lines["+n+"][montant]\"></td>"
                + "<td><button type=\"button\" class=\"button apc-rm-line\" style=\"padding:2px 6px;\">-</button></td>"
                + "</tr>";
            $("#apc-jav-lines-body").append(html);
            renumber(); calcTotal();
        });
        $(document).on("click", ".apc-rm-line", function() {
            if ($("#apc-jav-lines-body tr").length > 1) { $(this).closest("tr").remove(); renumber(); calcTotal(); }
        });
        calcTotal();
    });
    </script>';
} else {
    $formconfirm = '';
    if ($action === 'delete' && $permissiontodelete) {
        $formconfirm = $form->formconfirm(
            $_SERVER["PHP_SELF"] . '?id=' . $object->id,
            $langs->trans('Delete'),
            $langs->trans('ConfirmDeleteObject'),
            'confirm_delete',
            '',
            0, 1
        );
    }

    print '<table class="border centpercent">';
    print '<tr><td class="titlefield">' . $langs->trans('JAVRef') . '</td><td class="valeur"><b>' . $object->ref . '</b></td>'
        . '<td class="titlefield">' . $langs->trans('FieldStatus') . '</td><td class="valeur">' . $object->getStatusBadge() . '</td></tr>';
    print '<tr><td>' . $langs->trans('JAVDate') . '</td><td>' . dol_print_date($db->jdate($object->date_jav), 'day') . '</td>'
        . '<td>' . $langs->trans('DAVDevise') . '</td><td>' . dol_escape_htmltag($object->devise ?: 'CDF') . '</td></tr>';
    print '<tr><td class="tdtop" colspan="1">' . $langs->trans('FieldObjet') . '</td><td colspan="3">' . dol_escape_htmltag($object->objet) . '</td></tr>';
    if ($linkedDAV && $linkedDAV->id > 0) {
        print '<tr><td>' . $langs->trans('JAVFromDAV') . '</td><td>' . $linkedDAV->getNomUrl(1) . '</td>';
        print '<td>' . $langs->trans('JAVPriseAvance') . '</td><td class="apc-money right">' . price((float)$object->prise_avance, 0, $langs, 0, 0, -1, $object->devise ?: $conf->currency) . '</td></tr>';
    }
    if (!empty($object->note_public)) print '<tr><td class="tdtop">' . $langs->trans('FieldNotes') . '</td><td colspan="3">' . dol_escape_htmltag($object->note_public) . '</td></tr>';
    print '</table>';

    $object->fetchLines();
    $totDep = (float)$object->total_depense;
    $prise  = (float)$object->prise_avance;
    $ecart  = (float)$object->ecart;
    $sens   = (int)$object->sens_ecart;
    print '<h3 style="margin-top:18px;">' . $langs->trans('JAVBlocSynthese') . '</h3>';
    print '<table class="noborder centpercent" style="max-width:600px;">';
    print '<tr class="oddeven"><td style="width:60%; font-weight:bold;">' . $langs->trans('JAVTotalDepense') . '</td>'
        . '<td class="right apc-money" style="width:40%;">' . price($totDep, 0, $langs, 0, 0, -1, $object->devise ?: $conf->currency) . '</td></tr>';
    print '<tr class="oddeven"><td style="font-weight:bold;">' . $langs->trans('JAVPriseAvance') . '</td>'
        . '<td class="right apc-money">' . price($prise, 0, $langs, 0, 0, -1, $object->devise ?: $conf->currency) . '</td></tr>';
    $trCls = ($sens < 0) ? 'apc-money-negative' : (($sens > 0) ? 'apc-money-positive' : '');
    $lblEcart = ($sens < 0) ? $langs->trans('JAVEcartNegLabel') : (($sens > 0) ? $langs->trans('JAVEcartPosLabel') : $langs->trans('JAVEcartZeroLabel'));
    print '<tr class="liste_total ' . $trCls . '"><td style="font-weight:bold;">' . $langs->trans('JAVEcart') . ' — ' . $lblEcart . '</td>'
        . '<td class="right apc-money" style="font-weight:bold;">' . price($ecart, 0, $langs, 0, 0, -1, $object->devise ?: $conf->currency) . '</td></tr>';
    print '</table>';

    print '<h3 style="margin-top:18px;">' . $langs->trans('JAVColDepense') . 's — Détail justificatifs</h3>';
    print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
    print '<thead><tr class="liste_titre">';
    print '<th class="center width40">N°</th>';
    print '<th>' . $langs->trans('JAVColDepense') . '</th>';
    print '<th>' . $langs->trans('JAVColProjet') . '</th>';
    print '<th>' . $langs->trans('JAVColBudget') . '</th>';
    print '<th>' . $langs->trans('JAVColCompte') . '</th>';
    print '<th>' . $langs->trans('JAVColRefPiece') . '</th>';
    print '<th class="right" style="width:140px;">' . $langs->trans('JAVColMontant') . '</th>';
    print '</tr></thead><tbody>';
    $i = 1;
    foreach ($object->lines as $ln) {
        print '<tr class="oddeven"><td class="center">' . $i++ . '</td>';
        print '<td>' . dol_escape_htmltag($ln->depense_label) . '</td>';
        print '<td>' . dol_escape_htmltag($ln->projet) . '</td>';
        print '<td>' . dol_escape_htmltag($ln->budget) . '</td>';
        print '<td class="center">' . dol_escape_htmltag($ln->compte) . '</td>';
        print '<td>' . dol_escape_htmltag($ln->ref_piece_justificative) . '</td>';
        print '<td class="apc-money right">' . price((float)$ln->montant, 0, $langs, 0, 0, -1, $object->devise ?: $conf->currency) . '</td>';
        print '</tr>';
    }
    if (empty($object->lines)) print '<tr class="oddeven"><td colspan="7" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
    print '</tbody><tfoot><tr class="liste_total"><td colspan="6" class="right"><b>' . $langs->trans('JAVTotalDepense') . '</b></td>'
        . '<td class="right apc-money">' . price($totDep, 0, $langs, 0, 0, -1, $object->devise ?: $conf->currency) . '</td></tr></tfoot>';
    print '</table></div>';

    if ($permissiontovalidate && (int)$object->status < ApcJustifAvance::STATUS_VALIDATED) {
        print '<h3 style="margin-top:18px;">' . $langs->trans('AUDSignature') . '</h3>';
        foreach (array(0=>'JAVJustif', 1=>'JAVVerificateur', 2=>'JAVApprobateur') as $lvl => $key) {
            $signed = false;
            if ($lvl === 0) $signed = !empty($object->date_signature_justif);
            if ($lvl === 1) $signed = !empty($object->date_signature_verif);
            if ($lvl === 2) $signed = !empty($object->date_signature_approb);
            if (!$signed) {
                print '<div style="margin:10px 0; padding:10px; border:1px dashed #ccc; border-radius:6px;">';
                print '<form action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '" method="POST">';
                print '<input type="hidden" name="token" value="' . newToken() . '">';
                print '<input type="hidden" name="action" value="sign">';
                print '<input type="hidden" name="level" value="' . $lvl . '">';
                print '<b>Signer en tant que : ' . $langs->trans($key) . ' (étape '.($lvl+1).'/3)</b> — ';
                print 'Nom <input type="text" name="nom_sig" size="28" value="' . dol_escape_htmltag($user->getFullName($langs)) . '"> ';
                print 'Fonction <input type="text" name="fct_sig" size="22" value="' . dol_escape_htmltag($user->poste) . '"> ';
                print '<input type="submit" class="button" value="Signer">';
                print '</form></div>';
            }
        }
    }

    print '<div class="tabsAction" style="margin-top:24px;">';
    if ($id > 0) print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/justifavance_card.php?id=' . $object->id . '&action=generate_pdf&token=' . newToken() . '">' . $langs->trans('PDFGenerateBtn') . '</a>';
    if ($permissiontoedit && (int)$object->status < ApcJustifAvance::STATUS_VALIDATED) print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/justifavance_card.php?id=' . $object->id . '&action=edit">' . $langs->trans('Modify') . '</a>';
    if ($permissiontodelete) print '<a class="butActionDelete" href="' . DOL_URL_ROOT . '/custom/apclogistics/justifavance_card.php?id=' . $object->id . '&action=delete">' . $langs->trans('Delete') . '</a>';
    print '</div>';
}

dol_fiche_end();

if ($action === 'links' && $object->id > 0) {
    apcPrintLinksTab($db, $langs, $conf, $object);
}

if ($action === 'audit' && $object->id > 0) {
    $audits = ApcAuditLog::fetchForEntity($db, 'jav', $object->id, 200);
    print '<h3 style="margin-top:12px;">' . $langs->trans('TabHistory') . '</h3>';
    print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
    print '<tr class="liste_titre"><th>' . $langs->trans('AUDColDate') . '</th><th>' . $langs->trans('AUDColUser') . '</th>'
        . '<th>' . $langs->trans('AUDColAction') . '</th><th>' . $langs->trans('AUDColIp') . '</th>'
        . '<th>' . $langs->trans('AUDColDetails') . '</th></tr>';
    foreach ($audits as $a) {
        print '<tr class="oddeven">';
        print '<td>' . dol_print_date($db->jdate($a->date_action), 'dayhour') . '</td>';
        print '<td>' . dol_escape_htmltag($a->user_login) . ' (id ' . (int)$a->fk_user . ')</td>';
        print '<td>' . ApcAuditLog::formatAction($a->action_type) . '</td>';
        print '<td>' . dol_escape_htmltag($a->ip_address) . '</td>';
        print '<td>' . dol_escape_htmltag($a->action_details) . '</td>';
        print '</tr>';
    }
    if (empty($audits)) print '<tr><td colspan="5" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
    print '</table></div>';
}

print $formconfirm;

llxFooter();
$db->close();
