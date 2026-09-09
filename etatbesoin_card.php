<?php

/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * etatbesoin_card.php — Fiche Etat de Besoin : création/édition/suppression + onglets
 * Onglets : Fiche (champs + lignes) | Lignes (édition) | Liens | Historique/Audit | PDF
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
$res = 0; if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php"; if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php"; if (!$res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/class/ApcEtatBesoin.class.php';
require_once __DIR__ . '/class/apc_links.lib.php';
require_once __DIR__ . '/class/pdf/pdf_etatbesoin_apc.modules.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

$id = (int)GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ');
$confirm = GETPOST('confirm', 'alpha');
$token = GETPOST('token', 'alpha');

$object = new ApcEtatBesoin($db);
$extrafields = new ExtraFields($db);
$extralabels = $extrafields->fetch_name_optionals_label('apclogistics_etatbesoin');

if ($id > 0 || $ref) {
    $res = ($id > 0) ? $object->fetch($id) : $object->fetch(0, $ref);
    if ($res <= 0) { dol_print_error('', 'ErrorLoadFailed'); exit; }
    $object->fetchLines();
}

$permissiontoread   = !empty($user->rights->apclogistics->etatbesoin->read);
$permissiontocreate = !empty($user->rights->apclogistics->etatbesoin->create);
$permissiontoedit   = !empty($user->rights->apclogistics->etatbesoin->edit);
$permissiontodelete = !empty($user->rights->apclogistics->etatbesoin->delete);
$permissiontovalidate = !empty($user->rights->apclogistics->etatbesoin->validate);

if ($action === 'create' && !$permissiontocreate) accessforbidden();
if (empty($permissiontoread) && $action !== 'create') accessforbidden();

$error = 0;
$backtopage = DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_list.php';

/*
 * Actions
 */
if ($action === 'add' && $permissiontocreate && !$error && $user->valid && $token && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($conf->dol_url_root)) $backtopage = $conf->dol_url_root . '/custom/apclogistics/etatbesoin_list.php';
    $object->date_eb = dol_mktime(12,0,0, GETPOST('date_ebmonth', 'int'), GETPOST('date_ebday', 'int'), GETPOST('date_ebyear', 'int'));
    $object->date_eb = $db->idate($object->date_eb);
    $object->objet = GETPOST('objet', 'alphanohtml');
    $object->signataire_nom_d = GETPOST('signataire_nom_d', 'alphanohtml');
    $object->signataire_fonction_d = GETPOST('signataire_fonction_d', 'alphanohtml');
    $object->note_public = GETPOST('note_public', 'alphanohtml');

    if (empty($object->objet)) { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('FieldObjet')), null, 'errors'); $error++; }
    if (empty($object->date_eb) || $object->date_eb === '1970-01-01') { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('EBDate')), null, 'errors'); $error++; }

    if (!$error) {
        $res = $object->create($user);
        if ($res > 0) {
            // Traitement lignes
            $lignes = GETPOST('lines', 'array');
            $no = 1;
            if (is_array($lignes)) {
                foreach ($lignes as $line) {
                    if (empty($line['depense'])) continue;
                    $ln = new ApcEtatBesoinLine($db);
                    $ln->fk_etatbesoin = $object->id;
                    $ln->no_ligne = $no++;
                    $ln->depense = $line['depense'];
                    $ln->projet_or_budget = $line['projet_or_budget'];
                    $ln->compte = $line['compte'];
                    $ln->montant = (float)str_replace(',', '.', $line['montant']);
                    $ln->create($user);
                }
            }
            header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id);
            exit;
        } else { setEventMessages($object->error, $object->errors, 'errors'); }
    }
}

if ($action === 'update' && $permissiontoedit && !$error && $user->valid && $token) {
    $object->date_eb = dol_mktime(12,0,0, GETPOST('date_ebmonth', 'int'), GETPOST('date_ebday', 'int'), GETPOST('date_ebyear', 'int'));
    $object->date_eb = $db->idate($object->date_eb);
    $object->objet = GETPOST('objet', 'alphanohtml');
    $object->note_public = GETPOST('note_public', 'alphanohtml');
    $res = $object->update($user);
    if ($res > 0) {
        // Suppression + recréation lignes
        $ln = new ApcEtatBesoinLine($db);
        $ln->deleteAllForParent($user, $object->id);
        $lignes = GETPOST('lines', 'array');
        $no = 1;
        if (is_array($lignes)) {
            foreach ($lignes as $line) {
                if (empty($line['depense'])) continue;
                $nl = new ApcEtatBesoinLine($db);
                $nl->fk_etatbesoin = $object->id;
                $nl->no_ligne = $no++;
                $nl->depense = $line['depense'];
                $nl->projet_or_budget = $line['projet_or_budget'];
                $nl->compte = $line['compte'];
                $nl->montant = (float)str_replace(',', '.', $line['montant']);
                $nl->create($user);
            }
        }
        header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id);
        exit;
    } else { setEventMessages($object->error, $object->errors, 'errors'); }
}

