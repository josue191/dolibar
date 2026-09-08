<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * Générateur PDF spécifique : DEMANDE DE PRIX APC
 * Respecte strictement la trame Demande de Prix :
 *   - En-tête APC (via ApcPdfBase Header)
 *   - Titre "DEMANDE DE PRIX N° DP-YYYY-NNNN" centré, vert APC
 *   - Infos générales : date DP, fournisseur, lieu livraison, date livraison, contact
 *   - Tableau 5 colonnes : N° | Spécification articles | Unité | Quantité | (colonne mention légale)
 *   - Mention légale obligatoire (TR-7.1)
 *   - Bloc Notes / Observations
 */

if (! defined('DOL_VERSION')) die('');

require_once DOL_DOCUMENT_ROOT . '/core/class/translate.class.php';
require_once __DIR__ . '/ApcPdfBase.class.php';
require_once __DIR__ . '/../ApcDemandePrix.class.php';

class pdf_demandeprix_apc
{
    public $db;
    public $name;
    public $description;
    public $type;
    public $module = 'apclogistics';

    public function __construct($db = null)
    {
        global $langs;
        $this->db = $db;
        $this->name        = 'pdf_demandeprix_apc';
        $this->description = 'PDF Demande de Prix conforme trame APC ONG';
        $this->type        = 'apc_demandeprix';
    }

    /**
     * @param ApcDemandePrix $object
     * @param Translate $langs
     * @param string $outputdir
     * @param string $mode  I (inline) | F (file) | S (string)
     * @return int|string
     */
    public function write_file($object, $langs, $outputdir = '', $mode = 'I')
    {
        global $conf;

        $object->fetchLines();
        $langs->load('apclogistics@apclogistics');

        $pdf = new ApcPdfBase('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetTitle($langs->trans('DPTitle') . ' ' . $object->ref);
        $pdf->SetSubject($langs->trans('DPTitle') . ' APC ONG — ' . $object->ref);
        $pdf->SetKeywords('APC, DemandePrix, DP, ' . $object->ref);

        $pdf->AddPage();

        $pageW = $pdf->getPageWidth();
        $usableW = $pageW - 24;

        // ========== TITRE DOCUMENT ==========
        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetTextColor(15, 98, 60);
        $titre = $langs->trans('DPTitle') . ' N° ' . ($object->ref ?: '—');
        $pdf->Cell(0, 8, $titre, 0, 1, 'C', false);
        $pdf->Ln(2);

        // ========== INFOS GÉNÉRALES ==========
        $pdf->SetTextColor(0, 0, 0);

        $labelDateDP = $langs->trans('DPDate');
        if ($labelDateDP == 'DPDate') $labelDateDP = 'Date Demande';

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(38, 5.5, $labelDateDP . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5.5, ApcPdfBase::fmtDate($object->date_dp), 0, 1, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(38, 5.5, $langs->trans('DPFournisseur') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);

        $fournLines = array();
        $fournLines[] = trim($object->fournisseur_nom);
        if (!empty($object->fournisseur_adresse)) $fournLines[] = trim($object->fournisseur_adresse);
        $contactParts = array();
        if (!empty($object->fournisseur_contact)) $contactParts[] = trim($object->fournisseur_contact);
        if (!empty($object->fournisseur_tel))     $contactParts[] = trim($object->fournisseur_tel);
        if (!empty($object->fournisseur_email))   $contactParts[] = trim($object->fournisseur_email);
        if (!empty($contactParts)) $fournLines[] = implode('  |  ', $contactParts);
        $fournText = implode("\n", array_filter($fournLines));
        if (empty($fournText)) $fournText = '—';

        $pdf->MultiCell($usableW - 38, 5, $fournText, 0, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(38, 5.5, $langs->trans('DPLieuLiv') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell($usableW - 38, 5.5, $object->lieu_livraison ?: '—', 0, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(38, 5.5, $langs->trans('DPDateLiv') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5.5, $object->date_livraison ? ApcPdfBase::fmtDate($object->date_livraison) : '—', 0, 1, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(38, 5.5, $langs->trans('DPContact') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5.5, $object->fournisseur_contact ?: '—', 0, 1, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(38, 5.5, $langs->trans('DPTel') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(52, 5.5, $object->fournisseur_tel ?: '—', 0, 0, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(16, 5.5, $langs->trans('DPEmail') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5.5, $object->fournisseur_email ?: '—', 0, 1, 'L', false);

        $pdf->Ln(4);

        // ========== TABLEAU 5 COLONNES ==========
        $cols = array(
            array('label' => $langs->trans('DPColNo'),   'width' => 12, 'align' => 'C'),
            array('label' => $langs->trans('DPColSpec'), 'width' => 78, 'align' => 'L'),
            array('label' => $langs->trans('DPColUnit'), 'width' => 28, 'align' => 'C'),
            array('label' => $langs->trans('DPColQty'),  'width' => 28, 'align' => 'R'),
            array('label' => '',                         'width' => 44, 'align' => 'L'),
        );
        $pdf->setTableContext('', $cols);
        $pdf->drawTableHeader();

        $i = 1;
        foreach ($object->lines as $ln) {
            $pdf->drawTableRow(array(
                isset($ln->no_ligne) ? $ln->no_ligne : $i,
                $ln->specification,
                $ln->unite,
                ApcPdfBase::fmtQty($ln->quantite),
                '',
            ));
            $i++;
        }
        if (empty($object->lines)) {
            $pdf->drawTableRow(array('', '(Aucune ligne)', '', '', ''));
        }

        // Ligne séparateur total (simple ligne vide avec bordure inférieure renforcée)
        $pdf->drawTableTotalRow(array('', '', '', '', ''));

        $pdf->Ln(2);

        // ========== MENTION LÉGALE (TR-7.1) ==========
        $mention = $object->mention_legale;
        if (empty($mention) && !empty($conf->global->APCLOGISTICS_DP_LEGAL_NOTICE)) {
            $mention = $conf->global->APCLOGISTICS_DP_LEGAL_NOTICE;
        }
        if (empty($mention)) {
            $mention = "Cette demande de prix n'oblige en rien APC à contracter, à acheter ou à consommer votre service.";
        }

        $labelLegal = $langs->trans('DPLegalNotice');
        if ($labelLegal == 'DPLegalNotice') $labelLegal = 'Mention légale';

        $xLegal = $pageW - 44 - 12;
        $pdf->SetX($xLegal);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor(15, 98, 60);
        $pdf->Cell(44, 4.5, $labelLegal . ' :', 0, 1, 'L', false);
        $pdf->SetX($xLegal);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->MultiCell(44, 3.8, $mention, 0, 'L', false);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->Ln(4);

        // ========== NOTES / OBSERVATIONS ==========
        if (!empty($object->note_public)) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 5, 'Notes / Observations : ', 0, 1, 'L', false);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 4.5, $object->note_public, 0, 'L', false);
            $pdf->Ln(3);
        }

        // ========== SORTIE ==========
        $filename = 'DP_' . $object->ref . '.pdf';

        if ($mode === 'F') {
            $filepath = ($outputdir ? rtrim($outputdir, '/') . '/' : '') . $filename;
            $pdf->Output($filepath, 'F');
            return $filepath;
        } elseif ($mode === 'S') {
            return $pdf->Output($filename, 'S');
        } else {
            $pdf->Output($filename, 'I');
            return 1;
        }
    }
}
