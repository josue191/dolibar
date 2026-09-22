<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * lib/apclogistics.lib.php — Helpers partages du module APC Logistics.
 *
 * Resolution du logo APC :
 *   - Stockage web : DOL_DOCUMENT_ROOT/custom/apclogistics/img/ (accessible via
 *                    DOL_URL_ROOT/custom/apclogistics/img/...).
 *   - Constante APCLOGISTICS_LOGO      : nom de fichier (ex. 'logo_apc.png').
 *   - Constante APCLOGISTICS_LOGO_PATH : chemin ABSOLU — ancien format (retro-compat).
 */

if (! defined('DOL_VERSION')) die('');

/**
 * Repertoire web du module pour le logo (DOL_DOCUMENT_ROOT/custom/apclogistics/img).
 * Cree le dossier s'il n'existe pas.
 * @return string
 */
function apcLogoDir()
{
    $dir = DOL_DOCUMENT_ROOT . '/custom/apclogistics/img';
    if (!is_dir($dir)) {
        dol_mkdir($dir);
    }
    return $dir;
}

/**
 * Nom de fichier du logo (ex. 'logo_apc.png') depuis la constante
 * APCLOGISTICS_LOGO, sinon ''.
 * @return string
 */
function apcLogoRelative()
{
    global $conf;
    if (!empty($conf->global->APCLOGISTICS_LOGO)) {
        return (string)$conf->global->APCLOGISTICS_LOGO;
    }
    return '';
}

/**
 * Chemin ABSOLU du logo s'il existe sur le serveur, sinon ''.
 * Priorite : APCLOGISTICS_LOGO (img/) puis APCLOGISTICS_LOGO_PATH (absolu, legacy).
 * @return string
 */
function apcLogoPath()
{
    global $conf;
    $rel = apcLogoRelative();
    if ($rel !== '') {
        $abs = apcLogoDir() . '/' . $rel;
        if (@file_exists($abs)) return $abs;
    }
    if (!empty($conf->global->APCLOGISTICS_LOGO_PATH) && @file_exists($conf->global->APCLOGISTICS_LOGO_PATH)) {
        return $conf->global->APCLOGISTICS_LOGO_PATH;
    }
    return '';
}

/**
 * URL web du logo s'il existe, sinon ''.
 * @return string
 */
function apcLogoUrl()
{
    global $conf;
    $rel = apcLogoRelative();
    if ($rel !== '') {
        $abs = apcLogoDir() . '/' . $rel;
        if (@file_exists($abs)) {
            return DOL_URL_ROOT . '/custom/apclogistics/img/' . $rel;
        }
    }
    if (!empty($conf->global->APCLOGISTICS_LOGO_PATH)) {
        $abs = $conf->global->APCLOGISTICS_LOGO_PATH;
        if (@file_exists($abs) && strpos($abs, DOL_DATA_ROOT) === 0) {
            return DOL_URL_ROOT . '/documents/' . substr($abs, strlen(DOL_DATA_ROOT) + 1);
        }
    }
    return '';
}

/**
 * Helper générique pour créer un formulaire de ligne de dépense/article
 * Factorise le code dupliqué dans les fichiers *_card.php
 * 
 * @param string $fieldName     Nom du champ dans le formulaire
 * @param string $label         Label affiché
 * @param string $value         Valeur actuelle
 * @param string $type          Type d'input (text, number, textarea)
 * @param string $cssClass      Classe CSS additionnelle
 * @param bool   $required      Champ obligatoire
 * @param string $placeholder   Placeholder
 * @return string HTML du champ
 */
function apcFormInput($fieldName, $label, $value = '', $type = 'text', $cssClass = '', $required = false, $placeholder = '')
{
    global $langs;
    
    $req = $required ? ' <span class="error">*</span>' : '';
    $reqAttr = $required ? ' required' : '';
    $ph = $placeholder ? ' placeholder="' . dol_escape_htmltag($placeholder) . '"' : '';
    $cls = $cssClass ? ' class="' . $cssClass . '"' : '';
    
    $output = '<div class="field' . $cls . '">';
    $output .= '<label>' . dol_escape_htmltag($label) . $req . '</label>';
    
    if ($type === 'textarea') {
        $output .= '<textarea name="' . $fieldName . '"' . $reqAttr . $ph . '>' . dol_escape_htmltag($value) . '</textarea>';
    } else {
        $output .= '<input type="' . $type . '" name="' . $fieldName . '" value="' . dol_escape_htmltag($value) . '"' . $reqAttr . $ph . '>';
    }
    
    $output .= '</div>';
    return $output;
}

/**
 * Helper pour afficher un tableau de lignes de dépenses/articles
 * Factorise le code dupliqué dans les fichiers *_card.php
 * 
 * @param array  $lines       Tableau des lignes
 * @param array  $columns     Colonnes à afficher (nom => libellé)
 * @param string $totalLabel  Libellé du total
 * @param float  $total       Valeur du total
 * @param string $currency    Devise
 * @return string HTML du tableau
 */