if ($action === 'confirm_delete' && $confirm === 'yes' && $permissiontodelete && $token) {
    $res = $object->delete($user);
    if ($res > 0) { header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_list.php'); exit; }
    else setEventMessages($object->error, $object->errors, 'errors');
}

if ($action === 'generate_pdf' && $id > 0) {
    $g = new pdf_etatbesoin_apc($db);
    $g->write_file($object, $langs, '', 'I');
    exit;
}

if ($action === 'sign' && $permissiontovalidate && $id > 0) {
    $level = (int)GETPOST('level', 'int');
    $res = $object->sign($level, $user,
        GETPOST('nom_sig', 'alphanohtml'),
        GETPOST('fct_sig', 'alphanohtml')
    );
    if ($res > 0) { setEventMessages($langs->trans('SignOK'), null, 'mesgs'); }
    else setEventMessages($object->error, $object->errors, 'errors');
    header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id);
    exit;
}

/*
 * View
 */
$title = ($action === 'create' ? $langs->trans('NewEtatBesoin') : ($object->ref ?: $langs->trans('EBTitle')));
llxHeader('', $title, '', '', 0, 0, array('/custom/apclogistics/js/apclogistics.js'), array('/custom/apclogistics/css/apclogistics.css'));

$head = array();
$head[0][0] = DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . ($object->id ?: 0) . '&action=' . $action;
$head[0][1] = $langs->trans('TabCard');
$head[0][2] = 'tabcard';
$head[1][0] = DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . ($object->id ?: 0) . '&action=links';
$head[1][1] = $langs->trans('TabLinks');
$head[1][2] = 'tablinks';
$head[2][0] = DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . ($object->id ?: 0) . '&action=audit';
$head[2][1] = $langs->trans('TabHistory');
$head[2][2] = 'tabhistory';

$picto = 'apclogistics@apclogistics';
dol_fiche_head($head, 'tabcard', $title, -1, $picto);

