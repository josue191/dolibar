<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * requisition_card.php — Fiche Requisition / Bon de sortie magasin
 * Onglets : Fiche (champs + lignes) | Historique/Audit | PDF
 * Validation : Demandeur → Magasinier (avec validateAndProcessStock → stock sortie)
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/class/ApcRequisition.class.php';
require_once __DIR__ . '/class/ApcEtatBesoin.class.php';
require_once __DIR__ . '/class/apc_links.lib.php';
require_once __DIR__ . '/class/pdf/pdf_requisition_apc.modules.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

$id = (int)GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ');
$confirm = GETPOST('confirm', 'alpha');
$token = GETPOST('token', 'alpha');

$object = new ApcRequisition($db);
$extrafields = new ExtraFields($db);
$extralabels = $extrafields->fetch_name_optionals_label('apclogistics_requisition');

if ($id > 0 || $ref) {
    $res = ($id > 0) ? $object->fetch($id) : $object->fetch(0, $ref);
    if ($res <= 0) { dol_print_error('', 'ErrorLoadFailed'); exit; }
    $object->fetchLines();
}

$permissiontoread     = !empty($user->rights->apclogistics->requisition->read);
$permissiontocreate   = !empty($user->rights->apclogistics->requisition->create);
$permissiontoedit     = !empty($user->rights->apclogistics->requisition->edit);
$permissiontodelete   = !empty($user->rights->apclogistics->requisition->delete);
$permissiontovalidate = !empty($user->rights->apclogistics->requisition->validate);

if ($action === 'create' && !$permissiontocreate) accessforbidden();
if (empty($permissiontoread) && $action !== 'create') accessforbidden();

$error = 0;
$backtopage = DOL_URL_ROOT . '/custom/apclogistics/requisition_list.php';
$form = new Form($db);

/*
 * Actions
 */
if ($action === 'add' && $permissiontocreate && !$error && $user->valid && $token && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $object->date_demande = dol_mktime(12,0,0, GETPOST('date_demandemonth', 'int'), GETPOST('date_demandeday', 'int'), GETPOST('date_demandeyear', 'int'));
    $object->date_demande = $db->idate($object->date_demande);
    $object->objet = GETPOST('objet', 'alphanohtml');
    $object->fk_etatbesoin = (int)GETPOST('fk_etatbesoin', 'int');
    $object->demandeur_nom = GETPOST('demandeur_nom', 'alphanohtml');
    $object->demandeur_fonction = GETPOST('demandeur_fonction', 'alphanohtml');
    $object->note_public = GETPOST('note_public', 'alphanohtml');

    if (empty($object->objet)) { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('FieldObjet')), null, 'errors'); $error++; }
    if (empty($object->date_demande) || $object->date_demande === '1970-01-01') { setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('REQDateDemande')), null, 'errors'); $error++; }

    if (!$error) {
        $res = $object->create($user);
        if ($res > 0) {
            $lignes = GETPOST('lines', 'array');
            $no = 1;
            if (is_array($lignes)) {
                foreach ($lignes as $line) {
                    if (empty($line['description'])) continue;
                    $ln = new ApcRequisitionLine($db);
                    $ln->fk_requisition = $object->id;
                    $ln->no_ligne = $no++;
                    $d = GETPOST('line_date_mouvement_' . ($no-1), 'alpha');
                    if ($d) {
                        $t = dol_mktime(12,0,0, GETPOST('line_date_mouvement_' . ($no-1) . 'month', 'int'), GETPOST('line_date_mouvement_' . ($no-1) . 'day', 'int'), GETPOST('line_date_mouvement_' . ($no-1) . 'year', 'int'));
                        $ln->date_mouvement = $db->idate($t);
                    } else {
                        $ln->date_mouvement = $object->date_demande;
                    }
                    $ln->description = $line['description'];
                    $ln->fk_product = (int)$line['fk_product'];
                    $ln->unite = $line['unite'];
                    $ln->qte_demandee = (float)str_replace(',', '.', $line['qte_demandee']);
                    $ln->qte_sortie = (float)str_replace(',', '.', $line['qte_sortie']);
                    $ln->ecart_qte = $ln->qte_demandee - $ln->qte_sortie;
                    $ln->create($user);
                }
            }
            header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/requisition_card.php?id=' . $object->id);
            exit;
        } else { setEventMessages($object->error, $object->errors, 'errors'); }
    }
}

