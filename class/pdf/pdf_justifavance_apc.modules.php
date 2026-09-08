<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * Générateur PDF spécifique : JUSTIFICATION D'AVANCE APC (JAV)
 * Trame Excel spécifique :
 *   - En-tête APC
 *   - Titre + références JAV et DAV liée
 *   - BLOC SYNTHÈSE : Total Dépense | Prise Avance | Écart (avec mise en évidence couleur)
 *   - Tableau 6 colonnes : N° | Dépense | Projet | Budget | Compte | Montant
 *   - Total + 3 signatures en ligne (Justif/Auteur, Verificateur, Approbateur)
 */

if (! defined('DOL_VERSION')) die('');

require_once DOL_DOCUMENT_ROOT . '/core/class/translate.class.php';
require_once __DIR__ . '/ApcPdfBase.class.php';
require_once __DIR__ . '/../ApcJustifAvance.class.php';

class pdf_justifavance_apc
{
    public $db;
    public $name;
    public $description;
    public $type;
    public $module = 'apclogistics';

    public function __construct($db = null)
    {
        $this->db = $db;
        $this->name        = 'pdf_justifavance_apc';
        $this->description = 'PDF Justification d\'Avance conforme trame APC ONG';
        $this->type        = 'apc_justifavance';
    }

    public function write_file($object, $langs, $outputdir = '', $mode = 'I')
    {
        global $conf;
        $object->fetchLines();

        $pdf = new ApcPdfBase('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetTitle('JUSTIFICATION D\'AVANCE ' . $object->ref);
        $pdf->SetAuthor('APC ONG Agri-Peace and Child');
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(12, 32, 12);
        $pdf->SetAutoPageBreak(true, 25);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetTextColor(46, 125, 50);
        $pdf->Cell(0, 10, 'JUSTIFICATION D\'AVANCE N° ' . $object->ref, 0, 1, 'C');
        $pdf->SetTextColor(0,0,0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Ln(3);

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(235, 243, 238);
        $pdf->Cell(42, 6, 'Date justification :', 1, 0, 'L', true);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(55, 6, ApcPdfBase::fmtDate($object->date_jav), 1, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(235, 243, 238);
        $pdf->Cell(42, 6, 'Devise :', 1, 0, 'L', true);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, ($object->devise ?: 'CDF'), 1, 1, 'L');

        if (!empty($object->fk_demande_avance)) {
            $dav = new ApcDemandeAvance($pdf->db);
            $davRef = '';
            if ($object->fk_demande_avance > 0 && $dav->fetch($object->fk_demande_avance) > 0) $davRef = $dav->ref;
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(235, 243, 238);
            $pdf->Cell(42, 6, 'Demande d\'avance liée :', 1, 0, 'L', true);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(46, 125, 50);
            $pdf->Cell(55, 6, $davRef, 1, 0, 'L');
            $pdf->SetTextColor(0,0,0);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(235, 243, 238);
            $pdf->Cell(42, 6, 'Prise d\'avance :', 1, 0, 'L', true);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 6, ApcPdfBase::fmtMoney($object->prise_avance, $object->devise), 1, 1, 'R');
        }

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(235, 243, 238);
        $pdf->Cell(42, 6, 'Objet :', 1, 0, 'L', true);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 6, $object->objet, 1, 'L');
        $pdf->Ln(3);

        // ===== BLOC SYNTHÈSE =====
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(200, 220, 205);
        $pdf->Cell(0, 8, '  SYNTHÈSE — COMPARAISON DÉPENSES vs PRISE D\'AVANCE', 1, 1, 'L', true);
        $pdf->SetFont('helvetica', '', 10);

        $totDep = (float)$object->total_depense;
        $prise  = (float)$object->prise_avance;
        $ecart  = (float)$object->ecart;
        $sens   = (int)$object->sens_ecart;

        $pdf->Cell(110, 7, 'Total des dépenses justifiées :', 1, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 7, ApcPdfBase::fmtMoney($totDep, $object->devise), 1, 1, 'R');
        $pdf->SetFont('helvetica', '', 10);

        $pdf->Cell(110, 7, 'Montant de la prise d\'avance :', 1, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 7, ApcPdfBase::fmtMoney($prise, $object->devise), 1, 1, 'R');
        $pdf->SetFont('helvetica', '', 10);

        $lblEcart = 'Écart : ';
        if ($sens < 0) {
            $lblEcart .= 'Solde à rendre par le bénéficiaire (-)';
            $pdf->SetFillColor(255, 228, 228);
            $pdf->SetTextColor(180, 0, 0);
        } elseif ($sens > 0) {
            $lblEcart .= 'Du à APC / Complément de paiement (+)';
            $pdf->SetFillColor(228, 246, 228);
            $pdf->SetTextColor(30, 110, 30);
        } else {
            $lblEcart .= 'Comptes équilibrés';
            $pdf->SetFillColor(240, 240, 240);
        }
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(110, 8, $lblEcart, 1, 0, 'L', true);
        $pdf->Cell(0, 8, ApcPdfBase::fmtMoney($ecart, $object->devise), 1, 1, 'R', true);
        $pdf->SetTextColor(0,0,0);
        $pdf->Ln(5);

        $cols = array(
            array('label'=>'N°',        'width'=>10, 'align'=>'C'),
            array('label'=>'Description dépense', 'width'=>72, 'align'=>'L'),
            array('label'=>'Projet',    'width'=>28, 'align'=>'L'),
            array('label'=>'Budget',    'width'=>25, 'align'=>'L'),
            array('label'=>'Compte',    'width'=>25, 'align'=>'L'),
            array('label'=>'Montant',   'width'=>30, 'align'=>'R'),
        );
        $pdf->setTableContext('Justification Avance', $cols);
        $pdf->drawTableHeader();

        $i = 1; $total = 0;
        foreach ($object->lines as $ln) {
            $total += (float)$ln->montant;
            $pdf->drawTableRow(array(
                $i++,
                $ln->depense_label,
                $ln->projet,
                $ln->budget,
                $ln->compte,
                ApcPdfBase::fmtMoney($ln->montant, $object->devise),
            ));
        }
        if (empty($object->lines)) {
            $pdf->drawTableRow(array('', 'Aucune ligne', '', '', '', ''));
        }

        $pdf->drawTableTotalRow(array('', '', '', '', 'TOTAL DÉPENSES :', ApcPdfBase::fmtMoney($total, $object->devise)));
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

        $boxJust = array('Justifié par / Auteur', $object->justif_nom, $object->justif_fonction, ApcPdfBase::fmtDate($object->date_signature_justif));
        $boxVer  = array('Vérificateur Finances', $object->verif_nom, $object->verif_fonction, ApcPdfBase::fmtDate($object->date_signature_verif));
        $boxApp  = array('Approbateur / Ordonnateur', $object->approb_nom, $object->approb_fonction, ApcPdfBase::fmtDate($object->date_signature_approb));
        $pdf->drawSignatureRow3($boxJust, $boxVer, $boxApp, 6);

        $filename = 'JAV_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $object->ref) . '.pdf';
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
