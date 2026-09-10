<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 * admin/apclogistics_setup.php — Page de configuration du module APC Logistique
 * Accessible via : Administration > Modules > APC Logistique > bouton « Config »
 * Sauvegarde : $conf->global->APCLOGISTICS_*  dans la table llx_const
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (! defined('NOREQUIREMENU'))  define('NOREQUIREMENU', '1');
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/../lib/apclogistics.lib.php';

$langs->load('admin');
$langs->load('apclogistics@apclogistics');
$langs->load('main');

if (!$user->admin) accessforbidden();

$action  = GETPOST('action', 'aZ');
$token   = GETPOST('token', 'alpha');
$backtopage = DOL_URL_ROOT . '/admin/modules.php?mode=common';

$error = 0;

/*
 * Actions de sauvegarde
 */
if ($action === 'save' && $user->admin && $token && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $valTokenDays = (int)GETPOST('APCLOGISTICS_TOKEN_DAYS', 'int');
    if ($valTokenDays < 7)  $valTokenDays = 7;
    if ($valTokenDays > 60) $valTokenDays = 60;

    $constants = array(
        'APCLOGISTICS_TOKEN_DAYS'        => (string)$valTokenDays,
        'APCLOGISTICS_NOTIF_EMAIL'       => GETPOST('APCLOGISTICS_NOTIF_EMAIL', 'alpha'),
        'APCLOGISTICS_DP_LEGAL_NOTICE'   => GETPOST('APCLOGISTICS_DP_LEGAL_NOTICE', 'alphanohtml'),
        'APCLOGISTICS_PREFIX_EB'         => GETPOST('APCLOGISTICS_PREFIX_EB', 'alpha'),
        'APCLOGISTICS_PREFIX_REQ'        => GETPOST('APCLOGISTICS_PREFIX_REQ', 'alpha'),
        'APCLOGISTICS_PREFIX_DP'         => GETPOST('APCLOGISTICS_PREFIX_DP', 'alpha'),
        'APCLOGISTICS_PREFIX_COT'        => GETPOST('APCLOGISTICS_PREFIX_COT', 'alpha'),
        'APCLOGISTICS_PREFIX_BC'         => GETPOST('APCLOGISTICS_PREFIX_BC', 'alpha'),
        'APCLOGISTICS_PREFIX_BR'         => GETPOST('APCLOGISTICS_PREFIX_BR', 'alpha'),
        'APCLOGISTICS_PREFIX_DAV'        => GETPOST('APCLOGISTICS_PREFIX_DAV', 'alpha'),
        'APCLOGISTICS_PREFIX_JAV'        => GETPOST('APCLOGISTICS_PREFIX_JAV', 'alpha'),
        'APCLOGISTICS_PREFIX_DPAI'       => GETPOST('APCLOGISTICS_PREFIX_DPAI', 'alpha'),
        'APCLOGISTICS_STOCK_ALERT_QTY'   => GETPOST('APCLOGISTICS_STOCK_ALERT_QTY', 'int'),
        'APCLOGISTICS_HEADER_ONG'        => GETPOST('APCLOGISTICS_HEADER_ONG', 'alphanohtml'),
        'APCLOGISTICS_HEADER_ADDR'       => GETPOST('APCLOGISTICS_HEADER_ADDR', 'alphanohtml'),
        'APCLOGISTICS_HEADER_CONTACT'    => GETPOST('APCLOGISTICS_HEADER_CONTACT', 'alphanohtml'),
        'APCLOGISTICS_HEADER_LEGAL'      => GETPOST('APCLOGISTICS_HEADER_LEGAL', 'alphanohtml'),
    );

    $okCount = 0;
    foreach ($constants as $k => $v) {
        $res = dolibarr_set_const($db, $k, $v, 'chaine', 0, '', $conf->entity);
        if ($res > 0) { $okCount++; $conf->global->$k = $v; }
        else { $error++; setEventMessages('Erreur sauvegarde ' . $k, null, 'errors'); }
    }

    // Upload optionnel logo (PNG/JPG, max 2 Mo) -> $conf->apclogistics->dir_output/logos/
    if (isset($_FILES['APCLOGISTICS_LOGO']) && is_uploaded_file($_FILES['APCLOGISTICS_LOGO']['tmp_name']) && !$error) {
        $file = $_FILES['APCLOGISTICS_LOGO'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error++;
            setEventMessages('Erreur upload logo (code ' . (int)$file['error'] . ')', null, 'errors');
        } else {
            $imgInfo = @getimagesize($file['tmp_name']);
            $mime = $imgInfo ? $imgInfo['mime'] : '';
            $ext = '';
            if ($mime === 'image/png') $ext = 'png';
            elseif ($mime === 'image/jpeg') $ext = 'jpg';
            if ($ext === '') {
                $error++;
                setEventMessages('Logo invalide : PNG ou JPG requis', null, 'errors');
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $error++;
                setEventMessages('Logo trop volumineux (max 2 Mo)', null, 'errors');
            } else {
                $destDir = apcLogoDirOutput() . '/logos';
                if (!is_dir($destDir)) { dol_mkdir($destDir); }
                $dest = $destDir . '/logo_apc.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // Suppression des anciens fichiers (autre extension)
                    foreach (array('png', 'jpg', 'jpeg', 'gif') as $oldExt) {
                        if ($oldExt !== $ext) {
                            $oldFile = $destDir . '/logo_apc.' . $oldExt;
                            if (file_exists($oldFile)) @unlink($oldFile);
                        }
                    }
                    // Constante : chemin RELATIF a dir_output (ex. logos/logo_apc.png)
                    $relPath = 'logos/logo_apc.' . $ext;
                    dolibarr_set_const($db, 'APCLOGISTICS_LOGO', $relPath, 'chaine', 0, '', $conf->entity);
                    $conf->global->APCLOGISTICS_LOGO = $relPath;
                    // Nettoyage de l'ancienne constante (chemin absolu)
                    dolibarr_del_const($db, 'APCLOGISTICS_LOGO_PATH', $conf->entity);
                    unset($conf->global->APCLOGISTICS_LOGO_PATH);
                    $okCount++;
                } else {
                    $error++;
                    setEventMessages('Erreur upload logo', null, 'errors');
                }
            }
        }
    }

    // Suppression du logo (retour au placeholder)
    if (GETPOST('APCLOGISTICS_LOGO_DELETE', 'int') && !$error) {
        $destDir = apcLogoDirOutput() . '/logos';
        foreach (array('png', 'jpg', 'jpeg', 'gif') as $ext) {
            $f = $destDir . '/logo_apc.' . $ext;
            if (file_exists($f)) @unlink($f);
        }
        dolibarr_del_const($db, 'APCLOGISTICS_LOGO', $conf->entity);
        dolibarr_del_const($db, 'APCLOGISTICS_LOGO_PATH', $conf->entity);
        unset($conf->global->APCLOGISTICS_LOGO);
        unset($conf->global->APCLOGISTICS_LOGO_PATH);
        $okCount++;
    }

    if (!$error) {
        setEventMessages($okCount . ' paramètres sauvegardés avec succès', null, 'mesgs');
    }
    header('Location: ' . $_SERVER["PHP_SELF"]);
    exit;
}

