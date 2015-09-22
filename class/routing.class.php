<?php

class TRouting extends TObjetStd {
	function __construct() {
		global $langs,$db;
        
        parent::set_table(MAIN_DB_PREFIX.'routing');
        
        parent::add_champs('trigger_code,trigger_code_reverse', array('index'=>true, 'type'=>'string', 'length'=>50));
        parent::add_champs('fk_warehouse_from,fk_warehouse_to', array('index'=>true, 'type'=>'integer'));
        
        parent::add_champs('message_condition,message_code', array('type'=>'text'));
        
        parent::_init_vars('qty_field,fk_product_field,lines_field,product_type_field');
        parent::start();
        
		$this->qty_field = 'qty';
        $this->fk_product_field = 'fk_product';
		$this->lines_field = 'lines';
		$this->product_type_field = 'product_type';
    }
    
	function mouvement(&$PDOdb, $fk_product, $qty,$fk_warehouse_from, $fk_warehouse_to) {
		global $db, $user;
		
		dol_include_once('/product/stock/class/mouvementstock.class.php');
		
		/*var_dump($fk_product, $qty,$fk_warehouse_from, $fk_warehouse_to);
		exit;
			*/
		$stock=new MouvementStock($db);
		
		$stock->reception($user, $fk_product, $fk_warehouse_to, $qty);
		$stock->livraison($user, $fk_product, $fk_warehouse_from, $qty);
				
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
            if(!eval('return ('.$route->message_condition.')')) continue; //ne répond pas au test 
        }
		if(!empty($row->message_code)) {
            eval($row->message_code);
        }
        
		if(empty($object->{$this->lines_field})) return false;
		
		foreach($object->{$this->lines_field} as &$line) {
			
			if($line->{$this->product_type_field} == 0) {
				
				if($sens>0) {
					$this->mouvement($PDOdb, $line->{$this->fk_product_field}, $line->{$this->qty_field} ,$this->fk_warehouse_from,$this->fk_warehouse_to);
				}
				else {
					$this->mouvement($PDOdb, $line->{$this->fk_product_field}, $line->{$this->qty_field},$this->fk_warehouse_to,$this->fk_warehouse_from);
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
