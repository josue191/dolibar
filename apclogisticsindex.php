<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * Page d'accueil / redirection vers le tableau de bord du module APC Logistics
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';

// Redirige directement vers le dashboard
header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/dashboard.php');
exit;
