<?php
/**
 * CakePHP Migrations
 *
 * Copyright 2009 - 2010, Cake Development Corporation
 *                        1785 E. Sahara Avenue, Suite 490-423
 *                        Las Vegas, Nevada 89104
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright 2009 - 2010, Cake Development Corporation
 * @link      http://codaset.com/cakedc/migrations/
 * @package   plugns.migrations
 * @license   MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
App::uses('ConnectionManager', 'Model');

/**
 * Migration foreign key manager
 *
 * @package       migrations
 * @subpackage    migrations.libs
 */
class ForeignKeySchema {

/**
 * Connection used
 *
 * @var string
 */
	public $connection = 'default';
/**
 * Constructor
 *
 * @param array $options optional load object properties
 */
	public function __construct($options = array()) {
		if (!empty($options['connection'])) {
			$this->connection = $options['connection'];
		}
	}
	
	/**
	 * Generates the content for a php file that declares a $foreign_keys variable
	 * with the current foreign keys in the database
	 * @return string
	 */
	public function generateSnaphsot() {
		$db =& ConnectionManager::getDataSource($this->connection);
		$database = $db->config['database'];
		
		$sql = "SELECT fk.TABLE_NAME, kc.COLUMN_NAME, fk.REFERENCED_TABLE_NAME, kc.REFERENCED_COLUMN_NAME, fk.UPDATE_RULE, fk.DELETE_RULE
				FROM information_schema.REFERENTIAL_CONSTRAINTS as fk
				JOIN information_schema.KEY_COLUMN_USAGE kc
				ON fk.CONSTRAINT_SCHEMA = kc.CONSTRAINT_SCHEMA
				AND fk.TABLE_NAME = kc.TABLE_NAME
				AND fk.CONSTRAINT_NAME = kc.CONSTRAINT_NAME
				WHERE fk.CONSTRAINT_SCHEMA = '$database'";
		
		$fks = array();
		$result = $db->query($sql);
		foreach($result as $row){
			$fks[$row['fk']['TABLE_NAME']][$row['kc']['COLUMN_NAME']] = array(
				'table' => $row['fk']['REFERENCED_TABLE_NAME'],
				'column' => $row['kc']['REFERENCED_COLUMN_NAME'],
				'update' => $row['fk']['UPDATE_RULE'],
				'delete' => $row['fk']['DELETE_RULE']
			);
		}
		
		return $fks;
	}
	
	/**
	 * Generates the migration actions needed to go from the old FKs to the new FKs
	 * @param array $old
	 * @param array $new
	 * @return array 
	 */
	private function generateDiff($old, $new) {
		$drop = array();
		$add = array();
		
		foreach($old as $table => $cols){
			if(array_key_exists($table, $new)){
				foreach ($cols as $col => $fk) {
					if(!array_key_exists($col, $new[$table])){
						$drop[$table][] = $col;
					}
					else{
						$diff = array_diff_assoc($fk, $new[$table][$col]);
						if(!empty($diff)){
							$drop[$table][] = $col;
							$add[$table][$col] = $new[$table][$col];
						}
					}
				}
				
				foreach (array_diff(array_keys($new[$table]), array_keys($old[$table])) as $col) {
					$add[$table][$col] = $new[$table][$col];
				}
			}
			else{
				$drop[$table] = array_keys($cols);
			}
			
		}
		foreach (array_diff(array_keys($new), array_keys($old)) as $table) {
			$add[$table] = $new[$table];
		}
		
		return array('drop' => $drop, 'add' => $add);
	}
	
	/**
	 * Generates the foreign keys migrations in both directions
	 * @param array $old
	 * @param array $new
	 * @return array 
	 */
	public function generateMigration($old, $new) {
		return array(
			'up' => $this->generateDiff($old, $new),
			'down' => $this->generateDiff($new, $old)
		);
	}

}