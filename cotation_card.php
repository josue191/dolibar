<?php
if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/class/ApcCotation.class.php';
require_once __DIR__ . '/class/ApcDemandePrix.class.php';
require_once __DIR__ . '/class/ApcBonCommande.class.php';
require_once __DIR__ . '/class/apc_links.lib.php';
require_once __DIR__ . '/class/pdf/pdf_cotation_apc.modules.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

$id      = (int)GETPOST('id', 'int');
$ref     = GETPOST('ref', 'alpha');
$action  = GETPOST('action', 'aZ');
$confirm = GETPOST('confirm', 'alpha');
$token   = GETPOST('token', 'alpha');

$object = new ApcCotation($db);
$extrafields = new ExtraFields($db);
$extralabels = $extrafields->fetch_name_optionals_label('apclogistics_cotation');

if ($id > 0 || $ref) {
    $res = ($id > 0) ? $object->fetch($id) : $object->fetch(0, $ref);
    if ($res <= 0) { dol_print_error('', 'ErrorLoadFailed'); exit; }
    $object->fetchLines();
}

$permissiontoread     = !empty($user->rights->apclogistics->cotation->read);
$permissiontocreate   = !empty($user->rights->apclogistics->cotation->create);
$permissiontoedit     = !empty($user->rights->apclogistics->cotation->edit);
$permissiontodelete   = !empty($user->rights->apclogistics->cotation->delete);
$permissiontovalidate = !empty($user->rights->apclogistics->cotation->validate);

if ($action === 'create' && !$permissiontocreate) accessforbidden();
if (empty($permissiontoread) && $action !== 'create') accessforbidden();

$error = 0;
$backtopage = DOL_URL_ROOT . '/custom/apclogistics/cotation_list.php';
$form = new Form($db);
$formother = new FormOther($db);

if ($action === 'add' && $permissiontocreate && !$error && $user->valid && $token && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $object->fk_demandeprix    = (int)GETPOST('fk_demandeprix', 'int');
    $dc = dol_mktime(12,0,0, GETPOST('date_cotationmonth', 'int'), GETPOST('date_cotationday', 'int'), GETPOST('date_cotationyear', 'int'));
    $object->date_cotation      = $db->idate($dc);
    $dv = dol_mktime(12,0,0, GETPOST('date_validite_offremonth', 'int'), GETPOST('date_validite_offreday', 'int'), GETPOST('date_validite_offreyear', 'int'));
    $object->date_validite_offre = $db->idate($dv);
    $object->fournisseur_nom     = GETPOST('fournisseur_nom', 'alphanohtml');
    $object->fournisseur_adresse = GETPOST('fournisseur_adresse', 'alphanohtml');
    $object->fournisseur_tel     = GETPOST('fournisseur_tel', 'alphanohtml');
    $object->fournisseur_email   = GETPOST('fournisseur_email', 'alpha');
    $object->fournisseur_contact = GETPOST('fournisseur_contact', 'alphanohtml');
    $object->lieu_livraison      = GETPOST('lieu_livraison', 'alphanohtml');
    $object->conditions_reglement = GETPOST('conditions_reglement', 'alphanohtml');
    $object->delai_livraison     = GETPOST('delai_livraison', 'alphanohtml');
    $object->taux_tva_applicable = (float)str_replace(',', '.', GETPOST('taux_tva_applicable', 'alpha'));
    $object->note_public         = GETPOST('note_public', 'alphanohtml');

    if (empty($object->fournisseur_nom)) { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('DPFournisseur')), null, 'errors'); $error++; }
    if (empty($object->date_cotation) || $object->date_cotation === '1970-01-01') { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('COTDate')), null, 'errors'); $error++; }

    if (!$error) {
        $res = $object->create($user);
        if ($res > 0) {
            $lignes = GETPOST('lines', 'array');
            $no = 1;
            if (is_array($lignes)) {
                foreach ($lignes as $line) {
                    if (empty($line['description']) && (float)$line['quantite'] <= 0) continue;
                    $ln = new ApcCotationLine($db);
                    $ln->fk_cotation = $object->id;
                    $ln->no_ligne = $no++;
                    $ln->description     = $line['description'];
                    $ln->unite           = $line['unite'];
                    $ln->quantite        = (float)str_replace(',', '.', $line['quantite']);
                    $ln->prix_unitaire_ht = (float)str_replace(',', '.', $line['prix_unitaire_ht']);
                    $ln->remise_pct      = (float)str_replace(',', '.', $line['remise_pct']);
                    $pu = (float)$ln->prix_unitaire_ht;
                    $qt = (float)$ln->quantite;
                    $sousTotal = $pu * $qt;
                    if ((float)$ln->remise_pct > 0) $sousTotal = $sousTotal * (1 - ((float)$ln->remise_pct / 100));
                    $ln->total_ht        = round($sousTotal, 2);
                    $ln->fk_product      = (int)$line['fk_product'];
                    $ln->remarque        = $line['remarque'];
                    $ln->tva_tx          = (float)$object->taux_tva_applicable;
                    $ln->create($user);
                }
            }
            $object->calculateTotals();
            $object->update($user);
            header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/cotation_card.php?id=' . $object->id);
            exit;
        } else { setEventMessages($object->error, $object->errors, 'errors'); }
    }
}

