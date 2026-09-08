<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * Générateur PDF spécifique : DEMANDE D'AVANCE APC (DAV)
 * Respecte la trame Excel :
 *   - En-tête APC (via ApcPdfBase Header)
 *   - Titre "DEMANDE D'AVANCE N° DAV-YYYY-NNNN" centré + date
 *   - Infos générales (objet, compte, mode paiement, coord bancaires)
 *   - Tableau 4 colonnes : N° | Dépense | Projet | Budget | Montant (5 en réalité)
 *   - Ligne Total général
 *   - Zones 3 signatures (Demandeur / Vérificateur / Approbateur) en ligne
 */

if (! defined('DOL_VERSION')) die('');

require_once DOL_DOCUMENT_ROOT . '/core/class/translate.class.php';
require_once __DIR__ . '/ApcPdfBase.class.php';
require_once __DIR__ . '/../ApcDemandeAvance.class.php';

class pdf_demandeavance_apc
{
    public $db;
    public $name;
    public $description;
    public $type;
    public $module = 'apclogistics';

    public function __construct($db = null)
    {
        $this->db = $db;
        $this->name        = 'pdf_demandeavance_apc';
        $this->description = 'PDF Demande d\'Avance conforme trame APC ONG';
        $this->type        = 'apc_demandeavance';
    }

    public function write_file($object, $langs, $outputdir = '', $mode = 'I')
    {
        global $conf;
        $object->fetchLines();

        $pdf = new ApcPdfBase('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetTitle('DEMANDE D\'AVANCE ' . $object->ref);
        $pdf->SetAuthor('APC ONG Agri-Peace and Child');
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(12, 32, 12);
        $pdf->SetAutoPageBreak(true, 25);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetTextColor(46, 125, 50);
        $pdf->Cell(0, 10, 'DEMANDE D\'AVANCE N° ' . $object->ref, 0, 1, 'C');
        $pdf->SetTextColor(0,0,0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Ln(3);

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(235, 243, 238);
        $pdf->Cell(42, 6, 'Date demande :', 1, 0, 'L', true);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(55, 6, ApcPdfBase::fmtDate($object->date_dav), 1, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(235, 243, 238);
        $pdf->Cell(42, 6, 'Devise :', 1, 0, 'L', true);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, ($object->devise ?: 'CDF'), 1, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(235, 243, 238);
        $pdf->Cell(42, 6, 'Objet :', 1, 0, 'L', true);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 6, $object->objet, 1, 'L');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(235, 243, 238);
        $pdf->Cell(42, 6, 'Compte budgétaire :', 1, 0, 'L', true);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(55, 6, $object->compte, 1, 0, 'L');
        $mpLbl = ((int)$object->mode_paiement === 1) ? 'CAISSE' : 'BANQUE';
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(235, 243, 238);
        $pdf->Cell(42, 6, 'Mode paiement :', 1, 0, 'L', true);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, $mpLbl . ' (' . (int)$object->mode_paiement . ')', 1, 1, 'L');

        if ((int)$object->mode_paiement === 2 && (!empty($object->coord_banque_nom) || !empty($object->coord_banque_iban))) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(235, 243, 238);
            $pdf->Cell(42, 6, 'Titulaire compte :', 1, 0, 'L', true);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(55, 6, $object->coord_banque_nom, 1, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(235, 243, 238);
            $pdf->Cell(42, 6, 'Banque :', 1, 0, 'L', true);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 6, $object->coord_banque_banque, 1, 1, 'L');
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(235, 243, 238);
            $pdf->Cell(42, 6, 'IBAN :', 1, 0, 'L', true);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(55, 6, $object->coord_banque_iban, 1, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(235, 243, 238);
            $pdf->Cell(42, 6, 'SWIFT/BIC :', 1, 0, 'L', true);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 6, $object->coord_banque_swift, 1, 1, 'L');
        }
        $pdf->Ln(5);

        $cols = array(
            array('label'=>'N°',        'width'=>10, 'align'=>'C'),
            array('label'=>'Description dépense', 'width'=>88, 'align'=>'L'),
            array('label'=>'Projet',    'width'=>35, 'align'=>'L'),
            array('label'=>'Budget',    'width'=>30, 'align'=>'L'),
            array('label'=>'Montant',   'width'=>30, 'align'=>'R'),
        );
        $pdf->setTableContext('Demande Avance', $cols);
        $pdf->drawTableHeader();

        $i = 1; $total = 0;
        foreach ($object->lines as $ln) {
            $total += (float)$ln->montant;
            $pdf->drawTableRow(array(
                $i++,
                $ln->depense_label,
                $ln->projet,
                $ln->budget,
                ApcPdfBase::fmtMoney($ln->montant, $object->devise),
            ));
        }
        if (empty($object->lines)) {
            $pdf->drawTableRow(array('', 'Aucune ligne', '', '', ''));
        }

        $pdf->drawTableTotalRow(array('', '', '', 'TOTAL DEMANDE :', ApcPdfBase::fmtMoney($total, $object->devise)));
        $pdf->Ln(4);

        if (!empty($object->note_public)) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(235, 243, 238);
            $pdf->Cell(42, 6, 'Notes / Observations :', 1, 0, 'L', true);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 6, $object->note_public, 1, 'L');
            $pdf->Ln(3);
        }

        $pdf->startNewPageIfNeeded(50);
        $pdf->Ln(4);

        $boxDem = array('Demandeur', $object->demandeur_nom, $object->demandeur_fonction, ApcPdfBase::fmtDate($object->date_signature_demandeur));
        $boxVer = array('Vérificateur Finances', $object->verif_nom, $object->verif_fonction, ApcPdfBase::fmtDate($object->date_signature_verif));
        $boxApp = array('Approbateur / Ordonnateur', $object->approb_nom, $object->approb_fonction, ApcPdfBase::fmtDate($object->date_signature_approb));
        $pdf->drawSignatureRow3($boxDem, $boxVer, $boxApp, 6);

        $filename = 'DAV_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $object->ref) . '.pdf';
        if ($mode === 'F') {
            $dest = $outputdir . '/' . $filename;
            $pdf->Output($dest, 'F');
            return 1;
        } elseif ($mode === 'D') {
            $pdf->Output($filename, 'D');
            return 1;
        } else {
            $pdf->Output($filename, 'I');
            return 1;
        }
    }
}
