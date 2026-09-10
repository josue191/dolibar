<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * ApcPdfBase — Classe de base pour TOUS les générateurs PDF du module APC.
 * Etend TCPDF (inclus nativement dans Dolibarr : includes/tcpdf/tcpdf.php en 17, includes/tecnickcom/tcpdf/tcpdf.php en 18+)
 *
 * Fournit :
 *   - Header() standard APC (logo + en-tête institutionnel)
 *   - Footer() standard (n° de page, pied commun)
 *   - startNewPageIfNeeded($h)  — gestion sauts de page + répétition en-tête de tableau
 *   - drawSignatureBox()        — bloc 4 lignes standard Nom/Fonction/Signature/Date
 *
 * Chemin logo : lu depuis $conf->global->APCLOGISTICS_LOGO (relatif a dir_output,
 *               ex. 'logos/logo_apc.png') via lib/apclogistics.lib.php, repli sur
 *               l'ancienne APCLOGISTICS_LOGO_PATH puis sur le placeholder
 *               $dolibarr_main_url_root/custom/apclogistics/img/apc_logo_placeholder.png
 */

if (! defined('DOL_VERSION')) die('');

// TCPDF : chemin selon version Dolibarr (17 : includes/tcpdf, 18+ : includes/tecnickcom/tcpdf)
if (file_exists(DOL_DOCUMENT_ROOT . '/includes/tecnickcom/tcpdf/tcpdf.php')) {
    require_once DOL_DOCUMENT_ROOT . '/includes/tecnickcom/tcpdf/tcpdf.php';
} else {
    require_once DOL_DOCUMENT_ROOT . '/includes/tcpdf/tcpdf.php';
}

require_once __DIR__ . '/../../lib/apclogistics.lib.php';

class ApcPdfBase extends TCPDF
{
    /** @var string  Chemin absolu vers le logo APC */
    public $logoPath = '';

    /** @var string  Texte en-tête ligne 1 : Nom ONG */
    public $headerOngName = 'AGRI-PEACE AND CHILD — APC ONG';
    /** @var string  Texte en-tête ligne 2 : adresse */
    public $headerAddress = 'Goma, République Démocratique du Congo';
    /** @var string  Texte en-tête ligne 3 : téléphones + email */
    public $headerContact = 'Tél. : +243 000 000 000  —  Email : contact@apc-ong.org';
    /** @var string  Texte en-tête ligne 4 : arrêté ministériel */
    public $headerArrete = 'Arrêté Ministériel N°308/CAB/M.E/J&GS/2023';

    /** @var string  Titre document en cours (affiché dans entête tableau sur répétition saut page) */
    protected $tableTitle = '';
    /** @var array   Colonnes tableau en cours pour répétition : [ ['label'=>'xx','width'=>40,'align'=>'C'], ... ] */
    protected $tableHeaderCols = array();
    /** @var bool    true = on a déjà affiché l'entête tableau sur la page courante */
    protected $tableHeaderDrawnOnPage = false;
    /** @var int     Couleur fond entête tableau (RGB) */
    protected $tableHeaderBg = array(26, 135, 84); // vert APC
    /** @var int     Couleur texte entête tableau */
    protected $tableHeaderFg = array(255, 255, 255);

