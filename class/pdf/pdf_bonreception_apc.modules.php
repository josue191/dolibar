<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * Générateur PDF spécifique : BON DE RECEPTION APC
 * Respecte strictement la trame APC :
 *   - En-tête APC (via ApcPdfBase Header)
 *   - Titre "BON DE RECEPTION N° BR-YYYY-NNNN" centré, vert APC
 *   - Infos générales : date réception, fournisseur, N°BC, réf BL, date BL
 *   - Tableau 7 colonnes : N° | Description | Unités | Qté Cmd | Qté reçue | PU HT | Total HT
 *   - Ligne total
 *   - Bloc Notes / Observations
 *   - Zones doubles signatures via 1 ligne drawSignatureRow2 :
 *     Réception APC (reception_*) | Livraison Fournisseur (livraison_* + CNI)
 */

if (! defined('DOL_VERSION')) die('');

require_once DOL_DOCUMENT_ROOT . '/core/class/translate.class.php';
require_once __DIR__ . '/ApcPdfBase.class.php';
require_once __DIR__ . '/../ApcBonReception.class.php';
require_once __DIR__ . '/../ApcBonCommande.class.php';

class pdf_bonreception_apc
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
        $this->name        = 'pdf_bonreception_apc';
        $this->description = 'PDF Bon de Reception conforme trame APC';
        $this->type        = 'apc_bonreception';
    }

    /**
     * @param ApcBonReception $object
     * @param Translate $langs
     * @param string $outputdir
     * @param string $mode  I (inline) | F (file) | S (string)
     * @return int|string
     */
    public function write_file($object, $langs, $outputdir = '', $mode = 'I')
    {
        $object->fetchLines();
        $object->calculateTotals();
        $langs->load('apclogistics@apclogistics');

        $pdf = new ApcPdfBase('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetTitle('Bon de Reception ' . $object->ref);
        $pdf->SetSubject('Bon de Reception APC ONG — ' . $object->ref);
        $pdf->SetKeywords('APC, Reception, Stock, Fournisseur, ' . $object->ref);

        $pdf->AddPage();

        $pageW = $pdf->getPageWidth();
        $usableW = $pageW - 24;

        // ========== TITRE DOCUMENT ==========
        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetTextColor(15, 98, 60);
        $titre = 'BON DE RECEPTION N° ' . ($object->ref ?: '—');
        $pdf->Cell(0, 8, $titre, 0, 1, 'C', false);
        $pdf->Ln(2);

        // ========== SOUS-TITRE infos générales ==========
        $pdf->SetTextColor(0,0,0);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(32, 5.5, $langs->trans('BRDateRec') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 5.5, ApcPdfBase::fmtDate($object->date_reception), 0, 0, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 5.5, $langs->trans('BRFournisseur') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5.5, ($object->fournisseur_nom ?: '—'), 0, 1, 'L', false);

        $bcText = '—';
        if (!empty($object->fk_boncommande)) {
            $bc = new ApcBonCommande($this->db);
            if ($bc->fetch($object->fk_boncommande) > 0) $bcText = $bc->ref;
        }
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(32, 5.5, $langs->trans('BRFKBC') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 5.5, $bcText, 0, 0, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 5.5, $langs->trans('BRRefBL') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5.5, ($object->ref_bdl ?: '—'), 0, 1, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(32, 5.5, $langs->trans('BRDateBL') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5.5, ($object->date_bl ? ApcPdfBase::fmtDate($object->date_bl) : '—'), 0, 1, 'L', false);

        $pdf->Ln(4);

        // ========== TABLEAU 7 COLONNES ==========
        $cols = array(
            array('label' => $langs->trans('BRColNo'),     'width' => 10,  'align' => 'C'),
            array('label' => $langs->trans('BRColDesc'),   'width' => 62,  'align' => 'L'),
            array('label' => $langs->trans('BRColUnit'),  'width' => 18,  'align' => 'C'),
            array('label' => $langs->trans('BRColQC'),    'width' => 22,  'align' => 'R'),
            array('label' => $langs->trans('BRColQR'),    'width' => 22,  'align' => 'R'),
            array('label' => $langs->trans('BRColPU'),     'width' => 30,  'align' => 'R'),
            array('label' => $langs->trans('BRColTotal'), 'width' => 32,  'align' => 'R'),
        );
        $pdf->setTableContext('', $cols);
        $pdf->drawTableHeader();

        $grandTotal = 0;
        foreach ($object->lines as $ln) {
            $totL = (float)$ln->qte_recue * (float)$ln->prix_unitaire;
            $grandTotal += $totL;
            $pdf->drawTableRow(array(
                $ln->no_ligne,
                $ln->description,
                $ln->unite,
                ApcPdfBase::fmtQty($ln->qte_commandee),
                ApcPdfBase::fmtQty($ln->qte_recue),
                ApcPdfBase::fmtMoney($ln->prix_unitaire),
                ApcPdfBase::fmtMoney($totL),
            ));
        }
        if (empty($object->lines)) {
            $pdf->drawTableRow(array('', '(Aucune ligne)', '', '', '', '', ''));
        }

        $pdf->drawTableTotalRow(array(
            '',
            $langs->trans('BRTQC') . ' / ' . $langs->trans('BRTQR'),
            '',
            ApcPdfBase::fmtQty($object->total_qte_commandee),
            ApcPdfBase::fmtQty($object->total_qte_recue),
            '',
            '',
        ), 6);

        $pdf->drawTableTotalRow(array(
            '',
            $langs->trans('BRTEcart'),
            '',
            '',
            '',
            '',
            '',
        ), 6);

        $pdf->drawTableTotalRow(array(
            '',
            $langs->trans('BRTotalGeneral'),
            '',
            '',
            '',
            '',
            ApcPdfBase::fmtMoney($grandTotal),
        ), 6, true);

        $pdf->Ln(6);

        // ========== NOTES / OBSERVATIONS ==========
        if (!empty($object->note_public)) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 5, 'Notes / Observations : ', 0, 1, 'L', false);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 4.5, $object->note_public, 0, 'L', false);
            $pdf->Ln(3);
        }

        // ========== ZONES DOUBLES SIGNATURES (1 ligne : Reception APC | Livraison Fournisseur) ==========
        $ySig = $pdf->GetY() + 6;

        $livreurCNI = '';
        if (!empty($object->livraison_cni)) $livreurCNI = 'CNI : ' . $object->livraison_cni;

        $pdf->drawSignatureRow2(
            array(
                $langs->trans('BRSigRecepteur'),
                $object->reception_nom,
                $object->reception_fonction,
                ApcPdfBase::fmtDate($object->reception_date_sig),
                'Reception APC',
            ),
            array(
                $langs->trans('BRSigLivreur'),
                $object->livraison_nom,
                ($object->livraison_fonction ? $object->livraison_fonction . "\n" . $livreurCNI : $livreurCNI),
                ApcPdfBase::fmtDate($object->livraison_date_sig),
                'Livraison Fournisseur',
            ),
            $ySig,
            10
        );

        // ========== SORTIE ==========
        if ($mode === 'F') {
            $filepath = ($outputdir ? rtrim($outputdir, '/') . '/' : '') . 'BR_' . $object->ref . '.pdf';
            $pdf->Output($filepath, 'F');
            return $filepath;
        } elseif ($mode === 'S') {
            return $pdf->Output('BR_' . $object->ref . '.pdf', 'S');
        } else {
            $pdf->Output('BR_' . $object->ref . '.pdf', 'I');
            return 1;
        }
    }
}