/*
 * View
 */
$form = new Form($db);
$formother = new FormOther($db);

$title = $langs->trans('ModuleSetup') . ' - APC Logistics & Procurement';
llxHeader('', $title, '', '', 0, 0, array(), array());

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?mode=common">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($title, $linkback, 'setup', 0, '', '', 'apclogistics@apclogistics');

$h = 0;
$head[$h][0] = DOL_URL_ROOT . '/custom/apclogistics/admin/apclogistics_setup.php';
$head[$h][1] = $langs->trans('Settings');
$head[$h][2] = 'general';
$h++;
$head[$h][0] = DOL_URL_ROOT . '/custom/apclogistics/dashboard.php';
$head[$h][1] = $langs->trans('Dashboard');
$head[$h][2] = 'dashboard';

dol_fiche_head($head, 'general', '', -1);

$v = function($k, $def='') use ($conf, $langs) {
    return isset($conf->global->$k) ? $conf->global->$k : $def;
};

print '<form enctype="multipart/form-data" action="' . $_SERVER["PHP_SELF"] . '" method="POST">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="save">';

// ============ SECTION IDENTITÉ APC ============
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="titlefieldcreate" colspan="2"><b>' . $langs->trans('APCSetupIdentite') . '</b> — ' . $langs->trans('APCSetupIdentiteDesc') . '</td></tr>';

