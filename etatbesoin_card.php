<?php

/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * etatbesoin_card.php — Fiche Etat de Besoin : création/édition/suppression + onglets
 * Onglets : Fiche (champs + lignes) | Liens | Historique/Audit | PDF
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
error_reporting(E_ALL);
ini_set('display_errors', '1');
$res = 0; if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php"; if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php"; if (!$res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/class/ApcEtatBesoin.class.php';
require_once __DIR__ . '/class/apc_links.lib.php';
require_once __DIR__ . '/class/pdf/pdf_etatbesoin_apc.modules.php';
require_once __DIR__ . '/class/apc_init.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

// Initialisation de la variable pour éviter l'erreur "Undefined variable"
$formconfirm = '';

// Auto-create missing tables
$tableErrors = apc_ensure_tables($db);

$id      = (int) GETPOST('id', 'int');
$ref     = GETPOST('ref', 'alpha');
$action  = GETPOST('action', 'aZ');
$confirm = GETPOST('confirm', 'alpha');
$token   = GETPOST('token', 'alpha');

$object = new ApcEtatBesoin($db);
$extrafields = new ExtraFields($db);
$extralabels = $extrafields->fetch_name_optionals_label('apclogistics_etatbesoin');

if ($id > 0 || $ref) {
    $res = ($id > 0) ? $object->fetch($id) : $object->fetch(0, $ref);
    if ($res <= 0) { dol_print_error('', 'ErrorLoadFailed'); exit; }
    $object->fetchLines();
}

$permissiontoread     = !empty($user->rights->apclogistics->etatbesoin->read);
$permissiontocreate   = !empty($user->rights->apclogistics->etatbesoin->create);
$permissiontoedit     = !empty($user->rights->apclogistics->etatbesoin->edit);
$permissiontodelete   = !empty($user->rights->apclogistics->etatbesoin->delete);
$permissiontovalidate = !empty($user->rights->apclogistics->etatbesoin->validate);

if ($action === 'create' && !$permissiontocreate) accessforbidden();
if (empty($permissiontoread) && $action !== 'create') accessforbidden();

$error = 0;
$backtopage = DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_list.php';
$form = new Form($db);

/*
 * ======================== ACTIONS ========================
 */

// ---- CRÉER ----
if ($action === 'add' && $permissiontocreate && !$error && $user->valid && $token && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($conf->dol_url_root)) $backtopage = $conf->dol_url_root . '/custom/apclogistics/etatbesoin_list.php';

    $eb_month = GETPOST('date_ebmonth', 'int');
    $eb_day   = GETPOST('date_ebday', 'int');
    $eb_year  = GETPOST('date_ebyear', 'int');
    $object->date_eb = dol_mktime(12, 0, 0, $eb_month, $eb_day, $eb_year);
    $object->objet                = GETPOST('description', 'alphanohtml');
    $object->signataire_nom_d     = GETPOST('demandeur_nom', 'alphanohtml');
    $object->signataire_fonction_d = GETPOST('fonction', 'alphanohtml');
    $object->note_public          = GETPOST('remarques', 'alphanohtml');

    if (empty($object->objet)) { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('FieldObjet')), null, 'errors'); $error++; }
    if ($eb_month <= 0 || $eb_day <= 0 || $eb_year <= 0) { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('EBDate')), null, 'errors'); $error++; }

    if (!$error) {
        $res = $object->create($user);
        if ($res > 0) {
            $lignes = GETPOST('lines', 'array');
            $lineErrors = array();
            if (is_array($lignes)) {
                foreach ($lignes as $line) {
                    if (empty($line['depense'])) continue;
                    $addRes = $object->addline($user, $line['depense'], $line['projet_or_budget'], $line['compte'], (float) str_replace(',', '.', $line['montant']));
                    if ($addRes <= 0) {
                        $lineErrors[] = $line['depense'] . ': ' . $object->error;
                    }
                }
            }
            if (!empty($lineErrors)) {
                setEventMessages('Lignes non enregistrées : ' . implode(' | ', $lineErrors), null, 'errors');
            }
            header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id);
            exit;
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
        }
    }
}