function apcFormLinesTable($lines, $columns, $totalLabel = 'Total', $total = 0, $currency = '')
{
    global $langs;
    
    $output = '<table class="noborder centpercent" style="margin-top:10px;">';
    $output .= '<tr class="liste_titre">';
    
    foreach ($columns as $key => $label) {
        $output .= '<td>' . dol_escape_htmltag($label) . '</td>';
    }
    $output .= '<td class="right">' . dol_escape_htmltag($totalLabel) . '</td>';
    $output .= '</tr>';
    
    $idx = 0;
    foreach ($lines as $line) {
        $output .= '<tr class="oddeven">';
        foreach ($columns as $key => $label) {
            $value = isset($line->$key) ? $line->$key : '';
            $output .= '<td>' . dol_escape_htmltag($value) . '</td>';
        }
        
        // Calcul du total de ligne si montant existe
        $lineTotal = 0;
        if (isset($line->montant)) $lineTotal = (float)$line->montant;
        elseif (isset($line->total_ht)) $lineTotal = (float)$line->total_ht;
        elseif (isset($line->prix_unitaire_ht) && isset($line->quantite)) {
            $lineTotal = (float)$line->prix_unitaire_ht * (float)$line->quantite;
        }
        
        $totalFormatted = price($lineTotal);
        if ($currency) $totalFormatted .= ' ' . $currency;
        
        $output .= '<td class="right apc-money"><input type="text" class="flat width75 right apc-line-montant" name="lines[' . $idx . '][montant]" value="' . ($lineTotal > 0 ? $lineTotal : '') . '"></td>';
        $output .= '</tr>';
        $idx++;
    }
    
    // Ligne de total
    $output .= '<tr class="liste_titre">';
    $output .= '<td colspan="' . count($columns) . '" class="right"><b>' . dol_escape_htmltag($totalLabel) . ' :</b></td>';
    $totalFormatted = price($total);
    if ($currency) $totalFormatted .= ' ' . $currency;
    $output .= '<td class="right apc-money"><b>' . $totalFormatted . '</b></td>';
    $output .= '</tr>';
    
    $output .= '</table>';
    return $output;
}

/**
 * Helper pour valider une action avec token CSRF
 * Factorise la validation commune dans les fichiers *_card.php
 * 
 * @param string $action     Action attendue
 * @param bool   $permission Permission requise
 * @param string $token      Token CSRF
 * @param object $user       Utilisateur courant
 * @param bool   $post       Vérifier si méthode POST
 * @return bool   Validation réussie
 */
function apcValidateAction($action, $permission, $token, $user, $post = true)
{
    if (!$permission) return false;
    if (!$user->valid) return false;
    if (!$token) return false;
    if ($post && $_SERVER['REQUEST_METHOD'] !== 'POST') return false;
    
    // Validation du token Dolibarr
    if (!validateUser($user, $action)) return false;
    
    return true;
}

/**
 * Helper pour créer un bouton d'action standard
 * Factorise les boutons dupliqués dans les fichiers *_card.php
 * 
 * @param string $label      Libellé du bouton
 * @param string $action     Action associée
 * @param string $cssClass   Classe CSS additionnelle
 * @param string $icon       Icône (si disponible)
 * @return string HTML du bouton
 */
function apcActionButton($label, $action, $cssClass = '', $icon = '')
{
    $iconHtml = $icon ? '<span class="pictofixedwidth">' . img_picto('', $icon, '', false) . '</span> ' : '';
    $cls = $cssClass ? ' class="' . $cssClass . '"' : '';
    
    return '<input type="submit" name="action" value="' . dol_escape_htmltag($action) . '" class="button' . $cls . '">';
}

/**
 * Helper pour générer les blocs de signature standard
 * Factorise le code de signature dupliqué dans les fichiers *_card.php
 * 
 * @param array  $signataires Tableau des signataires avec leurs informations
 * @param string $docType    Type de document
 * @return string HTML des blocs de signature
 */
function apcGenerateSignatureBlocks($signataires, $docType)
{
    global $langs, $db;
    
    $output = '<div class="signature-blocks" style="margin-top:30px;">';
    $output .= '<h3>' . $langs->trans('AUDSignature') . '</h3>';
    
    $count = count($signataires);
    if ($count === 3) {
        $output .= '<div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:15px;">';
    } elseif ($count === 2) {
        $output .= '<div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:15px;">';
    } else {
        $output .= '<div style="display:grid; grid-template-columns: 1fr; gap:15px;">';
    }
    
    foreach ($signataires as $signataire) {
        $output .= '<div class="signature-box" style="border:1px solid #ccc; padding:15px; border-radius:4px;">';
        $output .= '<div style="background:#0f623c; color:#fff; padding:5px 10px; font-weight:bold; margin:-15px -15px 15px -15px; border-radius:4px 4px 0 0;">';
        $output .= dol_escape_htmltag($signataire['label']);
        $output .= '</div>';
        $output .= '<div style="margin:10px 0;">';
        $output .= '<div><strong>' . $langs->trans('FieldNom') . ' :</strong> ' . dol_escape_htmltag($signataire['nom'] ?: '________________________________') . '</div>';
        $output .= '<div><strong>' . $langs->trans('FieldFonction') . ' :</strong> ' . dol_escape_htmltag($signataire['fonction'] ?: '_________________________') . '</div>';
        $output .= '<div><strong>' . $langs->trans('FieldDateSignature') . ' :</strong> ' . dol_escape_htmltag($signataire['date'] ?: '____/____/__________') . '</div>';
        if (!empty($signataire['extra'])) {
            $output .= '<div style="font-style:italic; margin-top:5px;">' . dol_escape_htmltag($signataire['extra']) . '</div>';
        }
        $output .= '</div>';
        $output .= '</div>';
    }
    
    $output .= '</div></div>';
    return $output;
}