<?php

/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		core/triggers/interface_99_modMyodule_routingtrigger.class.php
 * 	\ingroup	routing
 * 	\brief		Sample trigger
 * 	\remarks	You can create other triggers by copying this one
 * 				- File name should be either:
 * 					interface_99_modMymodule_Mytrigger.class.php
 * 					interface_99_all_Mytrigger.class.php
 * 				- The file must stay in core/triggers
 * 				- The class name must be InterfaceMytrigger
 * 				- The constructor method must be named InterfaceMytrigger
 * 				- The name property name must be Mytrigger
 */

/**
 * Trigger class
 */
class Interfaceroutingtrigger
{

	public $db;

	/**
	 * Constructor
	 *
	 * 	@param		DoliDB		$db		Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "demo";
		$this->description = "Triggers of this module are empty functions."
			."They have no effect."
			."They are provided for tutorial purpose only.";
		// 'development', 'experimental', 'dolibarr' or version
		$this->version = 'development';
		$this->picto = 'routing@routing';
	}

	/**
	 * Trigger name
	 *
	 * 	@return		string	Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * 	@return		string	Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}

	/**
	 * Trigger version
	 *
	 * 	@return		string	Version of trigger file
	 */
	public function getVersion()
	{
		global $langs;
		$langs->load("admin");

		if ($this->version == 'development')
		{
			return $langs->trans("Development");
		}
		elseif ($this->version == 'experimental')
			return $langs->trans("Experimental");
		elseif ($this->version == 'dolibarr')
			return DOL_VERSION;
		elseif ($this->version)
			return $this->version;
		else
		{
			return $langs->trans("Unknown");
		}
	}

	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "run_trigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * 	@param		string		$action		Event action code
	 * 	@param		Object		$object		Object
	 * 	@param		User		$user		Object user
	 * 	@param		Translate	$langs		Object langs
	 * 	@param		conf		$conf		Object conf
	 * 	@return		int						<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function run_trigger($action, $object, $user, $langs, $conf)
	{

		define('INC_FROM_DOLIBARR', true);
		dol_include_once('/routing/config.php');

		if(class_exists('TRouting')) TRouting::route($action, $object);

		if ($action == 'STOCK_MOVEMENT' || $action == 'SHIPPING_DELETE')
		{
			global $db, $TShipping;
			if (is_null($TShipping))
				$TShipping = array();

			dol_include_once('/product/class/product.class.php');
			dol_include_once('/routing/class/routing.class.php');
			dol_include_once('/product/stock/class/mouvementstock.class.php');
			dol_include_once('/expedition/class/expedition.class.php');

			if ($action != 'SHIPPING_DELETE') // Le mouvement de stock n'a pas d'origine au moment de la suppression donc obligé de passer par le trigger shipping delete =)
			{
				$stockMovement = new MouvementStock($db);
				$stockMovement->fetch($object->id);
			}
			$shipping = new Expedition($db);
			$routingStock = new TRoutingStock($db);
			$PDOdb = new TPDOdb;
			if (!empty($stockMovement->origintype == 'shipping' && !empty($stockMovement->fk_origin)))
			{
				$shipping->fetch($stockMovement->fk_origin);
				$TRoutingStock = $routingStock->LoadAllBy($PDOdb, array('fk_soc' => $shipping->socid));
			}

			if ($action == 'SHIPPING_DELETE')
			{
				$shipping = $object;
				$TRoutingStock = $routingStock->LoadAllBy($PDOdb, array('fk_soc' => $shipping->socid));
				$testOnShippingDelete = ((($shipping->statut == Expedition::STATUS_CLOSED || $shipping->statut == Expedition::STATUS_VALIDATED) && $conf->global->STOCK_CALCULATE_ON_SHIPMENT)||($shipping->statut == Expedition::STATUS_CLOSED && $conf->global->STOCK_CALCULATE_ON_SHIPMENT_CLOSE)); // on vérifie que le mouvement de stock a été fait avant de l'annuler
			}
			else
			{
				if ($TShipping[$shipping->id])
					return 0; //Pour pas passer n fois dans la boucle si plusieurs mvt de stock pour la meme exped
				$TShipping[$shipping->id] = true;
				$testOnShippingDelete = true;
			}
			

			if (!empty($TRoutingStock) && $shipping->id > 0 && $testOnShippingDelete)
			{

				$langs->load('routing@routing');
				$TWarehouse = array();
				foreach ($TRoutingStock as $routingStock)
				{
					$TWarehouse[$routingStock->fk_warehouse_from] = $routingStock;
				}
				
				$shipping->fetch_lines(); //Pour avoir le detail batch
				
				$warehouseFrom = new Entrepot($db);
				$warehouseDest = new Entrepot($db);
				foreach ($shipping->lines as $line)
				{
					if (array_key_exists($line->entrepot_id, $TWarehouse)) // Si l'entrepot est dans la liste de ceux qui font des transferts
					{
						$prod = new Product($db);
						$result = $prod->fetch($line->fk_product);

						$db->begin();

						$prod->load_stock('novirtual'); // Load array product->stock_warehouse
						// Define value of products moved
						$pricesrc = 0;
						if (isset($prod->pmp))
							$pricesrc = $prod->pmp;
						$pricedest = $pricesrc;
						
						$warehouseDest->fetch($TWarehouse[$line->entrepot_id]->fk_warehouse_to);
						$warehouseFrom->fetch($line->entrepot_id);
						$label = ($stockMovement->qty<0)?$langs->trans('TransfertStockFromTo',$warehouseFrom->ref,$warehouseDest->ref):$langs->trans('CancelTransfertStockFromTo',$warehouseFrom->ref,$warehouseDest->ref);
						if($action == 'SHIPPING_DELETE')$label = $langs->trans('CancelTransfertStockFromTo',$warehouseFrom->ref,$warehouseDest->ref);
						$srcwarehouseid = $line->entrepot_id;
						if ($prod->hasbatch())
						{
							foreach ($line->detail_batch as $dbatch)
							{
								$batch = $dbatch->batch;
								$eatby = $dbatch->eatby;
								$sellby = $dbatch->sellby;

								if (!$error)
								{
									$sensMvt = 1;
									if($action != 'SHIPPING_DELETE'){
										$sensMvt = ($stockMovement->qty<0)?0:1;
									}
									
									// Add stock
									$result2 = $prod->correct_stock_batch(
										$user, $TWarehouse[$line->entrepot_id]->fk_warehouse_to, $dbatch->dluo_qty, $sensMvt, $label, $pricedest, $eatby, $sellby, $batch
									);
									if ($result2 < 0)
										$error++;
								}
							}
						}
						else
						{
					
							if (!$error)
							{
								$sensMvt = 1;
								if ($action != 'SHIPPING_DELETE')
								{
									$sensMvt = ($stockMovement->qty < 0) ? 0 : 1;
								}
								// Add stock
								$result2 = $prod->correct_stock(
									$user, $TWarehouse[$line->entrepot_id]->fk_warehouse_to, $line->qty, $sensMvt, $label, $pricedest
								);
								if ($result2 < 0)
									$error++;
							}
						}


						if (!$error && $result2 >= 0)
						{
							$db->commit();
						}
						else
						{
							setEventMessages($prod->error, $prod->errors, 'errors');
							$db->rollback();
							$action = 'transfert';
						}
					}
				}
			}
		}
		return 0;
	}

}