if ($action === 'update' && $permissiontoedit && !$error && $user->valid && $token) {
    $object->date_demande = dol_mktime(12,0,0, GETPOST('date_demandemonth', 'int'), GETPOST('date_demandeday', 'int'), GETPOST('date_demandeyear', 'int'));
    $object->date_demande = $db->idate($object->date_demande);
    $object->objet = GETPOST('objet', 'alphanohtml');
    $object->fk_etatbesoin = (int)GETPOST('fk_etatbesoin', 'int');
    $object->demandeur_nom = GETPOST('demandeur_nom', 'alphanohtml');
    $object->demandeur_fonction = GETPOST('demandeur_fonction', 'alphanohtml');
    $object->note_public = GETPOST('note_public', 'alphanohtml');
    $res = $object->update($user);
    if ($res > 0) {
        $ln = new ApcRequisitionLine($db);
        $ln->deleteAllForParent($user, $object->id);
        $lignes = GETPOST('lines', 'array');
        $no = 1;
        if (is_array($lignes)) {
            foreach ($lignes as $line) {
                if (empty($line['description'])) continue;
                $nl = new ApcRequisitionLine($db);
                $nl->fk_requisition = $object->id;
                $nl->no_ligne = $no++;
                $d = isset($line['date_mouvement']) ? $line['date_mouvement'] : '';
                if ($d) {
                    $t = dol_mktime(12,0,0, GETPOST('line_date_mouvement_' . ($no-1) . 'month', 'int'), GETPOST('line_date_mouvement_' . ($no-1) . 'day', 'int'), GETPOST('line_date_mouvement_' . ($no-1) . 'year', 'int'));
                    $nl->date_mouvement = $db->idate($t);
                } else {
                    $nl->date_mouvement = $object->date_demande;
                }
                $nl->description = $line['description'];
                $nl->fk_product = (int)$line['fk_product'];
                $nl->unite = $line['unite'];
                $nl->qte_demandee = (float)str_replace(',', '.', $line['qte_demandee']);
                $nl->qte_sortie = (float)str_replace(',', '.', $line['qte_sortie']);
                $nl->ecart_qte = $nl->qte_demandee - $nl->qte_sortie;
                $nl->create($user);
            }
        }
        header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/requisition_card.php?id=' . $object->id);
        exit;
    } else { setEventMessages($object->error, $object->errors, 'errors'); }
}

