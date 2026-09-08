<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * demandeprix_card.php — Fiche Demande de Prix APC
 * Onglets : Fiche (champs + lignes) | Cotations reçues | Historique/Audit | PDF
 * Actions : CRUD, generate_pdf, send_to_fournisseur (generateSupplierLink)
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/class/ApcDemandePrix.class.php';
require_once __DIR__ . '/class/ApcCotation.class.php';
require_once __DIR__ . '/class/ApcBonCommande.class.php';
require_once __DIR__ . '/class/apc_links.lib.php';
require_once __DIR__ . '/class/pdf/pdf_demandeprix_apc.modules.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

$id      = (int)GETPOST('id', 'int');
$ref     = GETPOST('ref', 'alpha');
$action  = GETPOST('action', 'aZ');
$confirm = GETPOST('confirm', 'alpha');
$token   = GETPOST('token', 'alpha');

$object = new ApcDemandePrix($db);
$extrafields = new ExtraFields($db);
$extralabels = $extrafields->fetch_name_optionals_label('apclogistics_demandeprix');

if ($id > 0 || $ref) {
    $res = ($id > 0) ? $object->fetch($id) : $object->fetch(0, $ref);
    if ($res <= 0) { dol_print_error('', 'ErrorLoadFailed'); exit; }
    $object->fetchLines();
}

$permissiontoread     = !empty($user->rights->apclogistics->demandeprix->read);
$permissiontocreate   = !empty($user->rights->apclogistics->demandeprix->create);
$permissiontoedit     = !empty($user->rights->apclogistics->demandeprix->edit);
$permissiontodelete   = !empty($user->rights->apclogistics->demandeprix->delete);
$permissiontovalidate = !empty($user->rights->apclogistics->demandeprix->validate);

if ($action === 'create' && !$permissiontocreate) accessforbidden();
if (empty($permissiontoread) && $action !== 'create') accessforbidden();

$error = 0;
$backtopage = DOL_URL_ROOT . '/custom/apclogistics/demandeprix_list.php';
$form = new Form($db);
$formother = new FormOther($db);

/*
 * Actions
 */
if ($action === 'add' && $permissiontocreate && !$error && $user->valid && $token && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $object->date_dp = dol_mktime(12,0,0, GETPOST('date_dpmonth', 'int'), GETPOST('date_dpday', 'int'), GETPOST('date_dpyear', 'int'));
    $object->date_dp = $db->idate($object->date_dp);
    $object->fournisseur_nom      = GETPOST('fournisseur_nom', 'alphanohtml');
    $object->fournisseur_adresse  = GETPOST('fournisseur_adresse', 'alphanohtml');
    $object->fournisseur_tel      = GETPOST('fournisseur_tel', 'alphanohtml');
    $object->fournisseur_email    = GETPOST('fournisseur_email', 'alpha');
    $object->fournisseur_contact  = GETPOST('fournisseur_contact', 'alphanohtml');
    $object->lieu_livraison       = GETPOST('lieu_livraison', 'alphanohtml');
    $dl = dol_mktime(12,0,0, GETPOST('date_livraisonmonth', 'int'), GETPOST('date_livraisonday', 'int'), GETPOST('date_livraisonyear', 'int'));
    $object->date_livraison = $db->idate($dl);
    $object->note_public = GETPOST('note_public', 'alphanohtml');

    if (empty($object->fournisseur_nom)) { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('DPFournisseur')), null, 'errors'); $error++; }
    if (empty($object->date_dp) || $object->date_dp === '1970-01-01') { setEventMessages($langs->trans('ErrorFieldRequired', 'Date DP'), null, 'errors'); $error++; }

    if (!$error) {
        $res = $object->create($user);
        if ($res > 0) {
            $lignes = GETPOST('lines', 'array');
            $no = 1;
            if (is_array($lignes)) {
                foreach ($lignes as $line) {
                    if (empty($line['specification'])) continue;
                    $ln = new ApcDemandePrixLine($db);
                    $ln->fk_demandeprix = $object->id;
                    $ln->no_ligne = $no++;
                    $ln->specification = $line['specification'];
                    $ln->unite         = $line['unite'];
                    $ln->quantite      = (float)str_replace(',', '.', $line['quantite']);
                    $ln->fk_product    = (int)$line['fk_product'];
                    $ln->create($user);
                }
            }
            header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/demandeprix_card.php?id=' . $object->id);
            exit;
        } else { setEventMessages($object->error, $object->errors, 'errors'); }
    }
}

