<?php
/**
 * Manages database meta data and provides information about various columns and
 * table relationships. Utilizes a cache to boost performance.
 */
class Schema {

	/**
	 * Stores the database connection
	 * @var Database
	 */
	protected $db;

	/**
	 * Stores the cache instance
	 * @var Cache
	 */
	protected $cache;

	/**
	 * Stores the session instance
	 * @var Session
	 */
	protected $session;

	/**
	 * A list of the schemas to consider
	 * @var array
	 */
	protected $schemas = array('public', 'pricing_model');

	/**
	 * A list of column names to ignore
	 * @var array
	 */
	protected static $ignore = array('id', 'created_timestamp', 'modified_timestamp', 'modified_by', 'created_by');

	/**
	 * Creates an instance of the Schema manager
	 * @param Database $db
	 */
	function __construct(Injector $injector = null){

		if(is_null($injector)) $injector = Registry::get('injector');

		$this->db = $injector->db;
		$this->cache = $injector->cache;
		$this->session = $injector->session;
	}

	/**
	 * Synchronizes metadata with the codebase and public schema
	 */
	function sync(){

		//Sync codebase classes
		$this->sync_classes('classes/html/form/field/', 'field_classes', 'Field');
		$this->sync_classes('classes/html/form/validator/', 'validator_classes', 'Validator');

		//Sync database tables
		$tables = $this->db->getAll("
			SELECT
				t.table_name as name, t.table_schema as schema
			FROM
				information_schema.tables t
			WHERE
				t.table_schema IN ('public', 'pricing_model')
			    AND t.table_type = 'BASE TABLE'
		");
		$db_tables = $this->db->getAll("SELECT name, schema FROM meta.tables");

		//Insert any missing tables into the database meta schema
		$diff = Utils::arrayDiff($tables, $db_tables);
		foreach($diff as $table):
			$this->db->execute("INSERT INTO meta.tables (name, schema) VALUES (?, ?)", $table);
			$this->session->addSuccess("Table <b>".$table['schema'].".".$table['name']."</b> registered to the database.");
		endforeach;

		//Remove any deprecated tables from the database meta schema
		$diff = Utils::arrayDiff($db_tables, $tables);
		foreach($diff as $table):
			$this->db->execute("DELETE FROM meta.tables WHERE name = ? AND schema = ?", $table);
			$this->session->addSuccess("Table <b>".$table['schema'].".".$table['name']."</b> unregistered from the database.");
		endforeach;

		//Sync database fields
		$fields = $this->db->getKeyPair("
			SELECT
				m.name||'.'||c.column_name as col,
				m.id as table_id,
				c.table_name,
				c.column_name as name,
				c.ordinal_position as ordering,
				CASE WHEN c.is_nullable = 'NO' THEN TRUE ELSE FALSE END AS is_required,
				c.data_type
			FROM
				information_schema.columns c
			    JOIN information_schema.tables t USING (table_schema, table_name)
			    JOIN meta.tables m ON m.name = c.table_name
			WHERE
				c.table_schema IN ('".join("', '", $this->schemas) . "')
			    AND c.column_name NOT IN ('".join("', '", self::$ignore) . "')
			    AND t.table_type = 'BASE TABLE'
			ORDER BY
				1, 3
		");
		$db_fields = $this->db->getKeyPair("
			SELECT
				m.name||'.'||f.name as col,
				m.name as table_name,
				m.schema as table_schema,
				f.*
			FROM
				meta.fields f
				JOIN meta.tables m ON m.id = f.table_id
			ORDER BY
				f.table_id, f.ordering
		");

		//Insert any missing fields into the meta schema
		foreach($fields as $id => $field):
			if(array_key_exists($id, $db_fields)):
				//Field already exists
				unset($db_fields[$id]);
			else:
				//Field is new
				$row = Utils::applyWhitelist($field, array('table_id', 'name', 'ordering', 'is_required', 'data_type'));
				$this->db->autoExecute("meta.fields", array('mode' => 'INSERT'), $row);
				$this->session->addSuccess("Field <b>" . $id . "</b> registered to the database.");
			endif;
		endforeach;

		//Remove any deprecated fields from the meta schema
		foreach($db_fields as $field):
			$this->db->execute("DELETE FROM meta.fields WHERE id = ?", array($field['id']));
			$this->session->addSuccess("Field <b>" . $field['table_name'] . '.' . $field['name'] . '</b> unregistered from the database.');
		endforeach;

		//Update any foreign key references
		$this->db->execute("
			UPDATE
				meta.fields
			SET
				fk_table_id = ft.id
			FROM
				meta.tables t
			    JOIN meta.fields f ON f.table_id = t.id
			    LEFT JOIN meta.foreign_keys_view fk
			    	ON fk.table_schema = t.schema
			        AND fk.table_name = t.name
			        AND fk.column_name = f.name
			    LEFT JOIN meta.tables ft
			    	ON
			        	ft.name = fk.foreign_table_name
			            AND ft.schema = fk.foreign_table_schema
			WHERE
				f.id = meta.fields.id
		");

		//Potentially invalidates the cache
		$this->recache();

	}

	/**
	 * Syncs the available (non-abstract) classes from the filesystem with the database
	 * @param string $path The path to the class folder
	 * @param string $table The table name to sync classes to
	 * @param string $name A human readable name
	 */
	protected function sync_classes($path, $table, $name){

		//Retrieve a list of codebase classes
		$classes = array();

		$it = new DirectoryIterator($path);
		foreach($it as $file):
			$filename = $file->getFileName();
			if(substr($filename, 0, 1) == '.') continue;
			$class = $this->get_class_name($path . $filename);
			if($class) $classes[] = $class;
		endforeach;

		//Retrieve a list of classes listed in the DB
		$db_classes = $this->db->getCol("SELECT name FROM meta.".$table);

		//Insert any remaining into the database
		$diff = array_diff($classes, $db_classes);
		foreach($diff as $class):
			$this->db->execute("INSERT INTO meta.".$table." (name) VALUES (?)", array($class));
			$this->session->addSuccess($name . " class <b>" . $class . "</b> registered to the database.");
		endforeach;

		//Remove any deprecated classes
		$diff = array_diff($db_classes, $classes);
		foreach($diff as $class):
			try{
				$this->db->execute("DELETE FROM meta.".$table." WHERE name = ?", array($class));
				$this->session->addSuccess($name . " class <b>" . $class . "</b> unregistered from the database.");
			}catch(Exception $e){
				$this->session->addError("Attempt to unregister ".$name." class <b>".$class."</b> failed due to dependencies.");
			}
		endforeach;

	}

	/**
	 * Returns the name of the first non-abstract class found in the file specified
	 * @param string $filename
	 */
	protected function get_class_name($filename){
		$tokens = token_get_all(file_get_contents($filename));
		$class_token = false;
		$abstract = false;
		foreach($tokens as $token):
			if(is_array($token)):
				if($token[0] == T_ABSTRACT):
					$abstract = true;
				elseif($token[0] == T_CLASS):
					$class_token = !$abstract;
					$abstract = false;
				elseif($class_token && $token[0] == T_STRING):
					return $token[1];
				endif;
			endif;
		endforeach;
	}

	/**
	 * Flushes the cache when a change to metadata has been detected.
	 * In other words, when a success message is found for this session.
	 */
	function recache(){
		if($this->session->getMessages(Session::MSG_SUCCESS)):
			$this->session->addInfo("Cache has been flushed due to metadata changes.");
			$this->cache->flush();
		endif;
	}

	/**
	 * Returns the meta data for the specified fully qualified table name from the database
	 * @param Database $db Instance of current database connection
	 * @param string $fqtn The fully qualified table name from which to retrieve information
	 * @return Returns array of the field meta data, if the FQTN is found in the database. Else null is returned.
	 */
	static function getMetaData(Injector $injector, $fqtn) {

		//separate the table and field names from the fqtn
		$schema = 'public';
		if(substr_count($fqtn, ".") == 2):
			list($schema, $fqtn) = explode(".", $fqtn, 2);
		endif;

		if(substr_count($fqtn, ".") != 1) return;
		list($table, $field) = explode(".", $fqtn);

		//get the meta data for the table name provided in the $fqtn
		$res = self::getTableData($injector, $table, $schema);

		//return the meta data for the field provided in the fqtn
		return isset($res[$field]) ? $res[$field] : null;

	}

	/**
	 * Returns the meta data for the specified table name from the metadata schema
	 * @param Injector $injector
	 * @param string $table
	 */
	static function getTableData(Injector $injector, $table, $schema){
		$db = $injector->db;
		if(!$db->usesMetaSchema()) return array();

		$res = array();

		//Automatically pull the id column using the table alias
		$res['id'] = array(
			'is_visible' => true,
			'schema_name' => $schema,
			'table_name' => $table,
			'field_name' => 'id',
			'field_class' => 'Span',
			'is_required' => false,
			'label' => $schema . "." . $table . ".id",
			'ordering' => -1,
			'validators' => '',
			'tooltip' => ''
		);

		$res = array_merge($res, $db->getCached('
			SELECT v.field_name as k, v.*
			FROM metadata_view v
			WHERE v.table_name = ? AND v.schema_name = ?
			ORDER BY v.ordering
			', array($table, $schema), 0, 'getKeyPair')
		);

		//Add created and modified field definitions
		$created = array(
			'is_visible' => true,
			'schema_name' => $schema,
			'table_name' => $table,
			'field_name' => 'created_timestamp',
			'field_class' => 'Timestamp',
			'is_required' => false,
			'label' => 'Date Created',
			'ordering' => 100,
			'validators' => '',
			'tooltip' => ''
		);
		$mod = $created;
		$mod['field_name'] = 'modified_timestamp';
		$mod['label'] = 'Date Modified';
		$mod['ordering'] = 101;

		$res['created_timestamp'] = $created;
		$res['modified_timestamp'] = $mod;

		$res['modified_by'] = array(
			'is_visible' => true,
			'schema_name' => $schema,
			'table_name' => $table,
			'field_name' => 'modified_by',
			'field_class' => 'Select',
			'is_required' => false,
			'label' => 'Modified By',
			'ordering' => 102,
			'validators' => '',
			'tooltip' => '',
			'fk_table_name' => 'users'
		);

		$res['created_by'] = array(
			'is_visible' => true,
			'schema_name' => $schema,
			'table_name' => $table,
			'field_name' => 'created_by',
			'field_class' => 'Select',
			'is_required' => false,
			'label' => 'Created By',
			'ordering' => 103,
			'validators' => '',
			'tooltip' => '',
			'fk_table_name' => 'users'
		);

		return $res;

	}

	/**
	 * Returns a list of column names that should automatically be ignored
	 * for * expansion purposes.
	 */
	static function getIgnoredColumns(){
		return self::$ignore;
	}

}