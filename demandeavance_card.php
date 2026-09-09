<?php
if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
$res = 0; if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php"; if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php"; if (!$res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/class/ApcDemandeAvance.class.php';
require_once __DIR__ . '/class/ApcJustifAvance.class.php';
require_once __DIR__ . '/class/apc_links.lib.php';
require_once __DIR__ . '/class/pdf/pdf_demandeavance_apc.modules.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

$id      = (int)GETPOST('id', 'int');
$ref     = GETPOST('ref', 'alpha');
$action  = GETPOST('action', 'aZ');
$confirm = GETPOST('confirm', 'alpha');
$token   = GETPOST('token', 'alpha');

$object = new ApcDemandeAvance($db);
$extrafields = new ExtraFields($db);
$extralabels = $extrafields->fetch_name_optionals_label('apclogistics_demandeavance');

if ($id > 0 || $ref) {
    $res = ($id > 0) ? $object->fetch($id) : $object->fetch(0, $ref);
    if ($res <= 0) { dol_print_error('', 'ErrorLoadFailed'); exit; }
    $object->fetchLines();
}

$permissiontoread     = !empty($user->rights->apclogistics->demandeavance->read);
$permissiontocreate   = !empty($user->rights->apclogistics->demandeavance->create);
$permissiontoedit     = !empty($user->rights->apclogistics->demandeavance->edit);
$permissiontodelete   = !empty($user->rights->apclogistics->demandeavance->delete);
$permissiontovalidate = !empty($user->rights->apclogistics->demandeavance->validate);
$permissiontojav      = !empty($user->rights->apclogistics->justifavance->create);

if ($action === 'create' && !$permissiontocreate) accessforbidden();
if (empty($permissiontoread) && $action !== 'create') accessforbidden();

$error = 0;
$backtopage = DOL_URL_ROOT . '/custom/apclogistics/demandeavance_list.php';
$form = new Form($db);
$formother = new FormOther($db);

if ($action === 'add' && $permissiontocreate && !$error && $user->valid && $token && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dd = dol_mktime(12,0,0, GETPOST('date_davmonth', 'int'), GETPOST('date_davday', 'int'), GETPOST('date_davyear', 'int'));
    $object->date_dav       = $db->idate($dd);
    $object->objet          = GETPOST('objet', 'alphanohtml');
    $object->compte         = GETPOST('compte', 'alphanohtml');
    $object->mode_paiement  = (int)GETPOST('mode_paiement', 'int');
    $object->coord_banque_nom    = GETPOST('coord_banque_nom', 'alphanohtml');
    $object->coord_banque_iban   = GETPOST('coord_banque_iban', 'alphanohtml');
    $object->coord_banque_banque = GETPOST('coord_banque_banque', 'alphanohtml');
    $object->coord_banque_swift  = GETPOST('coord_banque_swift', 'alphanohtml');
    $object->devise         = GETPOST('devise', 'alpha') ?: 'CDF';
    $object->note_public    = GETPOST('note_public', 'alphanohtml');

    if (empty($object->objet)) { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('FieldObjet')), null, 'errors'); $error++; }
    if (empty($object->date_dav) || $object->date_dav === '1970-01-01') { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('DAVDate')), null, 'errors'); $error++; }

    if (!$error) {
        $res = $object->create($user);
        if ($res > 0) {
            $lignes = GETPOST('lines', 'array');
            $no = 1;
            if (is_array($lignes)) {
                foreach ($lignes as $line) {
                    if (empty($line['depense_label']) && (float)$line['montant'] <= 0) continue;
                    $ln = new ApcDemandeAvanceLine($db);
                    $ln->fk_demandeavance = $object->id;
                    $ln->no_ligne         = $no++;
                    $ln->depense_label    = $line['depense_label'];
                    $ln->projet           = $line['projet'];
                    $ln->budget           = $line['budget'];
                    $ln->montant          = (float)str_replace(',', '.', $line['montant']);
                    $ln->create($user);
                }
            }
            $object->calculateTotals();
            $object->update($user);
            header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/demandeavance_card.php?id=' . $object->id);
            exit;
        } else { setEventMessages($object->error, $object->errors, 'errors'); }
    }
}