if ($action === 'update' && $permissiontoedit && !$error && $user->valid && $token) {
    $object->date_dp = dol_mktime(12,0,0, GETPOST('date_dpmonth', 'int'), GETPOST('date_dpday', 'int'), GETPOST('date_dpyear', 'int'));
    $object->date_dp = $db->idate($object->date_dp);
    $object->fournisseur_nom      = GETPOST('fournisseur_nom', 'alphanohtml');
    $object->fournisseur_adresse  = GETPOST('fournisseur_adresse', 'alphanohtml');
    $object->fournisseur_tel      = GETPOST('fournisseur_tel', 'alphanohtml');
    $object->fournisseur_email    = GETPOST('fournisseur_email', 'alpha');
    $object->fournisseur_contact  = GETPOST('fournisseur_contact', 'alphanohtml');
    $object->lieu_livraison       = GETPOST('lieu_livraison', 'alphanohtml');
    $dl = dol_mktime(12,0,0, GETPOST('date_livraisonmonth', 'int'), GETPOST('date_livraisonday', 'int'), GETPOST('date_livraisonyear', 'int'));
    $object->date_livraison = $db->idate($dl);
    $object->note_public = GETPOST('note_public', 'alphanohtml');
    $res = $object->update($user);
    if ($res > 0) {
        $ln = new ApcDemandePrixLine($db);
        $ln->deleteAllForParent($user, $object->id);
        $lignes = GETPOST('lines', 'array');
        $no = 1;
        if (is_array($lignes)) {
            foreach ($lignes as $line) {
                if (empty($line['specification'])) continue;
                $nl = new ApcDemandePrixLine($db);
                $nl->fk_demandeprix = $object->id;
                $nl->no_ligne = $no++;
                $nl->specification = $line['specification'];
                $nl->unite         = $line['unite'];
                $nl->quantite      = (float)str_replace(',', '.', $line['quantite']);
                $nl->fk_product    = (int)$line['fk_product'];
                $nl->create($user);
            }
        }
        header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/demandeprix_card.php?id=' . $object->id);
        exit;
    } else { setEventMessages($object->error, $object->errors, 'errors'); }
}

