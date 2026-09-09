<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * boncommande_card.php — Fiche Bon de Commande (BC) APC
 * Onglets : Fiche (champs + lignes) | Historique/Audit | PDF
 * Actions : CRUD, create_from_cotation (transformation 1 clic), sign(2 niveaux APC), generate_pdf
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
$res = 0; if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php"; if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php"; if (!$res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/class/ApcBonCommande.class.php';
require_once __DIR__ . '/class/ApcCotation.class.php';
require_once __DIR__ . '/class/apc_links.lib.php';
require_once __DIR__ . '/class/pdf/pdf_boncommande_apc.modules.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

$id      = (int)GETPOST('id', 'int');
$ref     = GETPOST('ref', 'alpha');
$action  = GETPOST('action', 'aZ');
$confirm = GETPOST('confirm', 'alpha');
$token   = GETPOST('token', 'alpha');
$cotation_id = (int)GETPOST('cotation_id', 'int');

$object = new ApcBonCommande($db);
$extrafields = new ExtraFields($db);
$extralabels = $extrafields->fetch_name_optionals_label('apclogistics_boncommande');

if ($id > 0 || $ref) {
    $res = ($id > 0) ? $object->fetch($id) : $object->fetch(0, $ref);
    if ($res <= 0) { dol_print_error('', 'ErrorLoadFailed'); exit; }
    $object->fetchLines();
}

$permissiontoread     = !empty($user->rights->apclogistics->boncommande->read);
$permissiontocreate   = !empty($user->rights->apclogistics->boncommande->create);
$permissiontoedit     = !empty($user->rights->apclogistics->boncommande->edit);
$permissiontodelete   = !empty($user->rights->apclogistics->boncommande->delete);
$permissiontovalidate = !empty($user->rights->apclogistics->boncommande->validate);

if (($action === 'create' || $action === 'create_from_cotation') && !$permissiontocreate) accessforbidden();
if (empty($permissiontoread) && $action !== 'create' && $action !== 'create_from_cotation') accessforbidden();

$error = 0;
$backtopage = DOL_URL_ROOT . '/custom/apclogistics/boncommande_list.php';
$form = new Form($db);
$formother = new FormOther($db);

/*
 * Actions
 */

// ====== ACTION : TRANSFORMATION 1-CLIC DEPUIS UNE COTATION RETENUE ======
if ($action === 'create_from_cotation' && $cotation_id > 0 && $permissiontocreate && !$error && $user->valid) {
    $cot = new ApcCotation($db);
    if ($cot->fetch($cotation_id) > 0) {
        $newBC = ApcBonCommande::createFromCotation($cot, $user, $user);
        if ($newBC && $newBC->id > 0) {
            setEventMessages($langs->trans('BCFromCotationOK') . ' — ' . $newBC->ref, null, 'mesgs');
            header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/boncommande_card.php?id=' . $newBC->id);
            exit;
        } else {
            setEventMessages($newBC ? $newBC->error : 'Erreur création BC', null, 'errors');
        }
    } else {
        setEventMessages('Erreur chargement cotation #' . $cotation_id, null, 'errors');
    }
}

if ($action === 'add' && $permissiontocreate && !$error && $user->valid && $token && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $object->date_cmde = dol_mktime(12,0,0, GETPOST('date_bcmonth', 'int'), GETPOST('date_bcday', 'int'), GETPOST('date_bcyear', 'int'));
    $object->date_cmde = $db->idate($object->date_cmde);
    $dl = dol_mktime(12,0,0, GETPOST('date_livraison_prevuemonth', 'int'), GETPOST('date_livraison_prevueday', 'int'), GETPOST('date_livraison_prevueyear', 'int'));
    $object->date_livraison = $db->idate($dl);
    $object->fournisseur_nom      = GETPOST('fournisseur_nom', 'alphanohtml');
    $object->fournisseur_adresse  = GETPOST('fournisseur_adresse', 'alphanohtml');
    $object->fournisseur_tel      = GETPOST('fournisseur_tel', 'alphanohtml');
    $object->fournisseur_email    = GETPOST('fournisseur_email', 'alpha');
    $object->fournisseur_contact  = GETPOST('fournisseur_contact', 'alphanohtml');
    $object->lieu_livraison       = GETPOST('lieu_livraison', 'alphanohtml');
    $object->conditions_paiement = GETPOST('conditions_reglement', 'alphanohtml');
    $object->delai_reglement_jours = (int)GETPOST('delai_reglement_jours', 'int');
    $object->taux_tva_applicable = (float)str_replace(',', '.', GETPOST('taux_tva_applicable', 'alpha'));
    $object->fk_cotation    = (int)GETPOST('fk_cotation', 'int');
    $object->note_public = GETPOST('note_public', 'alphanohtml');

    if (empty($object->fournisseur_nom)) { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('BCFournisseur')), null, 'errors'); $error++; }
    if (empty($object->date_cmde) || $object->date_cmde === '1970-01-01') { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('BCDateBC')), null, 'errors'); $error++; }

    if (!$error) {
        $res = $object->create($user);
        if ($res > 0) {
            $lignes = GETPOST('lines', 'array');
            $no = 1;
            if (is_array($lignes)) {
                foreach ($lignes as $line) {
                    if (empty($line['description']) && (float)$line['quantite'] <= 0) continue;
                    $ln = new ApcBonCommandeLine($db);
                    $ln->fk_boncommande  = $object->id;
                    $ln->no_ligne = $no++;
                    $ln->description     = $line['description'];
                    $ln->unite           = $line['unite'];
                    $ln->quantite        = (float)str_replace(',', '.', $line['quantite']);
                    $ln->prix_unitaire = (float)str_replace(',', '.', $line['prix_unitaire_ht']);
                    $ln->qte_restante    = $ln->quantite;
                    $ln->fk_product    = (int)$line['fk_product'];
                    $ln->prix_total_ligne = round($ln->prix_unitaire * $ln->quantite, 2);
                    $ln->tva_tx          = (float)$object->taux_tva_applicable;
                    $ln->create($user);
                }
            }
            $object->calculateTotals();
            $object->update($user);
            header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/boncommande_card.php?id=' . $object->id);
            exit;
        } else { setEventMessages($object->error, $object->errors, 'errors'); }
    }
}

