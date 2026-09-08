<?php
/* ============================================================
 * apc_links.lib.php
 * Onglet "Liens documents" (Task 13) — helper generique.
 * Affiche les documents parents (amont) et enfants (aval)
 * d'un objet APC, avec lignes cliquables vers les fiches.
 * ============================================================ */

if (!defined('DOL_VERSION')) die('Acces direct interdit');

/**
 * Mapping element -> fichier card (meme mapping que ApcObjectBase::getNomUrl).
 */
function apcLinksCardMap()
{
    return array(
        'eb'    => 'etatbesoin_card.php',
        'req'   => 'requisition_card.php',
        'dp'    => 'demandeprix_card.php',
        'cot'   => 'cotation_card.php',
        'bc'    => 'boncommande_card.php',
        'br'    => 'bonreception_card.php',
        'stock' => 'stock_card.php',
        'dav'   => 'demandeavance_card.php',
        'jav'   => 'justifavance_card.php',
        'dpai'  => 'demandepaiement_card.php',
    );
}

/**
 * Mapping element -> (classe, fichier classe).
 */
function apcLinksClassMap()
{
    return array(
        'eb'    => array('ApcEtatBesoin', 'ApcEtatBesoin.class.php'),
        'req'   => array('ApcRequisition', 'ApcRequisition.class.php'),
        'dp'    => array('ApcDemandePrix', 'ApcDemandePrix.class.php'),
        'cot'   => array('ApcCotation', 'ApcCotation.class.php'),
        'bc'    => array('ApcBonCommande', 'ApcBonCommande.class.php'),
        'br'    => array('ApcBonReception', 'ApcBonReception.class.php'),
        'stock' => array('ApcStock', 'ApcStock.class.php'),
        'dav'   => array('ApcDemandeAvance', 'ApcDemandeAvance.class.php'),
        'jav'   => array('ApcJustifAvance', 'ApcJustifAvance.class.php'),
        'dpai'  => array('ApcDemandePaiement', 'ApcDemandePaiement.class.php'),
    );
}

/**
 * Definition des liens par entite.
 * - parents  : documents amont, FK portee par l'objet courant (fk_col)
 * - children : documents aval, FK portee par la table cible (fk_col)
 */
function apcLinksDefinition()
{
    return array(
        'eb' => array(
            'children' => array(
                array('type' => 'req', 'table' => 'apclogistics_requisition', 'fk_col' => 'fk_etatbesoin', 'label' => 'REQTitle', 'date_col' => 'date_demande'),
            ),
        ),
        'req' => array(
            'parents' => array(
                array('type' => 'eb', 'table' => 'apclogistics_etatbesoin', 'fk_col' => 'fk_etatbesoin', 'label' => 'EBTitle', 'date_col' => 'date_eb'),
            ),
        ),
        'dp' => array(
            'children' => array(
                array('type' => 'cot', 'table' => 'apclogistics_cotation', 'fk_col' => 'fk_demandeprix', 'label' => 'COTTitle', 'date_col' => 'date_cotation', 'money_col' => 'total_ht'),
            ),
        ),
        'cot' => array(
            'parents' => array(
                array('type' => 'dp', 'table' => 'apclogistics_demandeprix', 'fk_col' => 'fk_demandeprix', 'label' => 'DPTitle', 'date_col' => 'date_dp'),
            ),
            'children' => array(
                array('type' => 'bc', 'table' => 'apclogistics_boncommande', 'fk_col' => 'fk_cotation', 'label' => 'BCTitle', 'date_col' => 'date_cmde', 'money_col' => 'total_ht'),
            ),
        ),
        'bc' => array(
            'parents' => array(
                array('type' => 'cot', 'table' => 'apclogistics_cotation', 'fk_col' => 'fk_cotation', 'label' => 'COTTitle', 'date_col' => 'date_cotation', 'money_col' => 'total_ht'),
                array('type' => 'dp', 'table' => 'apclogistics_demandeprix', 'fk_col' => 'fk_demandeprix', 'label' => 'DPTitle', 'date_col' => 'date_dp'),
            ),
            'children' => array(
                array('type' => 'br', 'table' => 'apclogistics_bonreception', 'fk_col' => 'fk_boncommande', 'label' => 'BRTitle', 'date_col' => 'date_reception', 'money_col' => 'total_ht'),
            ),
        ),
        'br' => array(
            'parents' => array(
                array('type' => 'bc', 'table' => 'apclogistics_boncommande', 'fk_col' => 'fk_boncommande', 'label' => 'BCTitle', 'date_col' => 'date_cmde', 'money_col' => 'total_ht'),
            ),
        ),
        'dav' => array(
            'children' => array(
                array('type' => 'jav', 'table' => 'apclogistics_justifavance', 'fk_col' => 'fk_demande_avance', 'label' => 'JAVTitle', 'date_col' => 'date_jav', 'money_col' => 'total_depense'),
            ),
        ),
        'jav' => array(
            'parents' => array(
                array('type' => 'dav', 'table' => 'apclogistics_demandeavance', 'fk_col' => 'fk_demande_avance', 'label' => 'DAVTitle', 'date_col' => 'date_dav', 'money_col' => 'total'),
            ),
        ),
        'stock' => array(),
        'dpai'  => array(),
    );
}

