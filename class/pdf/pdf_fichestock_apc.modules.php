<?php
/* Copyright (C) 2026 APC ONG Agri-Peace and Child <contact@apc-ong.org>
 *
 * Générateur PDF spécifique : FICHE DE STOCK APC
 * Respecte strictement la trame APC :
 *   - En-tête APC (via ApcPdfBase Header)
 *   - Titre "FICHE DE STOCK" centré, vert APC
 *   - Infos article : ref, label, unité, stock actuel, seuil alerte, responsables
 *   - Tableau 6 colonnes mouvements : Date | Référence (BC/REQ/BR) | Unité | Entrée | Sortie | Stock
 *   - Zones doubles signatures : Gestionnaire | Responsable Logistique
 */

if (! defined('DOL_VERSION')) die('');

require_once DOL_DOCUMENT_ROOT . '/core/class/translate.class.php';
require_once __DIR__ . '/ApcPdfBase.class.php';
require_once __DIR__ . '/../ApcStock.class.php';

class pdf_fichestock_apc
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
        $this->name        = 'pdf_fichestock_apc';
        $this->description = 'PDF Fiche de Stock conforme trame APC';
        $this->type        = 'apc_fichestock';
    }

    /**
     * @param ApcStock $object
     * @param Product  $prod
     * @param Translate $langs
     * @param string $outputdir
     * @param string $mode  I (inline) | F (file) | S (string)
     * @return int|string
     */
    public function write_file($object, $prod, $langs, $outputdir = '', $mode = 'I')
    {
        $movements = $object->fetchMovements(500);
        $langs->load('apclogistics@apclogistics');
        $langs->load('main');

        $pdf = new ApcPdfBase('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetTitle('Fiche de Stock ' . ($prod->ref ?: $object->fk_product));
        $pdf->SetSubject('Fiche de Stock APC ONG — ' . ($prod->ref ?: $object->fk_product));
        $pdf->SetKeywords('APC, Stock, Mouvements, ' . ($prod->ref ?: $object->fk_product));

        $pdf->AddPage();

        // ========== TITRE DOCUMENT ==========
        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetTextColor(15, 98, 60);
        $pdf->Cell(0, 8, 'FICHE DE STOCK', 0, 1, 'C', false);
        $pdf->Ln(2);

        // ========== INFOS ARTICLE ==========
        $pdf->SetTextColor(0,0,0);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(32, 5.5, $langs->trans('Ref') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(60, 5.5, ($prod->ref ?: ('#' . $object->fk_product)), 0, 0, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 5.5, $langs->trans('Label') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5.5, ($prod->label ?: $object->designation), 0, 1, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(32, 5.5, $langs->trans('StockUnit') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 5.5, ($object->unite ?: '—'), 0, 0, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 5.5, $langs->trans('StockCurrent') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 5.5, ApcPdfBase::fmtQty($object->stock_actuel), 0, 0, 'L', false);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 5.5, $langs->trans('StockAlert') . ' : ', 0, 0, 'L', false);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5.5, ApcPdfBase::fmtQty($object->seuil_alerte), 0, 1, 'L', false);

        $pdf->Ln(4);

        // ========== TABLEAU 6 COLONNES MOUVEMENTS ==========
        $cols = array(
            array('label' => $langs->trans('StockMovDate'), 'width' => 30, 'align' => 'C'),
            array('label' => $langs->trans('StockMovRef'),   'width' => 62, 'align' => 'L'),
            array('label' => $langs->trans('StockUnit'),     'width' => 18, 'align' => 'C'),
            array('label' => $langs->trans('StockMovIn'),    'width' => 26, 'align' => 'R'),
            array('label' => $langs->trans('StockMovOut'),   'width' => 26, 'align' => 'R'),
            array('label' => $langs->trans('StockCurrent'),  'width' => 30, 'align' => 'R'),
        );
        $pdf->setTableContext('', $cols);
        $pdf->drawTableHeader();

        // Stock calculé ligne par ligne (ordre chronologique)
        $totalDelta = 0;
        foreach ($movements as $m) { $totalDelta += (float)$m->entree - (float)$m->sortie; }
        $stkCourant = (float)$object->stock_actuel - $totalDelta;

        foreach ($movements as $m) {
            $stkCourant += (float)$m->entree - (float)$m->sortie;
            $refDoc = $m->ref_doc_type . ' #' . (int)$m->ref_doc_id;
            if (!empty($m->ref_doc_label)) $refDoc = $m->ref_doc_label;
            $pdf->drawTableRow(array(
                ApcPdfBase::fmtDate($m->date_movement),
                $refDoc,
                ($m->unite ?: '—'),
                ((float)$m->entree > 0 ? ApcPdfBase::fmtQty($m->entree) : ''),
                ((float)$m->sortie > 0 ? ApcPdfBase::fmtQty($m->sortie) : ''),
                ApcPdfBase::fmtQty($stkCourant),
            ));
        }
        if (empty($movements)) {
            $pdf->drawTableRow(array('', '(Aucun mouvement)', '', '', '', ''));
        }

        $pdf->drawTableTotalRow(array(
            '',
            $langs->trans('StockCurrent'),
            '',
            '',
            '',
            ApcPdfBase::fmtQty($object->stock_actuel),
        ), 6);

        $pdf->Ln(6);

        // ========== ZONES DOUBLES SIGNATURES (Gestionnaire | Resp Logistique) ==========
        $ySig = $pdf->GetY() + 6;

        $gName = '';
        $gFct  = '';
        if (!empty($object->fk_user_gestionnaire)) {
            $u = new User($this->db);
            if ($u->fetch($object->fk_user_gestionnaire) > 0) {
                $gName = $u->getFullName($langs);
                $gFct  = $u->poste;
            }
        }
        $rName = '';
        $rFct  = '';
        if (!empty($object->fk_user_resp_logistique)) {
            $u2 = new User($this->db);
            if ($u2->fetch($object->fk_user_resp_logistique) > 0) {
                $rName = $u2->getFullName($langs);
                $rFct  = $u2->poste;
            }
        }

        $pdf->drawSignatureRow2(
            array(
                $langs->trans('StockGest'),
                $gName,
                $gFct,
                '',
                'Gestionnaire de stock',
            ),
            array(
                $langs->trans('StockRespLog'),
                $rName,
                $rFct,
                '',
                'Responsable Logistique',
            ),
            $ySig,
            10
        );

        // ========== SORTIE ==========
        if ($mode === 'F') {
            $filepath = ($outputdir ? rtrim($outputdir, '/') . '/' : '') . 'STK_' . ($prod->ref ?: $object->fk_product) . '.pdf';
            $pdf->Output($filepath, 'F');
            return $filepath;
        } elseif ($mode === 'S') {
            return $pdf->Output('STK_' . ($prod->ref ?: $object->fk_product) . '.pdf', 'S');
        } else {
            $pdf->Output('STK_' . ($prod->ref ?: $object->fk_product) . '.pdf', 'I');
            return 1;
        }
    }
}