// ---- MODIFIER ----
if ($action === 'update' && $permissiontoedit && !$error && $user->valid && $token) {
    $eb_month = GETPOST('date_ebmonth', 'int');
    $eb_day   = GETPOST('date_ebday', 'int');
    $eb_year  = GETPOST('date_ebyear', 'int');
    $object->date_eb = dol_mktime(12, 0, 0, $eb_month, $eb_day, $eb_year);
    $object->objet                = GETPOST('description', 'alphanohtml');
    $object->signataire_nom_d     = GETPOST('demandeur_nom', 'alphanohtml');
    $object->signataire_fonction_d = GETPOST('fonction', 'alphanohtml');
    $object->note_public          = GETPOST('remarques', 'alphanohtml');

    $res = $object->update($user);
    if ($res > 0) {
        // Suppression + recréation lignes via addline()
        $ln = new ApcEtatBesoinLine($db);
        $ln->deleteAllForParent($user, $object->id);
        $lignes = GETPOST('lines', 'array');
        $lineErrors = array();
        if (is_array($lignes)) {
            foreach ($lignes as $line) {
                if (empty($line['depense'])) continue;
                $addRes = $object->addline($user, $line['depense'], $line['projet_or_budget'], $line['compte'], (float) str_replace(',', '.', $line['montant']));
                if ($addRes <= 0) {
                    $lineErrors[] = $line['depense'] . ': ' . $object->error;
                }
            }
        }
        if (!empty($lineErrors)) {
            setEventMessages('Lignes non enregistrées : ' . implode(' | ', $lineErrors), null, 'errors');
        }
        // Recalculer le total
        $object->calculateTotals();
        $object->update($user);
        header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id);
        exit;
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
    }
}

// ---- SUPPRIMER ----
if ($action === 'confirm_delete' && $confirm === 'yes' && $permissiontodelete && $token) {
    $res = $object->delete($user);
    if ($res > 0) {
        header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_list.php');
        exit;
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
    }
}

// ---- GÉNÉRER PDF ----
if ($action === 'generate_pdf' && $id > 0 && $token && $permissiontoread) {
    // Vérifier TCPDF
    $tcpdf_found = false;
    if (file_exists(DOL_DOCUMENT_ROOT . '/includes/tecnickcom/tcpdf/tcpdf.php')) $tcpdf_found = true;
    elseif (file_exists(DOL_DOCUMENT_ROOT . '/includes/tcpdf/tcpdf.php')) $tcpdf_found = true;

    if (!$tcpdf_found) {
        setEventMessages('TCPDF non trouvé. Vérifiez : ' . DOL_DOCUMENT_ROOT . '/includes/tecnickcom/tcpdf/', null, 'errors');
        header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id);
        exit;
    }

    try {
        $pdfGen = new pdf_etatbesoin_apc($db);
        $result = $pdfGen->write_file($object, $langs, '', 'I');
        if ($result === 0) {
            setEventMessages('Erreur génération PDF', null, 'errors');
            header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id);
            exit;
        }
    } catch (Exception $e) {
        setEventMessages('Erreur génération PDF : ' . $e->getMessage(), null, 'errors');
        header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id);
        exit;
    }
    exit;
}

// ---- SUPPRIMER PDF ----
if ($action === 'delete_pdf' && $id > 0 && $permissiontodelete && $token) {
    $filename = GETPOST('file', 'alpha');
    if ($filename) {
        $upload_dir = $conf->apclogistics->dir_output . '/etatbesoin/' . dol_sanitizeFileName($object->ref);
        $filepath = $upload_dir . '/' . basename($filename); // basename = protection path traversal
        if (is_file($filepath)) {
            unlink($filepath);
            setEventMessages($langs->trans('Deleted'), null, 'mesgs');
        }
    }
    header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id);
    exit;
}

// ---- TÉLÉCHARGER PDF ----
if ($action === 'download_pdf' && $id > 0 && $permissiontoread && $token) {
    $filename = GETPOST('file', 'alpha');
    if ($filename) {
        $upload_dir = $conf->apclogistics->dir_output . '/etatbesoin/' . dol_sanitizeFileName($object->ref);
        $filepath = $upload_dir . '/' . basename($filename); // basename = protection path traversal
        if (is_file($filepath)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($filename) . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        }
    }
    setEventMessages('Fichier non trouvé', null, 'errors');
    header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id);
    exit;
}

