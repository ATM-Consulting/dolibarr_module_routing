<?php

class TRouting extends TObjetStd {
	function __construct() {
		global $langs,$db;
        
        parent::set_table(MAIN_DB_PREFIX.'routing');
        
        parent::add_champs('trigger_code', array('index'=>true, 'type'=>'string', 'length'=>50));
        parent::add_champs('fk_warehouse_from,fk_warehouse_to', array('index'=>true, 'type'=>'integer'));
        
        parent::add_champs('message_condition,message_code', array('type'=>'text'));
        
        parent::_init_vars('qty_field');
        parent::start();
        
        
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
    
	static function route($action ,&$object) {
		
		
	}
	
}
