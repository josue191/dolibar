<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * bonreception_card.php — Fiche Bon de Réception (BR) APC
 * Onglets : Fiche (champs + lignes ajustables) | Historique/Audit | PDF
 * Actions : CRUD, create_from_bc (transformation 1 clic depuis BC), validateAndStockIn, generate_pdf
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
$res = 0; if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php"; if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php"; if (!$res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/class/ApcBonReception.class.php';
require_once __DIR__ . '/class/ApcBonCommande.class.php';
require_once __DIR__ . '/class/ApcAuditLog.class.php';
require_once __DIR__ . '/class/apc_links.lib.php';
require_once __DIR__ . '/class/pdf/pdf_bonreception_apc.modules.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

$id      = (int)GETPOST('id', 'int');
$ref     = GETPOST('ref', 'alpha');
$action  = GETPOST('action', 'aZ');
$confirm = GETPOST('confirm', 'alpha');
$token   = GETPOST('token', 'alpha');
$bc_id   = (int)GETPOST('bc_id', 'int');

$object = new ApcBonReception($db);
$extrafields = new ExtraFields($db);
$extralabels = $extrafields->fetch_name_optionals_label('apclogistics_bonreception');

if ($id > 0 || $ref) {
    $res = ($id > 0) ? $object->fetch($id) : $object->fetch(0, $ref);
    if ($res <= 0) { dol_print_error('', 'ErrorLoadFailed'); exit; }
    $object->fetchLines();
}

$permissiontoread     = !empty($user->rights->apclogistics->bonreception->read);
$permissiontocreate   = !empty($user->rights->apclogistics->bonreception->create);
$permissiontoedit     = !empty($user->rights->apclogistics->bonreception->edit);
$permissiontodelete   = !empty($user->rights->apclogistics->bonreception->delete);
$permissiontovalidate = !empty($user->rights->apclogistics->bonreception->validate);

if (($action === 'create' || $action === 'create_from_bc') && !$permissiontocreate) accessforbidden();
if (empty($permissiontoread) && $action !== 'create' && $action !== 'create_from_bc') accessforbidden();

$error = 0;
$backtopage = DOL_URL_ROOT . '/custom/apclogistics/bonreception_list.php';
$form = new Form($db);
$formother = new FormOther($db);

/*
 * Actions
 */

// ====== ACTION : TRANSFORMATION 1-CLIC DEPUIS UN BC ======
if ($action === 'create_from_bc' && $bc_id > 0 && $permissiontocreate && !$error && $user->valid) {
    $bc = new ApcBonCommande($db);
    if ($bc->fetch($bc_id) > 0) {
        $newBR = ApcBonReception::createFromBonCommande($bc, $user);
        if ($newBR && $newBR->id > 0) {
            setEventMessages($langs->trans('BRFromBCOK') . ' — ' . $newBR->ref, null, 'mesgs');
            header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/bonreception_card.php?id=' . $newBR->id);
            exit;
        } else {
            setEventMessages($newBR ? $newBR->error : 'Erreur création BR', null, 'errors');
        }
    } else {
        setEventMessages('Erreur chargement BC #' . $bc_id, null, 'errors');
    }
}

if ($action === 'add' && $permissiontocreate && !$error && $user->valid && $token && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dr = dol_mktime(12,0,0, GETPOST('date_receptionmonth', 'int'), GETPOST('date_receptionday', 'int'), GETPOST('date_receptionyear', 'int'));
    $object->date_reception = $db->idate($dr);
    $dbl = dol_mktime(12,0,0, GETPOST('date_blmonth', 'int'), GETPOST('date_blday', 'int'), GETPOST('date_blyear', 'int'));
    $object->date_bl = $db->idate($dbl);
    $object->ref_bdl         = GETPOST('ref_bdl', 'alphanohtml');
    $object->ref_facture      = GETPOST('ref_facture', 'alphanohtml');
    $object->fk_boncommande  = (int)GETPOST('fk_boncommande', 'int');
    $object->fk_cotation      = (int)GETPOST('fk_cotation', 'int');
    $object->fournisseur_nom    = GETPOST('fournisseur_nom', 'alphanohtml');
    $object->note_public     = GETPOST('note_public', 'alphanohtml');

    if (empty($object->fournisseur_nom)) { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('BRFournisseur')), null, 'errors'); $error++; }
    if (empty($object->date_reception) || $object->date_reception === '1970-01-01') { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('BRDateRec')), null, 'errors'); $error++; }

    if (!$error) {
        $res = $object->create($user);
        if ($res > 0) {
            $lignes = GETPOST('lines', 'array');
            $no = 1;
            if (is_array($lignes)) {
                foreach ($lignes as $line) {
                    if (empty($line['description']) && (float)$line['qte_commandee'] <= 0 && (float)$line['qte_recue'] <= 0) continue;
                    $ln = new ApcBonReceptionLine($db);
                    $ln->fk_bonreception  = $object->id;
                    $ln->no_ligne = $no++;
                    $ln->fk_boncommande_line = (int)$line['fk_boncommande_line'];
                    $ln->fk_product       = (int)$line['fk_product'];
                    $ln->description      = $line['description'];
                    $ln->unite            = $line['unite'];
                    $ln->qte_commandee    = (float)str_replace(',', '.', $line['qte_commandee']);
                    $ln->qte_recue        = (float)str_replace(',', '.', $line['qte_recue']);
                    $ln->ecart_qte        = $ln->qte_recue - $ln->qte_commandee;
                    $ln->prix_unitaire = (float)str_replace(',', '.', $line['prix_unitaire']);
                    $ln->motif_ecart      = $line['motif_ecart'];
                    $ln->create($user);
                }
            }
            $object->calculateTotals();
            $object->update($user);
            header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/bonreception_card.php?id=' . $object->id);
            exit;
        } else { setEventMessages($object->error, $object->errors, 'errors'); }
    }
}

