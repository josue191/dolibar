<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * Générateur PDF spécifique : BON DE SORTIE MAGASIN / REQUISITION APC
 * Respecte strictement la trame Excel "recquisistion" :
 *   - En-tête APC (via ApcPdfBase Header)
 *   - Titre "BON DE SORTIE MAGASIN N° REQ-YYYY-NNNN" centré, + date demande / date sortie
 *   - Objet + Demandeur + EB lié
 *   - Tableau 5 colonnes : Date | Description | Qté demandée | Qté sortie | Écart
 *   - Lignes totaux (cumul qte_demandée / qte_sortie / écart)
 *   - Bloc Notes
 *   - Zones 2 signatures en ligne (Demandeur | Magasinier) via drawSignatureRow2
 */

if (! defined('DOL_VERSION')) die('');

require_once DOL_DOCUMENT_ROOT . '/core/class/translate.class.php';
require_once __DIR__ . '/ApcPdfBase.class.php';
require_once __DIR__ . '/../ApcRequisition.class.php';
require_once __DIR__ . '/../ApcEtatBesoin.class.php';

class pdf_requisition_apc
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
        $this->name        = 'pdf_requisition_apc';
        $this->description = 'PDF Requisition / Bon de sortie magasin conforme trame APC ONG';
        $this->type        = 'apc_requisition';
    }

    /**
     * @param ApcRequisition $object
     * @param Translate $langs
     * @param string $outputdir
     * @param string $mode  I (inline) | F (file) | S (string)
     * @return int|string
     */
    public function write_file($object, $langs, $outputdir = '', $mode = 'I')
    {
        $object->fetchLines();
        $langs->load('apclogistics@apclogistics');

        $pdf = new ApcPdfBase('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetTitle('Bon de sortie magasin ' . $object->ref);
        $pdf->SetSubject('Requisition APC ONG — ' . $object->objet);
        $pdf->SetKeywords('APC, Requisition, Sortie magasin, ' . $object->ref);

        $pdf->AddPage();

        $pageW = $pdf->getPageWidth();
        $usableW = $pageW - 24;

        // ========== TITRE DOCUMENT ==========
        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetTextColor(15, 98, 60);
        $titre = 'BON DE SORTIE MAGASIN N° ' . ($object->ref ?: '—');
        $pdf->Cell(0, 8, $titre, 0, 1, 'C', false);
        $pdf->Ln(2);

        // ========== SOUS-TITRE infos générales ==========
        $pdf->SetTextColor(0,0,0);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(32, 5.5, $langs->trans('REQDateDemande') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 5.5, ApcPdfBase::fmtDate($object->date_demande), 0, 0, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 5.5, $langs->trans('REQDateSortie') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5.5, $object->date_sortie ? ApcPdfBase::fmtDate($object->date_sortie) : '—', 0, 1, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(32, 5.5, $langs->trans('FieldObjet') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell($usableW - 32, 5.5, $object->objet, 0, 'L', false);

        if ($object->fk_etatbesoin > 0) {
            $eb = new ApcEtatBesoin(isset($GLOBALS['db']) ? $GLOBALS['db'] : null);
            $ebRef = '';
            if ($eb && method_exists($eb, 'fetch') && $eb->fetch($object->fk_etatbesoin)) {
                $ebRef = $eb->ref . ' — ' . $eb->objet;
            } else {
                $ebRef = 'EB #' . (int)$object->fk_etatbesoin;
            }
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(32, 5.5, $langs->trans('REQFK_EB') . ' : ', 0, 0, 'L', false);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->MultiCell($usableW - 32, 5.5, $ebRef, 0, 'L', false);
        }

        if (!empty($object->demandeur_nom)) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(32, 5.5, $langs->trans('REQDemandeur') . ' : ', 0, 0, 'L', false);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 5.5, $object->demandeur_nom . (empty($object->demandeur_fonction) ? '' : ' — ' . $object->demandeur_fonction), 0, 1, 'L', false);
        }
        if (!empty($object->magasinier_nom)) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(32, 5.5, $langs->trans('REQMagasinier') . ' : ', 0, 0, 'L', false);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 5.5, $object->magasinier_nom . (empty($object->magasinier_fonction) ? '' : ' — ' . $object->magasinier_fonction), 0, 1, 'L', false);
        }
        $pdf->Ln(4);

        // ========== TABLEAU 5 COLONNES ==========
        $cols = array(
            array('label' => $langs->trans('REQColDate'),       'width' => 22,  'align' => 'C'),
            array('label' => $langs->trans('REQColDescription'),'width' => 82,  'align' => 'L'),
            array('label' => $langs->trans('REQColQteDem'),     'width' => 28,  'align' => 'R'),
            array('label' => $langs->trans('REQColQteSort'),    'width' => 28,  'align' => 'R'),
            array('label' => $langs->trans('REQColEcart'),      'width' => 28,  'align' => 'R'),
        );
        $pdf->setTableContext('', $cols);
        $pdf->drawTableHeader();

        $i = 1;
        $tQDem = 0.0; $tQSort = 0.0; $tEcart = 0.0;
        foreach ($object->lines as $ln) {
            $tQDem += (float)$ln->qte_demandee;
            $tQSort += (float)$ln->qte_sortie;
            $ec = (float)$ln->qte_demandee - (float)$ln->qte_sortie;
            $tEcart += $ec;
            $pdf->drawTableRow(array(
                $ln->date_mouvement ? ApcPdfBase::fmtDate($ln->date_mouvement) : '',
                $ln->description,
                ApcPdfBase::fmtQty($ln->qte_demandee),
                ApcPdfBase::fmtQty($ln->qte_sortie),
                ApcPdfBase::fmtQty($ec),
            ));
            $i++;
        }
        if (empty($object->lines)) {
            $pdf->drawTableRow(array('', '(Aucune ligne)', '', '', ''));
        }

        // Ligne TOTAUX quantités
        $pdf->drawTableTotalRow(array(
            '',
            $langs->trans('REQTotalLignes'),
            ApcPdfBase::fmtQty($tQDem),
            ApcPdfBase::fmtQty($tQSort),
            ApcPdfBase::fmtQty($tEcart),
        ));

        $pdf->Ln(6);

        // ========== NOTES ==========
        if (!empty($object->note_public)) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 5, 'Notes / Observations : ', 0, 1, 'L', false);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 4.5, $object->note_public, 0, 'L', false);
            $pdf->Ln(3);
        }

        // ========== ZONES 2 SIGNATURES (Demandeur | Magasinier) ==========
        $ySig = $pdf->GetY() + 4;
        $pdf->drawSignatureRow2(
            array(
                $langs->trans('REQDemandeur'),
                $object->demandeur_nom,
                $object->demandeur_fonction,
                ApcPdfBase::fmtDate($object->date_signature_demandeur),
                'Demandeur',
            ),
            array(
                $langs->trans('REQMagasinier'),
                $object->magasinier_nom,
                $object->magasinier_fonction,
                ApcPdfBase::fmtDate($object->date_signature_magasinier),
                'Magasinier / Sortie stock',
            ),
            $ySig,
            10
        );

        // ========== SORTIE ==========
        if ($mode === 'F') {
            $filepath = ($outputdir ? rtrim($outputdir, '/') . '/' : '') . 'REQ_' . $object->ref . '.pdf';
            $pdf->Output($filepath, 'F');
            return $filepath;
        } elseif ($mode === 'S') {
            return $pdf->Output('REQ_' . $object->ref . '.pdf', 'S');
        } else {
            $pdf->Output('REQ_' . $object->ref . '.pdf', 'I');
            return 1;
        }
    }
}
