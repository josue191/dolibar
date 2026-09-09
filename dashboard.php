
<style>
.chart-container {
    position: relative;
    width: 100%;
}
.chart-container canvas {
}
</style>
<?php
// Inclusion de Dolibarr : pattern de fallback standard (portable, fonctionne sur cPanel et en local)
$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once __DIR__ . '/class/ApcStock.class.php';

global $db, $conf, $langs, $user;

$langs->load('apclogistics@apclogistics');
$langs->load('main');

// Security check : au moins un droit de lecture
if (!$user->rights->apclogistics->etatbesoin->read
    && !$user->rights->apclogistics->stock->read
    && !$user->rights->apclogistics->boncommande->read) {
    accessforbidden();
}

$now = dol_now();
$monthStart = dol_print_date($now, '%Y-%m-01');
$weekStart = dol_print_date($now - 7 * 24 * 3600, '%Y-%m-%d');

/*
 * Helper : compte les documents "en attente" (status < 2, non annulé)
 */
function apcDashCountPending($db, $table, $statusCol = 'status')
{
    $sql = "SELECT COUNT(*) AS nb FROM " . MAIN_DB_PREFIX . $table
         . " WHERE " . $statusCol . " < 2 AND " . $statusCol . " <> 9";
    $res = $db->query($sql);
    if (!$res) return 0;
    $o = $db->fetch_object($res);
    return (int)$o->nb;
}

/*
 * Helper : somme des montants HT d'une table pour le mois en cours
 */
function apcDashSumMonth($db, $table, $dateCol, $moneyCol)
{
    global $monthStart;
    $sql = "SELECT COALESCE(SUM(" . $moneyCol . "),0) AS tot FROM " . MAIN_DB_PREFIX . $table
         . " WHERE " . $dateCol . " >= '" . $db->escape($monthStart) . "'";
    $res = $db->query($sql);
    if (!$res) return 0;
    $o = $db->fetch_object($res);
    return (float)$o->tot;
}

/*
 * Helper : série de montants HT par mois (mois glissant, 6 mois)
 * Retourne array('labels'=>[], 'data'=>[])
 */
function apcDashSeriesByMonth($db, $table, $dateCol, $moneyCol, $nbMonths = 6)
{
    $labels = array();
    $data = array();
    $now = dol_now();
    for ($i = $nbMonths - 1; $i >= 0; $i--) {
        $ts = strtotime('first day of this month', $now);
        $ts = strtotime('-' . $i . ' month', $ts);
        $ym = date('Y-m', $ts);
        $labels[] = strftime('%b %y', $ts);
        $sql = "SELECT COALESCE(SUM(" . $moneyCol . "),0) AS tot FROM " . MAIN_DB_PREFIX . $table
             . " WHERE DATE_FORMAT(" . $dateCol . ", '%Y-%m') = '" . $ym . "'";
        $res = $db->query($sql);
        $data[] = $res ? (float)$db->fetch_object($res)->tot : 0;
    }
    return array('labels' => $labels, 'data' => $data);
}

/*
 * Helper : dépenses JAV par mois (mois glissant)
 */
function apcDashJavSeries($db, $nbMonths = 6)
{
    $labels = array();
    $data = array();
    $now = dol_now();
    for ($i = $nbMonths - 1; $i >= 0; $i--) {
        $ts = strtotime('first day of this month', $now);
        $ts = strtotime('-' . $i . ' month', $ts);
        $ym = date('Y-m', $ts);
        $labels[] = strftime('%b %y', $ts);
        $sql = "SELECT COALESCE(SUM(total_depense),0) AS tot FROM " . MAIN_DB_PREFIX . "apclogistics_justifavance"
             . " WHERE DATE_FORMAT(date_jav, '%Y-%m') = '" . $ym . "'";
        $res = $db->query($sql);
        $data[] = $res ? (float)$db->fetch_object($res)->tot : 0;
    }
    return array('labels' => $labels, 'data' => $data);
}

/*
 * Helper : répartition stock par catégorie (piechart)
 * LEFT JOIN catégories Dolibarr (llx_categorie_product / llx_categorie), fallback designation
 */
function apcDashStockByCategory($db)
{
    $sql = "SELECT COALESCE(c.label, s.designation, CONCAT('Produit #', s.fk_product)) AS cat, SUM(s.stock_actuel) AS qty "
         . "FROM " . MAIN_DB_PREFIX . "apclogistics_stock s "
         . "LEFT JOIN " . MAIN_DB_PREFIX . "categorie_product cp ON cp.fk_product = s.fk_product "
         . "LEFT JOIN " . MAIN_DB_PREFIX . "categorie c ON c.rowid = cp.fk_categorie "
         . "GROUP BY cat ORDER BY qty DESC LIMIT 8";
    $res = $db->query($sql);
    $labels = array();
    $data = array();
    if ($res) {
        while ($o = $db->fetch_object($res)) {
            $labels[] = $o->cat;
            $data[] = (float)$o->qty;
        }
    }
    return array('labels' => $labels, 'data' => $data);
}