if ($action === 'create' || $action === 'edit') {
    $editing = ($action === 'edit');
    $obj =& $object;
    if ($editing) $obj->fetchLines();
    $form = new Form($db);
    $hiddentoken = '<input type="hidden" name="token" value="' . newToken() . '">';
    $act = ($editing ? 'update' : 'add');

    print '<form action="' . $_SERVER["PHP_SELF"] . ($editing ? '?id=' . $obj->id : '') . '" method="POST">';
    print $hiddentoken;
    print '<input type="hidden" name="action" value="' . $act . '">';

    print '<table class="border centpercent">';
    print '<tr><td class="titlefieldcreate fieldrequired">' . $langs->trans('EBRef') . '</td><td>';
    if ($editing) print $obj->ref; else print '<span class="opacitymedium">(Généré automatiquement à l\'enregistrement)</span>';
    print '</td></tr>';
    print '<tr><td class="fieldrequired">' . $langs->trans('EBDate') . '</td><td>'
        . $form->select_date($obj->date_eb ? $obj->date_eb : -1, 'date_eb', 0, 0, 1, '', 1, 0) . '</td></tr>';
    print '<tr><td class="fieldrequired">' . $langs->trans('FieldObjet') . '</td><td>'
        . '<input type="text" size="80" name="objet" value="' . dol_escape_htmltag($obj->objet) . '"></td></tr>';
    print '<tr><td>' . $langs->trans('EBDemandeur') . ' / Nom</td><td>'
        . '<input type="text" size="50" name="signataire_nom_d" value="' . dol_escape_htmltag($obj->signataire_nom_d) . '"></td></tr>';
    print '<tr><td>' . $langs->trans('FieldFonction') . '</td><td>'
        . '<input type="text" size="50" name="signataire_fonction_d" value="' . dol_escape_htmltag($obj->signataire_fonction_d) . '"></td></tr>';
    print '<tr><td class="tdtop">' . $langs->trans('FieldNotes') . '</td><td>'
        . '<textarea rows="3" cols="80" name="note_public">' . dol_escape_htmltag($obj->note_public) . '</textarea></td></tr>';
    print '</table>';

    // ========== SECTION LIGNES ==========
    print '<h3 style="margin-top:18px;">' . $langs->trans('EBColDepense') . 's</h3>';
    print '<table class="noborder centpercent" id="apc-eb-lines-table">';
    print '<thead><tr class="liste_titre">';
    print '<th class="width40" style="width:40px;">N°</th>';
    print '<th style="min-width:240px;">' . $langs->trans('EBColDepense') . ' *</th>';
    print '<th>' . $langs->trans('EBColBudget') . '</th>';
    print '<th>' . $langs->trans('EBColCompte') . '</th>';
    print '<th style="width:140px;" class="right">' . $langs->trans('EBColMontant') . '</th>';
    print '<th style="width:40px;">&nbsp;</th>';
    print '</tr></thead>';
    print '<tbody id="apc-eb-lines-body">';

    $lines = $editing && !empty($obj->lines) ? $obj->lines : array((object)array());
    $idx = 0;
    foreach ($lines as $ln) {
        $dep = isset($ln->depense) ? $ln->depense : '';
        $bud = isset($ln->projet_or_budget) ? $ln->projet_or_budget : '';
        $cmp = isset($ln->compte) ? $ln->compte : '';
        $mnt = isset($ln->montant) ? (float)$ln->montant : '';
        print '<tr class="apc-line-row">';
        print '<td class="apc-eb-no center">' . ($idx+1) . '</td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][depense]" value="' . dol_escape_htmltag($dep) . '"></td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][projet_or_budget]" value="' . dol_escape_htmltag($bud) . '"></td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][compte]" value="' . dol_escape_htmltag($cmp) . '"></td>';
        print '<td><input type="text" class="flat width100 right apc-line-montant" name="lines[' . $idx . '][montant]" value="' . ($mnt !== '' ? dol_escape_htmltag($mnt) : '') . '"></td>';
        print '<td><button type="button" class="button apc-rm-line" style="padding:2px 6px;">-</button></td>';
        print '</tr>';
        $idx++;
    }
    print '</tbody>';
    print '<tfoot><tr class="liste_total"><td colspan="4" class="right"><b>' . $langs->trans('EBGeneralTotal') . '</b></td>'
        . '<td class="right apc-money" id="apc-eb-total">0,00</td><td>&nbsp;</td></tr></tfoot>';
    print '</table>';

    print '<div style="margin-top:10px;">';
    print '<button type="button" id="apc-add-line" class="button small">+ Ajouter une ligne</button>';
    print '</div>';

    print '<div class="tabsAction" style="margin-top:24px;">';
    print '<input type="submit" class="button" value="' . ($editing ? $langs->trans('Save') : $langs->trans('Create')) . '">';
    print ' <a class="button button-cancel" href="' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_list.php">' . $langs->trans('Cancel') . '</a>';
    print '</div>';
    print '</form>';

    // ===== JS : ajout/suppression lignes + calcul total =====
    print '<script type="text/javascript">
    $(document).ready(function() {
        function calcTotal() {
            var sum = 0;
            $(".apc-line-montant").each(function(){
                var v = parseFloat($(this).val().replace(/\s/g,"").replace(",","."));
                if (!isNaN(v)) sum += v;
            });
            $("#apc-eb-total").text(APCNumFmt(sum));
        }
        function APCNumFmt(n){ n = Number(n||0).toFixed(2); var p = n.split("."); p[0]=p[0].replace(/\B(?=(\d{3})+(?!\d))/g," "); return p.join(","); }
        function renumber() { $(".apc-eb-no").each(function(i){ $(this).text(i+1); }); }
        $(document).on("input", ".apc-line-montant", calcTotal);
        $("#apc-add-line").on("click", function() {
            var n = $("#apc-eb-lines-body tr").length;
            var html = "<tr class=\"apc-line-row\">"
                + "<td class=\"apc-eb-no center\">"+(n+1)+"</td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][depense]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][projet_or_budget]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][compte]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100 right apc-line-montant\" name=\"lines["+n+"][montant]\"></td>"
                + "<td><button type=\"button\" class=\"button apc-rm-line\" style=\"padding:2px 6px;\">-</button></td>"
                + "</tr>";
            $("#apc-eb-lines-body").append(html);
            renumber(); calcTotal();
        });
        $(document).on("click", ".apc-rm-line", function() {
            if ($("#apc-eb-lines-body tr").length > 1) { $(this).closest("tr").remove(); renumber(); calcTotal(); }
        });
        calcTotal();
    });
    </script>';
} else {
    // ======== AFFICHAGE FICHE ========
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
    print '<tr><td class="titlefield">' . $langs->trans('EBRef') . '</td><td class="valeur"><b>' . $object->ref . '</b></td>'
        . '<td class="titlefield">' . $langs->trans('FieldStatus') . '</td><td class="valeur">' . $object->getStatusBadge() . '</td></tr>';
    print '<tr><td>' . $langs->trans('EBDate') . '</td><td>' . dol_print_date($db->jdate($object->date_eb), 'day') . '</td>'
        . '<td>' . $langs->trans('FieldDateCreation') . '</td><td>' . dol_print_date($db->jdate($object->date_creation), 'dayhour') . '</td></tr>';
    print '<tr><td class="tdtop">' . $langs->trans('FieldObjet') . '</td><td colspan="3">' . dol_escape_htmltag($object->objet) . '</td></tr>';
    print '<tr><td>' . $langs->trans('EBDemandeur') . '</td><td>' . dol_escape_htmltag($object->signataire_nom_d)
        . (empty($object->date_signature_demandeur) ? '' : ' <span class="opacitymedium">(signé ' . dol_print_date($db->jdate($object->date_signature_demandeur), 'day') . ')</span>')
        . '</td>';
    print '<td>' . $langs->trans('EBVerificateur') . '</td><td>' . dol_escape_htmltag($object->signataire_nom_v)
        . (empty($object->date_signature_verif) ? '' : ' <span class="opacitymedium">(signé ' . dol_print_date($db->jdate($object->date_signature_verif), 'day') . ')</span>')
        . '</td></tr>';
    print '<tr><td>' . $langs->trans('EBApprobateur') . '</td><td colspan="3">' . dol_escape_htmltag($object->signataire_nom_a)
        . (empty($object->date_signature_approb) ? '' : ' <span class="opacitymedium">(signé ' . dol_print_date($db->jdate($object->date_signature_approb), 'day') . ')</span>')
        . '</td></tr>';
    if (!empty($object->note_public)) print '<tr><td class="tdtop">' . $langs->trans('FieldNotes') . '</td><td colspan="3">' . dol_escape_htmltag($object->note_public) . '</td></tr>';
    print '</table>';

    // ======= TABLEAU LIGNES =======
    $object->fetchLines();
    print '<h3 style="margin-top:18px;">' . $langs->trans('EBColDepense') . 's</h3>';
    print '<table class="noborder centpercent">';
    print '<thead><tr class="liste_titre">';
    print '<th class="center width40">N°</th>';
    print '<th>' . $langs->trans('EBColDepense') . '</th>';
    print '<th>' . $langs->trans('EBColBudget') . '</th>';
    print '<th>' . $langs->trans('EBColCompte') . '</th>';
    print '<th class="right" style="width:160px;">' . $langs->trans('EBColMontant') . '</th>';
    print '</tr></thead><tbody>';
    $i = 1; $total = 0;
    foreach ($object->lines as $ln) {
        $total += (float)$ln->montant;
        print '<tr class="oddeven"><td class="center">' . $i++ . '</td>';
        print '<td>' . dol_escape_htmltag($ln->depense) . '</td>';
        print '<td>' . dol_escape_htmltag($ln->projet_or_budget) . '</td>';
        print '<td class="center">' . dol_escape_htmltag($ln->compte) . '</td>';
        print '<td class="apc-money">' . price((float)$ln->montant, 0, $langs, 0, 0, -1, $conf->currency) . '</td>';
        print '</tr>';
    }
    if (empty($object->lines)) print '<tr class="oddeven"><td colspan="5" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
    print '</tbody><tfoot><tr class="liste_total"><td colspan="4" class="right"><b>' . $langs->trans('EBGeneralTotal') . '</b></td>'
        . '<td class="right apc-money">' . price($total, 0, $langs, 0, 0, -1, $conf->currency) . '</td></tr></tfoot>';
    print '</table>';

    // ======= ZONES VALIDATION PAR SIGNATURES =======
    if ($permissiontovalidate && (int)$object->status < ApcEtatBesoin::STATUS_VALIDATED) {
        print '<h3 style="margin-top:18px;">' . $langs->trans('AUDSignature') . '</h3>';
        foreach (array(0=>'Demandeur', 1=>'Verificateur', 2=>'Approbateur') as $lvl => $key) {
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
                print '<b>Signer en tant que : ' . $langs->trans('EB' . $key) . '</b> — ';
                print 'Nom <input type="text" name="nom_sig" size="28" value="' . dol_escape_htmltag($user->getFullName($langs)) . '"> ';
                print 'Fonction <input type="text" name="fct_sig" size="22" value="' . dol_escape_htmltag($user->poste) . '"> ';
                print '<input type="submit" class="button" value="Signer">';
                print '</form></div>';
            }
        }
    }

    // ======= BOUTONS ACTIONS =======
    print '<div class="tabsAction" style="margin-top:24px;">';
    print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id . '&action=generate_pdf&token=' . newToken() . '">' . $langs->trans('PDFGenerateBtn') . '</a>';
    if ($permissiontoedit) print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id . '&action=edit">' . $langs->trans('Modify') . '</a>';
    if ($permissiontodelete) print '<a class="butActionDelete" href="' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id . '&action=delete">' . $langs->trans('Delete') . '</a>';
    print '</div>';
}

dol_fiche_end();

if ($action === 'links' && $object->id > 0) {
    apcPrintLinksTab($db, $langs, $conf, $object);
}

if ($action === 'audit' && $object->id > 0) {
    // Onglet Historique / Audit Log
    $audits = ApcAuditLog::fetchForEntity($db, 'eb', $object->id, 200);
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