// ---- SIGNER ----
if ($action === 'sign' && $permissiontovalidate && $id > 0) {
    $level = (int) GETPOST('level', 'int');
    $res = $object->sign($level, $user,
        GETPOST('nom_sig', 'alphanohtml'),
        GETPOST('fct_sig', 'alphanohtml')
    );
    if ($res > 0) {
        setEventMessages($langs->trans('SignOK'), null, 'mesgs');
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
    }
    header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id);
    exit;
}

/*
 * ======================== VIEW ========================
 */
$title = ($action === 'create' ? $langs->trans('NewEtatBesoin') : ($object->ref ?: $langs->trans('EBTitle')));
llxHeader('', $title, '', '', 0, 0, array('/custom/apclogistics/js/apclogistics.js'), array('/custom/apclogistics/css/apclogistics.css'));

// Debug: afficher erreurs creation tables
if (!empty($tableErrors)) {
    print '<div style="background:#fee;padding:12px;border:2px solid #c00;margin:12px;font-family:monospace;font-size:12px;">';
    print '<b>ERREUR CREATION TABLES :</b><br>';
    foreach ($tableErrors as $e) print '- ' . dol_escape_htmltag($e) . '<br>';
    print 'Exécutez le script de test : <a href="' . DOL_URL_ROOT . '/custom/apclogistics/scripts/test_eb_debug.php">test_eb_debug.php</a>';
    print '</div>';
}

// Debug: état des tables
$debugTbl = array();
foreach (array('apclogistics_etatbesoin', 'apclogistics_etatbesoin_lines', 'apclogistics_auditlog') as $t) {
    $full = MAIN_DB_PREFIX . $t;
    $chk = $db->query("SELECT 1 FROM " . $full . " LIMIT 1");
    $debugTbl[$t] = $chk ? 'OK' : 'MANQUANT (' . $db->lasterror() . ')';
}
print '<div style="background:#f0f8f0;padding:8px;border:1px solid #4a4;margin:6px;font-family:monospace;font-size:11px;">';
print '<b>Tables :</b> ';
foreach ($debugTbl as $k => $v) print $k . '=<b>' . $v . '</b> | ';
print '</div>';

// Onglets
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

/*
 * ======================== FORMULAIRE (Création / Édition) ========================
 */