if ($action === 'update' && $permissiontoedit && !$error && $user->valid && $token) {
    $dr = dol_mktime(12,0,0, GETPOST('date_receptionmonth', 'int'), GETPOST('date_receptionday', 'int'), GETPOST('date_receptionyear', 'int'));
    $object->date_reception = $db->idate($dr);
    $dbl = dol_mktime(12,0,0, GETPOST('date_blmonth', 'int'), GETPOST('date_blday', 'int'), GETPOST('date_blyear', 'int'));
    $object->date_bl = $db->idate($dbl);
    $object->ref_bdl         = GETPOST('ref_bdl', 'alphanohtml');
    $object->ref_facture      = GETPOST('ref_facture', 'alphanohtml');
    $object->fk_boncommande  = (int)GETPOST('fk_boncommande', 'int');
    $object->fk_cotation      = (int)GETPOST('fk_cotation', 'int');
    $object->fournisseur_nom    = GETPOST('fournisseur_nom', 'alphanohtml');
    $object->note_public     = GETPOST('note_public', 'alphanohtml');
    $res = $object->update($user);
    if ($res > 0) {
        $ln = new ApcBonReceptionLine($db);
        $ln->deleteAllForParent($user, $object->id);
        $lignes = GETPOST('lines', 'array');
        $no = 1;
        if (is_array($lignes)) {
            foreach ($lignes as $line) {
                if (empty($line['description']) && (float)$line['qte_commandee'] <= 0 && (float)$line['qte_recue'] <= 0) continue;
                $nl = new ApcBonReceptionLine($db);
                $nl->fk_bonreception  = $object->id;
                $nl->no_ligne = $no++;
                $nl->fk_boncommande_line = (int)$line['fk_boncommande_line'];
                $nl->fk_product       = (int)$line['fk_product'];
                $nl->description      = $line['description'];
                $nl->unite            = $line['unite'];
                $nl->qte_commandee    = (float)str_replace(',', '.', $line['qte_commandee']);
                $nl->qte_recue        = (float)str_replace(',', '.', $line['qte_recue']);
                $nl->ecart_qte        = $nl->qte_recue - $nl->qte_commandee;
                $nl->prix_unitaire = (float)str_replace(',', '.', $line['prix_unitaire']);
                $nl->motif_ecart      = $line['motif_ecart'];
                $nl->create($user);
            }
        }
        $object->calculateTotals();
        $object->update($user);
        header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/bonreception_card.php?id=' . $object->id);
        exit;
    } else { setEventMessages($object->error, $object->errors, 'errors'); }
}

