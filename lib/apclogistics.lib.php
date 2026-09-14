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