if ($action === 'update' && $permissiontoedit && !$error && $user->valid && $token) {
    $object->date_cmde = dol_mktime(12,0,0, GETPOST('date_bcmonth', 'int'), GETPOST('date_bcday', 'int'), GETPOST('date_bcyear', 'int'));
    $object->date_cmde = $db->idate($object->date_cmde);
    $dl = dol_mktime(12,0,0, GETPOST('date_livraison_prevuemonth', 'int'), GETPOST('date_livraison_prevueday', 'int'), GETPOST('date_livraison_prevueyear', 'int'));
    $object->date_livraison = $db->idate($dl);
    $object->fournisseur_nom      = GETPOST('fournisseur_nom', 'alphanohtml');
    $object->fournisseur_adresse  = GETPOST('fournisseur_adresse', 'alphanohtml');
    $object->fournisseur_tel      = GETPOST('fournisseur_tel', 'alphanohtml');
    $object->fournisseur_email    = GETPOST('fournisseur_email', 'alpha');
    $object->fournisseur_contact  = GETPOST('fournisseur_contact', 'alphanohtml');
    $object->lieu_livraison       = GETPOST('lieu_livraison', 'alphanohtml');
    $object->conditions_paiement = GETPOST('conditions_reglement', 'alphanohtml');
    $object->delai_reglement_jours = (int)GETPOST('delai_reglement_jours', 'int');
    $object->taux_tva_applicable = (float)str_replace(',', '.', GETPOST('taux_tva_applicable', 'alpha'));
    $object->fk_cotation    = (int)GETPOST('fk_cotation', 'int');
    $object->note_public = GETPOST('note_public', 'alphanohtml');
    $res = $object->update($user);
    if ($res > 0) {
        $ln = new ApcBonCommandeLine($db);
        $ln->deleteAllForParent($user, $object->id);
        $lignes = GETPOST('lines', 'array');
        $no = 1;
        if (is_array($lignes)) {
            foreach ($lignes as $line) {
                if (empty($line['description']) && (float)$line['quantite'] <= 0) continue;
                $nl = new ApcBonCommandeLine($db);
                $nl->fk_boncommande  = $object->id;
                $nl->no_ligne = $no++;
                $nl->description     = $line['description'];
                $nl->unite           = $line['unite'];
                $nl->quantite        = (float)str_replace(',', '.', $line['quantite']);
                $nl->prix_unitaire = (float)str_replace(',', '.', $line['prix_unitaire_ht']);
                $nl->qte_restante    = $nl->quantite;
                $nl->fk_product    = (int)$line['fk_product'];
                $nl->prix_total_ligne = round($nl->prix_unitaire * $nl->quantite, 2);
                $nl->tva_tx          = (float)$object->taux_tva_applicable;
                $nl->create($user);
            }
        }
        $object->calculateTotals();
        $object->update($user);
        header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/boncommande_card.php?id=' . $object->id);
        exit;
    } else { setEventMessages($object->error, $object->errors, 'errors'); }
}