if ($action === 'confirm_delete' && $confirm === 'yes' && $permissiontodelete && $token) {
    $res = $object->delete($user);
    if ($res > 0) { header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/demandeprix_list.php'); exit; }
    else setEventMessages($object->error, $object->errors, 'errors');
}

if ($action === 'generate_pdf' && $id > 0) {
    $g = new pdf_demandeprix_apc($db);
    $g->write_file($object, $langs, '', 'I');
    exit;
}

$generatedSupplierUrl = '';
if ($action === 'send_to_fournisseur' && $permissiontovalidate && $id > 0 && $token) {
    $generatedSupplierUrl = $object->generateSupplierLink($user, null);
    if ($generatedSupplierUrl) {
        setEventMessages($langs->trans('LinkGeneratedOK') . ' — <code class="nowrap">' . dol_escape_htmltag($generatedSupplierUrl) . '</code>', null, 'mesgs');
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
    }
    header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/demandeprix_card.php?id=' . $object->id);
    exit;
}

if ($action === 'transform_to_bc' && $permissiontovalidate && $id > 0 && $token) {
    $cotId = (int)GETPOST('cot_id', 'int');
    $cot = new ApcCotation($db);
    if ($cotId > 0 && $cot->fetch($cotId) > 0 && (int)$cot->fk_demandeprix === (int)$object->id) {
        $newBC = ApcBonCommande::createFromCotation($cot, $user, $user);
        if ($newBC && $newBC->id > 0) {
            setEventMessages($langs->trans('BCFromCotationOK') . ' — ' . $newBC->ref, null, 'mesgs');
            header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/boncommande_card.php?id=' . $newBC->id);
            exit;
        } else {
            setEventMessages($newBC ? $newBC->error : 'Erreur creation BC', null, 'errors');
        }
    } else {
        setEventMessages('Cotation introuvable pour cette DP', null, 'errors');
    }
    header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/demandeprix_card.php?id=' . $object->id . '&action=cotations');
    exit;
}

/*
 * View
 */
$title = ($action === 'create' ? $langs->trans('NewDemandePrix') : ($object->ref ?: $langs->trans('DPTitle')));
llxHeader('', $title, '', '', 0, 0, array('/custom/apclogistics/js/apclogistics.js'), array('/custom/apclogistics/css/apclogistics.css'));

$head = array();
$head[0][0] = DOL_URL_ROOT . '/custom/apclogistics/demandeprix_card.php?id=' . ($object->id ?: 0) . '&action=' . $action;
$head[0][1] = $langs->trans('TabCard');
$head[0][2] = 'tabcard';
$head[1][0] = DOL_URL_ROOT . '/custom/apclogistics/demandeprix_card.php?id=' . ($object->id ?: 0) . '&action=cotations';
$head[1][1] = $langs->trans('DPReceivedCotations');
$head[1][2] = 'tabcotations';
$head[2][0] = DOL_URL_ROOT . '/custom/apclogistics/demandeprix_card.php?id=' . ($object->id ?: 0) . '&action=links';
$head[2][1] = $langs->trans('TabLinks');
$head[2][2] = 'tablinks';
$head[3][0] = DOL_URL_ROOT . '/custom/apclogistics/demandeprix_card.php?id=' . ($object->id ?: 0) . '&action=audit';
$head[3][1] = $langs->trans('TabHistory');
$head[3][2] = 'tabhistory';

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
    print '<tr><td class="titlefieldcreate fieldrequired">' . $langs->trans('DPRef') . '</td><td>';
    if ($editing) print $obj->ref; else print '<span class="opacitymedium">(Généré automatiquement à l\'enregistrement)</span>';
    print '</td>';
    print '<td class="titlefield">' . $langs->trans('FieldStatus') . '</td><td>'
        . ($editing ? $obj->getStatusBadge() : '<span class="opacitymedium">' . $langs->trans('StatusDraft') . '</span>') . '</td></tr>';

    print '<tr><td class="fieldrequired">Date DP</td><td>'
        . $form->select_date($obj->date_dp ? $obj->date_dp : -1, 'date_dp', 0, 0, 1, '', 1, 0) . '</td>';
    print '<td>' . $langs->trans('DPDateLiv') . '</td><td>'
        . $form->select_date($obj->date_livraison ? $obj->date_livraison : -1, 'date_livraison', 0, 0, 1, '', 1, 0) . '</td></tr>';

    print '<tr><td class="fieldrequired">' . $langs->trans('DPFournisseur') . '</td><td>'
        . '<input type="text" size="60" name="fournisseur_nom" value="' . dol_escape_htmltag($obj->fournisseur_nom) . '"></td>';
    print '<td>' . $langs->trans('DPContact') . '</td><td>'
        . '<input type="text" size="40" name="fournisseur_contact" value="' . dol_escape_htmltag($obj->fournisseur_contact) . '"></td></tr>';

    print '<tr><td>' . $langs->trans('DPAdresse') . '</td><td>'
        . '<textarea rows="2" cols="60" name="fournisseur_adresse">' . dol_escape_htmltag($obj->fournisseur_adresse) . '</textarea></td>';
    print '<td>' . $langs->trans('DPTel') . ' / ' . $langs->trans('DPEmail') . '</td><td>'
        . '<input type="text" size="20" name="fournisseur_tel" value="' . dol_escape_htmltag($obj->fournisseur_tel) . '">'
        . ' &nbsp; <input type="text" size="30" name="fournisseur_email" value="' . dol_escape_htmltag($obj->fournisseur_email) . '"></td></tr>';

    print '<tr><td class="tdtop">' . $langs->trans('DPLieuLiv') . '</td><td colspan="3">'
        . '<textarea rows="2" cols="80" name="lieu_livraison">' . dol_escape_htmltag($obj->lieu_livraison) . '</textarea></td></tr>';
    print '<tr><td class="tdtop">' . $langs->trans('FieldNotes') . '</td><td colspan="3">'
        . '<textarea rows="3" cols="80" name="note_public">' . dol_escape_htmltag($obj->note_public) . '</textarea></td></tr>';
    print '</table>';

    // ========== SECTION LIGNES ==========
    print '<h3 style="margin-top:18px;">' . $langs->trans('DPColSpec') . 's / Articles</h3>';
    print '<table class="noborder centpercent" id="apc-dp-lines-table">';
    print '<thead><tr class="liste_titre">';
    print '<th style="width:40px;">N°</th>';
    print '<th style="min-width:280px;">' . $langs->trans('DPColSpec') . ' *</th>';
    print '<th style="width:90px;">' . $langs->trans('DPColUnit') . '</th>';
    print '<th style="width:100px;" class="right">' . $langs->trans('DPColQty') . '</th>';
    print '<th style="width:90px;" class="center">Produit (ID)</th>';
    print '<th style="width:40px;">&nbsp;</th>';
    print '</tr></thead>';
    print '<tbody id="apc-dp-lines-body">';

    $lines = $editing && !empty($obj->lines) ? $obj->lines : array((object)array());
    $idx = 0;
    foreach ($lines as $ln) {
        $spec = isset($ln->specification) ? $ln->specification : '';
        $unit = isset($ln->unite) ? $ln->unite : '';
        $qty  = isset($ln->quantite) ? (float)$ln->quantite : '';
        $fkpr = isset($ln->fk_product) ? (int)$ln->fk_product : 0;
        print '<tr class="apc-line-row">';
        print '<td class="apc-dp-no center">' . ($idx+1) . '</td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][specification]" value="' . dol_escape_htmltag($spec) . '"></td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][unite]" value="' . dol_escape_htmltag($unit) . '"></td>';
        print '<td><input type="text" class="flat width100 right" name="lines[' . $idx . '][quantite]" value="' . ($qty !== '' ? dol_escape_htmltag($qty) : '') . '"></td>';
        print '<td><input type="number" min="0" step="1" class="flat width100" name="lines[' . $idx . '][fk_product]" value="' . ($fkpr ? $fkpr : '') . '"></td>';
        print '<td><button type="button" class="button apc-rm-line" style="padding:2px 6px;">-</button></td>';
        print '</tr>';
        $idx++;
    }
    print '</tbody></table>';

    print '<div style="margin-top:10px;">';
    print '<button type="button" id="apc-add-line" class="button small">+ Ajouter une ligne</button>';
    print '</div>';

    print '<div class="tabsAction" style="margin-top:24px;">';
    print '<input type="submit" class="button" value="' . ($editing ? $langs->trans('Save') : $langs->trans('Create')) . '">';
    print ' <a class="button button-cancel" href="' . DOL_URL_ROOT . '/custom/apclogistics/demandeprix_list.php">' . $langs->trans('Cancel') . '</a>';
    print '</div>';
    print '</form>';

    // ===== JS : ajout/suppression lignes + renumérotation =====
    print '<script type="text/javascript">
    $(document).ready(function() {
        function renumber() { $(".apc-dp-no").each(function(i){ $(this).text(i+1); }); }
        $("#apc-add-line").on("click", function() {
            var n = $("#apc-dp-lines-body tr").length;
            var html = "<tr class=\"apc-line-row\">"
                + "<td class=\"apc-dp-no center\">"+(n+1)+"</td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][specification]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][unite]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100 right\" name=\"lines["+n+"][quantite]\"></td>"
                + "<td><input type=\"number\" min=\"0\" step=\"1\" class=\"flat width100\" name=\"lines["+n+"][fk_product]\"></td>"
                + "<td><button type=\"button\" class=\"button apc-rm-line\" style=\"padding:2px 6px;\">-</button></td>"
                + "</tr>";
            $("#apc-dp-lines-body").append(html);
            renumber();
        });
        $(document).on("click", ".apc-rm-line", function() {
            if ($("#apc-dp-lines-body tr").length > 1) { $(this).closest("tr").remove(); renumber(); }
        });
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
    print '<tr><td class="titlefield">' . $langs->trans('DPRef') . '</td><td class="valeur"><b>' . $object->ref . '</b></td>'
        . '<td class="titlefield">' . $langs->trans('FieldStatus') . '</td><td class="valeur">' . $object->getStatusBadge() . '</td></tr>';
    print '<tr><td>Date DP</td><td>' . dol_print_date($db->jdate($object->date_dp), 'day') . '</td>'
        . '<td>' . $langs->trans('FieldDateCreation') . '</td><td>' . dol_print_date($db->jdate($object->date_creation), 'dayhour') . '</td></tr>';
    print '<tr><td>' . $langs->trans('DPFournisseur') . '</td><td><b>' . dol_escape_htmltag($object->fournisseur_nom) . '</b></td>'
        . '<td>' . $langs->trans('DPContact') . '</td><td>' . dol_escape_htmltag($object->fournisseur_contact) . '</td></tr>';
    print '<tr><td class="tdtop">' . $langs->trans('DPAdresse') . '</td><td>' . dol_escape_htmltag($object->fournisseur_adresse) . '</td>'
        . '<td>' . $langs->trans('DPTel') . ' / ' . $langs->trans('DPEmail') . '</td><td>'
        . dol_escape_htmltag($object->fournisseur_tel)
        . (empty($object->fournisseur_email) ? '' : '<br>' . dol_escape_htmltag($object->fournisseur_email))
        . '</td></tr>';
    print '<tr><td>' . $langs->trans('DPLieuLiv') . '</td><td>' . dol_escape_htmltag($object->lieu_livraison) . '</td>'
        . '<td>' . $langs->trans('DPDateLiv') . '</td><td>'
        . ($object->date_livraison ? dol_print_date($db->jdate($object->date_livraison), 'day') : '<span class="opacitymedium">—</span>') . '</td></tr>';

    if (!empty($object->date_envoi)) {
        print '<tr><td>' . $langs->trans('DPSentDate') . '</td><td>' . dol_print_date($db->jdate($object->date_envoi), 'dayhour') . '</td>'
            . '<td>' . $langs->trans('DPSentBy') . '</td><td>id ' . (int)$object->fk_user_envoi . '</td></tr>';
    }
    if (!empty($object->note_public)) print '<tr><td class="tdtop">' . $langs->trans('FieldNotes') . '</td><td colspan="3">' . dol_escape_htmltag($object->note_public) . '</td></tr>';
    print '</table>';

    // ======= TABLEAU LIGNES =======
    $object->fetchLines();
    print '<h3 style="margin-top:18px;">' . $langs->trans('DPColSpec') . 's / Articles</h3>';
    print '<table class="noborder centpercent">';
    print '<thead><tr class="liste_titre">';
    print '<th class="center width40">N°</th>';
    print '<th>' . $langs->trans('DPColSpec') . '</th>';
    print '<th class="center">' . $langs->trans('DPColUnit') . '</th>';
    print '<th class="right" style="width:120px;">' . $langs->trans('DPColQty') . '</th>';
    print '<th class="center">Produit (ID)</th>';
    print '</tr></thead><tbody>';
    $i = 1; $totalQty = 0;
    foreach ($object->lines as $ln) {
        $totalQty += (float)$ln->quantite;
        print '<tr class="oddeven"><td class="center">' . $i++ . '</td>';
        print '<td>' . dol_escape_htmltag($ln->specification) . '</td>';
        print '<td class="center">' . dol_escape_htmltag($ln->unite) . '</td>';
        print '<td class="right">' . price((float)$ln->quantite, 0, $langs, 0, 0, 0, '') . '</td>';
        print '<td class="center">' . ($ln->fk_product ? (int)$ln->fk_product : '<span class="opacitymedium">—</span>') . '</td>';
        print '</tr>';
    }
    if (empty($object->lines)) print '<tr class="oddeven"><td colspan="5" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
    print '</tbody><tfoot><tr class="liste_total"><td colspan="3" class="right"><b>' . $langs->trans('TotalQty') . '</b></td>'
        . '<td class="right">' . price($totalQty, 0, $langs, 0, 0, 0, '') . '</td><td>&nbsp;</td></tr></tfoot>';
    print '</table>';

    // ======= ZONE ENVOI FOURNISSEUR : generateSupplierLink =======
    if ($permissiontovalidate && (int)$object->status !== ApcDemandePrix::STATUS_CANCELLED) {
        print '<h3 style="margin-top:18px;">' . $langs->trans('DPSendToFournisseur') . '</h3>';
        if ((int)$object->status === ApcDemandePrix::STATUS_SENT) {
            print '<div class="div-info-apc" style="padding:10px; background:#e8f5e9; border:1px solid #a5d6a7; border-radius:6px; margin:8px 0;">';
            print '<b style="color:#2e7d32;">✓ ' . $langs->trans('DPSentDate') . ' : ' . dol_print_date($db->jdate($object->date_envoi), 'dayhour') . '</b><br>';
            print '<small>' . $langs->trans('DPCotationLinkExpire') . ' : ';
            $days = !empty($conf->global->APCLOGISTICS_TOKEN_DAYS) ? (int)$conf->global->APCLOGISTICS_TOKEN_DAYS : 30;
            $exp = strtotime($object->date_envoi) + $days * 86400;
            print dol_print_date($exp, 'day');
            print '</small></div>';
        }
        print '<div style="margin:10px 0; padding:10px; border:1px dashed #ccc; border-radius:6px;">';
        print '<form action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '" method="POST">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="send_to_fournisseur">';
        print '<small class="opacitymedium">' . $langs->trans('DPSendToFournisseurDesc') . '</small><br>';
        print '<input type="submit" class="button" value="' . $langs->trans('DPSendToFournisseur') . '">';
        print '</form></div>';
    }

    // ======= BOUTONS ACTIONS =======
    print '<div class="tabsAction" style="margin-top:24px;">';
    print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/demandeprix_card.php?id=' . $object->id . '&action=generate_pdf&token=' . newToken() . '">' . $langs->trans('PDFGenerateBtn') . '</a>';
    if ($permissiontoedit) print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/demandeprix_card.php?id=' . $object->id . '&action=edit">' . $langs->trans('Modify') . '</a>';
    if ($permissiontodelete) print '<a class="butActionDelete" href="' . DOL_URL_ROOT . '/custom/apclogistics/demandeprix_card.php?id=' . $object->id . '&action=delete">' . $langs->trans('Delete') . '</a>';
    print '</div>';
}

dol_fiche_end();

// ============== TAB : COTATIONS REÇUES ==============
if ($action === 'cotations' && $object->id > 0) {
    $cots = $object->fetchCotations();
    print '<h3 style="margin-top:12px;">' . $langs->trans('DPReceivedCotations') . ' (' . count($cots) . ')</h3>';
    print '<div class="div-table-responsive-no-min"><table class="noborder centpercent liste">';
    print '<tr class="liste_titre">';
    print '<th>' . $langs->trans('DPRef') . ' Cotation</th>';
    print '<th>' . $langs->trans('DPFournisseur') . '</th>';
    print '<th>' . $langs->trans('Date') . '</th>';
    print '<th class="right">Total HT</th>';
    print '<th class="center">' . $langs->trans('FieldStatus') . '</th>';
    print '<th class="center"></th>';
    print '</tr>';
    $tmpCot = new ApcCotation($db);
    foreach ($cots as $c) {
        $tmpCot->id = $c->rowid; $tmpCot->ref = $c->ref; $tmpCot->status = $c->status;
        print '<tr class="oddeven">';
        print '<td>' . $tmpCot->getNomUrl(1) . '</td>';
        print '<td>' . dol_escape_htmltag($c->fournisseur_nom) . '</td>';
        print '<td>' . dol_print_date($db->jdate($c->date_cotation), 'day') . '</td>';
        print '<td class="apc-money">' . price((float)$c->total_ht, 0, $langs, 0, 0, -1, $conf->currency) . '</td>';
        print '<td class="center">' . $tmpCot->getStatusBadge() . '</td>';
        print '<td class="center">';
        if ($permissiontovalidate && (int)$c->status === ApcCotation::STATUS_RETAINED) {
            print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/demandeprix_card.php?id=' . $object->id . '&action=transform_to_bc&cot_id=' . (int)$c->rowid . '&token=' . newToken() . '">' . $langs->trans('CreateBCFromCotation') . '</a>';
        }
        print '</td>';
        print '</tr>';
    }
    if (empty($cots)) print '<tr class="oddeven"><td colspan="6" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
    print '</table></div>';
}

// ============== TAB : LIENS DOCUMENTS ==============
if ($action === 'links' && $object->id > 0) {
    apcPrintLinksTab($db, $langs, $conf, $object);
}

// ============== TAB : HISTORIQUE / AUDIT LOG ==============
if ($action === 'audit' && $object->id > 0) {
    $audits = ApcAuditLog::fetchForEntity($db, 'dp', $object->id, 200);
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