if ($action === 'update' && $permissiontoedit && !$error && $user->valid && $token) {
    $object->fk_demandeprix    = (int)GETPOST('fk_demandeprix', 'int');
    $dc = dol_mktime(12,0,0, GETPOST('date_cotationmonth', 'int'), GETPOST('date_cotationday', 'int'), GETPOST('date_cotationyear', 'int'));
    $object->date_cotation      = $db->idate($dc);
    $dv = dol_mktime(12,0,0, GETPOST('date_validite_offremonth', 'int'), GETPOST('date_validite_offreday', 'int'), GETPOST('date_validite_offreyear', 'int'));
    $object->date_validite_offre = $db->idate($dv);
    $object->fournisseur_nom     = GETPOST('fournisseur_nom', 'alphanohtml');
    $object->fournisseur_adresse = GETPOST('fournisseur_adresse', 'alphanohtml');
    $object->fournisseur_tel     = GETPOST('fournisseur_tel', 'alphanohtml');
    $object->fournisseur_email   = GETPOST('fournisseur_email', 'alpha');
    $object->fournisseur_contact = GETPOST('fournisseur_contact', 'alphanohtml');
    $object->lieu_livraison      = GETPOST('lieu_livraison', 'alphanohtml');
    $object->conditions_reglement = GETPOST('conditions_reglement', 'alphanohtml');
    $object->delai_livraison     = GETPOST('delai_livraison', 'alphanohtml');
    $object->taux_tva_applicable = (float)str_replace(',', '.', GETPOST('taux_tva_applicable', 'alpha'));
    $object->note_public         = GETPOST('note_public', 'alphanohtml');
    $res = $object->update($user);
    if ($res > 0) {
        $ln = new ApcCotationLine($db);
        $ln->deleteAllForParent($user, $object->id);
        $lignes = GETPOST('lines', 'array');
        $no = 1;
        if (is_array($lignes)) {
            foreach ($lignes as $line) {
                if (empty($line['description']) && (float)$line['quantite'] <= 0) continue;
                $nl = new ApcCotationLine($db);
                $nl->fk_cotation = $object->id;
                $nl->no_ligne = $no++;
                $nl->description     = $line['description'];
                $nl->unite           = $line['unite'];
                $nl->quantite        = (float)str_replace(',', '.', $line['quantite']);
                $nl->prix_unitaire_ht = (float)str_replace(',', '.', $line['prix_unitaire_ht']);
                $nl->remise_pct      = (float)str_replace(',', '.', $line['remise_pct']);
                $pu = (float)$nl->prix_unitaire_ht;
                $qt = (float)$nl->quantite;
                $sousTotal = $pu * $qt;
                if ((float)$nl->remise_pct > 0) $sousTotal = $sousTotal * (1 - ((float)$nl->remise_pct / 100));
                $nl->total_ht        = round($sousTotal, 2);
                $nl->fk_product      = (int)$line['fk_product'];
                $nl->remarque        = $line['remarque'];
                $nl->tva_tx          = (float)$object->taux_tva_applicable;
                $nl->create($user);
            }
        }
        $object->calculateTotals();
        $object->update($user);
        header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/cotation_card.php?id=' . $object->id);
        exit;
    } else { setEventMessages($object->error, $object->errors, 'errors'); }
}