if ($action === 'update' && $permissiontoedit && !$error && $user->valid && $token) {
    $dd = dol_mktime(12,0,0, GETPOST('date_davmonth', 'int'), GETPOST('date_davday', 'int'), GETPOST('date_davyear', 'int'));
    $object->date_dav       = $db->idate($dd);
    $object->objet          = GETPOST('objet', 'alphanohtml');
    $object->compte         = GETPOST('compte', 'alphanohtml');
    $object->mode_paiement  = (int)GETPOST('mode_paiement', 'int');
    $object->coord_banque_nom    = GETPOST('coord_banque_nom', 'alphanohtml');
    $object->coord_banque_iban   = GETPOST('coord_banque_iban', 'alphanohtml');
    $object->coord_banque_banque = GETPOST('coord_banque_banque', 'alphanohtml');
    $object->coord_banque_swift  = GETPOST('coord_banque_swift', 'alphanohtml');
    $object->devise         = GETPOST('devise', 'alpha') ?: 'CDF';
    $object->note_public    = GETPOST('note_public', 'alphanohtml');
    $res = $object->update($user);
    if ($res > 0) {
        $ln = new ApcDemandeAvanceLine($db);
        $ln->deleteAllForParent($user, $object->id);
        $lignes = GETPOST('lines', 'array');
        $no = 1;
        if (is_array($lignes)) {
            foreach ($lignes as $line) {
                if (empty($line['depense_label']) && (float)$line['montant'] <= 0) continue;
                $nl = new ApcDemandeAvanceLine($db);
                $nl->fk_demandeavance = $object->id;
                $nl->no_ligne         = $no++;
                $nl->depense_label    = $line['depense_label'];
                $nl->projet           = $line['projet'];
                $nl->budget           = $line['budget'];
                $nl->montant          = (float)str_replace(',', '.', $line['montant']);
                $nl->create($user);
            }
        }
        $object->calculateTotals();
        $object->update($user);
        header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/demandeavance_card.php?id=' . $object->id);
        exit;
    } else { setEventMessages($object->error, $object->errors, 'errors'); }
}