if ($action === 'confirm_delete' && $confirm === 'yes' && $permissiontodelete && $token) {
    $res = $object->delete($user);
    if ($res > 0) { header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/requisition_list.php'); exit; }
    else setEventMessages($object->error, $object->errors, 'errors');
}

if ($action === 'generate_pdf' && $id > 0) {
    $g = new pdf_requisition_apc($db);
    $g->write_file($object, $langs, '', 'I');
    exit;
}

if ($action === 'sign' && $permissiontovalidate && $id > 0 && $token) {
    $level = (int)GETPOST('level', 'int');
    $nom = GETPOST('nom_sig', 'alphanohtml');
    $fct = GETPOST('fct_sig', 'alphanohtml');
    $res = 0;
    if ($level === 0) {
        $res = $object->sign(0, $user, $nom, $fct);
    } elseif ($level === 1) {
        $res = $object->validateAndProcessStock($user, $user, $nom, $fct);
    }
    if ($res > 0) { setEventMessages($langs->trans('SignOK'), null, 'mesgs'); }
    else setEventMessages($object->error, $object->errors, 'errors');
    header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/requisition_card.php?id=' . $object->id);
    exit;
}

/*
 * View
 */
$title = ($action === 'create' ? $langs->trans('NewRequisition') : ($object->ref ?: $langs->trans('REQTitle')));
llxHeader('', $title, '', '', 0, 0, array('/custom/apclogistics/js/apclogistics.js'), array('/custom/apclogistics/css/apclogistics.css'));

$head = array();
$head[0][0] = DOL_URL_ROOT . '/custom/apclogistics/requisition_card.php?id=' . ($object->id ?: 0) . '&action=' . $action;
$head[0][1] = $langs->trans('TabCard');
$head[0][2] = 'tabcard';
$head[1][0] = DOL_URL_ROOT . '/custom/apclogistics/requisition_card.php?id=' . ($object->id ?: 0) . '&action=links';
$head[1][1] = $langs->trans('TabLinks');
$head[1][2] = 'tablinks';
$head[2][0] = DOL_URL_ROOT . '/custom/apclogistics/requisition_card.php?id=' . ($object->id ?: 0) . '&action=audit';
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
    print '<tr><td class="titlefieldcreate fieldrequired">' . $langs->trans('REQRef') . '</td><td>';
    if ($editing) print $obj->ref; else print '<span class="opacitymedium">(Généré automatiquement à l\'enregistrement)</span>';
    print '</td></tr>';
    print '<tr><td class="fieldrequired">' . $langs->trans('REQDateDemande') . '</td><td>'
        . $form->select_date($obj->date_demande ? $obj->date_demande : -1, 'date_demande', 0, 0, 1, '', 1, 0) . '</td></tr>';
    print '<tr><td class="fieldrequired">' . $langs->trans('FieldObjet') . '</td><td>'
        . '<input type="text" size="80" name="objet" value="' . dol_escape_htmltag($obj->objet) . '"></td></tr>';

    $ebSelect = '<select class="flat" name="fk_etatbesoin"><option value="">— ' . $langs->trans('None') . ' —</option>';
    $sqlEB = "SELECT rowid, ref, objet FROM " . MAIN_DB_PREFIX . "apclogistics_etatbesoin WHERE entity IN (0," . getEntity('apclogistics_etatbesoin') . ") ORDER BY date_creation DESC LIMIT 100";
    $resEB = $db->query($sqlEB);
    if ($resEB) while ($o = $db->fetch_object($resEB)) {
        $sel = ($obj->fk_etatbesoin == $o->rowid) ? ' selected' : '';
        $ebSelect .= '<option value="' . $o->rowid . '"' . $sel . '>' . $o->ref . ' — ' . dol_escape_htmltag($o->objet) . '</option>';
    }
    $ebSelect .= '</select>';
    print '<tr><td>' . $langs->trans('REQFK_EB') . '</td><td>' . $ebSelect . '</td></tr>';

    print '<tr><td>' . $langs->trans('REQDemandeur') . ' / Nom</td><td>'
        . '<input type="text" size="50" name="demandeur_nom" value="' . dol_escape_htmltag($obj->demandeur_nom) . '"></td></tr>';
    print '<tr><td>' . $langs->trans('FieldFonction') . '</td><td>'
        . '<input type="text" size="50" name="demandeur_fonction" value="' . dol_escape_htmltag($obj->demandeur_fonction) . '"></td></tr>';
    print '<tr><td class="tdtop">' . $langs->trans('FieldNotes') . '</td><td>'
        . '<textarea rows="3" cols="80" name="note_public">' . dol_escape_htmltag($obj->note_public) . '</textarea></td></tr>';
    print '</table>';

    // ========== SECTION LIGNES ==========
    print '<h3 style="margin-top:18px;">' . $langs->trans('REQTitle') . ' — ' . $langs->trans('REQLines') . '</h3>';
    print '<table class="noborder centpercent" id="apc-rq-lines-table">';
    print '<thead><tr class="liste_titre">';
    print '<th style="width:40px;">N°</th>';
    print '<th style="min-width:100px;">' . $langs->trans('REQColDate') . '</th>';
    print '<th style="min-width:240px;">' . $langs->trans('REQColDescription') . ' *</th>';
    print '<th style="width:90px;">' . $langs->trans('REQColProduct') . '</th>';
    print '<th style="width:80px;">' . $langs->trans('REQColUnite') . '</th>';
    print '<th style="width:100px;" class="right">' . $langs->trans('REQColQteDem') . '</th>';
    print '<th style="width:100px;" class="right">' . $langs->trans('REQColQteSort') . '</th>';
    print '<th style="width:100px;" class="right">' . $langs->trans('REQColEcart') . '</th>';
    print '<th style="width:40px;">&nbsp;</th>';
    print '</tr></thead>';
    print '<tbody id="apc-rq-lines-body">';

    $lines = $editing && !empty($obj->lines) ? $obj->lines : array((object)array());
    $idx = 0;
    foreach ($lines as $ln) {
        $ddm = isset($ln->date_mouvement) ? $ln->date_mouvement : ($obj->date_demande ? $obj->date_demande : '');
        $desc = isset($ln->description) ? $ln->description : '';
        $fkpr = isset($ln->fk_product) ? (int)$ln->fk_product : 0;
        $unit = isset($ln->unite) ? $ln->unite : '';
        $qdem = isset($ln->qte_demandee) ? (float)$ln->qte_demandee : '';
        $qsor = isset($ln->qte_sortie) ? (float)$ln->qte_sortie : '';
        print '<tr class="apc-line-row">';
        print '<td class="apc-rq-no center">' . ($idx+1) . '</td>';
        print '<td class="apc-line-datecell">' . $formother->select_date($ddm ? $ddm : -1, 'line_date_mouvement_' . $idx, 0, 0, 1, '', 1, 0) . '</td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][description]" value="' . dol_escape_htmltag($desc) . '"></td>';
        print '<td><input type="number" min="0" step="1" class="flat width100" name="lines[' . $idx . '][fk_product]" value="' . ($fkpr ? $fkpr : '') . '"></td>';
        print '<td><input type="text" class="flat width100" name="lines[' . $idx . '][unite]" value="' . dol_escape_htmltag($unit) . '"></td>';
        print '<td><input type="text" class="flat width100 right apc-line-qdem" name="lines[' . $idx . '][qte_demandee]" value="' . ($qdem !== '' ? dol_escape_htmltag($qdem) : '') . '"></td>';
        print '<td><input type="text" class="flat width100 right apc-line-qsor" name="lines[' . $idx . '][qte_sortie]" value="' . ($qsor !== '' ? dol_escape_htmltag($qsor) : '') . '"></td>';
        print '<td class="right apc-line-ecart">0,00</td>';
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
    print ' <a class="button button-cancel" href="' . DOL_URL_ROOT . '/custom/apclogistics/requisition_list.php">' . $langs->trans('Cancel') . '</a>';
    print '</div>';
    print '</form>';

    // ===== JS : ajout/suppression lignes + calcul écart =====
    print '<script type="text/javascript">
    $(document).ready(function() {
        function APCNumFmt(n){ n = Number(n||0).toFixed(2); var p = n.split("."); p[0]=p[0].replace(/\B(?=(\d{3})+(?!\d))/g," "); return p.join(","); }
        function recalcEcart() {
            $(".apc-line-row").each(function(){
                var d = parseFloat($(this).find(".apc-line-qdem").val().replace(/\s/g,"").replace(",","."))||0;
                var s = parseFloat($(this).find(".apc-line-qsor").val().replace(/\s/g,"").replace(",","."))||0;
                $(this).find(".apc-line-ecart").text(APCNumFmt(d-s));
            });
        }
        function renumber() { $(".apc-rq-no").each(function(i){ $(this).text(i+1); }); }
        $(document).on("input", ".apc-line-qdem,.apc-line-qsor", recalcEcart);

        $("#apc-add-line").on("click", function() {
            var n = $("#apc-rq-lines-body tr").length;
            var today = new Date();
            var dy = String(today.getDate()).padStart(2,"0");
            var mo = String(today.getMonth()+1).padStart(2,"0");
            var yr = today.getFullYear();
            var dateHTML = \'<span class="select_date">\'
                + \'<select class="flat" name="line_date_mouvement_\'+n+\'day"><option value="\'+dy+\'">\'+dy+\'</option></select>\'
                + \'<select class="flat" name="line_date_mouvement_\'+n+\'month"><option value="\'+mo+\'">\'+mo+\'</option></select>\'
                + \'<select class="flat" name="line_date_mouvement_\'+n+\'year"><option value="\'+yr+\'">\'+yr+\'</option></select>\'
                + \'</span>\';
            var html = "<tr class=\"apc-line-row\">"
                + "<td class=\"apc-rq-no center\">"+(n+1)+"</td>"
                + "<td class=\"apc-line-datecell\">"+dateHTML+"</td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][description]\"></td>"
                + "<td><input type=\"number\" min=\"0\" step=\"1\" class=\"flat width100\" name=\"lines["+n+"][fk_product]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100\" name=\"lines["+n+"][unite]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100 right apc-line-qdem\" name=\"lines["+n+"][qte_demandee]\"></td>"
                + "<td><input type=\"text\" class=\"flat width100 right apc-line-qsor\" name=\"lines["+n+"][qte_sortie]\"></td>"
                + "<td class=\"right apc-line-ecart\">0,00</td>"
                + "<td><button type=\"button\" class=\"button apc-rm-line\" style=\"padding:2px 6px;\">-</button></td>"
                + "</tr>";
            $("#apc-rq-lines-body").append(html);
            renumber(); recalcEcart();
        });
        $(document).on("click", ".apc-rm-line", function() {
            if ($("#apc-rq-lines-body tr").length > 1) { $(this).closest("tr").remove(); renumber(); recalcEcart(); }
        });
        recalcEcart();
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
    print '<tr><td class="titlefield">' . $langs->trans('REQRef') . '</td><td class="valeur"><b>' . $object->ref . '</b></td>'
        . '<td class="titlefield">' . $langs->trans('FieldStatus') . '</td><td class="valeur">' . $object->getStatusBadge() . '</td></tr>';
    print '<tr><td>' . $langs->trans('REQDateDemande') . '</td><td>' . dol_print_date($db->jdate($object->date_demande), 'day') . '</td>'
        . '<td>' . $langs->trans('REQDateSortie') . '</td><td>' . ($object->date_sortie ? dol_print_date($db->jdate($object->date_sortie), 'day') : '<span class="opacitymedium">—</span>') . '</td></tr>';
    print '<tr><td class="tdtop">' . $langs->trans('FieldObjet') . '</td><td colspan="3">' . dol_escape_htmltag($object->objet) . '</td></tr>';

    if ($object->fk_etatbesoin > 0) {
        $eb = new ApcEtatBesoin($db);
        if ($eb->fetch($object->fk_etatbesoin) > 0) {
            print '<tr><td>' . $langs->trans('REQFK_EB') . '</td><td colspan="3">' . $eb->getNomUrl(1) . ' — ' . dol_escape_htmltag($eb->objet) . '</td></tr>';
        }
    }

    print '<tr><td>' . $langs->trans('REQDemandeur') . '</td><td>' . dol_escape_htmltag($object->demandeur_nom)
        . (empty($object->date_signature_demandeur) ? '' : ' <span class="opacitymedium">(signé ' . dol_print_date($db->jdate($object->date_signature_demandeur), 'day') . ')</span>')
        . (empty($object->demandeur_fonction) ? '' : ' - <i>' . dol_escape_htmltag($object->demandeur_fonction) . '</i>')
        . '</td>';
    print '<td>' . $langs->trans('REQMagasinier') . '</td><td>' . dol_escape_htmltag($object->magasinier_nom)
        . (empty($object->date_signature_magasinier) ? '' : ' <span class="opacitymedium">(signé ' . dol_print_date($db->jdate($object->date_signature_magasinier), 'day') . ')</span>')
        . (empty($object->magasinier_fonction) ? '' : ' - <i>' . dol_escape_htmltag($object->magasinier_fonction) . '</i>')
        . '</td></tr>';
    if (!empty($object->note_public)) print '<tr><td class="tdtop">' . $langs->trans('FieldNotes') . '</td><td colspan="3">' . dol_escape_htmltag($object->note_public) . '</td></tr>';
    print '</table>';

    // ======= TABLEAU LIGNES =======
    $object->fetchLines();
    print '<h3 style="margin-top:18px;">' . $langs->trans('REQLines') . '</h3>';
    print '<table class="noborder centpercent">';
    print '<thead><tr class="liste_titre">';
    print '<th class="center width40">N°</th>';
    print '<th>' . $langs->trans('REQColDate') . '</th>';
    print '<th>' . $langs->trans('REQColDescription') . '</th>';
    print '<th class="center">' . $langs->trans('REQColProduct') . '</th>';
    print '<th class="center">' . $langs->trans('REQColUnite') . '</th>';
    print '<th class="right" style="width:120px;">' . $langs->trans('REQColQteDem') . '</th>';
    print '<th class="right" style="width:120px;">' . $langs->trans('REQColQteSort') . '</th>';
    print '<th class="right" style="width:120px;">' . $langs->trans('REQColEcart') . '</th>';
    print '</tr></thead><tbody>';
    $i = 1; $tQDem=0; $tQSort=0; $tEcart=0;
    foreach ($object->lines as $ln) {
        $tQDem += (float)$ln->qte_demandee;
        $tQSort += (float)$ln->qte_sortie;
        $ec = (float)$ln->qte_demandee - (float)$ln->qte_sortie;
        $tEcart += $ec;
        print '<tr class="oddeven"><td class="center">' . $i++ . '</td>';
        print '<td>' . ($ln->date_mouvement ? dol_print_date($db->jdate($ln->date_mouvement), 'day') : '<span class="opacitymedium">—</span>') . '</td>';
        print '<td>' . dol_escape_htmltag($ln->description) . '</td>';
        print '<td class="center">' . ($ln->fk_product ? (int)$ln->fk_product : '<span class="opacitymedium">—</span>') . '</td>';
        print '<td class="center">' . dol_escape_htmltag($ln->unite) . '</td>';
        print '<td class="right">' . price((float)$ln->qte_demandee, 0, $langs, 0, 0, 0, '') . '</td>';
        print '<td class="right">' . price((float)$ln->qte_sortie, 0, $langs, 0, 0, 0, '') . '</td>';
        $cls = ($ec < 0) ? 'apc-money warn' : 'apc-money';
        print '<td class="right ' . $cls . '">' . price($ec, 0, $langs, 0, 0, 0, '') . '</td>';
        print '</tr>';
    }
    if (empty($object->lines)) print '<tr class="oddeven"><td colspan="8" class="opacitymedium">' . $langs->trans('NoRecord') . '</td></tr>';
    print '</tbody><tfoot><tr class="liste_total"><td colspan="5" class="right"><b>' . $langs->trans('REQTotalLignes') . '</b></td>'
        . '<td class="right">' . price($tQDem, 0, $langs, 0, 0, 0, '') . '</td>'
        . '<td class="right">' . price($tQSort, 0, $langs, 0, 0, 0, '') . '</td>'
        . '<td class="right">' . price($tEcart, 0, $langs, 0, 0, 0, '') . '</td></tr></tfoot>';
    print '</table>';

    // ======= ZONES VALIDATION (2 signatures : Demandeur (0) puis Magasinier (1) + validateAndProcessStock) =======
    if ($permissiontovalidate && (int)$object->status < ApcRequisition::STATUS_VALIDATED) {
        print '<h3 style="margin-top:18px;">' . $langs->trans('AUDSignature') . ' + Traitement Stock</h3>';
        $sigStates = array(
            0 => array('key'=>'Demandeur',  'date_field'=>'date_signature_demandeur', 'nom_field'=>'demandeur_nom', 'libelle'=>$langs->trans('REQDemandeur'),  'desc'=>'Signer la demande en tant que demandeur (étape 1/2)'),
            1 => array('key'=>'Magasinier', 'date_field'=>'date_signature_magasinier','nom_field'=>'magasinier_nom','libelle'=>$langs->trans('REQMagasinier'), 'desc'=>'Valider la sortie (appliquer stock + signature, étape 2/2)'),
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
                print '<b>Étape ' . ($lvl+1) . '/2 — Signer en tant que : ' . $inf['libelle'] . '</b> <span class="opacitymedium">(' . $inf['desc'] . ')</span><br>';
                print 'Nom <input type="text" name="nom_sig" size="28" value="' . dol_escape_htmltag($user->getFullName($langs)) . '"> ';
                print 'Fonction <input type="text" name="fct_sig" size="22" value="' . dol_escape_htmltag($user->poste) . '"> ';
                print '<input type="submit" class="button" value="Signer' . ($lvl === 1 ? ' + Sortie stock' : '') . '">';
                print '</form></div>';
            }
        }
    }

    // ======= BOUTONS ACTIONS =======
    print '<div class="tabsAction" style="margin-top:24px;">';
    print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/requisition_card.php?id=' . $object->id . '&action=generate_pdf&token=' . newToken() . '">' . $langs->trans('PDFGenerateBtn') . '</a>';
    if ($permissiontoedit) print '<a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/requisition_card.php?id=' . $object->id . '&action=edit">' . $langs->trans('Modify') . '</a>';
    if ($permissiontodelete) print '<a class="butActionDelete" href="' . DOL_URL_ROOT . '/custom/apclogistics/requisition_card.php?id=' . $object->id . '&action=delete">' . $langs->trans('Delete') . '</a>';
    print '</div>';
}

dol_fiche_end();

if ($action === 'links' && $object->id > 0) {
    apcPrintLinksTab($db, $langs, $conf, $object);
}

if ($action === 'audit' && $object->id > 0) {
    $audits = ApcAuditLog::fetchForEntity($db, 'req', $object->id, 200);
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
