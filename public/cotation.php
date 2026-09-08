<?php
if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);
if (! defined('NOREQUIREMENU')) define('NOREQUIREMENU', 1);
if (! defined('NOREQUIREUSER')) define('NOREQUIREUSER', 1);
if (! defined('NOREQUIREHTML')) define('NOREQUIREHTML', 1);
if (! defined('NOLOGIN')) define('NOLOGIN', 1);
if (! defined('NOCSRFCHECK')) define('NOCSRFCHECK', 1);

require '../../../master.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/translate.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once __DIR__ . '/../class/ApcToken.class.php';
require_once __DIR__ . '/../class/ApcDemandePrix.class.php';
require_once __DIR__ . '/../class/ApcCotation.class.php';

global $db, $conf, $langs;

// Portail public sans session : user systeme pour les ecritures en base
$user = new User($db);
$user->id = 0;
$user->login = 'public';

$langs->setDefaultLang('fr_FR');
$langs->load('apclogistics@apclogistics');
$langs->load('main');

$clearToken = isset($_GET['token']) ? (string)$_GET['token'] : '';
$clearToken = trim($clearToken);

$tokenRow = null;
$tokenStatus = ApcToken::validate($db, $clearToken, $tokenRow);
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

$errorMessages = array();
$okMessages = array();
$dpLoaded = null;
$dpLines = array();
$cotCreatedRef = '';

$errorsTpl = function($arr) {
    if (empty($arr)) return '';
    $out = '<div style="background:#ffebee;color:#b71c1c;padding:12px 16px;border-radius:8px;margin-bottom:16px;border:1px solid #ef9a9a;">'
         . '<b>' . htmlspecialchars($GLOBALS['langs']->trans('Error'), ENT_COMPAT, 'UTF-8') . '</b><ul style="margin:6px 0 0 18px;padding:0;">';
    foreach ($arr as $m) $out .= '<li>' . htmlspecialchars($m, ENT_COMPAT, 'UTF-8') . '</li>';
    $out .= '</ul></div>';
    return $out;
};

$okTpl = function($title, $body = '') {
    return '<div style="background:#e8f5e9;color:#1b5e20;padding:16px 20px;border-radius:8px;border:1px solid #a5d6a7;">'
         . '<h2 style="margin:0 0 8px 0;">' . htmlspecialchars($title, ENT_COMPAT, 'UTF-8') . '</h2>'
         . ($body ? '<p style="margin:0;">' . $body . '</p>' : '')
         . '</div>';
};

$fmtDateInput = function($ts = null) {
    if (!$ts) $ts = time();
    return date('Y-m-d', $ts);
};

$fmtMoney = function($n) {
    return number_format((float)$n, 2, ',', ' ');
};

$dpLoaded = new ApcDemandePrix($db);
if ($tokenStatus === 'ok' && !empty($tokenRow->fk_demandeprix)) {
    if ($dpLoaded->fetch((int)$tokenRow->fk_demandeprix) > 0) {
        $dpLines = $dpLoaded->fetchLines();
    }
}