print '<tr class="oddeven"><td class="titlefieldcreate">' . $langs->trans('APCSetupLogo') . '</td><td>';
$logoAbs = apcLogoPath();
$logoUrl = apcLogoUrl();
if ($logoAbs !== '' && $logoUrl !== '') {
    print '<img src="' . $logoUrl . '" alt="Logo APC" style="max-height:60px; max-width:140px; border:1px solid #ddd; border-radius:4px; padding:4px; background:#fff; vertical-align:middle; margin-right:10px;">';
    print '<span class="ok">✓ personnalisé</span>';
} else {
    print '<span class="opacitymedium">placeholder par défaut</span>';
}
print '<br><input type="file" name="APCLOGISTICS_LOGO" accept="image/png,image/jpeg"> <small class="opacitymedium">PNG/JPG — recommandé 1000×1000 — max 2 Mo</small>';
print '<br><label style="font-weight:normal;"><input type="checkbox" name="APCLOGISTICS_LOGO_DELETE" value="1"> ' . $langs->trans('APCSetupLogoDelete') . '</label>';
print '</td></tr>';

print '<tr class="oddeven"><td class="titlefieldcreate">' . $langs->trans('APCSetupHeaderONG') . '</td><td>'
    . '<input type="text" size="60" name="APCLOGISTICS_HEADER_ONG" value="' . dol_escape_htmltag($v('APCLOGISTICS_HEADER_ONG','AGRI-PEACE AND CHILD (APC) ASBL')) . '"></td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">' . $langs->trans('APCSetupHeaderAddr') . '</td><td>'
    . '<input type="text" size="80" name="APCLOGISTICS_HEADER_ADDR" value="' . dol_escape_htmltag($v('APCLOGISTICS_HEADER_ADDR','Goma — Province du Nord-Kivu, RD Congo')) . '"></td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">' . $langs->trans('APCSetupHeaderContact') . '</td><td>'
    . '<input type="text" size="80" name="APCLOGISTICS_HEADER_CONTACT" value="' . dol_escape_htmltag($v('APCLOGISTICS_HEADER_CONTACT','Email: contact@apc-ong.org  —  Tél: +243 000 000 000')) . '"></td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">' . $langs->trans('APCSetupHeaderLegal') . '</td><td>'
    . '<input type="text" size="80" name="APCLOGISTICS_HEADER_LEGAL" value="' . dol_escape_htmltag($v('APCLOGISTICS_HEADER_LEGAL','Arrêté Ministériel N°308/CAB/M.E/J&GS/2023')) . '"></td></tr>';
print '</table>';

// ============ SECTION NOTIFICATIONS & PORTAIL ============
print '<br><table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="titlefieldcreate" colspan="2"><b>' . $langs->trans('APCSetupNotif') . '</b> — ' . $langs->trans('APCSetupNotifDesc') . '</td></tr>';

print '<tr class="oddeven"><td class="titlefieldcreate">' . $langs->trans('APCSetupNotifEmail') . ' *</td><td>'
    . '<input type="email" size="50" name="APCLOGISTICS_NOTIF_EMAIL" value="' . dol_escape_htmltag($v('APCLOGISTICS_NOTIF_EMAIL','logistique@apc-ong.org')) . '">'
    . ' <small class="opacitymedium">' . $langs->trans('APCSetupNotifEmailDesc') . '</small></td></tr>';

$tokDays = (int)$v('APCLOGISTICS_TOKEN_DAYS', '30');
print '<tr class="oddeven"><td class="titlefieldcreate">' . $langs->trans('APCSetupTokenDays') . ' <span class="opacitymedium">(7—60j)</span></td><td>'
    . '<input type="range" name="APCLOGISTICS_TOKEN_DAYS" min="7" max="60" step="1" value="' . $tokDays . '" id="tokenRange" oninput="document.getElementById(\'tokenOut\').value=this.value"> '
    . '<output id="tokenOut"><b>' . $tokDays . '</b></output> jours — '
    . '<small class="opacitymedium">' . $langs->trans('APCSetupTokenDaysDesc') . '</small></td></tr>';