if ($action === 'confirm_delete' && $confirm === 'yes' && $permissiontodelete && $token) {
    $res = $object->delete($user);
    if ($res > 0) { header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/bonreception_list.php'); exit; }
    else setEventMessages($object->error, $object->errors, 'errors');
}

if ($action === 'generate_pdf' && $id > 0) {
    $g = new pdf_bonreception_apc($db);
    $g->write_file($object, $langs, '', 'I');
    exit;
}

if ($action === 'validate_stock_in' && $permissiontovalidate && $id > 0 && $token && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $livreurNom = GETPOST('livreur_nom', 'alphanohtml');
    $livreurFct = GETPOST('livreur_fct', 'alphanohtml');
    $livreurCni = GETPOST('livreur_cni', 'alphanohtml');
    if (empty($livreurNom)) { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('BRSigLivreurNom')), null, 'errors'); $error++; }
    if (empty($livreurCni)) { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('BRSigLivreurCNI')), null, 'errors'); $error++; }
    if (!$error) {
        $res = $object->validateAndStockIn($user, $livreurNom, $livreurFct, $livreurCni);
        if ($res > 0) {
            setEventMessages($langs->trans('BRValidatedStockInOK'), null, 'mesgs');
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
        }
        header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/bonreception_card.php?id=' . $object->id);
        exit;
    }
}

/*
 * View
 */
$title = ($action === 'create' || $action === 'create_from_bc') ? $langs->trans('NewBR') : ($object->ref ?: $langs->trans('BRTitle'));
llxHeader('', $title, '', '', 0, 0, array('/custom/apclogistics/js/apclogistics.js'), array('/custom/apclogistics/css/apclogistics.css'));

