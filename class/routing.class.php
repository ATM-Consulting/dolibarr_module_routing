<?php

class TRouting extends TObjetStd {
	function __construct() {
		global $langs,$db;
        
        parent::set_table(MAIN_DB_PREFIX.'routing');
        
        parent::add_champs('trigger_code,trigger_code_reverse', array('index'=>true, 'type'=>'string', 'length'=>50));
        parent::add_champs('fk_warehouse_from,fk_warehouse_to', array('index'=>true, 'type'=>'integer'));
        parent::add_champs('check_old', array( 'type'=>'integer'));
        
        parent::add_champs('message_condition,message_code', array('type'=>'text'));
        
        parent::_init_vars('qty_field,fk_product_field,lines_field,product_type_field');
        parent::start();
        
		$this->qty_field = 'qty';
        $this->fk_product_field = 'fk_product';
		$this->lines_field = 'lines';
		$this->product_type_field = 'product_type';
    }
    
	function mouvement(&$PDOdb, &$object, $fk_product, $qty,$fk_warehouse_from, $fk_warehouse_to) {
		global $db, $user, $langs;
		
		dol_include_once('/product/stock/class/mouvementstock.class.php');
		dol_include_once('/product/class/product.class.php');
		
		/*var_dump($fk_product, $qty,$fk_warehouse_from, $fk_warehouse_to);
		exit;
			*/
		$stock=new MouvementStock($db);
		
		$label = '';
		if(method_exists($object, 'getNomUrl')) {
			$label.=$object->getNomUrl(1);
		}
		
		if(!empty($conf->global->ROUTING_INFO_ALERT)) {
			$product = new Product($db);
			$product->fetch($fk_product);
			$msg = $product->getNomUrl(0).' '.$product->label.' '.$langs->trans('MoveFrom').' '.$wh_from_label.' '.$langs->trans('MoveTo').' '.$wh_to_label;
			setEventMessage($msg);			
		}
		
		$stock->origin = $object;
		$stock->reception($user, $fk_product, $fk_warehouse_to, $qty,0,$label);
		$stock->livraison($user, $fk_product, $fk_warehouse_from, $qty,0,$label);
				
	}
	
    static function getAll(&$PDOdb) {
        
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."routing WHERE 1 ";
        
        $sql.=" ORDER BY date_cre ";
        
        $Tab = $PDOdb->ExecuteAsArray($sql);
        
        $TRes = array();
        foreach($Tab as $row) {
            
            $r=new TRouting;
            $r->load($PDOdb, $row->rowid);
            
            $TRes[] = $r;
        }
        
        return $TRes ;
    }
    
	private function routeLines(&$object, $sens = 1) {
		
		if(!empty($route->message_condition)) {
            if(!eval('return ('.$route->message_condition.')')) return false; //ne répond pas au test 
        }
		if(!empty($row->message_code)) {
            eval($row->message_code);
        }
        
		$lines_field = $this->lines_field;
		
		if($lines_field ==='this') {
			$lines_field = 'lines';
			$object->lines=array(); 
			$object->lines[] = $object;  
		}
		else if(empty($object->{$lines_field})) {
			return false;
		}
		
		foreach($object->{$lines_field} as &$line) {
			
			if($line->{$this->product_type_field} == 0) {
				
				$qty = $line->{$this->qty_field};
				if($this->check_old) {
					
					$old = null;
					if(!empty($line->oldline)) $old = &$line->oldline;
					else if(!empty($line->oldline)) $old = &$line->old;
					
					if(!is_null($old) && !empty($old->{$this->qty_field})) {
						$old_qty = $old->{$this->qty_field};
						$qty = $qty - $old_qty;
					}
					
				}
				
				if($sens>0) {
					$this->mouvement($PDOdb,$object, $line->{$this->fk_product_field}, $qty ,$this->fk_warehouse_from,$this->fk_warehouse_to);
				}
				else {
					$this->mouvement($PDOdb,$object, $line->{$this->fk_product_field}, $qty,$this->fk_warehouse_to,$this->fk_warehouse_from);
				}		
				
			}
			
		}
		
		
	}
	
	static function route($action ,&$object) {
		$PDOdb = new TPDOdb;
		
		$sql = "SELECT rowid
                FROM ".MAIN_DB_PREFIX."routing
                WHERE trigger_code='".$action."'";
        $Tab = $PDOdb->ExecuteAsArray($sql);
                
        foreach($Tab as $row) {
        	$route = new TRouting;
			$route->load($PDOdb, $row->rowid);
			
			$route->routeLines($object);
		}
		
		// mvt inverse pour annulation
		$sql = "SELECT rowid
                FROM ".MAIN_DB_PREFIX."routing
                WHERE trigger_code_reverse='".$action."'";
        $Tab = $PDOdb->ExecuteAsArray($sql);
                
        foreach($Tab as $row) {
        	$route = new TRouting;
			$route->load($PDOdb, $row->rowid);
		
			$route->routeLines($object,-1);
			
		}
			
	}
	
}

class TRoutingStock extends TRouting{
	function __construct() {
		global $langs,$db;
        
        parent::set_table(MAIN_DB_PREFIX.'routing_stock');
        
        parent::add_champs('fk_warehouse_from,fk_warehouse_to, fk_soc', array('index'=>true, 'type'=>'integer'));
        parent::start();

    }
}