/*
 * ============ CALCUL DES DONNÉES ============
 */
// Widgets "en attente"
$wEB   = apcDashCountPending($db, 'apclogistics_etatbesoin');
$wREQ  = apcDashCountPending($db, 'apclogistics_requisition');
$wDP   = apcDashCountPending($db, 'apclogistics_demandeprix');
$wBC   = apcDashCountPending($db, 'apclogistics_boncommande');

// BR dernière semaine (tous statuts sauf annulé)
$sqlBR = "SELECT COUNT(*) AS nb FROM " . MAIN_DB_PREFIX . "apclogistics_bonreception"
       . " WHERE date_reception >= '" . $db->escape($weekStart) . "' AND status <> 9";
$resBR = $db->query($sqlBR);
$wBR = $resBR ? (int)$db->fetch_object($resBR)->nb : 0;

// Stock critique
$wStock = count(ApcStock::fetchAllLowStock($db));

// Totaux montants du mois en cours
$totEB   = apcDashSumMonth($db, 'apclogistics_etatbesoin', 'date_eb', 'total_ht');
$totDP   = apcDashSumMonth($db, 'apclogistics_demandeprix', 'date_dp', 'total_ht');
$totBC   = apcDashSumMonth($db, 'apclogistics_boncommande', 'date_cmde', 'total_ht');
$totBR   = apcDashSumMonth($db, 'apclogistics_bonreception', 'date_reception', 'total_ht');
$totDAV  = apcDashSumMonth($db, 'apclogistics_demandeavance', 'date_dav', 'total');
$totJAV  = apcDashSumMonth($db, 'apclogistics_justifavance', 'date_jav', 'total_depense');
$totDPAI = apcDashSumMonth($db, 'apclogistics_demandepaiement', 'date_dpai', 'total');

// Graphiques
$chartMontants = apcDashSeriesByMonth($db, 'apclogistics_boncommande', 'date_cmde', 'total_ht');
$chartJAV      = apcDashJavSeries($db);
$chartStock    = apcDashStockByCategory($db);

$title = $langs->trans('APCDashboardTitle');
llxHeader('', $title, '', '', 0, 0, array(
    '/custom/apclogistics/vendor/chartjs/chart.umd.min.js',
), array('/custom/apclogistics/css/apclogistics.css'));

print load_fiche_titre($langs->trans('APCDashboardTitle'), '', 'apclogistics@apclogistics', 0);