if ($action === 'confirm_delete' && $confirm === 'yes' && $permissiontodelete && $token) {
    $res = $object->delete($user);
    if ($res > 0) { header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/demandeavance_list.php'); exit; }
    else setEventMessages($object->error, $object->errors, 'errors');
}

if ($action === 'generate_pdf' && $id > 0) {
    $g = new pdf_demandeavance_apc($db);
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
    header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/demandeavance_card.php?id=' . $object->id);
    exit;
}

if ($action === 'create_justif' && $permissiontojav && $id > 0 && $token) {
    $jav = ApcJustifAvance::createFromDemandeAvance($object, $user);
    if ($jav && $jav->id > 0) {
        setEventMessages($langs->trans('JAVFromDAVOK') . ' — ' . $jav->ref, null, 'mesgs');
        header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/justifavance_card.php?id=' . $jav->id);
        exit;
    } else {
        setEventMessages($jav ? $jav->error : 'Erreur creation JAV', null, 'errors');
    }
}

$title = ($action === 'create' ? $langs->trans('NewDAV') : ($object->ref ?: $langs->trans('DAVTitle')));
llxHeader('', $title, '', '', 0, 0, array('/custom/apclogistics/js/apclogistics.js'), array('/custom/apclogistics/css/apclogistics.css'));

$head = array();
$head[0][0] = DOL_URL_ROOT . '/custom/apclogistics/demandeavance_card.php?id=' . ($object->id ?: 0) . '&action=' . ($action ?: 'view');
$head[0][1] = $langs->trans('TabCard');
$head[0][2] = 'tabcard';
$head[1][0] = DOL_URL_ROOT . '/custom/apclogistics/demandeavance_card.php?id=' . ($object->id ?: 0) . '&action=links';
$head[1][1] = $langs->trans('TabLinks');
$head[1][2] = 'tablinks';
$head[2][0] = DOL_URL_ROOT . '/custom/apclogistics/demandeavance_card.php?id=' . ($object->id ?: 0) . '&action=audit';
$head[2][1] = $langs->trans('TabHistory');
$head[2][2] = 'tabhistory';

$picto = 'apclogistics@apclogistics';
dol_fiche_head($head, 'tabcard', $title, -1, $picto);

if ($action === 'create' || $action === 'edit') {
    $editing = ($action === 'edit');
    $obj =& $object;
    if ($editing) $obj->fetchLines();
    $hiddentoken = '<input type="hidden" name="token" value="' . newToken() . '">';
    $act = ($editing ? 'update' : 'add');

    print '<form action="' . $_SERVER["PHP_SELF"] . ($editing ? '?id=' . $obj->id : '') . '" method="POST">';
    print $hiddentoken;
    print '<input type="hidden" name="action" value="' . $act . '">';

    print '<table class="border centpercent">';
    print '<tr><td class="titlefieldcreate fieldrequired">' . $langs->trans('DAVRef') . '</td><td>';
    if ($editing) print $obj->ref; else print '<span class="opacitymedium">(Généré automatiquement)</span>';
    print '</td>';
    print '<td class="titlefield">' . $langs->trans('FieldStatus') . '</td><td>'
        . ($editing ? $obj->getStatusBadge() : '<span class="opacitymedium">' . $langs->trans('StatusDraft') . '</span>') . '</td></tr>';

    print '<tr><td class="fieldrequired">' . $langs->trans('DAVDate') . '</td><td>'
        . $form->select_date($obj->date_dav ? $obj->date_dav : -1, 'date_dav', 0, 0, 1, '', 1, 0) . '</td>';
    print '<td>' . $langs->trans('DAVDevise') . '</td><td>'
        . '<input type="text" size="8" name="devise" value="' . dol_escape_htmltag($obj->devise ?: 'CDF') . '"></td></tr>';

    print '<tr><td class="fieldrequired" colspan="1">' . $langs->trans('FieldObjet') . '</td><td colspan="3">'
        . '<input type="text" size="100" name="objet" value="' . dol_escape_htmltag($obj->objet) . '"></td></tr>';

    print '<tr><td>' . $langs->trans('DAVCompte') . '</td><td>'
        . '<input type="text" size="40" name="compte" value="' . dol_escape_htmltag($obj->compte) . '"></td>';
    print '<td class="fieldrequired">' . $langs->trans('DAVModePaiement') . '</td><td>';
    $mp = (int)($obj->mode_paiement ? $obj->mode_paiement : 2);
    print '<label><input type="radio" name="mode_paiement" value="1"' . ($mp === 1 ? ' checked' : '') . '> ' . $langs->trans('DAVModeCaisse') . '</label> &nbsp;&nbsp; ';
    print '<label><input type="radio" name="mode_paiement" value="2"' . ($mp === 2 ? ' checked' : '') . '> ' . $langs->trans('DAVModeBanque') . '</label>';
    print '</td></tr>';

    print '<tr><td colspan="4" class="titlefield" style="padding-top:10px;"><b>' . $langs->trans('DAVCoordBancaires') . '</b> <span class="opacitymedium">(' . $langs->trans('DAVCoordBancairesDesc') . ')</span></td></tr>';
    print '<tr><td>' . $langs->trans('DAVCoordNom') . '</td><td>'
        . '<input type="text" size="45" name="coord_banque_nom" value="' . dol_escape_htmltag($obj->coord_banque_nom) . '"></td>';
    print '<td>' . $langs->trans('DAVCoordBanque') . '</td><td>'
        . '<input type="text" size="45" name="coord_banque_banque" value="' . dol_escape_htmltag($obj->coord_banque_banque) . '"></td></tr>';
    print '<tr><td>' . $langs->trans('DAVCoordIBAN') . '</td><td>'
        . '<input type="text" size="45" name="coord_banque_iban" value="' . dol_escape_htmltag($obj->coord_banque_iban) . '"></td>';
    print '<td>' . $langs->trans('DAVCoordSWIFT') . '</td><td>'
        . '<input type="text" size="30" name="coord_banque_swift" value="' . dol_escape_htmltag($obj->coord_banque_swift) . '"></td></tr>';

    print '<tr><td class="tdtop">' . $langs->trans('FieldNotes') . '</td><td colspan="3">'
        . '<textarea rows="3" cols="100" name="note_public">' . dol_escape_htmltag($obj->note_public) . '</textarea></td></tr>';
    print '</table>';

    print '<h3 style="margin-top:18px;">' . $langs->trans('DAVColDepense') . 's</h3>';
    print '<table class="noborder centpercent" id="apc-dav-lines-table">';
    print '<thead><tr class="liste_titre">';
    print '<th class="width40" style="width:40px;">N°</th>';
    print '<th style="min-width:240px;">' . $langs->trans('DAVColDepense') . ' *</th>';
    print '<th>' . $langs->trans('DAVColProjet') . '</th>';
    print '<th>' . $langs->trans('DAVColBudget') . '</th>';
    print '<th style="width:160px;" class="right">' . $langs->trans('DAVColMontant') . '</th>';
    print '<th style="width:40px;">&nbsp;</th>';
    print '</tr></thead>';
    print '<tbody id="apc-dav-lines-body">';

    $lines = $editing && !empty($obj->lines) ? $obj->lines : array((object)array());
    $idx = 0;
    foreach ($lines as $ln) {
        $dep = isset($ln->depense_label) ? $ln->depense_label : '';
        $prj = isset($ln->projet) ? $ln->projet : '';
        $bud = isset($ln->budget) ? $ln->budget : '';
        $mnt = isset($ln->montant) ? (float)$ln->montant : '';
        print '<tr class="apc-line-row">';
        print '<td class="apc-dav-no center">' . ($idx+1) . '</td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][depense_label]" value="' . dol_escape_htmltag($dep) . '"></td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][projet]" value="' . dol_escape_htmltag($prj) . '"></td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][budget]" value="' . dol_escape_htmltag($bud) . '"></td>';
        print '<td><input type="text" class="flat width100 right apc-line-montant" name="lines[' . $idx . '][montant]" value="' . ($mnt !== '' ? dol_escape_htmltag($mnt) : '') . '"></td>';
        print '<td><button type="button" class="button apc-rm-line" style="padding:2px 6px;">-</button></td>';
        print '</tr>';
        $idx++;
    }
    print '</tbody>';
    print '<tfoot><tr class="liste_total"><td colspan="4" class="right"><b>' . $langs->trans('DAVGeneralTotal') . '</b></td>'
        . '<td class="right apc-money" id="apc-dav-total">0,00</td><td>&nbsp;</td></tr></tfoot>';
    print '</table>';

    print '<div style="margin-top:10px;">';
    print '<button type="button" id="apc-add-line" class="button small">+ Ajouter une ligne</button>';
    print '</div>';

    print '<div class="tabsAction" style="margin-top:24px;">';
    print '<input type="submit" class="button" value="' . ($editing ? $langs->trans('Save') : $langs->trans('Create')) . '">';
    print ' <a class="button button-cancel" href="' . DOL_URL_ROOT . '/custom/apclogistics/demandeavance_list.php">' . $langs->trans('Cancel') . '</a>';
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
            $("#apc-dav-total").text(APCNumFmt(sum));
        }
        function APCNumFmt(n){ n = Number(n||0).toFixed(2); var p = n.split("."); p[0]=p[0].replace(/\B(?=(\d{3})+(?!\d))/g," "); return p.join(","); }
        function renumber() { $(".apc-dav-no").each(function(i){ $(this).text(i+1); }); }
        $(document).on("input", ".apc-line-montant", calcTotal);
        $("#apc-add-line").on("click", function() {
            var n = $("#apc-dav-lines-body tr").length;
            var html = "<tr class=\"apc-line-row\">"
                + "<td class=\"apc-dav-no center\">"+(n+1)+"</td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][depense_label]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][projet]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][budget]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100 right apc-line-montant\" name=\"lines["+n+"][montant]\"></td>"
                + "<td><button type=\"button\" class=\"button apc-rm-line\" style=\"padding:2px 6px;\">-</button></td>"
                + "</tr>";
            $("#apc-dav-lines-body").append(html);
            renumber(); calcTotal();
        });
        $(document).on("click", ".apc-rm-line", function() {
            if ($("#apc-dav-lines-body tr").length > 1) { $(this).closest("tr").remove(); renumber(); calcTotal(); }
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
    print '<tr><td class="titlefield">' . $langs->trans('DAVRef') . '</td><td class="valeur"><b>' . $object->ref . '</b></td>'
        . '<td class="titlefield">' . $langs->trans('FieldStatus') . '</td><td class="valeur">' . $object->getStatusBadge() . '</td></tr>';
    print '<tr><td>' . $langs->trans('DAVDate') . '</td><td>' . dol_print_date($db->jdate($object->date_dav), 'day') . '</td>'
        . '<td>' . $langs->trans('DAVDevise') . '</td><td>' . dol_escape_htmltag($object->devise ?: 'CDF') . '</td></tr>';
    print '<tr><td class="tdtop" colspan="1">' . $langs->trans('FieldObjet') . '</td><td colspan="3">' . dol_escape_htmltag($object->objet) . '</td></tr>';
    print '<tr><td>' . $langs->trans('DAVCompte') . '</td><td>' . dol_escape_htmltag($object->compte) . '</td>';
    $mpLbl = ((int)$object->mode_paiement === 1) ? $langs->trans('DAVModeCaisse') : $langs->trans('DAVModeBanque');
    print '<td>' . $langs->trans('DAVModePaiement') . '</td><td><b>' . $mpLbl . '</b> (' . (int)$object->mode_paiement . ')</td></tr>';
    if ((int)$object->mode_paiement === 2) {
        print '<tr><td>' . $langs->trans('DAVCoordNom') . '</td><td>' . dol_escape_htmltag($object->coord_banque_nom) . '</td>';
        print '<td>' . $langs->trans('DAVCoordBanque') . '</td><td>' . dol_escape_htmltag($object->coord_banque_banque) . '</td></tr>';
        print '<tr><td>' . $langs->trans('DAVCoordIBAN') . '</td><td>' . dol_escape_htmltag($object->coord_banque_iban) . '</td>';
        print '<td>' . $langs->trans('DAVCoordSWIFT') . '</td><td>' . dol_escape_htmltag($object->coord_banque_swift) . '</td></tr>';
    }
    if (!empty($object->date_decaissement)) {
        print '<tr><td>' . $langs->trans('DAVDateDecaissement') . '</td><td colspan="3">' . dol_print_date($db->jdate($object->date_decaissement), 'day') . '</td></tr>';
    }
    print '<tr><td>' . $langs->trans('DAVDemandeur') . '</td><td>' . dol_escape_htmltag($object->demandeur_nom)
        . (empty($object->date_signature_demandeur) ? '' : ' <span class="opacitymedium">(signé ' . dol_print_date($db->jdate($object->date_signature_demandeur), 'day') . ')</span>')
        . '</td>';
    print '<td>' . $langs->trans('DAVVerificateur') . '</td><td>' . dol_escape_htmltag($object->verif_nom)
        . (empty($object->date_signature_verif) ? '' : ' <span class="opacitymedium">(signé ' . dol_print_date($db->jdate($object->date_signature_verif), 'day') . ')</span>')
        . '</td></tr>';
    print '<tr><td>' . $langs->trans('DAVApprobateur') . '</td><td colspan="3">' . dol_escape_htmltag($object->approb_nom)
        . (empty($object->date_signature_approb) ? '' : ' <span class="opacitymedium">(signé ' . dol_print_date($db->jdate($object->date_signature_approb), 'day') . ')</span>')
        . '</td></tr>';
    if (!empty($object->note_public)) print '<tr><td class="tdtop">' . $langs->trans('FieldNotes') . '</td><td colspan="3">' . dol_escape_htmltag($object->note_public) . '</td></tr>';
    print '</table>';

    $object->fetchLines();
    print '<h3 style="margin-top:18px;">' . $langs->trans('DAVColDepense') . 's</h3>';
    print '<table class="noborder centpercent">';
    print '<thead><tr class="liste_titre">';
    print '<th class="center width40">N°</th>';
    print '<th>' . $langs->trans('DAVColDepense') . '</th>';
    print '<th>' . $langs->trans('DAVColProjet') . '</th>';
    print '<th>' . $langs->trans('DAVColBudget') . '</th>';
    print '<th class="right" style="width:160px;">' . $langs->trans('DAVColMontant') . '</th>';
    print '</tr></thead><tbody>';
    $i = 1; $total = 0;
    foreach ($object->lines as $ln) {
        $total += (float)$ln->montant;
        print '<tr class="oddeven"><td class="center">' . $i++ . '</td>';
        print '<td>' . dol_escape_htmltag($ln->depense_label) . '</td>';
        print '<td>' . dol_escape_htmltag($ln->projet) . '</td>';
        print '<td>' . dol_escape_htmltag($ln->budget) . '</td>';
        print '<td class="apc-money right">' . price((float)$ln->montant, 0, $langs, 0, 0, -1, $object->devise ?: $conf->currency) . '</td>';
        print '</tr>';
    }
    if (empty($object->lines)) print '<tr class="oddeven"><td colspan="5" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
    print '</tbody><tfoot><tr class="liste_total"><td colspan="4" class="right"><b>' . $langs->trans('DAVGeneralTotal') . '</b></td>'
        . '<td class="right apc-money">' . price($total, 0, $langs, 0, 0, -1, $object->devise ?: $conf->currency) . '</td></tr></tfoot>';
    print '</table>';

    if ($permissiontovalidate && (int)$object->status < ApcDemandeAvance::STATUS_VALIDATED) {
        print '<h3 style="margin-top:18px;">' . $langs->trans('AUDSignature') . '</h3>';
        foreach (array(0=>'DAVDemandeur', 1=>'DAVVerificateur', 2=>'DAVApprobateur') as $lvl => $key) {
            $signed = false;
            if ($lvl === 0) $signed = !empty($object->date_signature_demandeur);
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

    if ($id > 0 && $permissiontojav && (int)$object->status >= ApcDemandeAvance::STATUS_VALIDATED) {
        $justifs = $object->fetchJustifs();
        print '<h3 style="margin-top:18px;">' . $langs->trans('DAVJustifsLies') . '</h3>';
        if (empty($justifs)) {
            print '<div style="padding:10px; border:1px dashed #2E7D32; border-radius:6px; background:#f5faf5;">';
            print '<form action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '" method="POST">';
            print '<input type="hidden" name="token" value="' . newToken() . '">';
            print '<input type="hidden" name="action" value="create_justif">';
            print '<small class="opacitymedium">Aucune justification saisie. Créer automatiquement une justification pré-remplie depuis cette Demande d\'Avance.</small><br>';
            print '<input type="submit" class="button" value="' . $langs->trans('DAVCreateJAV') . '">';
            print '</form></div>';
        } else {
            print '<table class="noborder centpercent">';
            print '<tr class="liste_titre"><th>Ref JAV</th><th>Date</th><th>Total Dépense</th><th>Prise Avance</th><th>Écart</th><th>Statut</th></tr>';
            foreach ($justifs as $j) {
                $jav = new ApcJustifAvance($db);
                $jav->fetch($j->rowid);
                print '<tr class="oddeven"><td>' . $jav->getNomUrl(1) . '</td>';
                print '<td>' . dol_print_date($db->jdate($j->date_jav), 'day') . '</td>';
                print '<td class="right apc-money">' . price((float)$j->total_depense, 0, $langs, 0, 0, -1, $conf->currency) . '</td>';
                print '<td class="right apc-money">' . price((float)$j->prise_avance, 0, $langs, 0, 0, -1, $conf->currency) . '</td>';
                $ecartCls = ($j->ecart < 0) ? 'apc-money-negative' : (($j->ecart > 0) ? 'apc-money-positive' : '');
                print '<td class="right ' . $ecartCls . '">' . price((float)$j->ecart, 0, $langs, 0, 0, -1, $conf->currency) . '</td>';
                print '<td>' . $jav->getStatusBadge() . '</td></tr>';
            }
            print '</table>';
        }
    }

    print '<div class="tabsAction" style="margin-top:24px;">';
    if ($id > 0) print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/demandeavance_card.php?id=' . $object->id . '&action=generate_pdf&token=' . newToken() . '">' . $langs->trans('PDFGenerateBtn') . '</a>';
    if ($permissiontoedit && (int)$object->status < ApcDemandeAvance::STATUS_VALIDATED) print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/demandeavance_card.php?id=' . $object->id . '&action=edit">' . $langs->trans('Modify') . '</a>';
    if ($permissiontodelete) print '<a class="butActionDelete" href="' . DOL_URL_ROOT . '/custom/apclogistics/demandeavance_card.php?id=' . $object->id . '&action=delete">' . $langs->trans('Delete') . '</a>';
    print '</div>';
}

dol_fiche_end();

if ($action === 'links' && $object->id > 0) {
    apcPrintLinksTab($db, $langs, $conf, $object);
}

if ($action === 'audit' && $object->id > 0) {
    $audits = ApcAuditLog::fetchForEntity($db, 'dav', $object->id, 200);
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