if ($isPost && $tokenStatus === 'ok' && !empty($tokenRow)) {
    $suppNom      = trim((string)GETPOST('supp_nom', 'alphanohtml'));
    $suppAdr      = trim((string)GETPOST('supp_adr', 'alphanohtml'));
    $suppTel      = trim((string)GETPOST('supp_tel', 'alphanohtml'));
    $suppContact  = trim((string)GETPOST('supp_contact', 'alphanohtml'));
    $suppEmail    = trim((string)GETPOST('supp_email', 'alpha'));

    $livLieu      = trim((string)GETPOST('liv_lieu', 'alphanohtml'));
    $livDate      = trim((string)GETPOST('liv_date', 'alphanohtml'));
    $livDelai     = trim((string)GETPOST('liv_delai', 'alphanohtml'));
    $livCond      = trim((string)GETPOST('liv_cond', 'alphanohtml'));

    $linesPU      = GETPOST('pu', 'array');
    $linesRem     = GETPOST('rem', 'array');
    $checkOK      = (GETPOST('check_cond', 'alpha') === 'on');

    if (empty($suppNom))     $errorMessages[] = $langs->trans('PUBErrRequired') . ' : ' . $langs->trans('PUBCoordNom');
    if (empty($suppTel))     $errorMessages[] = $langs->trans('PUBErrRequired') . ' : ' . $langs->trans('PUBCoordTel');
    if (empty($suppContact)) $errorMessages[] = $langs->trans('PUBErrRequired') . ' : ' . $langs->trans('PUBCoordContact');
    if (empty($livLieu))     $errorMessages[] = $langs->trans('PUBErrRequired') . ' : ' . $langs->trans('PUBLivLieu');
    if (empty($livDate))     $errorMessages[] = $langs->trans('PUBErrRequired') . ' : ' . $langs->trans('PUBLivDate');
    if (!$checkOK)           $errorMessages[] = $langs->trans('PUBErrTerms');

    $idx = 0;
    $validLines = 0;
    foreach ($dpLines as $ln) {
        $v = isset($linesPU[$idx]) ? trim((string)$linesPU[$idx]) : '';
        if ($v === '') {
            $errorMessages[] = $langs->trans('PUBErrRequired') . ' : PU ligne ' . ($idx + 1);
        } elseif (!is_numeric(str_replace(',', '.', $v))) {
            $errorMessages[] = 'PU ligne ' . ($idx + 1) . ' invalide (nombre attendu)';
        } else {
            $validLines++;
        }
        $idx++;
    }

    if (empty($errorMessages)) {
        $db->begin();
        try {
            $cot = new ApcCotation($db);
            $cot->fk_demandeprix    = (int)$tokenRow->fk_demandeprix;
            $cot->fk_token          = (int)$tokenRow->rowid;
            $cot->fournisseur_nom     = $suppNom;
            $cot->fournisseur_adresse = $suppAdr;
            $cot->fournisseur_tel     = $suppTel;
            $cot->fournisseur_email   = $suppEmail;
            $cot->fournisseur_contact = $suppContact;
            $cot->date_cotation       = $db->idate(dol_now());
            $cot->lieu_livraison      = $livLieu;
            $cot->date_livraison      = $db->idate($livDate ? strtotime($livDate) : (time() + 14 * 86400));
            $cot->date_validite_offre = $db->idate(time() + 30 * 86400);   // validite de l'offre : 30 jours par defaut
            $cot->delai_livraison     = $livDelai;
            $cot->conditions_reglement = $livCond;
            $cot->conditions_acceptation = $checkOK ? 'OUI' : '';
            $cot->taux_tva_applicable = 0;

            $cot->signature_fournisseur_nom  = $suppContact ? $suppContact : $suppNom;
            $cot->signature_fournisseur_fct  = '';
            $cot->signature_fournisseur_date = $db->idate(dol_now());
            $cot->signature_fournisseur_ip   = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';

            $cot->status = ApcCotation::STATUS_RECEIVED;

            $totalHT = 0;
            $resCot = $cot->create($user);
            if ($resCot > 0) {
                $noLigne = 1;
                foreach ($dpLines as $ln) {
                    $puStr = isset($linesPU[$idx = ($noLigne - 1)]) ? (string)$linesPU[$noLigne - 1] : '0';
                    $pu = (float)str_replace(',', '.', $puStr);
                    $remarque = isset($linesRem[$noLigne - 1]) ? (string)$linesRem[$noLigne - 1] : '';
                    $cl = new ApcCotationLine($db);
                    $cl->fk_cotation         = $cot->id;
                    $cl->fk_demandeprix_line = (int)$ln->rowid;
                    $cl->no_ligne            = $noLigne;
                    $cl->fk_product          = (int)$ln->fk_product;
                    $cl->description         = $ln->specification;
                    $cl->unite               = $ln->unite;
                    $cl->quantite            = (float)$ln->quantite;
                    $cl->prix_unitaire_ht    = $pu;
                    $cl->remise_pct          = 0;
                    $cl->total_ht            = round($pu * (float)$ln->quantite, 2);
                    $cl->remarque            = $remarque;
                    $cl->tva_tx              = 0;
                    $cl->create($user);
                    $totalHT += $cl->total_ht;
                    $noLigne++;
                }
                $cot->total_ht = round($totalHT, 2);
                $cot->total_tva = 0;
                $cot->total_ttc = $cot->total_ht;
                $cot->update($user);

                $consumed = ApcToken::consume($db, (int)$tokenRow->rowid, (int)$cot->id,
                    isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '',
                    isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '');

                $db->commit();
                $cotCreatedRef = $cot->ref;

                $objetMail = 'Cotation ' . $cot->ref . ' enregistrée — APC ONG';
                $corpsMail  = "Bonjour,\n\n";
                $corpsMail .= "Votre cotation référence {$cot->ref} a bien été enregistrée.\n";
                $corpsMail .= "Nous vous remercions pour votre réponse à la demande de prix {$dpLoaded->ref}.\n\n";
                $corpsMail .= "Cordialement,\nAPC ONG Agri-Peace and Child";
                if (!empty($suppEmail)) {
                    try {
                        $mail = new CMailFile(
                            $objetMail,
                            $suppEmail,
                            !empty($conf->global->MAIN_MAIL_EMAIL_FROM) ? $conf->global->MAIN_MAIL_EMAIL_FROM : 'noreply@apc-ong.org',
                            $corpsMail
                        );
                        $mail->sendfile();
                    } catch (Exception $e) {
                        dol_syslog('APC public/cotation email supp error: ' . $e->getMessage(), LOG_WARNING);
                    }
                }
                $notifEmail = !empty($conf->global->APCLOGISTICS_NOTIF_EMAIL) ? $conf->global->APCLOGISTICS_NOTIF_EMAIL : '';
                if ($notifEmail) {
                    try {
                        $corpsNotif = "Bonjour,\n\nUne nouvelle cotation {$cot->ref} a été soumise par {$suppNom}.\n"
                                    . "Demande de prix : {$dpLoaded->ref}\nTotal HT : " . $fmtMoney($cot->total_ht) . "\n\n"
                                    . "Accès Dolibarr : custom/apclogistics/cotation_card.php?id={$cot->id}\n\nAPC Logistics";
                        $mailN = new CMailFile(
                            '[APC] Nouvelle cotation ' . $cot->ref . ' — ' . $suppNom,
                            $notifEmail,
                            !empty($conf->global->MAIN_MAIL_EMAIL_FROM) ? $conf->global->MAIN_MAIL_EMAIL_FROM : 'noreply@apc-ong.org',
                            $corpsNotif
                        );
                        $mailN->sendfile();
                    } catch (Exception $e) {
                        dol_syslog('APC public/cotation email notif error: ' . $e->getMessage(), LOG_WARNING);
                    }
                }

                $okMessages[] = 'OK';
            } else {
                $db->rollback();
                $errorMessages[] = 'Erreur enregistrement cotation : ' . $cot->error;
            }
        } catch (Exception $e) {
            $db->rollback();
            $errorMessages[] = 'Exception : ' . $e->getMessage();
        }
    }
}