if ($action === 'confirm_delete' && $confirm === 'yes' && $permissiontodelete && $token) {
    $res = $object->delete($user);
    if ($res > 0) { header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/cotation_list.php'); exit; }
    else setEventMessages($object->error, $object->errors, 'errors');
}

if ($action === 'change_status' && $permissiontovalidate && $id > 0 && $token) {
    $newStatus = (int)GETPOST('new_status', 'int');
    if (in_array($newStatus, array(ApcCotation::STATUS_RECEIVED, ApcCotation::STATUS_REVIEWED, ApcCotation::STATUS_RETAINED, ApcCotation::STATUS_REJECTED))) {
        $object->status = $newStatus;
        if ($newStatus === ApcCotation::STATUS_RETAINED) {
            $object->lockRef();
        }
        $object->update($user);
        ApcAuditLog::log($object->element, $object->id, 'STATUS', $user->id, null, null, 'Passage statut ' . $newStatus);
    }
    header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/cotation_card.php?id=' . $object->id);
    exit;
}

if ($action === 'transform_to_bc' && $permissiontovalidate && $id > 0 && $token) {
    $newBC = ApcBonCommande::createFromCotation($object, $user, $user);
    if ($newBC && $newBC->id > 0) {
        setEventMessages($langs->trans('BCFromCotationOK') . ' — ' . $newBC->ref, null, 'mesgs');
        header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/boncommande_card.php?id=' . $newBC->id);
        exit;
    } else {
        setEventMessages($newBC ? $newBC->error : 'Erreur creation BC', null, 'errors');
    }
}

if ($action === 'generate_pdf' && $id > 0) {
    $g = new pdf_cotation_apc($db);
    $g->write_file($object, $langs, '', 'I');
    exit;
}

$title = ($action === 'create' ? $langs->trans('COTTitle') : ($object->ref ?: $langs->trans('COTTitle')));
llxHeader('', $title, '', '', 0, 0, array('/custom/apclogistics/js/apclogistics.js'), array('/custom/apclogistics/css/apclogistics.css'));

$head = array();
$head[0][0] = DOL_URL_ROOT . '/custom/apclogistics/cotation_card.php?id=' . ($object->id ?: 0) . '&action=' . ($action ?: 'view');
$head[0][1] = $langs->trans('TabCard');
$head[0][2] = 'tabcard';
$head[1][0] = DOL_URL_ROOT . '/custom/apclogistics/cotation_card.php?id=' . ($object->id ?: 0) . '&action=links';
$head[1][1] = $langs->trans('TabLinks');
$head[1][2] = 'tablinks';
$head[2][0] = DOL_URL_ROOT . '/custom/apclogistics/cotation_card.php?id=' . ($object->id ?: 0) . '&action=audit';
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
    print '<tr><td class="titlefieldcreate fieldrequired">' . $langs->trans('COTRef') . '</td><td>';
    if ($editing) print $obj->ref; else print '<span class="opacitymedium">(Généré automatiquement)</span>';
    print '</td>';
    print '<td class="titlefield">' . $langs->trans('FieldStatus') . '</td><td>'
        . ($editing ? $obj->getStatusBadge() : '<span class="opacitymedium">' . $langs->trans('StatusDraft') . '</span>') . '</td></tr>';

    print '<tr><td class="fieldrequired">' . $langs->trans('COTDate') . '</td><td>'
        . $form->select_date($obj->date_cotation ? $obj->date_cotation : -1, 'date_cotation', 0, 0, 1, '', 1, 0) . '</td>';
    print '<td>' . $langs->trans('CotDateValidOffre') . '</td><td>'
        . $form->select_date($obj->date_validite_offre ? $obj->date_validite_offre : -1, 'date_validite_offre', 0, 0, 1, '', 1, 0) . '</td></tr>';

    if (!$editing || (int)$obj->fk_demandeprix > 0) {
        print '<tr><td>' . $langs->trans('CotFK_DP') . '</td><td>'
            . '<input type="number" min="0" step="1" name="fk_demandeprix" value="' . ((int)$obj->fk_demandeprix ? (int)$obj->fk_demandeprix : '') . '">'
            . ' <span class="opacitymedium">(rowid Demande de Prix)</span></td>';
    } else {
        print '<tr><td>' . $langs->trans('CotFK_DP') . '</td><td colspan="3">'
            . '<span class="opacitymedium">(lier depuis la fiche DP)</span></td></tr>';
    }

    print '<tr><td class="fieldrequired">' . $langs->trans('COTNomFournisseur') . '</td><td>'
        . '<input type="text" size="60" name="fournisseur_nom" value="' . dol_escape_htmltag($obj->fournisseur_nom) . '"></td>';
    print '<td>' . $langs->trans('COTContactFournisseur') . '</td><td>'
        . '<input type="text" size="40" name="fournisseur_contact" value="' . dol_escape_htmltag($obj->fournisseur_contact) . '"></td></tr>';

    print '<tr><td>' . $langs->trans('COTAdresseFournisseur') . '</td><td>'
        . '<textarea rows="2" cols="60" name="fournisseur_adresse">' . dol_escape_htmltag($obj->fournisseur_adresse) . '</textarea></td>';
    print '<td>' . $langs->trans('COTTelFournisseur') . ' / Email</td><td>'
        . '<input type="text" size="20" name="fournisseur_tel" value="' . dol_escape_htmltag($obj->fournisseur_tel) . '">'
        . ' &nbsp; <input type="text" size="30" name="fournisseur_email" value="' . dol_escape_htmltag($obj->fournisseur_email) . '"></td></tr>';

    print '<tr><td class="tdtop">' . $langs->trans('COTLieuLiv') . '</td><td>'
        . '<textarea rows="2" cols="60" name="lieu_livraison">' . dol_escape_htmltag($obj->lieu_livraison) . '</textarea></td>';
    print '<td>' . $langs->trans('CotDelaiLiv') . '</td><td>'
        . '<input type="text" size="40" name="delai_livraison" value="' . dol_escape_htmltag($obj->delai_livraison) . '"></td></tr>';

    print '<tr><td>' . $langs->trans('COTConditions') . '</td><td>'
        . '<input type="text" size="60" name="conditions_reglement" value="' . dol_escape_htmltag($obj->conditions_reglement) . '"></td>';
    print '<td>' . $langs->trans('BCTauxTVA') . ' (%)</td><td>'
        . '<input type="text" size="8" name="taux_tva_applicable" value="' . dol_escape_htmltag($obj->taux_tva_applicable !== null && $obj->taux_tva_applicable != '' ? $obj->taux_tva_applicable : '0') . '"></td></tr>';

    print '<tr><td class="tdtop">' . $langs->trans('FieldNotes') . '</td><td colspan="3">'
        . '<textarea rows="3" cols="80" name="note_public">' . dol_escape_htmltag($obj->note_public) . '</textarea></td></tr>';
    print '</table>';

    print '<h3 style="margin-top:18px;">Lignes de la cotation</h3>';
    print '<table class="noborder centpercent" id="apc-cot-lines-table">';
    print '<thead><tr class="liste_titre">';
    print '<th style="width:40px;">N°</th>';
    print '<th style="min-width:220px;">Description *</th>';
    print '<th style="width:80px;">Unité</th>';
    print '<th style="width:90px;" class="right">Quantité</th>';
    print '<th style="width:110px;" class="right">PU HT</th>';
    print '<th style="width:90px;" class="right">Remise %</th>';
    print '<th style="width:120px;" class="right">Total HT</th>';
    print '<th style="width:90px;" class="center">Produit (ID)</th>';
    print '<th style="min-width:140px;">Remarques</th>';
    print '<th style="width:40px;">&nbsp;</th>';
    print '</tr></thead>';
    print '<tbody id="apc-cot-lines-body">';

    $lines = $editing && !empty($obj->lines) ? $obj->lines : array((object)array());
    $idx = 0;
    foreach ($lines as $ln) {
        $desc = isset($ln->description) ? $ln->description : '';
        $unit = isset($ln->unite) ? $ln->unite : '';
        $qty  = isset($ln->quantite) ? (float)$ln->quantite : '';
        $pu   = isset($ln->prix_unitaire_ht) ? (float)$ln->prix_unitaire_ht : '';
        $rem  = isset($ln->remise_pct) ? (float)$ln->remise_pct : '';
        $fkpr = isset($ln->fk_product) ? (int)$ln->fk_product : 0;
        $remq = isset($ln->remarque) ? $ln->remarque : '';
        $tot  = '';
        if ($qty !== '' && $pu !== '') {
            $st = (float)$qty * (float)$pu;
            if ((float)$rem > 0) $st *= (1 - ((float)$rem / 100));
            $tot = number_format($st, 2, ',', ' ');
        }
        print '<tr class="apc-line-row">';
        print '<td class="apc-cot-no center">' . ($idx+1) . '</td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][description]" value="' . dol_escape_htmltag($desc) . '"></td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][unite]" value="' . dol_escape_htmltag($unit) . '"></td>';
        print '<td><input type="text" class="flat width100 right apc-cot-qty" name="lines[' . $idx . '][quantite]" value="' . ($qty !== '' ? dol_escape_htmltag($qty) : '') . '"></td>';
        print '<td><input type="text" class="flat width100 right apc-cot-pu" name="lines[' . $idx . '][prix_unitaire_ht]" value="' . ($pu !== '' ? dol_escape_htmltag($pu) : '') . '"></td>';
        print '<td><input type="text" class="flat width100 right apc-cot-rem" name="lines[' . $idx . '][remise_pct]" value="' . ($rem !== '' ? dol_escape_htmltag($rem) : '') . '"></td>';
        print '<td class="right apc-cot-total">' . $tot . '</td>';
        print '<td><input type="number" min="0" step="1" class="flat width100" name="lines[' . $idx . '][fk_product]" value="' . ($fkpr ? $fkpr : '') . '"></td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][remarque]" value="' . dol_escape_htmltag($remq) . '"></td>';
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
    print ' <a class="button button-cancel" href="' . DOL_URL_ROOT . '/custom/apclogistics/cotation_list.php">' . $langs->trans('Cancel') . '</a>';
    print '</div>';
    print '</form>';

    print '<script type="text/javascript">
    $(document).ready(function() {
        function APCNumFmt(n){ n = Number(n||0).toFixed(2); var p = n.split("."); p[0]=p[0].replace(/\B(?=(\d{3})+(?!\d))/g," "); return p.join(","); }
        function recalcLignes() {
            $(".apc-line-row").each(function(){
                var q = parseFloat($(this).find(".apc-cot-qty").val().replace(/\s/g,"").replace(",","."))||0;
                var p = parseFloat($(this).find(".apc-cot-pu").val().replace(/\s/g,"").replace(",","."))||0;
                var r = parseFloat($(this).find(".apc-cot-rem").val().replace(/\s/g,"").replace(",","."))||0;
                var st = q*p;
                if (r>0) st = st * (1-(r/100));
                $(this).find(".apc-cot-total").text(APCNumFmt(st));
            });
        }
        function renumber() { $(".apc-cot-no").each(function(i){ $(this).text(i+1); }); }
        $(document).on("input", ".apc-cot-qty,.apc-cot-pu,.apc-cot-rem", recalcLignes);
        $("#apc-add-line").on("click", function() {
            var n = $("#apc-cot-lines-body tr").length;
            var html = "<tr class=\"apc-line-row\">"
                + "<td class=\"apc-cot-no center\">"+(n+1)+"</td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][description]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][unite]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100 right apc-cot-qty\" name=\"lines["+n+"][quantite]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100 right apc-cot-pu\" name=\"lines["+n+"][prix_unitaire_ht]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100 right apc-cot-rem\" name=\"lines["+n+"][remise_pct]\"></td>"
                + "<td class=\"right apc-cot-total\">0,00</td>"
                + "<td><input type=\"number\" min=\"0\" step=\"1\" class=\"flat width100\" name=\"lines["+n+"][fk_product]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][remarque]\"></td>"
                + "<td><button type=\"button\" class=\"button apc-rm-line\" style=\"padding:2px 6px;\">-</button></td>"
                + "</tr>";
            $("#apc-cot-lines-body").append(html);
            renumber(); recalcLignes();
        });
        $(document).on("click", ".apc-rm-line", function() {
            if ($("#apc-cot-lines-body tr").length > 1) { $(this).closest("tr").remove(); renumber(); recalcLignes(); }
        });
        recalcLignes();
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
    print '<tr><td class="titlefield">' . $langs->trans('COTRef') . '</td><td class="valeur"><b>' . $object->ref . '</b></td>'
        . '<td class="titlefield">' . $langs->trans('FieldStatus') . '</td><td class="valeur">' . $object->getStatusBadge() . '</td></tr>';
    print '<tr><td>' . $langs->trans('COTDate') . '</td><td>' . dol_print_date($db->jdate($object->date_cotation), 'day') . '</td>'
        . '<td>' . $langs->trans('CotDateValidOffre') . '</td><td>'
        . ($object->date_validite_offre ? dol_print_date($db->jdate($object->date_validite_offre), 'day') : '<span class="opacitymedium">—</span>') . '</td></tr>';
    if ((int)$object->fk_demandeprix > 0) {
        $dp = new ApcDemandePrix($db);
        if ($dp->fetch((int)$object->fk_demandeprix) > 0) {
            print '<tr><td>' . $langs->trans('CotFK_DP') . '</td><td colspan="3">' . $dp->getNomUrl(1) . ' — ' . dol_escape_htmltag($dp->fournisseur_nom) . '</td></tr>';
        }
    }
    print '<tr><td>' . $langs->trans('COTNomFournisseur') . '</td><td><b>' . dol_escape_htmltag($object->fournisseur_nom) . '</b></td>'
        . '<td>' . $langs->trans('COTContactFournisseur') . '</td><td>' . dol_escape_htmltag($object->fournisseur_contact) . '</td></tr>';
    print '<tr><td class="tdtop">' . $langs->trans('COTAdresseFournisseur') . '</td><td>' . dol_escape_htmltag($object->fournisseur_adresse) . '</td>'
        . '<td>' . $langs->trans('COTTelFournisseur') . ' / Email</td><td>'
        . dol_escape_htmltag($object->fournisseur_tel)
        . (empty($object->fournisseur_email) ? '' : '<br>' . dol_escape_htmltag($object->fournisseur_email))
        . '</td></tr>';
    print '<tr><td>' . $langs->trans('COTLieuLiv') . '</td><td>' . dol_escape_htmltag($object->lieu_livraison) . '</td>'
        . '<td>' . $langs->trans('CotDelaiLiv') . '</td><td>' . dol_escape_htmltag($object->delai_livraison) . '</td></tr>';
    print '<tr><td>' . $langs->trans('COTConditions') . '</td><td>' . dol_escape_htmltag($object->conditions_reglement) . '</td>'
        . '<td>' . $langs->trans('BCTauxTVA') . '</td><td>'
        . ($object->taux_tva_applicable !== null && $object->taux_tva_applicable != '' ? (float)$object->taux_tva_applicable . ' %' : '<span class="opacitymedium">—</span>') . '</td></tr>';
    if (!empty($object->note_public)) print '<tr><td class="tdtop">' . $langs->trans('FieldNotes') . '</td><td colspan="3">' . dol_escape_htmltag($object->note_public) . '</td></tr>';
    print '</table>';

    $object->fetchLines();
    $object->calculateTotals();
    print '<h3 style="margin-top:18px;">Lignes de la cotation</h3>';
    print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
    print '<thead><tr class="liste_titre">';
    print '<th class="center width40">N°</th>';
    print '<th>' . $langs->trans('BCColDesc') . '</th>';
    print '<th class="center">' . $langs->trans('BCColUnit') . '</th>';
    print '<th class="right" style="width:100px;">' . $langs->trans('BCColQty') . '</th>';
    print '<th class="right" style="width:120px;">PU HT</th>';
    print '<th class="right" style="width:90px;">Remise %</th>';
    print '<th class="right" style="width:140px;">' . $langs->trans('BCColTotal') . ' HT</th>';
    print '<th class="center">Produit (ID)</th>';
    print '<th>Remarques</th>';
    print '</tr></thead><tbody>';
    $i = 1; $tQ=0; $tTot=0;
    foreach ($object->lines as $ln) {
        $tQ += (float)$ln->quantite;
        $tTot += (float)$ln->total_ht;
        print '<tr class="oddeven"><td class="center">' . $i++ . '</td>';
        print '<td>' . dol_escape_htmltag($ln->description) . '</td>';
        print '<td class="center">' . dol_escape_htmltag($ln->unite) . '</td>';
        print '<td class="right">' . price((float)$ln->quantite, 0, $langs, 0, 0, 0, '') . '</td>';
        print '<td class="apc-money">' . price((float)$ln->prix_unitaire_ht, 0, $langs, 0, 0, -1, $conf->currency) . '</td>';
        print '<td class="right">' . ((float)$ln->remise_pct > 0 ? (float)$ln->remise_pct . ' %' : '<span class="opacitymedium">—</span>') . '</td>';
        print '<td class="apc-money">' . price((float)$ln->total_ht, 0, $langs, 0, 0, -1, $conf->currency) . '</td>';
        print '<td class="center">' . ($ln->fk_product ? (int)$ln->fk_product : '<span class="opacitymedium">—</span>') . '</td>';
        print '<td>' . dol_escape_htmltag($ln->remarque) . '</td>';
        print '</tr>';
    }
    if (empty($object->lines)) print '<tr class="oddeven"><td colspan="9" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
    print '</tbody><tfoot>';
    print '<tr class="liste_total"><td colspan="6" class="right"><b>' . $langs->trans('BCTotalHT') . '</b></td>'
        . '<td class="right apc-money"><b>' . price((float)$object->total_ht, 0, $langs, 0, 0, -1, $conf->currency) . '</b></td><td colspan="2">&nbsp;</td></tr>';
    if ((float)$object->total_tva > 0)
    print '<tr class="liste_total"><td colspan="6" class="right"><b>' . $langs->trans('BCTotalTVA') . '</b></td>'
        . '<td class="right apc-money"><b>' . price((float)$object->total_tva, 0, $langs, 0, 0, -1, $conf->currency) . '</b></td><td colspan="2">&nbsp;</td></tr>';
    print '<tr class="liste_total"><td colspan="6" class="right"><b>' . $langs->trans('BCTotalTTC') . '</b></td>'
        . '<td class="right apc-money"><b>' . price((float)$object->total_ttc, 0, $langs, 0, 0, -1, $conf->currency) . '</b></td><td colspan="2">&nbsp;</td></tr>';
    print '</tfoot></table></div>';

    if ($permissiontovalidate && (int)$object->status !== ApcCotation::STATUS_REJECTED) {
        print '<h3 style="margin-top:18px;">Actions / Statuts</h3>';
        $nexts = array();
        if ((int)$object->status < ApcCotation::STATUS_RECEIVED) $nexts[] = array('st'=>ApcCotation::STATUS_RECEIVED, 'lbl'=>$langs->trans('CotStatusReceived'));
        if ((int)$object->status < ApcCotation::STATUS_REVIEWED) $nexts[] = array('st'=>ApcCotation::STATUS_REVIEWED, 'lbl'=>$langs->trans('CotStatusReviewed'));
        if ((int)$object->status < ApcCotation::STATUS_RETAINED) $nexts[] = array('st'=>ApcCotation::STATUS_RETAINED, 'lbl'=>$langs->trans('CotStatusRetained'));
        if ((int)$object->status < ApcCotation::STATUS_REJECTED) $nexts[] = array('st'=>ApcCotation::STATUS_REJECTED, 'lbl'=>$langs->trans('CotStatusRejected'));
        foreach ($nexts as $n) {
            print '<form style="display:inline; margin-right:6px;" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '" method="POST">';
            print '<input type="hidden" name="token" value="' . newToken() . '">';
            print '<input type="hidden" name="action" value="change_status">';
            print '<input type="hidden" name="new_status" value="' . $n['st'] . '">';
            print '<input type="submit" class="button small" value="Passer : ' . $n['lbl'] . '">';
            print '</form>';
        }
        if ((int)$object->status === ApcCotation::STATUS_RETAINED) {
            print '<h3 style="margin-top:18px;">' . $langs->trans('CotTransformToBC') . '</h3>';
            print '<form action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '" method="POST">';
            print '<input type="hidden" name="token" value="' . newToken() . '">';
            print '<input type="hidden" name="action" value="transform_to_bc">';
            print '<small class="opacitymedium">Cette action crée automatiquement un Bon de Commande pré-rempli depuis cette cotation.</small><br>';
            print '<input type="submit" class="button" value="' . $langs->trans('CotTransformToBC') . '">';
            print '</form>';
        }
    }

    print '<div class="tabsAction" style="margin-top:24px;">';
    if ($id > 0) print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/cotation_card.php?id=' . $object->id . '&action=generate_pdf">' . $langs->trans('GeneratePDF') . '</a>';
    if ($permissiontoedit && (int)$object->status < ApcCotation::STATUS_RETAINED) print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/cotation_card.php?id=' . $object->id . '&action=edit">' . $langs->trans('Modify') . '</a>';
    if ($permissiontodelete) print '<a class="butActionDelete" href="' . DOL_URL_ROOT . '/custom/apclogistics/cotation_card.php?id=' . $object->id . '&action=delete">' . $langs->trans('Delete') . '</a>';
    print '</div>';
}

dol_fiche_end();

if ($action === 'links' && $object->id > 0) {
    apcPrintLinksTab($db, $langs, $conf, $object);
}

if ($action === 'audit' && $object->id > 0) {
    $audits = ApcAuditLog::fetchForEntity($db, 'cot', $object->id, 200);
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
