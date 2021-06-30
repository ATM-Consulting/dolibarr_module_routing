<?php



require('config.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/routing/class/routing.class.php');
dol_include_once('/product/class/html.formproduct.class.php');

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'alphanohtml');
$warehouse_src = GETPOST('warehouse_src', 'alphanohtml');
$warehouse_dest = GETPOST('warehouse_dest', 'alphanohtml');
$lineid= GETPOST('lineid', 'int');


$langs->load('routing@routing');
$object = new Societe($db);
$object->fetch($id);

$PDOdb = new TPDOdb;
$formProduct = new FormProduct($db);

$routingStock = new TRoutingStock($db);

if($action == 'save'){
	$routingStock->fk_warehouse_from=$warehouse_src;
	$routingStock->fk_warehouse_to=$warehouse_dest;
	$routingStock->fk_soc=$id;
	$routingStock->save($PDOdb);
}
if($action == 'delete'){
	$routingStock->load($PDOdb, $lineid);
	$routingStock->delete($PDOdb);
}
$TObj = $routingStock->LoadAllBy($PDOdb,array('fk_soc' => $id));




$exclude = array();

foreach($TObj as $obj) $exclude[]=$obj->fk_warehouse_from;

/*
 * VIEW
 */

llxHeader('', $langs->trans('StockTransfert'));

$head = societe_prepare_head($object);
dol_fiche_head($head, 'stock_transfert', $langs->trans('StockTransfert'), 0);

print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '?id='.$id.'">';
print '<input type="hidden" name="action" value="save" />';


print '<table width=100% class="tagtable liste">';
print '<tr class="liste_titre">';
print_liste_field_titre($langs->trans('WarehourseSrc'), $_SERVER["PHP_SELF"]);
print_liste_field_titre($langs->trans('WarehourseDest'), $_SERVER["PHP_SELF"]);
print '<td></td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td>'.$formProduct->selectWarehouses('', 'warehouse_src', 'warehouseopen', 1, 0, 0, $empty_label, 0, 0, array(), 'minwidth200', $exclude, 1).'</td>';
print '<td>'.$formProduct->selectWarehouses('', 'warehouse_dest', 'warehouseopen', 1, 0, 0, $empty_label, 0, 0, array(), 'minwidth200', '', 1).'</td>';
print '<td class="nowrap" align="center"><input type="submit" value="'.$langs->trans('Save').'" class="butAction" /></td>';
print '</tr>';

foreach($TObj as $obj){
	print '<tr class="oddeven">';
	$warehouseFrom = new Entrepot($db);
	$warehouseFrom->fetch($obj->fk_warehouse_from);
	$warehouseTo = new Entrepot($db);
	$warehouseTo->fetch($obj->fk_warehouse_to);
	print '<td>'.$warehouseFrom->getNomUrl(1).'</td>';
	print '<td>'.$warehouseTo->getNomUrl(1).'</td>';
	print '<td class="nowrap" align="center"> <a href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=delete&lineid='.$obj->rowid.'" >'.img_delete().'</a></td>';
	print '</tr>';
}

print '</table>';
print '</form>';

dol_fiche_end();

llxFooter();
$db->close();
exit;
