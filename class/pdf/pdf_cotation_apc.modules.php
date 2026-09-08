<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * Générateur PDF spécifique : COTATION FOURNISSEUR
 * Respecte strictement la trame APC :
 *   - En-tête APC (via ApcPdfBase Header)
 *   - Titre "COTATION N° COT-YYYY-NNNN" centré, vert
 *   - Infos générales : fournisseur, date, validité offre, lieu livraison, conditions
 *   - Tableau 8 colonnes : N° | Spécification | Unité | Quantité | PU HT | Remise % | Total HT | Remarques
 *   - Lignes totaux (Total HT, TVA, Total TTC)
 *   - Signature électronique fournisseur + case conditions
 */

if (! defined('DOL_VERSION')) die('');

require_once DOL_DOCUMENT_ROOT . '/core/class/translate.class.php';
require_once __DIR__ . '/ApcPdfBase.class.php';
require_once __DIR__ . '/../ApcCotation.class.php';
require_once __DIR__ . '/../ApcDemandePrix.class.php';

class pdf_cotation_apc
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
        $this->name        = 'pdf_cotation_apc';
        $this->description = 'PDF Cotation fournisseur conforme APC';
        $this->type        = 'apc_cotation';
    }

    public function write_file($object, $langs, $outputdir = '', $mode = 'I')
    {
        $object->fetchLines();
        $object->calculateTotals();
        $langs->load('apclogistics@apclogistics');

        $pdf = new ApcPdfBase('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetTitle('Cotation ' . $object->ref);
        $pdf->SetSubject('Cotation Fournisseur APC — ' . $object->ref);
        $pdf->SetKeywords('APC, Cotation, Devis, Fournisseur, ' . $object->ref);

        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetTextColor(15, 98, 60);
        $pdf->Cell(0, 8, 'COTATION N° ' . ($object->ref ?: '—'), 0, 1, 'C', false);
        $pdf->Ln(2);

        $pdf->SetTextColor(0,0,0);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(32, 5.5, $langs->trans('COTDate') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 5.5, ApcPdfBase::fmtDate($object->date_cotation), 0, 0, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 5.5, $langs->trans('CotDateValidOffre') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5.5, ($object->date_validite_offre ? ApcPdfBase::fmtDate($object->date_validite_offre) : '—'), 0, 1, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(32, 5.5, $langs->trans('COTNomFournisseur') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $fournText = '';
        if (!empty($object->fournisseur_nom)) $fournText .= $object->fournisseur_nom . "\n";
        if (!empty($object->fournisseur_adresse)) $fournText .= $object->fournisseur_adresse . "\n";
        if (!empty($object->fournisseur_contact)) $fournText .= $object->fournisseur_contact . "\n";
        if (!empty($object->fournisseur_tel)) $fournText .= $object->fournisseur_tel . "\n";
        if (!empty($object->fournisseur_email)) $fournText .= $object->fournisseur_email;
        $fournText = trim($fournText);
        $pdf->MultiCell(0, 5.5, ($fournText ?: '—'), 0, 'L', false);

        $dpRef = '—';
        if (!empty($object->fk_demandeprix)) {
            $dp = new ApcDemandePrix($this->db);
            if ($dp->fetch($object->fk_demandeprix) > 0) $dpRef = $dp->ref;
        }
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(32, 5.5, $langs->trans('CotFK_DP') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 5.5, $dpRef, 0, 0, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 5.5, $langs->trans('COTLieuLiv') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5.5, ($object->lieu_livraison ?: '—'), 0, 1, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(32, 5.5, $langs->trans('CotDelaiLiv') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 5.5, ($object->delai_livraison ?: '—'), 0, 0, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 5.5, $langs->trans('COTConditions') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5.5, ($object->conditions_reglement ?: '—'), 0, 1, 'L', false);

        $pdf->Ln(4);

        $cols = array(
            array('label' => $langs->trans('COTColNo'),    'width' => 8,  'align' => 'C'),
            array('label' => $langs->trans('COTColSpec'),  'width' => 52, 'align' => 'L'),
            array('label' => $langs->trans('COTColUnit'),  'width' => 14, 'align' => 'C'),
            array('label' => $langs->trans('COTColQty'),   'width' => 18, 'align' => 'R'),
            array('label' => $langs->trans('COTColPU'),    'width' => 24, 'align' => 'R'),
            array('label' => $langs->trans('COTColRem'),   'width' => 16, 'align' => 'C'),
            array('label' => $langs->trans('COTColTot'),   'width' => 26, 'align' => 'R'),
            array('label' => $langs->trans('COTColNotes'), 'width' => 36, 'align' => 'L'),
        );
        $pdf->setTableContext('', $cols);
        $pdf->drawTableHeader();

        foreach ($object->lines as $ln) {
            $pdf->drawTableRow(array(
                $ln->no_ligne,
                $ln->description,
                $ln->unite,
                ApcPdfBase::fmtQty($ln->quantite),
                ApcPdfBase::fmtMoney($ln->prix_unitaire_ht),
                ((float)$ln->remise_pct > 0 ? (float)$ln->remise_pct . ' %' : ''),
                ApcPdfBase::fmtMoney($ln->total_ht),
                $ln->remarque,
            ));
        }
        if (empty($object->lines)) {
            $pdf->drawTableRow(array('', '(Aucune ligne)', '', '', '', '', '', ''));
        }

        $pdf->drawTableTotalRow(array('','','','','',$langs->trans('BCTotalHT'),ApcPdfBase::fmtMoney($object->total_ht),''), 6);
        $pdf->drawTableTotalRow(array('','','','','',$langs->trans('BCTotalTVA'),ApcPdfBase::fmtMoney($object->total_tva),''), 6);
        $pdf->drawTableTotalRow(array('','','','','',$langs->trans('BCTotalTTC'),ApcPdfBase::fmtMoney($object->total_ttc),''), 6, true);

        $pdf->Ln(6);

        if (!empty($object->conditions_acceptation) || !empty($object->note_public)) {
            if (!empty($object->note_public)) {
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(0, 5, $langs->trans('FieldNotes') . ' : ', 0, 1, 'L', false);
                $pdf->SetFont('helvetica', '', 9);
                $pdf->MultiCell(0, 4.5, $object->note_public, 0, 'L', false);
                $pdf->Ln(2);
            }
            if (!empty($object->conditions_acceptation)) {
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetFillColor(232,245,233);
                $pdf->MultiCell(0, 5, '[X] ' . $langs->trans('PUBCheckConditions'), 0, 'L', true);
            }
        }

        $ySig = $pdf->GetY() + 6;
        $pdf->drawSignatureRow2(
            array(
                $langs->trans('COTSignFourn'),
                $object->signature_fournisseur_nom,
                $object->signature_fournisseur_fct,
                ApcPdfBase::fmtDate($object->signature_fournisseur_date),
                'Fournisseur',
            ),
            array(
                $langs->trans('COTSignAPC'),
                '',
                '',
                '',
                'Cachet APC',
            ),
            $ySig,
            10
        );

        if ($mode === 'F') {
            $filepath = ($outputdir ? rtrim($outputdir, '/') . '/' : '') . 'COT_' . $object->ref . '.pdf';
            $pdf->Output($filepath, 'F');
            return $filepath;
        } elseif ($mode === 'S') {
            return $pdf->Output('COT_' . $object->ref . '.pdf', 'S');
        } else {
            $pdf->Output('COT_' . $object->ref . '.pdf', 'I');
            return 1;
        }
    }
}