    /** @var int  Hauteur disponible restante avant saut (pour startNewPageIfNeeded) */
    protected $pageBottomMargin = 28; // espace reserve pour footer

    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false, $pdfa = false)
    {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);

        global $conf;
        $logoPath = apcLogoPath();
        if ($logoPath !== '') {
            $this->logoPath = $logoPath;
        } else {
            $this->logoPath = DOL_DOCUMENT_ROOT . '/custom/apclogistics/img/apc_logo_placeholder.png';
        }

        // Constantes alignees sur la page de configuration (admin/apclogistics_setup.php)
        if (!empty($conf->global->APCLOGISTICS_HEADER_ONG))      $this->headerOngName   = $conf->global->APCLOGISTICS_HEADER_ONG;
        if (!empty($conf->global->APCLOGISTICS_HEADER_ADDR))     $this->headerAddress   = $conf->global->APCLOGISTICS_HEADER_ADDR;
        if (!empty($conf->global->APCLOGISTICS_HEADER_CONTACT))  $this->headerContact   = $conf->global->APCLOGISTICS_HEADER_CONTACT;
        if (!empty($conf->global->APCLOGISTICS_HEADER_LEGAL))    $this->headerArrete    = $conf->global->APCLOGISTICS_HEADER_LEGAL;

        $this->SetMargins(12, 42, 12);
        $this->SetAutoPageBreak(true, $this->pageBottomMargin);
        $this->setFontSubsetting(true);
        $this->SetCreator('Dolibarr APC Logistics Module 1.0.0');
        $this->SetAuthor('APC ONG Agri-Peace and Child');
    }

    /**
     * Header TCPDF standard.
     * Affiche : logo APC (gauche 30mm) + 4 lignes en-tête institutionnel (droite) + séparateur
     */
    public function Header()
    {
        $this->SetFont('helvetica', 'B', 10);
        $curY = 7;

        // --- Logo (gauche) ---
        $logoW = 26;
        if (@file_exists($this->logoPath)) {
            $ext = strtolower(pathinfo($this->logoPath, PATHINFO_EXTENSION));
            $imgType = 'PNG';
            if ($ext === 'jpg' || $ext === 'jpeg') $imgType = 'JPEG';
            elseif ($ext === 'gif') $imgType = 'GIF';
            $this->Image($this->logoPath, 12, $curY, $logoW, 0, $imgType, '', '', false, 300, '', false, false, 0, false, false, false);
        } else {
            $this->SetFillColor(26, 135, 84);
            $this->Rect(12, $curY, $logoW, 22, 'F');
            $this->SetTextColor(255,255,255);
            $this->SetXY(12, $curY + 7);
            $this->Cell($logoW, 6, 'APC', 0, 0, 'C', false);
            $this->SetTextColor(0,0,0);
        }

        $xTxt = 12 + $logoW + 4;
        $pageW = $this->getPageWidth();
        $wTxt  = $pageW - $xTxt - 12;

        $this->SetFont('helvetica', 'B', 11);
        $this->SetXY($xTxt, $curY);
        $this->Cell($wTxt, 5, $this->headerOngName, 0, 1, 'L', false);

        $this->SetFont('helvetica', '', 8.5);
        $this->SetX($xTxt);
        $this->Cell($wTxt, 4.2, $this->headerAddress, 0, 1, 'L', false);
        $this->SetX($xTxt);
        $this->Cell($wTxt, 4.2, $this->headerContact, 0, 1, 'L', false);
        $this->SetX($xTxt);
        $this->SetFont('helvetica', 'I', 8.5);
        $this->Cell($wTxt, 4.2, $this->headerArrete, 0, 1, 'L', false);

        // Ligne séparatrice
        $sepY = $curY + 25;
        $this->SetDrawColor(26, 135, 84);
        $this->SetLineWidth(0.5);
        $this->Line(12, $sepY, $pageW - 12, $sepY);

        // Reset marqueurs entête tableau (nouvelle page = on devra ré-afficher l'entête)
        $this->tableHeaderDrawnOnPage = false;
        $this->SetY($sepY + 4);
    }

    /**
     * Footer TCPDF standard.
     * Gauche : APC ONG / Droite : Page X/Y / Centre : date génération
     */
    public function Footer()
    {
        $this->SetY(-15);
        $pageW = $this->getPageWidth();

        $this->SetDrawColor(180, 180, 180);
        $this->SetLineWidth(0.2);
        $this->Line(12, $this->GetY() - 2, $pageW - 12, $this->GetY() - 2);

        $this->SetFont('helvetica', '', 7.5);
        $this->SetTextColor(90, 90, 90);

        $this->SetX(12);
        $this->Cell($pageW / 3, 5, 'APC ONG — Agri-Peace and Child', 0, 0, 'L', false);

        $this->SetX($pageW / 3);
        $this->Cell($pageW / 3, 5, 'Généré le ' . date('d/m/Y H:i'), 0, 0, 'C', false);

        $this->SetX(2 * $pageW / 3);
        $this->Cell($pageW / 3 - 12, 5, 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R', false);
    }

    /**
     * Vérifie s'il reste suffisamment de hauteur pour $h mm ; si NON :
     *   - saut de page
     *   - ré-affichage automatique de l'entête du tableau (title + colonnes)
     * @param float $h  Hauteur nécessaire en mm
     * @return bool  true si un saut a été effectué
     */
    public function startNewPageIfNeeded($h = 10)
    {
        $pageH  = $this->getPageHeight();
        $limitY = $pageH - $this->pageBottomMargin - $h;
        if ($this->GetY() <= $limitY) return false;

        $this->AddPage();

        // Ré-afficher le titre + entête colonnes
        if (!empty($this->tableTitle)) {
            $this->SetFont('helvetica', 'B', 12);
            $this->SetTextColor(15, 98, 60);
            $this->Cell(0, 7, $this->tableTitle, 0, 1, 'C', false);
            $this->Ln(2);
            $this->SetTextColor(0,0,0);
        }
        if (!empty($this->tableHeaderCols)) {
            $this->drawTableHeader();
        }
        return true;
    }

    /**
     * Mémorise le titre document + structure colonnes pour les sauts de page.
     * @param string $title
     * @param array  $cols   [ ['label','width','align'=>'L|C|R','border'=>1] ]
     */
    public function setTableContext($title, $cols)
    {
        $this->tableTitle = $title;
        $this->tableHeaderCols = $cols;
    }

    /**
     * Dessine une ligne d'entête de tableau (fond vert APC, texte blanc).
     */
    public function drawTableHeader()
    {
        if (empty($this->tableHeaderCols)) return;
        $this->SetFillColor($this->tableHeaderBg[0], $this->tableHeaderBg[1], $this->tableHeaderBg[2]);
        $this->SetTextColor($this->tableHeaderFg[0], $this->tableHeaderFg[1], $this->tableHeaderFg[2]);
        $this->SetFont('helvetica', 'B', 9);

        foreach ($this->tableHeaderCols as $col) {
            $w     = isset($col['width'])  ? (float)$col['width']  : 25;
            $align = isset($col['align'])  ? $col['align']         : 'L';
            $border= isset($col['border']) ? $col['border']       : 1;
            $this->Cell($w, 7, $col['label'], $border, 0, $align, true);
        }
        $this->Ln();
        $this->SetTextColor(0,0,0);
        $this->SetFont('helvetica', '', 9);
        $this->tableHeaderDrawnOnPage = true;
    }

    /**
     * Dessine UNE ligne de tableau avec vérification saut de page avant.
     * @param array  $cells   [ valeur, ... ] dans le même ordre que tableHeaderCols
     * @param float  $rowH    Hauteur ligne (défaut 6)
     */
    public function drawTableRow($cells, $rowH = 6)
    {
        $this->startNewPageIfNeeded($rowH + 1);
        $pageW = $this->getPageWidth();
        $x0 = 12;
        $this->SetX($x0);
        $i = 0;
        foreach ($this->tableHeaderCols as $col) {
            $w     = isset($col['width'])  ? (float)$col['width']  : 25;
            $align = isset($col['align'])  ? $col['align']         : 'L';
            $border= isset($col['border']) ? $col['border']       : 1;
            $val   = isset($cells[$i]) ? $cells[$i] : '';
            $this->Cell($w, $rowH, $val, $border, 0, $align, false);
            $i++;
        }
        $this->Ln($rowH);
    }

    /**
     * Dessine une ligne "Total" en gras avec fond gris clair.
     */
    public function drawTableTotalRow($cells, $rowH = 7)
    {
        $this->startNewPageIfNeeded($rowH + 1);
        $this->SetFillColor(235, 243, 238);
        $this->SetFont('helvetica', 'B', 9.5);
        $i = 0;
        foreach ($this->tableHeaderCols as $col) {
            $w     = isset($col['width'])  ? (float)$col['width']  : 25;
            $align = isset($col['align'])  ? $col['align']         : 'L';
            $border= isset($col['border']) ? $col['border']       : 1;
            $val   = isset($cells[$i]) ? $cells[$i] : '';
            $this->Cell($w, $rowH, $val, $border, 0, $align, true);
            $i++;
        }
        $this->Ln($rowH);
        $this->SetFont('helvetica', '', 9);
    }

    /**
     * Dessine UN bloc signature standard 4 lignes :
     *   Label (en gras)
     *   Nom : XXXXX
     *   Fonction : XXXXX
     *   Date : XX/XX/XXXX
     *   (espace pour signature manuscrite)
     *
     * @param float  $x        Position X du bloc (mm)
     * @param float  $y        Position Y du bloc (mm)
     * @param float  $w        Largeur bloc (mm, défaut 85)
     * @param string $label    Titre ex: "Demandeur", "Approbateur", "Fournisseur"
     * @param string $nom
     * @param string $fonction
     * @param string $date
     * @param string $extra    Texte supplémentaire (ex: "Pour APC ONG")
     */
    public function drawSignatureBox($x, $y, $w = 85, $label = '', $nom = '', $fonction = '', $date = '', $extra = '')
    {
        $h = 30;
        $this->SetXY($x, $y);
        $this->SetDrawColor(90, 90, 90);
        $this->Rect($x, $y, $w, $h, 'D');

        $this->SetFillColor(26, 135, 84);
        $this->Rect($x, $y, $w, 6, 'F');
        $this->SetTextColor(255,255,255);
        $this->SetFont('helvetica', 'B', 9.5);
        $this->SetXY($x + 2, $y + 1);
        $this->Cell($w - 4, 4, $label, 0, 1, 'L', true);
        $this->SetTextColor(0,0,0);

        $curY = $y + 8;
        $this->SetFont('helvetica', '', 8.5);
        $this->SetXY($x + 3, $curY);
        $this->Cell($w - 6, 4, 'Nom : ' . ($nom ?: '________________________________'), 0, 1, 'L', false);
        $curY += 5;
        $this->SetXY($x + 3, $curY);
        $this->Cell($w - 6, 4, 'Fonction : ' . ($fonction ?: '_________________________'), 0, 1, 'L', false);
        $curY += 5;
        $this->SetXY($x + 3, $curY);
        $this->Cell($w - 6, 4, 'Date : ' . ($date ?: '____/____/__________'), 0, 1, 'L', false);
        $curY += 5;
        if ($extra) {
            $this->SetXY($x + 3, $curY);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell($w - 6, 4, $extra, 0, 1, 'L', false);
            $curY += 4;
        }
        $this->SetFont('helvetica', '', 8.5);
    }

    /**
     * Affiche 2 blocs signatures côte à côte (gauche/droite).
     * @param array $left   [label, nom, fonction, date, extra]
     * @param array $right  [label, nom, fonction, date, extra]
     * @param float $y      Y de départ
     * @param float $gap    écart entre 2 blocs
     */
    public function drawSignatureRow2($left, $right, $y = null, $gap = 10)
    {
        if ($y === null) $y = $this->GetY() + 10;
        $pageW = $this->getPageWidth();
        $w = ($pageW - 24 - $gap) / 2;
        $xL = 12;
        $xR = $pageW - 12 - $w;
        $this->drawSignatureBox($xL, $y, $w,
            isset($left[0]) ? $left[0] : '',
            isset($left[1]) ? $left[1] : '',
            isset($left[2]) ? $left[2] : '',
            isset($left[3]) ? $left[3] : '',
            isset($left[4]) ? $left[4] : '');
        $this->drawSignatureBox($xR, $y, $w,
            isset($right[0]) ? $right[0] : '',
            isset($right[1]) ? $right[1] : '',
            isset($right[2]) ? $right[2] : '',
            isset($right[3]) ? $right[3] : '',
            isset($right[4]) ? $right[4] : '');
    }

    /**
     * Affiche 3 blocs signatures alignés (Demandeur / Vérificateur / Approbateur)
     * @param array $a
     * @param array $b
     * @param array $c
     * @param float $y
     */
    public function drawSignatureRow3($a, $b, $c, $y = null)
    {
        if ($y === null) $y = $this->GetY() + 10;
        $pageW = $this->getPageWidth();
        $gap = 6;
        $w = ($pageW - 24 - 2 * $gap) / 3;
        $x1 = 12;
        $x2 = $x1 + $w + $gap;
        $x3 = $x2 + $w + $gap;
        $this->drawSignatureBox($x1, $y, $w, $a[0], @$a[1], @$a[2], @$a[3], @$a[4]);
        $this->drawSignatureBox($x2, $y, $w, $b[0], @$b[1], @$b[2], @$b[3], @$b[4]);
        $this->drawSignatureBox($x3, $y, $w, $c[0], @$c[1], @$c[2], @$c[3], @$c[4]);
    }

    /**
     * Helper : formate un montant avec séparateurs milliers (2 décimales)
     */
    public static function fmtMoney($val, $currency = '')
    {
        $v = (float)$val;
        $s = number_format($v, 2, ',', ' ');
        return $currency ? $s . ' ' . $currency : $s;
    }

    /**
     * Helper : formate une quantité avec séparateurs milliers (2 décimales par défaut)
     */
    public static function fmtQty($val, $decimals = 2)
    {
        $v = (float)$val;
        if (floor($v) == $v && $decimals <= 0) {
            return number_format($v, 0, ',', ' ');
        }
        return number_format($v, $decimals, ',', ' ');
    }

    /**
     * Helper : formate une date YYYY-MM-DD -> DD/MM/YYYY
     */
    public static function fmtDate($d)
    {
        if (empty($d) || $d === '0000-00-00') return '';
        $t = is_string($d) ? strtotime($d) : (is_numeric($d) ? $d : strtotime($d));
        if (!$t) return (string)$d;
        return date('d/m/Y', $t);
    }
}
