<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * lib/apclogistics.lib.php — Helpers partages du module APC Logistics.
 *
 * Resolution du logo APC :
 *   - Constante APCLOGISTICS_LOGO      : chemin RELATIF a $conf->apclogistics->dir_output
 *                                        (ex. 'logos/logo_apc.png') — nouveau format.
 *   - Constante APCLOGISTICS_LOGO_PATH : chemin ABSOLU — ancien format (retro-compat).
 */

if (! defined('DOL_VERSION')) die('');

/**
 * Repertoire de sortie du module (DOL_DATA_ROOT/apclogistics/documents).
 * @return string
 */
function apcLogoDirOutput()
{
    global $conf;
    if (!empty($conf->apclogistics->dir_output)) return $conf->apclogistics->dir_output;
    return DOL_DATA_ROOT . '/apclogistics/documents';
}

/**
 * Chemin RELATIF du logo (ex. 'logos/logo_apc.png') depuis la constante
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
 * Priorite : APCLOGISTICS_LOGO (relatif) puis APCLOGISTICS_LOGO_PATH (absolu, legacy).
 * @return string
 */
function apcLogoPath()
{
    global $conf;
    $rel = apcLogoRelative();
    if ($rel !== '') {
        $abs = apcLogoDirOutput() . '/' . $rel;
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
        return DOL_URL_ROOT . '/documents/apclogistics/documents/' . $rel;
    }
    if (!empty($conf->global->APCLOGISTICS_LOGO_PATH)) {
        $abs = $conf->global->APCLOGISTICS_LOGO_PATH;
        if (strpos($abs, DOL_DATA_ROOT) === 0) {
            return DOL_URL_ROOT . '/documents/' . substr($abs, strlen(DOL_DATA_ROOT) + 1);
        }
    }
    return '';
}