if ($action === 'create' || $action === 'edit') {
    $editing = ($action === 'edit');
    $obj =& $object;
    if ($editing) $obj->fetchLines();
    $hiddentoken = '<input type="hidden" name="token" value="' . newToken() . '">';
    $act = ($editing ? 'update' : 'add');

    print '<form action="' . $_SERVER["PHP_SELF"] . ($editing ? '?id=' . $obj->id : '') . '" method="POST">';
    print $hiddentoken;
    print '<input type="hidden" name="action" value="' . $act . '">';

    // Champs fiche
    print '<table class="border centpercent">';
    print '<tr><td class="titlefieldcreate fieldrequired">' . $langs->trans('EBRef') . '</td><td>';
    if ($editing) print $obj->ref; else print '<span class="opacitymedium">(Généré automatiquement à l\'enregistrement)</span>';
    print '</td></tr>';
    print '<tr><td class="fieldrequired">' . $langs->trans('EBDate') . '</td><td>'
        . $form->select_date($obj->date_eb ? $obj->date_eb : -1, 'date_eb', 0, 0, 1, '', 1, 0) . '</td></tr>';
    print '<tr><td class="fieldrequired">' . $langs->trans('FieldObjet') . '</td><td>'
        . '<input type="text" size="80" name="description" value="' . dol_escape_htmltag($obj->objet) . '"></td></tr>';
    print '<tr><td>' . $langs->trans('EBDemandeur') . ' / Nom</td><td>'
        . '<input type="text" size="50" name="demandeur_nom" value="' . dol_escape_htmltag($obj->signataire_nom_d) . '"></td></tr>';
    print '<tr><td>' . $langs->trans('FieldFonction') . '</td><td>'
        . '<input type="text" size="50" name="fonction" value="' . dol_escape_htmltag($obj->signataire_fonction_d) . '"></td></tr>';
    print '<tr><td class="tdtop">' . $langs->trans('FieldNotes') . '</td><td>'
        . '<textarea rows="3" cols="80" name="remarques">' . dol_escape_htmltag($obj->note_public) . '</textarea></td></tr>';
    print '</table>';

    // Lignes
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

    $lines = $editing && !empty($obj->lines) ? $obj->lines : array((object) array());
    $idx = 0;
    foreach ($lines as $ln) {
        $dep = isset($ln->depense) ? $ln->depense : '';
        $bud = isset($ln->projet_or_budget) ? $ln->projet_or_budget : '';
        $cmp = isset($ln->compte) ? $ln->compte : '';
        $mnt = isset($ln->montant) ? (float) $ln->montant : '';
        print '<tr class="apc-line-row">';
        print '<td class="apc-eb-no center">' . ($idx + 1) . '</td>';
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

    // JS
    print '<script type="text/javascript">
    $(document).ready(function() {
        function calcTotal() {
            var sum = 0;
            $(".apc-line-montant").each(function(){
                var v = parseFloat($(this).val().replace(/\\s/g,"").replace(",","."));
                if (!isNaN(v)) sum += v;
            });
            $("#apc-eb-total").text(APCNumFmt(sum));
        }
        function APCNumFmt(n){ n = Number(n||0).toFixed(2); var p = n.split("."); p[0]=p[0].replace(/\\B(?=(\\d{3})+(?!\\d))/g," "); return p.join(","); }
        function renumber() { $(".apc-eb-no").each(function(i){ $(this).text(i+1); }); }
        $(document).on("input", ".apc-line-montant", calcTotal);
        $("#apc-add-line").on("click", function() {
            var n = $("#apc-eb-lines-body tr").length;
            var html = "<tr class=\\"apc-line-row\\">"
                + "<td class=\\"apc-eb-no center\\">"+(n+1)+"</td>"
                + "<td><input type=\\"text\\" class=\\"flat width100\\" name=\\"lines["+n+"][depense]\\"></td>"
                + "<td><input type=\\"text\\" class=\\"flat width100\\" name=\\"lines["+n+"][projet_or_budget]\\"></td>"
                + "<td><input type=\\"text\\" class=\\"flat width100\\" name=\\"lines["+n+"][compte]\\"></td>"
                + "<td><input type=\\"text\\" class=\\"flat width100 right apc-line-montant\\" name=\\"lines["+n+"][montant]\\"></td>"
                + "<td><button type=\\"button\\" class=\\"button apc-rm-line\\" style=\\"padding:2px 6px;\\">-</button></td>"
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
    /*
     * ======================== AFFICHAGE FICHE ========================
     */
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

    // Informations générales
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

    // Tableau des lignes
    print '<h3 style="margin-top:18px;">' . $langs->trans('EBColDepense') . 's</h3>';
    print '<table class="noborder centpercent">';
    print '<thead><tr class="liste_titre">';
    print '<th class="center width40">N°</th>';
    print '<th>' . $langs->trans('EBColDepense') . '</th>';
    print '<th>' . $langs->trans('EBColBudget') . '</th>';
    print '<th>' . $langs->trans('EBColCompte') . '</th>';
    print '<th class="right" style="width:160px;">' . $langs->trans('EBColMontant') . '</th>';
    print '</tr></thead><tbody>';
    $i = 1;
    $total = 0;
    foreach ($object->lines as $ln) {
        $total += (float) $ln->montant;
        print '<tr class="oddeven"><td class="center">' . $i++ . '</td>';
        print '<td>' . dol_escape_htmltag($ln->depense) . '</td>';
        print '<td>' . dol_escape_htmltag($ln->projet_or_budget) . '</td>';
        print '<td class="center">' . dol_escape_htmltag($ln->compte) . '</td>';
        print '<td class="apc-money">' . price((float) $ln->montant, 0, $langs, 0, 0, -1, $conf->currency) . '</td>';
        print '</tr>';
    }
    if (empty($object->lines)) print '<tr class="oddeven"><td colspan="5" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
    print '</tbody><tfoot><tr class="liste_total"><td colspan="4" class="right"><b>' . $langs->trans('EBGeneralTotal') . '</b></td>'
        . '<td class="right apc-money">' . price($total, 0, $langs, 0, 0, -1, $conf->currency) . '</td></tr></tfoot>';
    print '</table>';

    // Signatures
    if ($permissiontovalidate && (int)$object->status < ApcEtatBesoin::STATUS_VALIDATED) {
        print '<h3 style="margin-top:18px;">' . $langs->trans('AUDSignature') . '</h3>';
        foreach (array(0 => 'Demandeur', 1 => 'Verificateur', 2 => 'Approbateur') as $lvl => $key) {
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

    // ===================== DOCUMENTS PDF =====================
    // Liste des PDF déjà générés + bouton Générer
    $upload_dir = $conf->apclogistics->dir_output . '/etatbesoin/' . dol_sanitizeFileName($object->ref);
    $dir_files = array();
    if (is_dir($upload_dir)) {
        $handle = opendir($upload_dir);
        if ($handle) {
            while (false !== ($file = readdir($handle))) {
                if ($file === '.' || $file === '..') continue;
                if (preg_match('/\.pdf$/i', $file)) {
                    $dir_files[] = $file;
                }
            }
            closedir($handle);
            sort($dir_files);
        }
    }

    print '<h3 style="margin-top:18px;">' . $langs->trans('Documents') . '</h3>';
    if (!empty($dir_files)) {
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre"><th>' . $langs->trans('File') . '</th><th class="right" style="width:120px;">' . $langs->trans('Actions') . '</th></tr>';
        foreach ($dir_files as $f) {
            $filepath = $upload_dir . '/' . $f;
            $filesize = is_file($filepath) ? dol_size(filesize($filepath)) : '';
            print '<tr class="oddeven"><td>' . dol_escape_htmltag($f) . ' <span class="opacitymedium">' . $filesize . '</span></td>';
            print '<td class="right">';
            print '<a class="button small" href="' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id . '&action=download_pdf&file=' . urlencode($f) . '&token=' . newToken() . '">' . $langs->trans('Download') . '</a> ';
            if ($permissiontodelete) {
                print '<a class="button small button-delete" href="' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id . '&action=delete_pdf&file=' . urlencode($f) . '&token=' . newToken() . '" onclick="return confirm(\'' . $langs->trans('ConfirmDelete') . '\');">' . $langs->trans('Delete') . '</a>';
            }
            print '</td></tr>';
        }
        print '</table>';
    } else {
        print '<p class="opacitymedium">' . $langs->trans('NoRecord') . '</p>';
    }

    // Bouton Générer PDF
    print '<div style="margin-top:10px;">';
    print '<a class="button" href="' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id . '&action=generate_pdf&token=' . newToken() . '">' . $langs->trans('GeneratePDF') . '</a>';
    print '</div>';

    // Boutons d'action
    print '<div class="tabsAction" style="margin-top:24px;">';
    if ($permissiontoedit) print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id . '&action=edit">' . $langs->trans('Modify') . '</a>';
    if ($permissiontodelete) print '<a class="butActionDelete" href="' . DOL_URL_ROOT . '/custom/apclogistics/etatbesoin_card.php?id=' . $object->id . '&action=delete">' . $langs->trans('Delete') . '</a>';
    print '</div>';
}

dol_fiche_end();

// Onglet Liens
if ($action === 'links' && $object->id > 0) {
    apcPrintLinksTab($db, $langs, $conf, $object);
}

// Onglet Historique / Audit Log
if ($action === 'audit' && $object->id > 0) {
    $audits = ApcAuditLog::fetchForEntity($db, 'eb', $object->id, 200);
    print '<h3 style="margin-top:12px;">' . $langs->trans('TabHistory') . '</h3>';
    print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
    print '<tr class="liste_titre"><th>' . $langs->trans('AUDColDate') . '</th><th>' . $langs->trans('AUDColUser') . '</th>'
        . '<th>' . $langs->trans('AUDColAction') . '</th><th>' . $langs->trans('AUDColIp') . '</th>'
        . '<th>' . $langs->trans('AUDColDetails') . '</th></tr>';
    foreach ($audits as $a) {
        print '<tr class="oddeven">';
        print '<td>' . dol_print_date($db->jdate($a->date_action), 'dayhour') . '</td>';
        print '<td>' . dol_escape_htmltag($a->user_login) . ' (id ' . (int) $a->fk_user . ')</td>';
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