// Lien vers la config (admin uniquement)
if ($user->admin) {
    print '<div style="margin-bottom:14px;"><a class="butAction" href="' . DOL_URL_ROOT . '/custom/apclogistics/admin/apclogistics_setup.php">'
        . $langs->trans('ModuleSetup') . '</a></div>';
}
?>
<div class="dashboard-apc">
    <!-- Barre de raccourcis -->
    <div class="apc-quick-actions">
        <?php if ($user->rights->apclogistics->etatbesoin->create): ?>
            <a class="butAction" href="<?php print DOL_URL_ROOT; ?>/custom/apclogistics/etatbesoin_card.php?action=create">
                + <?php print $langs->trans('NewEtatBesoin'); ?>
            </a>
        <?php endif; ?>
        <?php if ($user->rights->apclogistics->demandeprix->create): ?>
            <a class="butAction" href="<?php print DOL_URL_ROOT; ?>/custom/apclogistics/demandeprix_card.php?action=create">
                + <?php print $langs->trans('NewDemandePrix'); ?>
            </a>
        <?php endif; ?>
        <?php if ($user->rights->apclogistics->boncommande->create): ?>
            <a class="butAction" href="<?php print DOL_URL_ROOT; ?>/custom/apclogistics/boncommande_card.php?action=create">
                + <?php print $langs->trans('NewBonCommande'); ?>
            </a>
        <?php endif; ?>
    </div>

    <!-- Widgets 6 cases -->
    <div class="apc-widgets-grid">
        <div class="apc-widget apc-widget--warning">
            <div class="apc-widget__label"><?php print $langs->trans('WidgetEtatBesoinPending'); ?></div>
            <div class="apc-widget__value"><?php print $wEB; ?></div>
        </div>
        <div class="apc-widget apc-widget--info">
            <div class="apc-widget__label"><?php print $langs->trans('WidgetRequisitionPending'); ?></div>
            <div class="apc-widget__value"><?php print $wREQ; ?></div>
        </div>
        <div class="apc-widget apc-widget--primary">
            <div class="apc-widget__label"><?php print $langs->trans('WidgetDemandePrixPending'); ?></div>
            <div class="apc-widget__value"><?php print $wDP; ?></div>
        </div>
        <div class="apc-widget apc-widget--accent">
            <div class="apc-widget__label"><?php print $langs->trans('WidgetBonCommandePending'); ?></div>
            <div class="apc-widget__value"><?php print $wBC; ?></div>
        </div>
        <div class="apc-widget apc-widget--success">
            <div class="apc-widget__label"><?php print $langs->trans('WidgetBonReceptionWeek'); ?></div>
            <div class="apc-widget__value"><?php print $wBR; ?></div>
        </div>
        <div class="apc-widget apc-widget--danger">
            <div class="apc-widget__label"><?php print $langs->trans('WidgetStockCritique'); ?></div>
            <div class="apc-widget__value"><?php print $wStock; ?></div>
        </div>
    </div>

    <!-- Totaux montants du mois en cours -->
    <div class="apc-totals">
        <div class="apc-totals__title"><?php print $langs->trans('APCDashTotalsMonth'); ?></div>
        <div class="apc-totals__grid">
            <div class="apc-total"><span class="apc-total__label">EB</span><span class="apc-total__value"><?php print price($totEB); ?></span></div>
            <div class="apc-total"><span class="apc-total__label">DP</span><span class="apc-total__value"><?php print price($totDP); ?></span></div>
            <div class="apc-total"><span class="apc-total__label">BC</span><span class="apc-total__value"><?php print price($totBC); ?></span></div>
            <div class="apc-total"><span class="apc-total__label">BR</span><span class="apc-total__value"><?php print price($totBR); ?></span></div>
            <div class="apc-total"><span class="apc-total__label">DAV</span><span class="apc-total__value"><?php print price($totDAV); ?></span></div>
            <div class="apc-total"><span class="apc-total__label">JAV</span><span class="apc-total__value"><?php print price($totJAV); ?></span></div>
            <div class="apc-total"><span class="apc-total__label">DPAI</span><span class="apc-total__value"><?php print price($totDPAI); ?></span></div>
        </div>
    </div>

    <!-- Graphiques -->
    <div class="apc-charts-grid">
        <div class="apc-chart">
            <div class="apc-chart__title"><?php print $langs->trans('ChartMontantsPerMonth'); ?></div>
            <div class="chart-container"><canvas id="chartMontants" height="140"></canvas></div>
        </div>
        <div class="apc-chart">
            <div class="apc-chart__title"><?php print $langs->trans('ChartDepensesJustifPerMonth'); ?></div>
            <div class="chart-container"><canvas id="chartJAV" height="140"></canvas></div>
        </div>
        <div class="apc-chart apc-chart--wide">
            <div class="apc-chart__title"><?php print $langs->trans('ChartStockCategory'); ?></div>
            <div class="chart-container"><canvas id="chartStock" height="100"></canvas></div>
        </div>
    </div>
</div>

<script type="text/javascript">
$(document).ready(function() {
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js non charge');
        return;
    }

    var montantsLabels = <?php print json_encode($chartMontants['labels']); ?>;
    var montantsData   = <?php print json_encode($chartMontants['data']); ?>;
    var javLabels      = <?php print json_encode($chartJAV['labels']); ?>;
    var javData        = <?php print json_encode($chartJAV['data']); ?>;
    var stockLabels    = <?php print json_encode($chartStock['labels']); ?>;
    var stockData      = <?php print json_encode($chartStock['data']); ?>;

    // Histogramme montants BC par mois
    new Chart(document.getElementById('chartMontants').getContext('2d'), {
        type: 'bar',
        data: {
            labels: montantsLabels,
            datasets: [{
                label: 'Total HT',
                data: montantsData,
                backgroundColor: '#1a8754'
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    // Linéaire dépenses JAV par mois
    new Chart(document.getElementById('chartJAV').getContext('2d'), {
        type: 'line',
        data: {
            labels: javLabels,
            datasets: [{
                label: 'Depenses Justif',
                data: javData,
                borderColor: '#0f623c',
                fill: true,
                backgroundColor: 'rgba(26,135,84,0.15)',
                tension: 0.25
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    // Piechart stock par catégorie
    new Chart(document.getElementById('chartStock').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: stockLabels,
            datasets: [{
                data: stockData,
                backgroundColor: ['#1a8754','#0f623c','#2db472','#ccf0df','#7a4fd9','#f0b429','#1a82d9','#e04b4b']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
});
</script>

<?php
llxFooter();
$db->close();