print '<tr class="oddeven"><td class="titlefieldcreate tdtop">' . $langs->trans('APCSetupLegalNotice') . '</td><td>'
    . '<textarea rows="3" cols="90" name="APCLOGISTICS_DP_LEGAL_NOTICE">'
    . dol_escape_htmltag($v('APCLOGISTICS_DP_LEGAL_NOTICE', "Cette demande de prix n'oblige en rien APC à contracter, à acheter ou à consommer votre service"))
    . '</textarea> <br><small class="opacitymedium">' . $langs->trans('APCSetupLegalNoticeDesc') . '</small></td></tr>';
print '</table>';

// ============ SECTION MODÈLES NUMÉROTATION ============
print '<br><table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2"><b>' . $langs->trans('APCSetupPrefixes') . '</b> — ' . $langs->trans('APCSetupPrefixesDesc') . '</td></tr>';
print '<tr class="oddeven"><td>';
$prefixes = array(
    'APCLOGISTICS_PREFIX_EB'    => array('EB',  'État de besoin'),
    'APCLOGISTICS_PREFIX_REQ'   => array('REQ', 'Réquisition / Sortie magasin'),
    'APCLOGISTICS_PREFIX_DP'    => array('DP',  'Demande de prix'),
    'APCLOGISTICS_PREFIX_COT'   => array('COT', 'Cotation fournisseur'),
    'APCLOGISTICS_PREFIX_BC'    => array('BC',  'Bon de commande'),
    'APCLOGISTICS_PREFIX_BR'    => array('BR',  'Bon de réception'),
    'APCLOGISTICS_PREFIX_DAV'   => array('DAV', 'Demande d\'avance'),
    'APCLOGISTICS_PREFIX_JAV'   => array('JAV', 'Justification d\'avance'),
    'APCLOGISTICS_PREFIX_DPAI'  => array('DPAI','Demande de paiement'),
);
$half = ceil(count($prefixes)/2);
$i = 0;
print '<table style="width:100%;"><tr><td style="width:50%; vertical-align:top;">';
foreach ($prefixes as $k => $def) {
    if ($i === $half) { print '</td><td style="width:50%; vertical-align:top;">'; }
    print '<div style="margin:4px 0;"><b>' . $def[1] . '</b> : '
        . '<input type="text" size="10" name="' . $k . '" value="' . dol_escape_htmltag($v($k, $def[0].'-')) . '"> '
        . '<small class="opacitymedium">(YYYY-NNNN)</small></div>';
    $i++;
}
print '</td></tr></table>';
print '</td></tr></table>';

// ============ SECTION STOCK ============
print '<br><table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="titlefieldcreate" colspan="2"><b>' . $langs->trans('APCSetupStock') . '</b></td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">' . $langs->trans('APCSetupStockAlert') . '</td><td>'
    . '<input type="number" min="0" step="1" size="8" name="APCLOGISTICS_STOCK_ALERT_QTY" value="' . dol_escape_htmltag($v('APCLOGISTICS_STOCK_ALERT_QTY','5')) . '">'
    . ' <small class="opacitymedium">' . $langs->trans('APCSetupStockAlertDesc') . '</small></td></tr>';
print '</table>';

// ============ BOUTONS ============
print '<div class="tabsAction" style="margin-top:24px;">';
print '<input type="submit" class="button button-save" value="' . $langs->trans('Save') . '"> ';
print '<a class="button button-cancel" href="' . DOL_URL_ROOT . '/admin/modules.php?mode=common">' . $langs->trans('Cancel') . '</a>';
print '</div>';
print '</form>';

dol_fiche_end();

// Petit aide mémoire
print '<div style="margin-top:28px; padding:14px; background:#f5faf5; border:1px solid #cfe3cf; border-radius:6px;">';
print '<b>' . $langs->trans('APCSetupHelpTitle') . '</b><ul style="margin:6px 0 0 20px;">';
print '<li>' . $langs->trans('APCSetupHelp1') . '</li>';
print '<li>' . $langs->trans('APCSetupHelp2') . '</li>';
print '<li>' . $langs->trans('APCSetupHelp3') . '</li>';
print '</ul></div>';

llxFooter();
$db->close();
