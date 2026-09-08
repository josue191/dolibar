<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * Générateur PDF spécifique : BON DE COMMANDE APC
 * Respecte strictement la trame APC :
 *   - En-tête APC (via ApcPdfBase Header)
 *   - Titre "BON DE COMMANDE N° BC-YYYY-NNNN" centré, vert
 *   - Infos générales : date BC, fournisseur, lieu livraison, date livraison, conditions règlement, taux TVA
 *   - Tableau 6 colonnes : N° | Description | Unités | Quantité | Prix Unitaire HT | Prix Total HT
 *   - Lignes totaux (Total HT, Total TVA, Total TTC)
 *   - Bloc Notes / Observations
 *   - Zones 4 signatures via 2 lignes drawSignatureRow2 :
 *     Ligne 1 : APC Logisticien | APC Coordinateur
 *     Ligne 2 : Fournisseur | Cachet / Signature fournisseur
 */

if (! defined('DOL_VERSION')) die('');

require_once DOL_DOCUMENT_ROOT . '/core/class/translate.class.php';
require_once __DIR__ . '/ApcPdfBase.class.php';
require_once __DIR__ . '/../ApcBonCommande.class.php';
require_once __DIR__ . '/../ApcCotation.class.php';

class pdf_boncommande_apc
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
        $this->name        = 'pdf_boncommande_apc';
        $this->description = 'PDF Bon de Commande conforme trame APC';
        $this->type        = 'apc_boncommande';
    }

    /**
     * @param ApcBonCommande $object
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
        $pdf->SetTitle('Bon de Commande ' . $object->ref);
        $pdf->SetSubject('Bon de Commande APC ONG — ' . $object->ref);
        $pdf->SetKeywords('APC, Bon de Commande, Commande, Fournisseur, ' . $object->ref);

        $pdf->AddPage();

        $pageW = $pdf->getPageWidth();
        $usableW = $pageW - 24;

        // ========== TITRE DOCUMENT ==========
        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetTextColor(15, 98, 60);
        $titre = 'BON DE COMMANDE N° ' . ($object->ref ?: '—');
        $pdf->Cell(0, 8, $titre, 0, 1, 'C', false);
        $pdf->Ln(2);

        // ========== SOUS-TITRE infos générales ==========
        $pdf->SetTextColor(0,0,0);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(32, 5.5, $langs->trans('BCDateBC') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 5.5, ApcPdfBase::fmtDate($object->date_cmde), 0, 0, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 5.5, $langs->trans('BCLieuLiv') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5.5, ($object->lieu_livraison ?: '—'), 0, 1, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(32, 5.5, $langs->trans('BCFournisseur') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $fournText = '';
        if (!empty($object->fournisseur_nom)) $fournText .= $object->fournisseur_nom . "\n";
        if (!empty($object->fournisseur_adresse)) $fournText .= $object->fournisseur_adresse . "\n";
        if (!empty($object->fournisseur_contact)) $fournText .= $object->fournisseur_contact . "\n";
        if (!empty($object->fournisseur_tel)) $fournText .= $object->fournisseur_tel . "\n";
        if (!empty($object->fournisseur_email)) $fournText .= $object->fournisseur_email;
        $fournText = trim($fournText);
        $pdf->MultiCell($usableW - 32, 5.5, ($fournText ?: '—'), 0, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(32, 5.5, $langs->trans('BCDateLiv') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5.5, ($object->date_livraison ? ApcPdfBase::fmtDate($object->date_livraison) : '—'), 0, 1, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(32, 5.5, $langs->trans('BCModeRegl') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5.5, ($object->conditions_paiement ?: '—'), 0, 1, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(32, 5.5, $langs->trans('BCTauxTVA') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $tauxTVA = ((float)$object->taux_tva_applicable > 0) ? ((float)$object->taux_tva_applicable . ' %') : 'Non applicable';
        $pdf->Cell(0, 5.5, $tauxTVA, 0, 1, 'L', false);

        $pdf->Ln(4);

        // ========== TABLEAU 6 COLONNES ==========
        $cols = array(
            array('label' => $langs->trans('BCColNo'),      'width' => 10,  'align' => 'C'),
            array('label' => $langs->trans('BCColDesc'),    'width' => 68,  'align' => 'L'),
            array('label' => $langs->trans('BCColUnit'),    'width' => 20,  'align' => 'C'),
            array('label' => $langs->trans('BCColQty'),     'width' => 22,  'align' => 'R'),
            array('label' => $langs->trans('BCColPu'),      'width' => 34,  'align' => 'R'),
            array('label' => $langs->trans('BCColTotal'),   'width' => 36,  'align' => 'R'),
        );
        $pdf->setTableContext('', $cols);
        $pdf->drawTableHeader();

        foreach ($object->lines as $ln) {
            $pdf->drawTableRow(array(
                $ln->no_ligne,
                $ln->description,
                $ln->unite,
                ApcPdfBase::fmtQty($ln->quantite),
                ApcPdfBase::fmtMoney($ln->prix_unitaire),
                ApcPdfBase::fmtMoney($ln->prix_total_ligne),
            ));
        }
        if (empty($object->lines)) {
            $pdf->drawTableRow(array('', '(Aucune ligne)', '', '', '', ''));
        }

        // Lignes TOTAUX
        $pdf->drawTableTotalRow(array(
            '',
            $langs->trans('BCTotalHT'),
            '',
            '',
            '',
            ApcPdfBase::fmtMoney($object->total_ht),
        ), 5);

        $pdf->drawTableTotalRow(array(
            '',
            $langs->trans('BCTotalTVA'),
            '',
            '',
            '',
            ApcPdfBase::fmtMoney($object->total_tva),
        ), 5);

        $pdf->drawTableTotalRow(array(
            '',
            $langs->trans('BCTotalTTC'),
            '',
            '',
            '',
            ApcPdfBase::fmtMoney($object->total_ttc),
        ), 5, true);

        $pdf->Ln(6);

        // ========== NOTES / OBSERVATIONS ==========
        if (!empty($object->note_public)) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 5, 'Notes / Observations : ', 0, 1, 'L', false);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 4.5, $object->note_public, 0, 'L', false);
            $pdf->Ln(3);
        }

        // ========== ZONES 4 SIGNATURES (2 lignes) ==========
        $ySig = $pdf->GetY() + 4;

        // ROW 1 (HAUT) : APC Logisticien | APC Coordinateur
        $pdf->drawSignatureRow2(
            array(
                $langs->trans('BCLogisticien'),
                $object->logisticien_nom,
                $object->logisticien_fonction,
                ApcPdfBase::fmtDate($object->date_signature_log),
                'APC Logisticien',
            ),
            array(
                $langs->trans('BCCoordinateur'),
                $object->coordinateur_nom,
                $object->coordinateur_fonction,
                ApcPdfBase::fmtDate($object->date_signature_coord),
                'APC Coordinateur',
            ),
            $ySig,
            10
        );

        // ROW 2 (BAS) : Fournisseur | Cachet / Signature fournisseur
        $pdf->drawSignatureRow2(
            array(
                $langs->trans('BCFournisseurSig'),
                $object->fournisseur_sig_nom,
                $object->fournisseur_sig_fct,
                ApcPdfBase::fmtDate($object->fournisseur_sig_date),
                'Fournisseur',
            ),
            array(
                'Cachet / Signature fournisseur',
                '',
                '',
                '',
                '',
            ),
            $ySig + 35,
            10
        );

        // ========== SORTIE ==========
        if ($mode === 'F') {
            $filepath = ($outputdir ? rtrim($outputdir, '/') . '/' : '') . 'BC_' . $object->ref . '.pdf';
            $pdf->Output($filepath, 'F');
            return $filepath;
        } elseif ($mode === 'S') {
            return $pdf->Output('BC_' . $object->ref . '.pdf', 'S');
        } else {
            $pdf->Output('BC_' . $object->ref . '.pdf', 'I');
            return 1;
        }
    }
}