header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<title>' . htmlspecialchars($langs->trans('PUBWelcomeTitle'), ENT_COMPAT, 'UTF-8') . ' — APC ONG</title>';
echo '<style>
:root{ --apc-green:#0f623c; --apc-green-dark:#0b4a2d; --apc-green-light:#e8f5e9;
  --text:#1a1a1a; --muted:#6b7280; --border:#e5e7eb; --error:#b71c1c; --error-bg:#ffebee; }
*{box-sizing:border-box;}
body{margin:0;font-family:Arial,Helvetica,sans-serif;color:var(--text);background:#f3f4f6;padding:0;line-height:1.45;}
.wrap{max-width:760px;margin:0 auto;padding:16px;}
.card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.06);padding:20px;}
.hdr{display:flex;align-items:center;gap:14px;padding:16px 20px;background:var(--apc-green);color:#fff;border-radius:12px 12px 0 0;margin:-20px -20px 20px;}
.hdr-logo{width:52px;height:52px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;color:var(--apc-green);font-weight:900;font-size:18px;}
.hdr-title h1{margin:0;font-size:18px;}
.hdr-title p{margin:2px 0 0;opacity:.9;font-size:13px;}
h2{font-size:17px;margin:22px 0 10px;color:var(--apc-green-dark);border-bottom:2px solid var(--apc-green-light);padding-bottom:6px;}
.field{margin-bottom:12px;}
.field label{display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#374151;}
.field .req{color:var(--error);}
.field input, .field select, .field textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;color:var(--text);background:#fff;}
.field input:focus, .field select:focus, .field textarea:focus{outline:none;border-color:var(--apc-green);box-shadow:0 0 0 3px rgba(15,98,60,.12);}
.grid-2{display:grid;grid-template-columns:1fr;gap:10px;}
@media(min-width:640px){ .grid-2{grid-template-columns:1fr 1fr;} }
table.articles{width:100%;border-collapse:collapse;margin-top:6px;font-size:13px;background:#fff;}
table.articles th{background:var(--apc-green);color:#fff;text-align:left;padding:8px 10px;font-size:12px;}
table.articles td{padding:8px 10px;border-bottom:1px solid var(--border);vertical-align:top;}
table.articles input[type=number], table.articles input[type=text], table.articles textarea{width:100%;padding:8px;border:1px solid var(--border);border-radius:6px;font-size:13px;}
.table-wrap{border:1px solid var(--border);border-radius:10px;overflow-x:auto;-webkit-overflow-scrolling:touch;}
.spec-cell{min-width:200px;}
.checkbox-row{display:flex;align-items:flex-start;gap:10px;margin-top:18px;padding:14px;background:#fafafa;border:1px dashed #9ca3af;border-radius:10px;}
.checkbox-row input[type=checkbox]{width:20px;height:20px;margin-top:2px;flex-shrink:0;}
.btn-submit{display:block;width:100%;margin-top:22px;padding:15px;background:var(--apc-green);color:#fff;border:none;border-radius:10px;font-size:16px;font-weight:700;cursor:pointer;min-height:52px;}
.btn-submit:hover{background:var(--apc-green-dark);}
.meta-box{background:#f9fafb;border:1px solid var(--border);border-radius:10px;padding:14px 16px;margin-bottom:16px;font-size:13px;}
.meta-box dt{font-weight:600;color:#374151;}
.meta-box dd{margin:0 0 6px;color:#111827;}
.footer{text-align:center;margin-top:24px;font-size:12px;color:var(--muted);}
.muted{color:var(--muted);}
.money{text-align:right;font-variant-numeric:tabular-nums;font-weight:600;}
.total-row{font-weight:700;background:var(--apc-green-light)!important;color:var(--apc-green-dark);}
</style></head><body>';

echo '<div class="wrap">';

if (!empty($cotCreatedRef)) {
    $titre = $langs->trans('PUBThanksTitle');
    $texte = $langs->trans('PUBThanksRef') . ' <b>' . htmlspecialchars($cotCreatedRef) . '</b>. '
           . $langs->trans('PUBThanksMail');
    echo '<div class="card" style="margin-top:40px;">'
       . '<div class="hdr"><div class="hdr-logo">APC</div><div class="hdr-title"><h1>' . htmlspecialchars($langs->trans('PUBWelcomeTitle'), ENT_COMPAT, 'UTF-8') . '</h1>'
       . '<p>APC ONG Agri-Peace and Child</p></div></div>';
    echo $okTpl($titre, $texte);
    echo '<p class="footer">© APC ONG — ' . date('Y') . '</p>';
    echo '</div></div></body></html>';
    exit;
}

if ($tokenStatus !== 'ok') {
    $msgMap = array(
        'invalid'      => array($langs->trans('PUBWelcomeTitle'), $langs->trans('PUBErrInvalid')),
        'expired'      => array($langs->trans('PUBWelcomeTitle'), $langs->trans('PUBErrExpired')),
        'used'         => array($langs->trans('PUBWelcomeTitle'), $langs->trans('PUBErrUsed')),
        'ratelimited'  => array($langs->trans('ErrorAccessForbidden'), 'Trop de tentatives. Merci de réessayer plus tard.'),
    );
    $pair = isset($msgMap[$tokenStatus]) ? $msgMap[$tokenStatus] : $msgMap['invalid'];
    echo '<div class="card" style="margin-top:60px;">'
       . '<div class="hdr"><div class="hdr-logo">APC</div><div class="hdr-title"><h1>' . htmlspecialchars($pair[0], ENT_COMPAT, 'UTF-8') . '</h1>'
       . '<p>APC ONG Agri-Peace and Child</p></div></div>';
    echo '<div style="background:var(--error-bg);color:var(--error);padding:14px 18px;border-radius:8px;border:1px solid #ef9a9a;">'
       . '<b>' . htmlspecialchars($langs->trans('Error'), ENT_COMPAT, 'UTF-8') . ' :</b> '
       . htmlspecialchars($pair[1], ENT_COMPAT, 'UTF-8') . '</div>';
    echo '<p class="footer">© APC ONG — ' . date('Y') . '</p>';
    echo '</div></div></body></html>';
    exit;
}

echo '<div class="card">';
echo '<div class="hdr"><div class="hdr-logo">APC</div><div class="hdr-title">'
   . '<h1>' . htmlspecialchars($langs->trans('PUBWelcomeTitle'), ENT_COMPAT, 'UTF-8') . '</h1>'
   . '<p>' . htmlspecialchars($langs->trans('PUBWelcomeSub'), ENT_COMPAT, 'UTF-8') . '</p></div></div>';

if (!empty($errorMessages)) echo $errorsTpl($errorMessages);

$dp = $dpLoaded;

echo '<dl class="meta-box grid-2" style="grid-template-columns:1fr 1fr;">';
echo '<dt>' . htmlspecialchars($langs->trans('DPRef'), ENT_COMPAT, 'UTF-8') . '</dt><dd><b>' . htmlspecialchars($dp->ref, ENT_COMPAT, 'UTF-8') . '</b></dd>';
echo '<dt>' . htmlspecialchars($langs->trans('DPDate'), ENT_COMPAT, 'UTF-8') . '</dt><dd>' . htmlspecialchars($db->jdate($dp->date_dp) ? dol_print_date($db->jdate($dp->date_dp), 'day') : '', ENT_COMPAT, 'UTF-8') . '</dd>';
echo '<dt>' . htmlspecialchars($langs->trans('DPFournisseur'), ENT_COMPAT, 'UTF-8') . '</dt><dd>' . htmlspecialchars($dp->fournisseur_nom ?: '—', ENT_COMPAT, 'UTF-8') . '</dd>';
if (!empty($dp->lieu_livraison)) {
    echo '<dt>' . htmlspecialchars($langs->trans('DPLieuLiv'), ENT_COMPAT, 'UTF-8') . '</dt><dd>' . htmlspecialchars($dp->lieu_livraison, ENT_COMPAT, 'UTF-8') . '</dd>';
}
if (!empty($dp->date_livraison)) {
    echo '<dt>' . htmlspecialchars($langs->trans('DPDateLiv'), ENT_COMPAT, 'UTF-8') . '</dt><dd>' . htmlspecialchars(dol_print_date($db->jdate($dp->date_livraison), 'day'), ENT_COMPAT, 'UTF-8') . '</dd>';
}
if (!empty($dp->note_public)) {
    echo '<dt style="grid-column:1 / -1;">' . htmlspecialchars($langs->trans('FieldNotes'), ENT_COMPAT, 'UTF-8') . '</dt>'
       . '<dd style="grid-column:1 / -1;">' . htmlspecialchars($dp->note_public, ENT_COMPAT, 'UTF-8') . '</dd>';
}
echo '</dl>';

echo '<form method="POST" action="' . htmlspecialchars($_SERVER['REQUEST_URI'], ENT_COMPAT, 'UTF-8') . '" novalidate>';

echo '<h2>' . htmlspecialchars($langs->trans('PUBSectionCoord'), ENT_COMPAT, 'UTF-8') . '</h2>';
echo '<div class="grid-2">';
$val = function($k, $def = '') use ($isPost) { if (!$isPost) return $def; $v = GETPOST($k, 'alphanohtml'); return $v === null ? $def : (string)$v; };
$valAlpha = function($k, $def = '') use ($isPost) { if (!$isPost) return $def; $v = GETPOST($k, 'alpha'); return $v === null ? $def : (string)$v; };
echo '<div class="field"><label>' . htmlspecialchars($langs->trans('PUBCoordNom'), ENT_COMPAT, 'UTF-8') . ' <span class="req">*</span></label>'
   . '<input type="text" name="supp_nom" required value="' . htmlspecialchars($val('supp_nom', $dp->fournisseur_nom), ENT_COMPAT, 'UTF-8') . '"></div>';
echo '<div class="field"><label>' . htmlspecialchars($langs->trans('PUBCoordContact'), ENT_COMPAT, 'UTF-8') . ' <span class="req">*</span></label>'
   . '<input type="text" name="supp_contact" required value="' . htmlspecialchars($val('supp_contact', $dp->fournisseur_contact), ENT_COMPAT, 'UTF-8') . '"></div>';
echo '<div class="field"><label>' . htmlspecialchars($langs->trans('PUBCoordTel'), ENT_COMPAT, 'UTF-8') . ' <span class="req">*</span></label>'
   . '<input type="tel" name="supp_tel" required value="' . htmlspecialchars($val('supp_tel', $dp->fournisseur_tel), ENT_COMPAT, 'UTF-8') . '"></div>';
echo '<div class="field"><label>' . htmlspecialchars($langs->trans('PUBCoordEmail'), ENT_COMPAT, 'UTF-8') . '</label>'
   . '<input type="email" name="supp_email" value="' . htmlspecialchars($valAlpha('supp_email', $dp->fournisseur_email), ENT_COMPAT, 'UTF-8') . '"></div>';
echo '<div class="field" style="grid-column:1 / -1;"><label>' . htmlspecialchars($langs->trans('PUBCoordAdr'), ENT_COMPAT, 'UTF-8') . '</label>'
   . '<textarea name="supp_adr" rows="2">' . htmlspecialchars($val('supp_adr', $dp->fournisseur_adresse), ENT_COMPAT, 'UTF-8') . '</textarea></div>';
echo '</div>';

echo '<h2>' . htmlspecialchars($langs->trans('PUBSectionLiv'), ENT_COMPAT, 'UTF-8') . '</h2>';
echo '<div class="grid-2">';
$defDate = $dp->date_livraison ? date('Y-m-d', $db->jdate($dp->date_livraison)) : date('Y-m-d', time() + 14 * 86400);
echo '<div class="field"><label>' . htmlspecialchars($langs->trans('PUBLivLieu'), ENT_COMPAT, 'UTF-8') . ' <span class="req">*</span></label>'
   . '<input type="text" name="liv_lieu" required value="' . htmlspecialchars($val('liv_lieu', $dp->lieu_livraison), ENT_COMPAT, 'UTF-8') . '"></div>';
echo '<div class="field"><label>' . htmlspecialchars($langs->trans('PUBLivDate'), ENT_COMPAT, 'UTF-8') . ' <span class="req">*</span></label>'
   . '<input type="date" name="liv_date" required value="' . htmlspecialchars($val('liv_date', $defDate), ENT_COMPAT, 'UTF-8') . '"></div>';
echo '<div class="field"><label>' . htmlspecialchars($langs->trans('PUBLivDelai'), ENT_COMPAT, 'UTF-8') . '</label>'
   . '<input type="text" name="liv_delai" value="' . htmlspecialchars($val('liv_delai'), ENT_COMPAT, 'UTF-8') . '" placeholder="ex: 15 jours ouvrés"></div>';
echo '<div class="field"><label>' . htmlspecialchars($langs->trans('PUBLivConditions'), ENT_COMPAT, 'UTF-8') . '</label>'
   . '<select name="liv_cond"><option value="">(à préciser)</option>'
   . '<option value="30j"' . ($val('liv_cond') === '30j' ? ' selected' : '') . '>30 jours</option>'
   . '<option value="45j"' . ($val('liv_cond') === '45j' ? ' selected' : '') . '>45 jours</option>'
   . '<option value="60j"' . ($val('liv_cond') === '60j' ? ' selected' : '') . '>60 jours</option>'
   . '<option value="comptant"' . ($val('liv_cond') === 'comptant' ? ' selected' : '') . '>Comptant</option>'
   . '<option value="autre"' . ($val('liv_cond') === 'autre' ? ' selected' : '') . '>Autre (préciser en remarques)</option>'
   . '</select></div>';
echo '</div>';

echo '<h2>' . htmlspecialchars($langs->trans('PUBSecPrices'), ENT_COMPAT, 'UTF-8') . '</h2>';
echo '<p class="muted" style="font-size:13px;">Les colonnes Spécification, Unité et Quantité sont pré-remplies selon la demande de prix APC. Indiquez votre prix unitaire HT par ligne.</p>';
echo '<div class="table-wrap"><table class="articles"><thead><tr>';
echo '<th style="width:36px;">N°</th>';
echo '<th class="spec-cell">' . htmlspecialchars($langs->trans('PUBColSpec'), ENT_COMPAT, 'UTF-8') . '</th>';
echo '<th style="width:70px;">' . htmlspecialchars($langs->trans('PUBColUnit'), ENT_COMPAT, 'UTF-8') . '</th>';
echo '<th style="width:80px;" class="money">' . htmlspecialchars($langs->trans('PUBColQty'), ENT_COMPAT, 'UTF-8') . '</th>';
echo '<th style="width:120px;">' . htmlspecialchars($langs->trans('PUBColPu'), ENT_COMPAT, 'UTF-8') . ' <span class="req">*</span></th>';
echo '<th style="width:120px;" class="money">' . htmlspecialchars($langs->trans('PUBColTotal'), ENT_COMPAT, 'UTF-8') . '</th>';
echo '<th style="min-width:160px;">' . htmlspecialchars($langs->trans('PUBColNotes'), ENT_COMPAT, 'UTF-8') . '</th>';
echo '</tr></thead><tbody>';

$idxLigne = 0;
$totalGlobalJs = 0;
$postPU = $isPost ? GETPOST('pu', 'array') : array();
$postRem = $isPost ? GETPOST('rem', 'array') : array();
foreach ($dpLines as $ln) {
    $puDef = isset($postPU[$idxLigne]) ? (string)$postPU[$idxLigne] : '';
    $remDef = isset($postRem[$idxLigne]) ? (string)$postRem[$idxLigne] : '';
    echo '<tr>';
    echo '<td>' . ($idxLigne + 1) . '</td>';
    echo '<td class="spec-cell">' . htmlspecialchars($ln->specification, ENT_COMPAT, 'UTF-8') . '</td>';
    echo '<td style="text-align:center;">' . htmlspecialchars($ln->unite ?: '—', ENT_COMPAT, 'UTF-8') . '</td>';
    echo '<td class="money">' . $fmtMoney($ln->quantite) . '</td>';
    echo '<td><input type="number" min="0" step="0.01" class="pub-pu" data-qty="' . (float)$ln->quantite . '" name="pu[' . $idxLigne . ']" value="' . htmlspecialchars($puDef, ENT_COMPAT, 'UTF-8') . '" placeholder="0,00"></td>';
    echo '<td class="money pub-total" data-idx="' . $idxLigne . '">0,00</td>';
    echo '<td><input type="text" name="rem[' . $idxLigne . ']" value="' . htmlspecialchars($remDef, ENT_COMPAT, 'UTF-8') . '" placeholder="(facultatif)"></td>';
    echo '</tr>';
    $idxLigne++;
}
echo '<tr class="total-row"><td colspan="5" style="text-align:right;">' . htmlspecialchars($langs->trans('BCTotalHT'), ENT_COMPAT, 'UTF-8') . ' :</td>'
   . '<td class="money" id="pub-grand-total" style="font-size:15px;">0,00</td><td></td></tr>';
echo '</tbody></table></div>';

echo '<div class="checkbox-row">';
$checked = ($isPost && GETPOST('check_cond','aZ') === 'on') ? ' checked' : '';
echo '<input type="checkbox" id="check_cond" name="check_cond"' . $checked . '>';
echo '<label for="check_cond" style="font-size:13px;"><b>' . htmlspecialchars($langs->trans('PUBCheckConditions'), ENT_COMPAT, 'UTF-8') . ' <span class="req">*</span></b><br>'
   . '<span class="muted">Je confirme avoir pris connaissance des conditions et certifie l\'exactitude des prix et informations saisis.</span></label>';
echo '</div>';

echo '<button type="submit" class="btn-submit">' . htmlspecialchars($langs->trans('PUBSubmitBtn'), ENT_COMPAT, 'UTF-8') . '</button>';
echo '</form>';

echo '<script>
(function(){
  function fmt(n){ n = Number(n||0).toFixed(2); var p = n.split("."); p[0] = p[0].replace(/\\B(?=(\\d{3})+(?!\\d))/g," "); return p.join(","); }
  function recalc(){
    var total = 0;
    document.querySelectorAll("tr .pub-pu").forEach(function(inp){
      var qty = parseFloat(inp.getAttribute("data-qty"))||0;
      var pu  = parseFloat((""+inp.value).replace(/\s/g,"").replace(",","."))||0;
      var st = qty*pu;
      total += st;
      var td = inp.closest("tr").querySelector(".pub-total");
      if (td) td.textContent = fmt(st);
    });
    var gt = document.getElementById("pub-grand-total");
    if (gt) gt.textContent = fmt(total);
  }
  document.addEventListener("input", function(e){ if (e.target.classList.contains("pub-pu")) recalc(); }, true);
  recalc();
})();
</script>';

echo '<p class="footer">© APC ONG Agri-Peace and Child — ' . date('Y') . ' — Tous droits réservés.</p>';
echo '</div></div></body></html>';

$db->close();