$head = array();
$head[0][0] = DOL_URL_ROOT . '/custom/apclogistics/bonreception_card.php?id=' . ($object->id ?: 0) . '&action=' . ($action ?: 'view');
$head[0][1] = $langs->trans('TabCard');
$head[0][2] = 'tabcard';
$head[1][0] = DOL_URL_ROOT . '/custom/apclogistics/bonreception_card.php?id=' . ($object->id ?: 0) . '&action=links';
$head[1][1] = $langs->trans('TabLinks');
$head[1][2] = 'tablinks';
$head[2][0] = DOL_URL_ROOT . '/custom/apclogistics/bonreception_card.php?id=' . ($object->id ?: 0) . '&action=audit';
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
    print '<tr><td class="titlefieldcreate fieldrequired">' . $langs->trans('BRRef') . '</td><td>';
    if ($editing) print $obj->ref; else print '<span class="opacitymedium">(Généré automatiquement à l\'enregistrement)</span>';
    print '</td>';
    print '<td class="titlefield">' . $langs->trans('FieldStatus') . '</td><td>'
        . ($editing ? $obj->getStatusBadge() : '<span class="opacitymedium">' . $langs->trans('StatusDraft') . '</span>') . '</td></tr>';

    print '<tr><td class="fieldrequired">' . $langs->trans('BRDateRec') . '</td><td>'
        . $form->select_date($obj->date_reception ? $obj->date_reception : -1, 'date_reception', 0, 0, 1, '', 1, 0) . '</td>';
    print '<td>' . $langs->trans('BRDateBL') . '</td><td>'
        . $form->select_date($obj->date_bl ? $obj->date_bl : -1, 'date_bl', 0, 0, 1, '', 1, 0) . '</td></tr>';

    print '<tr><td class="fieldrequired">' . $langs->trans('BRFournisseur') . '</td><td>'
        . '<input type="text" size="60" name="fournisseur_nom" value="' . dol_escape_htmltag($obj->fournisseur_nom) . '"></td>';
    print '<td>' . $langs->trans('BRRefBL') . '</td><td>'
        . '<input type="text" size="30" name="ref_bdl" value="' . dol_escape_htmltag($obj->ref_bdl) . '"></td></tr>';

    print '<tr><td>' . $langs->trans('BRRefFacture') . '</td><td>'
        . '<input type="text" size="30" name="ref_facture" value="' . dol_escape_htmltag($obj->ref_facture) . '"></td>';
    print '<td>&nbsp;</td><td>&nbsp;</td></tr>';

    print '<tr><td>' . $langs->trans('BRFKBC') . '</td><td>'
        . '<input type="number" min="0" step="1" size="12" name="fk_boncommande" value="' . ((int)$obj->fk_boncommande ? (int)$obj->fk_boncommande : '') . '">';
    if (!empty($obj->fk_boncommande)) {
        $bc = new ApcBonCommande($db);
        if ($bc->fetch($obj->fk_boncommande) > 0) {
            print ' &nbsp; <span class="opacitymedium">→ ' . $bc->getNomUrl(1) . '</span>';
        }
    }
    print '</td>';
    print '<td>' . $langs->trans('BRFKCotation') . ' (optionnel)</td><td>'
        . '<input type="number" min="0" step="1" size="12" name="fk_cotation" value="' . ((int)$obj->fk_cotation ? (int)$obj->fk_cotation : '') . '"></td></tr>';

    print '<tr><td class="tdtop">' . $langs->trans('FieldNotes') . '</td><td colspan="3">'
        . '<textarea rows="3" cols="80" name="note_public">' . dol_escape_htmltag($obj->note_public) . '</textarea></td></tr>';
    print '</table>';

    // ========== SECTION LIGNES ==========
    print '<h3 style="margin-top:18px;">Lignes Bon de Réception</h3>';
    print '<table class="noborder centpercent" id="apc-br-lines-table">';
    print '<thead><tr class="liste_titre">';
    print '<th style="width:40px;">N°</th>';
    print '<th style="min-width:220px;">' . $langs->trans('BRColDesc') . ' *</th>';
    print '<th style="width:70px;">' . $langs->trans('BRColUnit') . '</th>';
    print '<th style="width:90px;" class="right">' . $langs->trans('BRColQC') . '</th>';
    print '<th style="width:90px;" class="right">' . $langs->trans('BRColQR') . '</th>';
    print '<th style="width:80px;" class="right">' . $langs->trans('BRColEcart') . '</th>';
    print '<th style="width:110px;" class="right">' . $langs->trans('BRColPU') . ' HT</th>';
    print '<th style="width:120px;" class="right">' . $langs->trans('BRColTotal') . ' HT</th>';
    print '<th style="width:80px;" class="center">Produit</th>';
    print '<th style="min-width:140px;">' . $langs->trans('BRColMotifEcart') . '</th>';
    print '<th style="width:40px;">&nbsp;</th>';
    print '</tr></thead>';
    print '<tbody id="apc-br-lines-body">';

    $lines = $editing && !empty($obj->lines) ? $obj->lines : array((object)array());
    $idx = 0;
    foreach ($lines as $ln) {
        $desc = isset($ln->description) ? $ln->description : '';
        $unit = isset($ln->unite) ? $ln->unite : '';
        $qC  = isset($ln->qte_commandee) ? (float)$ln->qte_commandee : '';
        $qR  = isset($ln->qte_recue) ? (float)$ln->qte_recue : '';
        $pu   = isset($ln->prix_unitaire) ? (float)$ln->prix_unitaire : '';
        $fkpr = isset($ln->fk_product) ? (int)$ln->fk_product : 0;
        $fkbl = isset($ln->fk_boncommande_line) ? (int)$ln->fk_boncommande_line : 0;
        $motif = isset($ln->motif_ecart) ? $ln->motif_ecart : '';
        $tot  = $qR !== '' && $pu !== '' ? number_format((float)$qR * (float)$pu, 2, ',', ' ') : '';
        $ecart = ($qR !== '' && $qC !== '' ? price((float)$qR - (float)$qC, 0, $langs, 0, 0, 0, '') : '');
        print '<tr class="apc-br-line-row">';
        print '<td class="apc-br-no center">' . ($idx+1) . '</td>';
        print '<input type="hidden" name="lines[' . $idx . '][fk_boncommande_line]" value="' . $fkbl . '">';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][description]" value="' . dol_escape_htmltag($desc) . '"></td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][unite]" value="' . dol_escape_htmltag($unit) . '"></td>';
        print '<td><input type="text" class="flat width100 right apc-br-qc" name="lines[' . $idx . '][qte_commandee]" value="' . ($qC !== '' ? dol_escape_htmltag($qC) : '') . '"></td>';
        print '<td><input type="text" class="flat width100 right apc-br-qr" name="lines[' . $idx . '][qte_recue]" value="' . ($qR !== '' ? dol_escape_htmltag($qR) : '') . '"></td>';
        print '<td class="right apc-br-ecart">' . $ecart . '</td>';
        print '<td><input type="text" class="flat width100 right apc-br-pu" name="lines[' . $idx . '][prix_unitaire]" value="' . ($pu !== '' ? dol_escape_htmltag($pu) : '') . '"></td>';
        print '<td class="right apc-br-total">' . $tot . '</td>';
        print '<td><input type="number" min="0" step="1" class="flat width100" name="lines[' . $idx . '][fk_product]" value="' . ($fkpr ? $fkpr : '') . '"></td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][motif_ecart]" value="' . dol_escape_htmltag($motif) . '"></td>';
        print '<td><button type="button" class="button apc-br-rm-line" style="padding:2px 6px;">-</button></td>';
        print '</tr>';
        $idx++;
    }
    print '</tbody></table>';

    print '<div style="margin-top:10px;">';
    print '<button type="button" id="apc-br-add-line" class="button small">+ Ajouter une ligne</button>';
    print '</div>';

    print '<div class="tabsAction" style="margin-top:24px;">';
    print '<input type="submit" class="button" value="' . ($editing ? $langs->trans('Save') : $langs->trans('Create')) . '">';
    print ' <a class="button button-cancel" href="' . DOL_URL_ROOT . '/custom/apclogistics/bonreception_list.php">' . $langs->trans('Cancel') . '</a>';
    print '</div>';
    print '</form>';

    // ===== JS : ajout/suppression lignes + calcul ecart et totaux
    print '<script type="text/javascript">
    $(document).ready(function() {
        function APCNumFmt(n){ n = Number(n||0).toFixed(2); var p = n.split("."); p[0]=p[0].replace(/\B(?=(\d{3})+(?!\d))/g," "); return p.join(","); }
        function APCIntFmt(n){ return Number(n||0).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g," "); }
        function recalcLignes() {
            $(".apc-br-line-row").each(function(){
                var qc = parseFloat($(this).find(".apc-br-qc").val().replace(/\s/g,"").replace(",","."))||0;
                var qr = parseFloat($(this).find(".apc-br-qr").val().replace(/\s/g,"").replace(",","."))||0;
                var pu = parseFloat($(this).find(".apc-br-pu").val().replace(/\s/g,"").replace(",","."))||0;
                $(this).find(".apc-br-ecart").text(APCIntFmt(qr - qc));
                $(this).find(".apc-br-total").text(APCNumFmt(qr*pu));
            });
        }
        function renumber() { $(".apc-br-no").each(function(i){ $(this).text(i+1); }); }
        $(document).on("input", ".apc-br-qc,.apc-br-qr,.apc-br-pu", recalcLignes);
        $("#apc-br-add-line").on("click", function() {
            var n = $("#apc-br-lines-body tr").length;
            var html = "<tr class=\"apc-br-line-row\">"
                + "<td class=\"apc-br-no center\">"+(n+1)+"</td>"
                + "<input type=\"hidden\" name=\"lines["+n+"][fk_boncommande_line]\" value=\"0\">"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][description]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][unite]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100 right apc-br-qc\" name=\"lines["+n+"][qte_commandee]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100 right apc-br-qr\" name=\"lines["+n+"][qte_recue]\"></td>"
                + "<td class=\"right apc-br-ecart\">0</td>"
                + "<td><input type=\"text\" class=\"flat width100 right apc-br-pu\" name=\"lines["+n+"][prix_unitaire]\"></td>"
                + "<td class=\"right apc-br-total\">0,00</td>"
                + "<td><input type=\"number\" min=\"0\" step=\"1\" class=\"flat width100\" name=\"lines["+n+"][fk_product]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][motif_ecart]\"></td>"
                + "<td><button type=\"button\" class=\"button apc-br-rm-line\" style=\"padding:2px 6px;\">-</button></td>"
                + "</tr>";
            $("#apc-br-lines-body").append(html);
            renumber(); recalcLignes();
        });
        $(document).on("click", ".apc-br-rm-line", function() {
            if ($("#apc-br-lines-body tr").length > 1) { $(this).closest("tr").remove(); renumber(); recalcLignes(); }
        });
        recalcLignes();
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
    print '<tr><td class="titlefield">' . $langs->trans('BRRef') . '</td><td class="valeur"><b>' . $object->ref . '</b></td>'
        . '<td class="titlefield">' . $langs->trans('FieldStatus') . '</td><td class="valeur">' . $object->getStatusBadge() . '</td></tr>';
    print '<tr><td>' . $langs->trans('BRDateRec') . '</td><td>' . dol_print_date($db->jdate($object->date_reception), 'day') . '</td>'
        . '<td>' . $langs->trans('BRDateBL') . '</td><td>'
        . ($object->date_bl ? dol_print_date($db->jdate($object->date_bl), 'day') : '<span class="opacitymedium">—</span>') . '</td></tr>';
    print '<tr><td>' . $langs->trans('BRFournisseur') . '</td><td><b>' . dol_escape_htmltag($object->fournisseur_nom) . '</b></td>'
        . '<td>' . $langs->trans('BRRefBL') . '</td><td>'
        . ($object->ref_bdl ? dol_escape_htmltag($object->ref_bdl) : '<span class="opacitymedium">—</span>') . '</td></tr>';
    print '<tr><td>' . $langs->trans('BRRefFacture') . '</td><td>'
        . ($object->ref_facture ? dol_escape_htmltag($object->ref_facture) : '<span class="opacitymedium">—</span>')
        . '<td>&nbsp;</td><td>&nbsp;</td></tr>';

    if ($object->fk_boncommande > 0) {
        $bc = new ApcBonCommande($db);
        if ($bc->fetch($object->fk_boncommande) > 0) {
            print '<tr><td>' . $langs->trans('BRFKBC') . '</td><td>' . $bc->getNomUrl(1) . '</td>';
            print '<td>' . $langs->trans('BRFKCotation') . '</td><td>';
            if ($object->fk_cotation > 0) {
                $cot = new ApcCotation($db);
                if ($cot->fetch($object->fk_cotation) > 0) print $cot->getNomUrl(1); else print '<span class="opacitymedium">—</span>';
            } else print '<span class="opacitymedium">—</span>';
            print '</td></tr>';
        }
    }
    if (!empty($object->note_public)) print '<tr><td class="tdtop">' . $langs->trans('FieldNotes') . '</td><td colspan="3">' . dol_escape_htmltag($object->note_public) . '</td></tr>';
    print '</table>';

    // ======= TABLEAU LIGNES =======
    $object->fetchLines();
    $object->calculateTotals();
    print '<h3 style="margin-top:18px;">Lignes Bon de Réception</h3>';
    print '<table class="noborder centpercent">';
    print '<thead><tr class="liste_titre">';
    print '<th class="center width40">N°</th>';
    print '<th>' . $langs->trans('BRColDesc') . '</th>';
    print '<th class="center">' . $langs->trans('BRColUnit') . '</th>';
    print '<th class="right" style="width:100px;">' . $langs->trans('BRColQC') . '</th>';
    print '<th class="right" style="width:100px;">' . $langs->trans('BRColQR') . '</th>';
    print '<th class="right" style="width:100px;">' . $langs->trans('BRColEcart') . '</th>';
    print '<th class="right" style="width:120px;">' . $langs->trans('BRColPU') . ' HT</th>';
    print '<th class="right" style="width:140px;">' . $langs->trans('BRColTotal') . ' HT</th>';
    print '<th class="center">Produit</th>';
    print '<th>' . $langs->trans('BRColMotifEcart') . '</th>';
    print '</tr></thead><tbody>';
    $i = 1; $tQC=0; $tQR=0; $tTot=0;
    foreach ($object->lines as $ln) {
        $tQC += (float)$ln->qte_commandee;
        $tQR += (float)$ln->qte_recue;
        $totL = (float)$ln->qte_recue * (float)$ln->prix_unitaire;
        $tTot += $totL;
        $ecart = (float)$ln->qte_recue - (float)$ln->qte_commandee;
        $eCls = '';
        if ($ecart < 0) $eCls = 'apc-neg';
        if ($ecart > 0) $eCls = 'apc-pos';
        print '<tr class="oddeven"><td class="center">' . $i++ . '</td>';
        print '<td>' . dol_escape_htmltag($ln->description) . '</td>';
        print '<td class="center">' . dol_escape_htmltag($ln->unite) . '</td>';
        print '<td class="right">' . price((float)$ln->qte_commandee, 0, $langs, 0, 0, 0, '') . '</td>';
        print '<td class="right">' . price((float)$ln->qte_recue, 0, $langs, 0, 0, 0, '') . '</td>';
        print '<td class="right ' . $eCls . '">' . price($ecart, 0, $langs, 0, 0, 0, '') . '</td>';
        print '<td class="apc-money">' . price((float)$ln->prix_unitaire, 0, $langs, 0, 0, -1, $conf->currency) . '</td>';
        print '<td class="apc-money">' . price($totL, 0, $langs, 0, 0, -1, $conf->currency) . '</td>';
        print '<td class="center">' . ($ln->fk_product ? (int)$ln->fk_product : '<span class="opacitymedium">—</span>') . '</td>';
        print '<td>' . ($ln->motif_ecart ? dol_escape_htmltag($ln->motif_ecart) : '<span class="opacitymedium">—</span>') . '</td>';
        print '</tr>';
    }
    if (empty($object->lines)) print '<tr class="oddeven"><td colspan="10" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
    print '</tbody><tfoot>';
    print '<tr class="liste_total"><td colspan="3" class="right"><b>' . $langs->trans('BRTQC') . '</b></td>'
        . '<td class="right"><b>' . price($tQC, 0, $langs, 0, 0, 0, '') . '</b></td>'
        . '<td class="right"><b>' . price($tQR, 0, $langs, 0, 0, 0, '') . '</b></td>'
        . '<td class="right ' . ($tQR - $tQC < 0 ? 'apc-neg' : ($tQR - $tQC > 0 ? 'apc-pos' : '')) . '"><b>' . price($tQR - $tQC, 0, $langs, 0, 0, 0, '') . '</b></td>'
        . '<td>&nbsp;</td><td class="right apc-money"><b>' . price($tTot, 0, $langs, 0, 0, -1, $conf->currency) . '</b></td><td colspan="2">&nbsp;</td></tr>';
    print '</tfoot></table>';

    // ======= VALIDATION + ENTREE STOCK (2 signatures : Recepteur APC + Livreur) =======
    if ($permissiontovalidate && (int)$object->status !== ApcBonReception::STATUS_VALIDATED
        && (int)$object->status !== ApcBonReception::STATUS_CANCELLED
        && (int)$object->status !== ApcBonReception::STATUS_CLOSED) {
        print '<h3 style="margin-top:22px;">Valider la réception & déclarer l\'entrée stock</h3>';
        print '<div style="padding:14px; border:1px dashed #ccc; border-radius:6px;">';
        print '<form action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '" method="POST">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="validate_stock_in">';
        print '<b>' . $langs->trans('BRStockInExplain') . '</b><br><br>';
        print '<table class="noborder" style="width:100%;"><tr>';
        print '<td style="width:50%; vertical-align:top;">';
        print '<b>' . $langs->trans('BRSigRecepteur') . ' (APC)</b><br>';
        print '<span class="opacitymedium">' . $langs->trans('BRSigRecepteurDesc') . '</span><br>';
        print $langs->trans('Nom') . ' : <b>' . dol_escape_htmltag($user->getFullName($langs)) . '</b><br>';
        print $langs->trans('Function') . ' : <b>' . dol_escape_htmltag($user->poste) . '</b>';
        print '</td><td style="vertical-align:top;">';
        print '<b>' . $langs->trans('BRSigLivreur') . ' *</b><br>';
        print $langs->trans('Nom') . ' * <input type="text" name="livreur_nom" size="28" required> ';
        print $langs->trans('Function') . ' <input type="text" name="livreur_fct" size="22"><br>';
        print $langs->trans('BRSigLivreurCNI') . ' * <input type="text" name="livreur_cni" size="22" required>';
        print '</td></tr></table>';
        print '<div style="margin-top:10px;"><input type="submit" class="button" value="' . $langs->trans('BRValidateStockInBtn') . '"></div>';
        print '</form></div>';
    }

    // ======= SIGNATURES DEJA APPOSEES =======
    print '<h3 style="margin-top:18px;">Signatures enregistrées</h3>';
    print '<table class="noborder centpercent">';
    print '<thead><tr class="liste_titre"><th>' . $langs->trans('BRSigRecepteur') . ' APC</th><th>' . $langs->trans('BRSigLivreur') . ' (Fournisseur)</th></tr></thead>';
    print '<tr><td class="center" style="padding:12px; border:1px solid #ddd;">'
        . (empty($object->reception_date_sig) ? '<span class="opacitymedium">—</span>'
            : ('<b>' . dol_escape_htmltag($object->reception_nom) . '</b><br><i>' . dol_escape_htmltag($object->reception_fonction) . '</i><br>Signé le ' . dol_print_date($db->jdate($object->reception_date_sig), 'day')))
        . '</td><td class="center" style="padding:12px; border:1px solid #ddd;">'
        . (empty($object->livraison_date_sig) ? '<span class="opacitymedium">—</span>'
            : ('<b>' . dol_escape_htmltag($object->livraison_nom) . '</b><br><i>' . dol_escape_htmltag($object->livraison_fonction) . '</i>'
                . (!empty($object->livraison_cni) ? '<br>CNI : ' . dol_escape_htmltag($object->livraison_cni) : '')
                . '<br>Signé le ' . dol_print_date($db->jdate($object->livraison_date_sig), 'day')))
        . '</td></tr></table>';

    // ======= BOUTONS ACTIONS =======
    print '<div class="tabsAction" style="margin-top:24px;">';
    print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/bonreception_card.php?id=' . $object->id . '&action=generate_pdf&token=' . newToken() . '">' . $langs->trans('PDFGenerateBtn') . '</a>';
    if ($permissiontoedit && (int)$object->status !== ApcBonReception::STATUS_VALIDATED) print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/bonreception_card.php?id=' . $object->id . '&action=edit">' . $langs->trans('Modify') . '</a>';
    if ($permissiontodelete && (int)$object->status !== ApcBonReception::STATUS_VALIDATED) print '<a class="butActionDelete" href="' . DOL_URL_ROOT . '/custom/apclogistics/bonreception_card.php?id=' . $object->id . '&action=delete">' . $langs->trans('Delete') . '</a>';
    print '</div>';
}