if ($action === 'confirm_delete' && $confirm === 'yes' && $permissiontodelete && $token) {
    $res = $object->delete($user);
    if ($res > 0) { header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/boncommande_list.php'); exit; }
    else setEventMessages($object->error, $object->errors, 'errors');
}

if ($action === 'generate_pdf' && $id > 0) {
    $g = new pdf_boncommande_apc($db);
    $g->write_file($object, $langs, '', 'I');
    exit;
}

if ($action === 'sign' && $permissiontovalidate && $id > 0 && $token) {
    $level = (int)GETPOST('level', 'int');
    $nom = GETPOST('nom_sig', 'alphanohtml');
    $fct = GETPOST('fct_sig', 'alphanohtml');
    if ($level === 0) {
        $object->logisticien_nom      = $nom;
        $object->logisticien_fonction = $fct;
        $object->date_signature_log   = $db->idate(dol_now());
        $object->fk_user_logisticien  = $user->id;
        if ((int)$object->status < ApcBonCommande::STATUS_DRAFT) { $object->status = ApcBonCommande::STATUS_ORDERED; $object->lockRef(); }
        $res = $object->update($user);
        ApcAuditLog::log($object->element, $object->id, 'SIGN_LOG', $user->id, null, null, 'Signature Logisticien APC');
    } elseif ($level === 1) {
        $object->coordinateur_nom      = $nom;
        $object->coordinateur_fonction = $fct;
        $object->date_signature_coord  = $db->idate(dol_now());
        $object->fk_user_coordinateur  = $user->id;
        $res = $object->update($user);
        ApcAuditLog::log($object->element, $object->id, 'SIGN_COORD', $user->id, null, null, 'Signature Coordinateur APC');
    } elseif ($level === 2) {
        $object->fournisseur_sig_nom  = $nom;
        $object->fournisseur_sig_fct  = $fct;
        $object->fournisseur_sig_date = $db->idate(dol_now());
        $object->fournisseur_sig_ip   = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
        $res = $object->update($user);
        ApcAuditLog::log($object->element, $object->id, 'SIGN_FOURN', $user->id, null, null, 'Signature Fournisseur saisie manuelle');
    }
    if ($res > 0) { setEventMessages($langs->trans('SignOK'), null, 'mesgs'); }
    else setEventMessages($object->error, $object->errors, 'errors');
    header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/boncommande_card.php?id=' . $object->id);
    exit;
}

/*
 * View
 */
$title = ($action === 'create' || $action === 'create_from_cotation') ? $langs->trans('NewBonCommande') : ($object->ref ?: $langs->trans('BCTitle'));
llxHeader('', $title, '', '', 0, 0, array('/custom/apclogistics/js/apclogistics.js'), array('/custom/apclogistics/css/apclogistics.css'));

$head = array();
$head[0][0] = DOL_URL_ROOT . '/custom/apclogistics/boncommande_card.php?id=' . ($object->id ?: 0) . '&action=' . ($action ?: 'view');
$head[0][1] = $langs->trans('TabCard');
$head[0][2] = 'tabcard';
$head[1][0] = DOL_URL_ROOT . '/custom/apclogistics/boncommande_card.php?id=' . ($object->id ?: 0) . '&action=links';
$head[1][1] = $langs->trans('TabLinks');
$head[1][2] = 'tablinks';
$head[2][0] = DOL_URL_ROOT . '/custom/apclogistics/boncommande_card.php?id=' . ($object->id ?: 0) . '&action=audit';
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
    print '<tr><td class="titlefieldcreate fieldrequired">' . $langs->trans('BCRef') . '</td><td>';
    if ($editing) print $obj->ref; else print '<span class="opacitymedium">(Généré automatiquement à l\'enregistrement)</span>';
    print '</td>';
    print '<td class="titlefield">' . $langs->trans('FieldStatus') . '</td><td>'
        . ($editing ? $obj->getStatusBadge() : '<span class="opacitymedium">' . $langs->trans('StatusDraft') . '</span>') . '</td></tr>';

    print '<tr><td class="fieldrequired">' . $langs->trans('BCDateBC') . '</td><td>'
        . $form->select_date($obj->date_cmde ? $obj->date_cmde : -1, 'date_bc', 0, 0, 1, '', 1, 0) . '</td>';
    print '<td>' . $langs->trans('BCDateLivPrev') . '</td><td>'
        . $form->select_date($obj->date_livraison ? $obj->date_livraison : -1, 'date_livraison_prevue', 0, 0, 1, '', 1, 0) . '</td></tr>';

    print '<tr><td class="fieldrequired">' . $langs->trans('BCFournisseur') . '</td><td>'
        . '<input type="text" size="60" name="fournisseur_nom" value="' . dol_escape_htmltag($obj->fournisseur_nom) . '"></td>';
    print '<td>' . $langs->trans('DPContact') . '</td><td>'
        . '<input type="text" size="40" name="fournisseur_contact" value="' . dol_escape_htmltag($obj->fournisseur_contact) . '"></td></tr>';

    print '<tr><td>' . $langs->trans('DPAdresse') . '</td><td>'
        . '<textarea rows="2" cols="60" name="fournisseur_adresse">' . dol_escape_htmltag($obj->fournisseur_adresse) . '</textarea></td>';
    print '<td>' . $langs->trans('DPTel') . ' / ' . $langs->trans('DPEmail') . '</td><td>'
        . '<input type="text" size="20" name="fournisseur_tel" value="' . dol_escape_htmltag($obj->fournisseur_tel) . '">'
        . ' &nbsp; <input type="text" size="30" name="fournisseur_email" value="' . dol_escape_htmltag($obj->fournisseur_email) . '"></td></tr>';

    print '<tr><td class="tdtop">' . $langs->trans('BCLieuLiv') . '</td><td colspan="3">'
        . '<textarea rows="2" cols="80" name="lieu_livraison">' . dol_escape_htmltag($obj->lieu_livraison) . '</textarea></td></tr>';
    print '<tr><td>' . $langs->trans('BCModeRegl') . '</td><td>'
        . '<input type="text" size="50" name="conditions_reglement" value="' . dol_escape_htmltag($obj->conditions_paiement) . '"></td>';
    print '<td>' . $langs->trans('BCDelaiRegl') . ' (jours)</td><td>'
        . '<input type="number" min="0" step="1" size="8" name="delai_reglement_jours" value="' . ((int)$obj->delai_reglement_jours ? (int)$obj->delai_reglement_jours : 30) . '"></td></tr>';
    print '<tr><td>' . $langs->trans('BCTauxTVA') . ' (%)</td><td>'
        . '<input type="text" size="8" name="taux_tva_applicable" value="' . dol_escape_htmltag($obj->taux_tva_applicable !== null && $obj->taux_tva_applicable != '' ? $obj->taux_tva_applicable : '0') . '"></td>';
    print '<td>' . $langs->trans('BCFKCotation') . ' (optionnel)</td><td>'
        . '<input type="number" min="0" step="1" name="fk_cotation" value="' . ((int)$obj->fk_cotation ? (int)$obj->fk_cotation : '') . '"></td></tr>';
    print '<tr><td class="tdtop">' . $langs->trans('FieldNotes') . '</td><td colspan="3">'
        . '<textarea rows="3" cols="80" name="note_public">' . dol_escape_htmltag($obj->note_public) . '</textarea></td></tr>';
    print '</table>';

    // ========== SECTION LIGNES ==========
    print '<h3 style="margin-top:18px;">Lignes Bon de Commande</h3>';
    print '<table class="noborder centpercent" id="apc-bc-lines-table">';
    print '<thead><tr class="liste_titre">';
    print '<th style="width:40px;">N°</th>';
    print '<th style="min-width:240px;">' . $langs->trans('BCColDesc') . ' *</th>';
    print '<th style="width:80px;">' . $langs->trans('BCColUnit') . '</th>';
    print '<th style="width:90px;" class="right">' . $langs->trans('BCColQty') . '</th>';
    print '<th style="width:110px;" class="right">' . $langs->trans('BCColPu') . ' (HT)</th>';
    print '<th style="width:120px;" class="right">' . $langs->trans('BCColTotal') . ' HT</th>';
    print '<th style="width:90px;" class="center">Produit (ID)</th>';
    print '<th style="width:40px;">&nbsp;</th>';
    print '</tr></thead>';
    print '<tbody id="apc-bc-lines-body">';

    $lines = $editing && !empty($obj->lines) ? $obj->lines : array((object)array());
    $idx = 0;
    foreach ($lines as $ln) {
        $desc = isset($ln->description) ? $ln->description : '';
        $unit = isset($ln->unite) ? $ln->unite : '';
        $qty  = isset($ln->quantite) ? (float)$ln->quantite : '';
        $pu   = isset($ln->prix_unitaire) ? (float)$ln->prix_unitaire : '';
        $fkpr = isset($ln->fk_product) ? (int)$ln->fk_product : 0;
        $tot  = $qty !== '' && $pu !== '' ? number_format((float)$qty * (float)$pu, 2, ',', ' ') : '';
        print '<tr class="apc-line-row">';
        print '<td class="apc-bc-no center">' . ($idx+1) . '</td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][description]" value="' . dol_escape_htmltag($desc) . '"></td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][unite]" value="' . dol_escape_htmltag($unit) . '"></td>';
        print '<td><input type="text" class="flat width100 right apc-bc-qty" name="lines[' . $idx . '][quantite]" value="' . ($qty !== '' ? dol_escape_htmltag($qty) : '') . '"></td>';
        print '<td><input type="text" class="flat width100 right apc-bc-pu" name="lines[' . $idx . '][prix_unitaire_ht]" value="' . ($pu !== '' ? dol_escape_htmltag($pu) : '') . '"></td>';
        print '<td class="right apc-bc-total">' . $tot . '</td>';
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
    print ' <a class="button button-cancel" href="' . DOL_URL_ROOT . '/custom/apclogistics/boncommande_list.php">' . $langs->trans('Cancel') . '</a>';
    print '</div>';
    print '</form>';

    // ===== JS : ajout/suppression lignes + calcul totaux PU*QTE
    print '<script type="text/javascript">
    $(document).ready(function() {
        function APCNumFmt(n){ n = Number(n||0).toFixed(2); var p = n.split("."); p[0]=p[0].replace(/\B(?=(\d{3})+(?!\d))/g," "); return p.join(","); }
        function recalcLignes() {
            $(".apc-line-row").each(function(){
                var q = parseFloat($(this).find(".apc-bc-qty").val().replace(/\s/g,"").replace(",","."))||0;
                var p = parseFloat($(this).find(".apc-bc-pu").val().replace(/\s/g,"").replace(",","."))||0;
                $(this).find(".apc-bc-total").text(APCNumFmt(q*p));
            });
        }
        function renumber() { $(".apc-bc-no").each(function(i){ $(this).text(i+1); }); }
        $(document).on("input", ".apc-bc-qty,.apc-bc-pu", recalcLignes);
        $("#apc-add-line").on("click", function() {
            var n = $("#apc-bc-lines-body tr").length;
            var html = "<tr class=\"apc-line-row\">"
                + "<td class=\"apc-bc-no center\">"+(n+1)+"</td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][description]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][unite]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100 right apc-bc-qty\" name=\"lines["+n+"][quantite]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100 right apc-bc-pu\" name=\"lines["+n+"][prix_unitaire_ht]\"></td>"
                + "<td class=\"right apc-bc-total\">0,00</td>"
                + "<td><input type=\"number\" min=\"0\" step=\"1\" class=\"flat width100\" name=\"lines["+n+"][fk_product]\"></td>"
                + "<td><button type=\"button\" class=\"button apc-rm-line\" style=\"padding:2px 6px;\">-</button></td>"
                + "</tr>";
            $("#apc-bc-lines-body").append(html);
            renumber(); recalcLignes();
        });
        $(document).on("click", ".apc-rm-line", function() {
            if ($("#apc-bc-lines-body tr").length > 1) { $(this).closest("tr").remove(); renumber(); recalcLignes(); }
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
    print '<tr><td class="titlefield">' . $langs->trans('BCRef') . '</td><td class="valeur"><b>' . $object->ref . '</b></td>'
        . '<td class="titlefield">' . $langs->trans('FieldStatus') . '</td><td class="valeur">' . $object->getStatusBadge() . '</td></tr>';
    print '<tr><td>' . $langs->trans('BCDateBC') . '</td><td>' . dol_print_date($db->jdate($object->date_cmde), 'day') . '</td>'
        . '<td>' . $langs->trans('BCDateLivPrev') . '</td><td>'
        . ($object->date_livraison ? dol_print_date($db->jdate($object->date_livraison), 'day') : '<span class="opacitymedium">—</span>') . '</td></tr>';
    print '<tr><td>' . $langs->trans('BCFournisseur') . '</td><td><b>' . dol_escape_htmltag($object->fournisseur_nom) . '</b></td>'
        . '<td>' . $langs->trans('DPContact') . '</td><td>' . dol_escape_htmltag($object->fournisseur_contact) . '</td></tr>';
    print '<tr><td class="tdtop">' . $langs->trans('DPAdresse') . '</td><td>' . dol_escape_htmltag($object->fournisseur_adresse) . '</td>'
        . '<td>' . $langs->trans('DPTel') . ' / ' . $langs->trans('DPEmail') . '</td><td>'
        . dol_escape_htmltag($object->fournisseur_tel)
        . (empty($object->fournisseur_email) ? '' : '<br>' . dol_escape_htmltag($object->fournisseur_email))
        . '</td></tr>';
    print '<tr><td>' . $langs->trans('BCLieuLiv') . '</td><td>' . dol_escape_htmltag($object->lieu_livraison) . '</td>'
        . '<td>' . $langs->trans('BCModeRegl') . '</td><td>' . dol_escape_htmltag($object->conditions_paiement) . '</td></tr>';
    print '<tr><td>' . $langs->trans('BCDelaiRegl') . '</td><td>'
        . ((int)$object->delai_reglement_jours ? (int)$object->delai_reglement_jours . ' ' . $langs->trans('Days') : '<span class="opacitymedium">—</span>') . '</td>'
        . '<td>' . $langs->trans('BCTauxTVA') . '</td><td>'
        . ($object->taux_tva_applicable !== null && $object->taux_tva_applicable != '' ? (float)$object->taux_tva_applicable . ' %' : '<span class="opacitymedium">—</span>') . '</td></tr>';

    if ($object->fk_cotation > 0) {
        $cot = new ApcCotation($db);
        if ($cot->fetch($object->fk_cotation) > 0) {
            print '<tr><td>' . $langs->trans('BCFKCotation') . '</td><td colspan="3">' . $cot->getNomUrl(1) . ' — ' . dol_escape_htmltag($cot->fournisseur_nom) . '</td></tr>';
        }
    }
    if (!empty($object->note_public)) print '<tr><td class="tdtop">' . $langs->trans('FieldNotes') . '</td><td colspan="3">' . dol_escape_htmltag($object->note_public) . '</td></tr>';
    print '</table>';

    // ======= TABLEAU LIGNES =======
    $object->fetchLines();
    $object->calculateTotals();
    print '<h3 style="margin-top:18px;">Lignes Bon de Commande</h3>';
    print '<table class="noborder centpercent">';
    print '<thead><tr class="liste_titre">';
    print '<th class="center width40">N°</th>';
    print '<th>' . $langs->trans('BCColDesc') . '</th>';
    print '<th class="center">' . $langs->trans('BCColUnit') . '</th>';
    print '<th class="right" style="width:100px;">' . $langs->trans('BCColQty') . '</th>';
    print '<th class="right" style="width:120px;">' . $langs->trans('BCColPu') . ' HT</th>';
    print '<th class="right" style="width:140px;">' . $langs->trans('BCColTotal') . ' HT</th>';
    print '<th class="center">Produit (ID)</th>';
    print '</tr></thead><tbody>';
    $i = 1; $tQ=0; $tTot=0;
    foreach ($object->lines as $ln) {
        $tQ += (float)$ln->quantite;
        $tTot += (float)$ln->prix_total_ligne;
        print '<tr class="oddeven"><td class="center">' . $i++ . '</td>';
        print '<td>' . dol_escape_htmltag($ln->description) . '</td>';
        print '<td class="center">' . dol_escape_htmltag($ln->unite) . '</td>';
        print '<td class="right">' . price((float)$ln->quantite, 0, $langs, 0, 0, 0, '') . '</td>';
        print '<td class="apc-money">' . price((float)$ln->prix_unitaire, 0, $langs, 0, 0, -1, $conf->currency) . '</td>';
        print '<td class="apc-money">' . price((float)$ln->prix_total_ligne, 0, $langs, 0, 0, -1, $conf->currency) . '</td>';
        print '<td class="center">' . ($ln->fk_product ? (int)$ln->fk_product : '<span class="opacitymedium">—</span>') . '</td>';
        print '</tr>';
    }
    if (empty($object->lines)) print '<tr class="oddeven"><td colspan="7" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
    print '</tbody><tfoot>';
    print '<tr class="liste_total"><td colspan="5" class="right"><b>' . $langs->trans('BCTotalHT') . '</b></td>'
        . '<td class="right apc-money"><b>' . price((float)$object->total_ht, 0, $langs, 0, 0, -1, $conf->currency) . '</b></td><td>&nbsp;</td></tr>';
    if ((float)$object->total_tva > 0)
    print '<tr class="liste_total"><td colspan="5" class="right"><b>' . $langs->trans('BCTotalTVA') . '</b></td>'
        . '<td class="right apc-money"><b>' . price((float)$object->total_tva, 0, $langs, 0, 0, -1, $conf->currency) . '</b></td><td>&nbsp;</td></tr>';
    print '<tr class="liste_total"><td colspan="5" class="right"><b>' . $langs->trans('BCTotalTTC') . '</b></td>'
        . '<td class="right apc-money"><b>' . price((float)$object->total_ttc, 0, $langs, 0, 0, -1, $conf->currency) . '</b></td><td>&nbsp;</td></tr>';
    print '</tfoot></table>';

    // ======= ZONES VALIDATION PAR SIGNATURES (3 niveaux : 0=Logisticien APC, 1=Coordinateur APC, 2=Fournisseur) =======
    if ($permissiontovalidate && (int)$object->status !== ApcBonCommande::STATUS_CANCELLED && (int)$object->status !== ApcBonCommande::STATUS_CLOSED) {
        print '<h3 style="margin-top:18px;">Zones de signatures</h3>';
        $sigStates = array(
            0 => array('date_field'=>'date_signature_log',   'nom_field'=>'logisticien_nom',      'libelle'=>$langs->trans('BCLogisticien'),  'desc'=>'Signature Logisticien APC (étape 1/3)'),
            1 => array('date_field'=>'date_signature_coord',  'nom_field'=>'coordinateur_nom',     'libelle'=>$langs->trans('BCCoordinateur'),'desc'=>'Signature Coordinateur APC (étape 2/3)'),
            2 => array('date_field'=>'fournisseur_sig_date',  'nom_field'=>'fournisseur_sig_nom',  'libelle'=>$langs->trans('BCFournisseurSig'),'desc'=>'Signature Fournisseur (étape 3/3 — saisie manuelle APC'),
        );
        foreach ($sigStates as $lvl => $inf) {
            $dateF = $inf['date_field'];
            $signed = !empty($object->$dateF);
            if (!$signed) {
                print '<div style="margin:10px 0; padding:10px; border:1px dashed #ccc; border-radius:6px;">';
                print '<form action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '" method="POST">';
                print '<input type="hidden" name="token" value="' . newToken() . '">';
                print '<input type="hidden" name="action" value="sign">';
                print '<input type="hidden" name="level" value="' . $lvl . '">';
                print '<b>Étape ' . ($lvl+1) . '/3 — Signer en tant que : ' . $inf['libelle'] . '</b> <span class="opacitymedium">(' . $inf['desc'] . ')</span><br>';
                print 'Nom <input type="text" name="nom_sig" size="28" value="' . dol_escape_htmltag($user->getFullName($langs)) . '"> ';
                print 'Fonction <input type="text" name="fct_sig" size="22" value="' . dol_escape_htmltag($user->poste) . '"> ';
                print '<input type="submit" class="button" value="Signer">';
                print '</form></div>';
            }
        }
    }

    // ======= SIGNATURES DEJA APPOSEES =======
    print '<h3 style="margin-top:18px;">Signatures enregistrées</h3>';
    print '<table class="noborder centpercent">';
    print '<thead><tr class="liste_titre"><th>' . $langs->trans('BCSignLog') . '</th><th>' . $langs->trans('BCSignCoord') . '</th><th>' . $langs->trans('BCSignFourn') . '</th></tr></thead>';
    print '<tr><td class="center" style="padding:12px; border:1px solid #ddd;">'
        . (empty($object->date_signature_log) ? '<span class="opacitymedium">—</span>'
            : ('<b>' . dol_escape_htmltag($object->logisticien_nom) . '</b><br><i>' . dol_escape_htmltag($object->logisticien_fonction) . '</i><br>Signé le ' . dol_print_date($db->jdate($object->date_signature_log), 'day')))
        . '</td><td class="center" style="padding:12px; border:1px solid #ddd;">'
        . (empty($object->date_signature_coord) ? '<span class="opacitymedium">—</span>'
            : ('<b>' . dol_escape_htmltag($object->coordinateur_nom) . '</b><br><i>' . dol_escape_htmltag($object->coordinateur_fonction) . '</i><br>Signé le ' . dol_print_date($db->jdate($object->date_signature_coord), 'day')))
        . '</td><td class="center" style="padding:12px; border:1px solid #ddd;">'
        . (empty($object->fournisseur_sig_date) ? '<span class="opacitymedium">—</span>'
            : ('<b>' . dol_escape_htmltag($object->fournisseur_sig_nom) . '</b><br><i>' . dol_escape_htmltag($object->fournisseur_sig_fct) . '</i><br>Signé le ' . dol_print_date($db->jdate($object->fournisseur_sig_date), 'day')))
        . '</td></tr></table>';

    // ======= BOUTONS ACTIONS =======
    print '<div class="tabsAction" style="margin-top:24px;">';
    print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/boncommande_card.php?id=' . $object->id . '&action=generate_pdf&token=' . newToken() . '">' . $langs->trans('PDFGenerateBtn') . '</a>';
    if ($permissiontoedit) print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/boncommande_card.php?id=' . $object->id . '&action=edit">' . $langs->trans('Modify') . '</a>';
    print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/bonreception_card.php?action=create_from_bc&bc_id=' . $object->id . '">' . $langs->trans('CreateBRFromBC') . '</a>';
    if ($permissiontodelete) print '<a class="butActionDelete" href="' . DOL_URL_ROOT . '/custom/apclogistics/boncommande_card.php?id=' . $object->id . '&action=delete">' . $langs->trans('Delete') . '</a>';
    print '</div>';
}

dol_fiche_end();

// ============== TAB : HISTORIQUE / AUDIT LOG ==============
if ($action === 'links' && $object->id > 0) {
    apcPrintLinksTab($db, $langs, $conf, $object);
}

if ($action === 'audit' && $object->id > 0) {
    $audits = ApcAuditLog::fetchForEntity($db, 'bc', $object->id, 200);
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
