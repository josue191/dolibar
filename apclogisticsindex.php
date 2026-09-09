<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * Page d'accueil / redirection vers le tableau de bord du module APC Logistics
 */

$res = 0; if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php"; if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php"; if (!$res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';

// Redirige directement vers le dashboard
header('Location: ' . DOL_URL_ROOT . '/custom/apclogistics/dashboard.php');
exit;