/**
 * Charge un objet APC cible (instancie la classe, fetch par rowid).
 * @return object|null
 */
function apcLinksLoadObject($db, $type, $rowid)
{
    $map = apcLinksClassMap();
    if (!isset($map[$type])) return null;
    list($class, $file) = $map[$type];
    require_once __DIR__ . '/' . $file;
    $obj = new $class($db);
    if ($obj->fetch((int)$rowid) > 0) return $obj;
    return null;
}

/**
 * Affiche une section (parents ou enfants) sous forme de tableau.
 *
 * @param array $items Liste de array('link' => ..., 'fk_value' => int, 'is_parent' => bool)
 */
function apcLinksRenderSection($db, $langs, $conf, $title, $items)
{
    print '<h3>' . $langs->trans($title) . '</h3>';
    print '<div class="div-table-responsive">';
    print '<table class="noborder centpercent liste">';
    print '<tr class="liste_titre">';
    print '<th>' . $langs->trans('Type') . '</th>';
    print '<th>' . $langs->trans('Ref') . '</th>';
    print '<th class="center">' . $langs->trans('Date') . '</th>';
    print '<th class="right">' . $langs->trans('AmountHT') . '</th>';
    print '<th class="center">' . $langs->trans('Status') . '</th>';
    print '</tr>';

    $rows = 0;
    foreach ($items as $item) {
        $link = $item['link'];
        if (!empty($item['is_parent'])) {
            $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . $link['table'] . " WHERE rowid = " . (int)$item['fk_value'];
        } else {
            $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . $link['table'] . " WHERE " . $link['fk_col'] . " = " . (int)$item['fk_value'] . " ORDER BY rowid DESC";
        }
        $res = $db->query($sql);
        if (!$res) continue;
        while ($obj = $db->fetch_object($res)) {
            $target = apcLinksLoadObject($db, $link['type'], $obj->rowid);
            if (!$target) continue;
            $rows++;
            print '<tr class="oddeven">';
            print '<td>' . $langs->trans($link['label']) . '</td>';
            print '<td>' . $target->getNomUrl(1) . '</td>';
            print '<td class="center">' . dol_print_date($target->{$link['date_col']}, 'day') . '</td>';
            $money = isset($link['money_col']) ? $target->{$link['money_col']} : null;
            print '<td class="right">' . ($money !== null ? price($money) : '') . '</td>';
            print '<td class="center">' . $target->getStatusBadge() . '</td>';
            print '</tr>';
        }
    }
    if ($rows === 0) {
        print '<tr class="oddeven"><td colspan="5">' . $langs->trans('LinkNone') . '</td></tr>';
    }
    print '</table>';
    print '</div>';
}

/**
 * Onglet "Liens documents" : parents + enfants de l'objet courant.
 */
function apcPrintLinksTab($db, $langs, $conf, $object)
{
    $def = apcLinksDefinition();
    $type = $object->element;
    if (!isset($def[$type])) return;

    $parents = array();
    if (!empty($def[$type]['parents'])) {
        foreach ($def[$type]['parents'] as $link) {
            $fkVal = isset($object->{$link['fk_col']}) ? (int)$object->{$link['fk_col']} : 0;
            if ($fkVal > 0) $parents[] = array('link' => $link, 'fk_value' => $fkVal, 'is_parent' => true);
        }
    }
    $children = array();
    if (!empty($def[$type]['children'])) {
        foreach ($def[$type]['children'] as $link) {
            $children[] = array('link' => $link, 'fk_value' => (int)$object->id, 'is_parent' => false);
        }
    }

    if (empty($parents) && empty($children)) {
        print '<div class="opacitymedium">' . $langs->trans('LinkNone') . '</div>';
        return;
    }

    if (!empty($parents)) apcLinksRenderSection($db, $langs, $conf, 'LinkParents', $parents);
    if (!empty($children)) apcLinksRenderSection($db, $langs, $conf, 'LinkChildren', $children);
}