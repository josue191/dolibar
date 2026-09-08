<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * Générateur PDF spécifique : ETAT DE BESOIN APC
 * Respecte strictement la trame Excel :
 *   - En-tête APC (via ApcPdfBase Header)
 *   - Titre "ETAT DE BESOIN N° EB-YYYY-NNNN" centré, + date
 *   - Objet + Demandeur (en-tête du document)
 *   - Tableau 5 colonnes : N° | Dépense | Budget | Compte | Montant
 *   - Ligne Total général
 *   - Zones 3 signatures (Demandeur / Vérificateur / Approbateur) en ligne
 */

if (! defined('DOL_VERSION')) die('');

require_once DOL_DOCUMENT_ROOT . '/core/class/translate.class.php';
require_once __DIR__ . '/ApcPdfBase.class.php';
require_once __DIR__ . '/../ApcEtatBesoin.class.php';

class pdf_etatbesoin_apc
{
    public $db;
    public $name;
    public $description;
    public $type;
    public $module = 'apclogistics';
    public $page_hauteur;
    public $page_largeur;
    public $marge_gauche;
    public $marge_droite;
    public $marge_haute;
    public $marge_basse;
    public $espaceHumeur;

    public function __construct($db = null)
    {
        global $langs;
        $this->db = $db;
        $this->name        = 'pdf_etatbesoin_apc';
        $this->description = 'PDF Etat de besoin conforme trame APC ONG';
        $this->type        = 'apc_etatbesoin';
    }

    /**
     * Génère et affiche le PDF d'un Etat de besoin.
     * @param ApcEtatBesoin $object
     * @param Translate $langs
     * @return int 1 si OK, 0 si KO
     */
    public function write_file($object, $langs, $outputdir = '', $mode = 'I')
    {
        $object->fetchLines();

        $pdf = new ApcPdfBase('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetTitle('Etat de besoin ' . $object->ref);
        $pdf->SetSubject('Etat de besoin APC ONG — ' . $object->objet);
        $pdf->SetKeywords('APC, Etat de besoin, ' . $object->ref);

        $pdf->AddPage();

        $pageW = $pdf->getPageWidth();
        $usableW = $pageW - 24;

        // ========== TITRE DOCUMENT ==========
        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetTextColor(15, 98, 60);
        $titre = 'ETAT DE BESOIN N° ' . ($object->ref ?: '—');
        $pdf->Cell(0, 8, $titre, 0, 1, 'C', false);
        $pdf->Ln(2);

        // ========== SOUS-TITRE : DATE + OBJET ==========
        $pdf->SetTextColor(0,0,0);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(28, 5.5, 'Date : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(52, 5.5, ApcPdfBase::fmtDate($object->date_eb), 0, 0, 'L', false);
        $pdf->Ln(6);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(28, 5.5, 'Objet : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell($usableW - 28, 5.5, $object->objet, 0, 'L', false);
        $pdf->Ln(2);

        if (!empty($object->demandeur_nom) || !empty($object->fk_user_demandeur)) {
            $nom = $object->signataire_nom_d ?: '';
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(28, 5.5, 'Demandeur : ', 0, 0, 'L', false);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell($usableW - 28, 5.5, $nom, 0, 1, 'L', false);
        }
        $pdf->Ln(4);

        // ========== TABLEAU 5 COLONNES ==========
        $langs->load('apclogistics@apclogistics');
        $cols = array(
            array('label' => $langs->trans('EBColNo'),        'width' => 12,  'align' => 'C'),
            array('label' => $langs->trans('EBColDepense'),   'width' => 78,  'align' => 'L'),
            array('label' => $langs->trans('EBColBudget'),    'width' => 38,  'align' => 'L'),
            array('label' => $langs->trans('EBColCompte'),    'width' => 28,  'align' => 'C'),
            array('label' => $langs->trans('EBColMontant'),   'width' => 30,  'align' => 'R'),
        );
        $pdf->setTableContext('', $cols);
        $pdf->drawTableHeader();

        $i = 1;
        $total = 0.0;
        foreach ($object->lines as $ln) {
            $total += (float)$ln->montant;
            $pdf->drawTableRow(array(
                (string)$i,
                $ln->depense,
                $ln->projet_or_budget,
                $ln->compte,
                ApcPdfBase::fmtMoney($ln->montant),
            ));
            $i++;
        }
        if (empty($object->lines)) {
            $pdf->drawTableRow(array('', '(Aucune ligne)', '', '', ''));
        }

        // Ligne TOTAL
        $pdf->drawTableTotalRow(array(
            '',
            $langs->trans('EBGeneralTotal'),
            '',
            '',
            ApcPdfBase::fmtMoney($total),
        ));

        $pdf->Ln(6);

        // ========== NOTES ==========
        if (!empty($object->note_public)) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 5, 'Notes : ', 0, 1, 'L', false);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 4.5, $object->note_public, 0, 'L', false);
            $pdf->Ln(3);
        }

        // ========== ZONES 3 SIGNATURES ==========
        $ySig = $pdf->GetY() + 4;
        $pdf->drawSignatureRow3(
            array(
                $langs->trans('EBDemandeur'),
                $object->signataire_nom_d,
                $object->signataire_fonction_d,
                ApcPdfBase::fmtDate($object->date_signature_demandeur),
                'Pour le demandeur',
            ),
            array(
                $langs->trans('EBVerificateur'),
                $object->signataire_nom_v,
                $object->signataire_fonction_v,
                ApcPdfBase::fmtDate($object->date_signature_verif),
                'Vérification conforme',
            ),
            array(
                $langs->trans('EBApprobateur'),
                $object->signataire_nom_a,
                $object->signataire_fonction_a,
                ApcPdfBase::fmtDate($object->date_signature_approb),
                'Pour approbation',
            ),
            $ySig
        );

        // ========== SORTIE ==========
        if ($mode === 'F') {
            $filepath = ($outputdir ? rtrim($outputdir, '/') . '/' : '') . 'EB_' . $object->ref . '.pdf';
            $pdf->Output($filepath, 'F');
            return $filepath;
        } elseif ($mode === 'S') {
            return $pdf->Output('EB_' . $object->ref . '.pdf', 'S');
        } else {
            $pdf->Output('EB_' . $object->ref . '.pdf', 'I');
            return 1;
        }
    }
}