dol_fiche_end();

// ============== TAB : HISTORIQUE / AUDIT LOG ==============
if ($action === 'links' && $object->id > 0) {
    apcPrintLinksTab($db, $langs, $conf, $object);
}

if ($action === 'audit' && $object->id > 0) {
    $audits = ApcAuditLog::fetchForEntity($db, 'br', $object->id, 200);
    print '<h3 style="margin-top:12px;">' . $langs->trans('TabHistory') . '</h3>';
    print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
    print '<tr class="liste_titre"><th class="center width160">' . $langs->trans('Date') . '</th><th>' . $langs->trans('User') . '</th><th>' . $langs->trans('Action') . '</th><th>' . $langs->trans('Details') . '</th></tr>';
    if ($audits) {
        foreach ($audits as $a) {
            require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
            $u = new User($db);
            $uName = '';
            if (!empty($a->fk_user) && $u->fetch($a->fk_user) > 0) $uName = $u->getFullName($langs);
            print '<tr class="oddeven"><td>' . dol_print_date($db->jdate($a->date_action), 'dayhour') . '</td>';
            print '<td>' . dol_escape_htmltag($uName) . '</td><td><b>' . dol_escape_htmltag($a->action_type) . '</b></td>';
            print '<td>' . dol_escape_htmltag($a->action_details ?? '') . '</td></tr>';
        }
    } else {
        print '<tr class="oddeven"><td colspan="4" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
    }
    print '</table></div>';
}

llxFooter();
$db->close();
