<?php
/**
 * Database abstraction and query result classes (requires PHP 5.3)
 *
 * This class is built primarily for PostgreSQL 8.4+
 * Compatibility with other database types is not guaranteed
 *
 * @author Kenaniah Cerny <kenaniah@gmail.com> https://github.com/kenaniah/insight
 * @license http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright Copyright (c) 2009, Kenaniah Cerny
 * @requires Cache
 */
class Database extends \PDO {

	/**
	 * Whether or not statement-level debugging is enabled on this connection
	 * @var boolean
	 */
	public $debug = false;

	/**
	 * Name of the relation in which files may be stored
	 * Example PostgreSQL schema:
	 *
	 * CREATE TABLE public.files (
	 *	  id SERIAL PRIMARY KEY,
	 *	  name TEXT NOT NULL,
	 *	  mime TEXT NOT NULL,
	 *	  file BYTEA NOT NULL,
	 *	);
	 *
	 * @var string
	 */
	protected $files_table = "files";

	/**
	 * Sets the default fetch mode for queries
	 * @var integer
	 */
	protected $default_fetch_mode = PDO::FETCH_ASSOC;

	/**
	 * Array that tracks database connection instances
	 * @internal
	 * @var array (keys: name of instance, values: database connection)
	 */
	private static $connections = array();

	/**
	 * Internal array that stores connection configuration information
	 * Format:
	 * array(
	 * 	'connection1' => array(
	 * 		'driver' => 'pgsql'
	 * 		'host' => 'localhost' //Optional. Attempts to use the UDS when absent
	 * 		'user' => 'username'
	 * 		'pass' => 'password'
	 * 		'db' => 'db_name',
	 * 		'cache' => new Cache //Optional. Accepts an instance of the Cache class
	 * 	),
	 *  'connection2' => ...
	 * )
	 * @see $this->setConfig()
	 * @var array
	 */
	private static $config = array();

	/**
	 * Holds the resulting instance of DatabaseStatement
	 * @var DatabaseStatement
	 */
	private $stmt;

	/**
	 * Tracks whether or not an open database transaction is still in good standing
	 * @var boolean
	 */
	private $good_trans = null;

	/**
	 * Tracks the virtual transaction nesting level
	 * @var integer
	 */
	private $nested_transactions = 0;

	/**
	 * Tracks the original error returned by the database driver in a transaction
	 * @var PDOException
	 */
	private $transaction_error = null;

	/**
	 * Tracks the name of this database connection
	 * @see self::getInstance()
	 * @var string
	 */
	private $instance_name = null;

	/**
	 * Determines whether or not errors are thrown immediately in a transaction
	 * When true, errors are thrown as soon as they are encountered
	 * When false, errors are suppressed until the end of the transaction
	 * @see $this->commit()
	 * @see $this->completeTrans()
	 */
	private $throw_errors = false;

	/**
	 * Instance of the cache implementation for this connection
	 * If not provided, caching is disabled
	 * @var Cache
	 */
	private $cache = null;

	/**
	 * Tracks the number of queries processed by this connection
	 * @var integer
	 */
	protected $num_queries = 0;

	/**
	 * Tracks the total number of queries processed by all connections
	 * @var integer
	 */
	protected static $total_queries = 0;

	/**
	 * Sets the connection configuration array. This must be set before using getInstance()
	 * @param array $config
	 * @see self::$config
	 */
	static function setConfig(array $config){
		self::$config = $config;
	}

	/**
	 * PDO Constructor
	 * @param $dsn
	 * @param $username
	 * @param $password
	 * @param array $params Connection parameters
	 * @param Cache $cache The caching instance to use for this connection
	 */
	function __construct($dsn, $username, $password, $params = array(), Cache $cache = null) {

		//Supply any additional connection parameters
		switch(substr($dsn, 0, 5)):
			case 'mysql':
				$params[PDO::MYSQL_ATTR_FOUND_ROWS] = true;
				break;
		endswitch;

		//Initialize the connection
		parent::__construct($dsn, $username, $password, $params);

		//Change the error mode to always throw exceptions
		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		//Set the cache
		$this->cache = $cache ?: new Cache;

	}

	/**
	 * Returns a database instance using lazy instantiation
	 * @param string $name a database connection name (to be read from config)
	 */
	static function getInstance($name = 'main'){

		//Attempt to return an existing connection
		if(array_key_exists($name, self::$connections)):
			return self::$connections[$name];
		endif;

		//Attempt to create a new connection
		$config = Registry::get('config');
		if(!array_key_exists($name, self::$config)):
			user_error("No configuration found for connection: " . $name);
		endif;

		//Grab the connection information
		$conn = self::$config[$name];

		//Check if host is defined (otherwise use the Unix Domain Socket)
		$host = "";
		if(isset($conn['host']) && $conn['host']):
			$host = ";host=" . $conn['host'];
		endif;

		//Instantiate this connection
		$db = new Database($conn['driver'].":dbname=".$conn['name'].$host, $conn['user'], $conn['pass']);
		$db->instance_name = $name;

		//Save to the connection pool
		self::$connections[$name] = $db;

		//Return the Database instance
		return $db;

	}

	/**
	 * Returns the name of this database connection instance
	 */
	public function getInstanceName(){
		return $this->instance_name;
	}

	/**
	 * Sets the cache instance to be used by getCached
	 * @see $this->getCached()
	 */
	public function setCacheInstance(Cache $cache){
		$this->cache = $cache;
	}

	/**
	 * Returns the cache instance
	 */
	public function getCacheInstance(){
		return $this->cache;
	}

	/**
	 * Prepares an SQL statement
	 * @param string $sql
	 * @see PDO::prepare()
	 */
	function prepare($sql) {

		$stmt = parent::prepare($sql, array(
			PDO::ATTR_STATEMENT_CLASS => array(__NAMESPACE__.'\DatabaseStatement'),
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
		);

		if(!$stmt):

			//If the statement failed to compile, log it in the PHP error log
			error_log($this->errormsg());

			//Output the statement and the error message if debugging is enabled
			if($this->debug):
				var_dump("Statement: \n" . $sql);
				var_dump($this->errormsg());
			endif;

		else:

			//Set the default fetch mode for this statement
			$stmt->setFetchMode($this->default_fetch_mode);

		endif;

		//Return the statement (or false if not compiled)
		return $stmt;

	}

	/**
	 * Prepares and executes an SQL statement with the parameters provided
	 * @param string $sql
	 * @param array $params
	 */
	function execute($sql, $params = array()) {

		//Track the number of queries attempted
		$this->num_queries++;
		self::$total_queries++;

		//Output the statement and any parameters if debugging is enabled
		if($this->debug):
			var_dump("Statement:\n".$sql."\nParams: ".$this->fmt($params));
		endif;

		//Attempt to execute the statement
		try {

			$stmt = $this->prepare($sql);
			$val = $stmt->execute((array) $params);

			//Did we receive an error from the database?
			if($stmt->errorCode() != '00000'){
				if($this->debug) var_dump($stmt->errormsg());
				error_log($stmt->errormsg() . "\n" . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true));
			}

			if(!$val) return false;

		} catch (PDOException $e){

			//Did we receive an error from the database?
			if($this->debug) var_dump($this->errormsg());
			error_log($this->errormsg() . "\n" . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true));

			//If inside a transaction, record the error and mark the transaction as failed
			//The error will be thrown if requested
			if($this->nested_transactions){
				if(!$this->transaction_error) $this->transaction_error = $e;
				$this->failTrans();
			}
			else throw $e;

			//Throw the error if requested (and not thrown yet)
			if($this->throw_errors) throw $e;

		}

		//Cache the DatabaseStatement internally
		$this->stmt = $stmt;

		//Return the DatabaseStatement
		return $stmt;

	}

	/**
	 * Saves a file to the database and returns the file id
	 * @param array $file An uploaded file reference (compatible with $_FILES['<filename>'])
	 * @param string $files_table The qualified name of the files table. Defaults to $this->files_table.
	 */
	function saveFile($file, $files_table = null){

		$sql = "INSERT INTO ".($files_table ?: $this->files_table)." (name, mime, file)
				VALUES (?, ?, ?)
				RETURNING id";

		$stmt = $this->prepare($sql);
		$stmt->bindParam(1, $file['name'], PDO::PARAM_STR);
		$stmt->bindParam(2, $file['type'], PDO::PARAM_STR);
		$stmt->bindParam(3, fopen($file['tmp_name'], 'rb'), PDO::PARAM_LOB);

		$stmt->execute();

		return $stmt->fetch(PDO::FETCH_COLUMN);

	}

	/**
	 * Enables / disables statement debugging for this instance
	 * @param $boolean Whether or not to output debugging information
	 */
	function debug($boolean){
		$this->debug = (bool) $boolean;
		return $this;
	}

	/**
	 * Enables / disables throwing errors when they are encountered
	 * @param boolean $boolean Whether or not to throw exceptions on errors
	 */
	function throwErrors($boolean){
		$this->throw_errors = (bool) $boolean;
	}

	/**
	  * Caches the full output of a query by serializing the query and its params
	  * Should only be used with SELECT queries on small, generally static, datasets.
	  * @param $sql
	  * @param $params
	  * @param $expiry Number of seconds before cache expires
	  * @param $mode Optional mode for which to run the query
	  */
	function getCached($sql, $params = array(), $expiry = null, $mode = 'getAll'){

		//Define the cache key
		$cache_name = "sql-" . md5($sql . serialize((array) $params));

		//Define the cache
		$db = $this;
		$this->cache->set(
			$cache_name,
			function() use ($db, $mode, $sql, $params){
				return $db->$mode($sql, $params);
			},
			$expiry
		);

		//Return the value
		return $this->cache->$cache_name;

	}

	/**
	 * Returns the value of the first column of the first row
	 * of the database result.
	 * @param $sql
	 * @param $params
	 */
	function getOne($sql, $params = array()){
		$stmt = $this->execute($sql, $params);
		return $stmt ? $stmt->getOne() : false;
	}

	/**
	 * Fetches a single column (the first column) of a result set
	 * @param $sql
	 * @param $params
	 */
	function getCol($sql, $params = array()){
		$stmt = $this->execute($sql, $params);
		return $stmt ? $stmt->getCol() : false;
	}

	/**
	 * Fetches rows in associative array format
	 * @param $sql
	 * @param $params
	 */
	function getAssoc($sql, $params = array()){
		$stmt = $this->execute($sql, $params);
		return $stmt ? $stmt->getAssoc() : false;
	}

	/**
	 * Fetches rows in array format with columns
	 * indexed by ordinal position
	 * @param $sql
	 * @param $params
	 */
	function getArray($sql, $params = array()){
		$stmt = $this->execute($sql, $params);
		return $stmt ? $stmt->getArray() : false;
	}

	/**
	 * Fetches all rows in associative array format
	 * @param $sql
	 * @param $params
	 */
	function getAll($sql, $params = array()){
		return $this->getAssoc($sql, $params);
	}

	/**
	 * Fetches rows in array format where the first column
	 * is the key name and all other columns are values
	 * @param $sql
	 * @param $params
	 */
	function getKeyPair($sql, $params = array()){
		$stmt = $this->execute($sql, $params);
		return $stmt ? $stmt->getKeyPair() : false;
	}

	/**
	 * Fetches rows in multi-dimensional format where the first
	 * column is the key name and all other colums are grouped
	 * into associative arrays for each row
	 * @param $sql
	 * @param $params
	 */
	function getGroup($sql, $params = array()){
		$stmt = $this->execute($sql, $params);
		return $stmt ? $stmt->getGroup() : false;
	}

	/**
	 * Fetches only the first row and returns it as an
	 * associative array
	 * @param $sql
	 * @param $params
	 */
	function getRow($sql, $params = array()){
		$stmt = $this->execute($sql, $params);
		return $stmt ? $stmt->getRow() : false;
	}

	/**
	 * Internal function used for formatting parameters in debug output
	 * @param array $params
	 */
	private function fmt(array $params){
		$arr = array();
		foreach((array) $params as $k=>$v){
			if(is_null($v)) $v = "NULL";
			elseif(is_bool($v)) $v = $v ? "TRUE" : "FALSE";
			$arr[] = "[".$k."] => ".$v;
		}
		return "Array(".join(", ", $arr).")";
	}

	/**
	 * Returns the number of affected rows from an executed statement
	 */
	function affectedRows(){
		return $this->stmt ? $this->stmt->rowcount() : false;
	}

	/**
	 * Automated statement processing
	 *
	 * @param string $table The table name to operate on
	 * @param array $params See below:
	 * Params array takes the following fields:
	 *
	 *  - mode			INSERT, UPDATE, REPLACE (Update or Insert), or NEW (Insert if not exists)
	 *
	 *  - where			Can be a string or key-value set. Not used on INSERTs
	 *  				If key-value set and numerically indexed, uses values from data
	 *  				If key-value and keys are named, uses its own values
	 *
	 *  - params		An array of param values for the where clause
	 *
	 *  - returning		Optional string defining what to return from query.
	 *  				Uses PostgreSQL's RETURNING construct
	 *  - return_mode   Mode by which to return values from the query (getOne, getAll, getCol, etc.)
	 *
	 *  This method will return either a boolean indicating success, an array
	 *  containing the data requested by returning, or a boolean FALSE indicating
	 *  a failed query.
	 *
	 *  @param array $data The data (row) to be sent to the table
	 */
	function autoExecute($table, $params, $data = array()){

		$fields = array(); //Temp array for field names
		$values = array(); //Temp array for field values
		$set = array(); //Temp array for update sets
		$ins = array(); //Insert value arguments

		$params['table'] = $table;
		$params['data'] = $data;
		$params['mode'] = isset($params['mode']) ? $params['mode'] : null;
		$params['where'] = isset($params['where']) ? $params['where'] : null;
		$params['params'] = isset($params['params']) ? (array) $params['params'] : array();
		$params['returning'] = isset($params['returning']) ? $params['returning'] : null;
		$params['return_mode'] = isset($params['return_mode']) ? $params['return_mode'] : 'getRow';

		//MySQL driver does not allow anything but execute() to be called on prepared statements
		if($this->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql'):
			$params['return_mode'] = 'execute';
		endif;

		//Parse the data set and prepare it for different query types
		foreach((array) $params['data'] as $field => $val):

			$fields[] = $field;
			$values[] = $val;
			$ins[] = "?";
			$set[] = $field . " = ?";

		endforeach;

		//Check for and convert the array/object version of the where clause param
		if(is_object($params['where']) || is_array($params['where'])):

			$clause = array();
			$params['params'] = array(); //Reset the parameters list

			foreach($params['where'] as $key => $val):

				if(is_numeric($key)):
					//Numerically indexed elements use their values as field names
					//and values from the data array as param values
					$field = $val;
					$params['params'][] = $params['data'][$val];
				else:
					//Named elements use their own names and values
					$field = $key;
					$params['params'][] = $val;
				endif;

				$clause[] = $field . " = ?";

			endforeach;

			$params['where'] = join(" AND ", $clause);

		endif;

		//Figure out what type of query we want to run
		$mode = strtoupper($params['mode']);
		switch($mode):
			case 'NEW':
			case 'INSERT':

				//Build the insert query
				if(count($fields)):
					$sql =  "INSERT INTO " . $params['table']
							. " (" . join(", ", $fields) . ")"
					 		. " SELECT " . join(", ", $ins);
				else:
					$sql =  "INSERT INTO " . $params['table']
							. " DEFAULT VALUES";
				endif;

				//Do we need to add a conditional check?
				if($mode == "NEW" && count($fields)):
					$sql .= " WHERE NOT EXISTS ("
							. " SELECT 1 FROM " . $params['table']
							. " WHERE " . $params['where']
							. " )";
					//Add in where clause params
					$values = array_merge($values, $params['params']);
				endif;

				//Do we need to add a returning clause?
				if(isset($params['returning']) && $params['returning']):
					$sql .= " RETURNING " . $params['returning'];
				endif;

				$result = $this->$params['return_mode']($sql, $values);

				//Return our result
				if($params['returning']):
					return $result;
				else:
					return $result !== false;
				endif;

				break;
			case 'UPDATE':

				if(!count($fields)) return false;

				//Build the update query
				$sql =  "UPDATE " . $params['table']
						. " SET " . join(", ", $set)
						. " WHERE " . $params['where'];

				//Do we need to add a returning clause?
				if(isset($params['returning']) && $params['returning']):
					$sql .= " RETURNING " . $params['returning'];
				endif;

				//Add in where clause params
				$values = array_merge($values, $params['params']);

				$result = $this->$params['return_mode']($sql, $values);

				//Return our result
				if($params['returning']):
					return $result;
				else:
					return $result !== false;
				endif;

				break;
			case 'REPLACE': //UPDATE or INSERT

				//Attempt an UPDATE
				$params['mode'] = "UPDATE";
				$result = $this->autoExecute($params['table'], $params, $params['data']);

				//Attempt an INSERT if UPDATE didn't match anything
				if($this->affectedRows() === 0):
					$params['mode'] = "INSERT";
					$result = $this->autoExecute($params['table'], $params, $params['data']);
				endif;

				return $result;

				break;
			case 'DELETE':

				//Don't run if we don't have a where clause
				if(!$params['where']) return false;

				//Build the delete query
				$sql =  "DELETE FROM " . $params['table']
						. " WHERE " . $params['where'];

				//Do we need to add a returning clause?
				if($params['returning']):
					$sql .= " RETURNING " . $params['returning'];
				endif;

				$result = $this->$params['return_mode']($sql, $params['params']);

				//Return our result
				if($params['returning']):
					return $result;
				else:
					return $result !== false;
				endif;

				break;
			default:
				user_error('AutoExecute called incorrectly', E_USER_ERROR);
				break;
		endswitch;

	}

	/**
	 * @see $this->startTrans()
	 * @see PDO::beginTransaction()
	 */
	function beginTransaction(){
		$this->startTrans();
	}

	/**
	 * Starts a smart transaction handler. Transaction nesting is emulated
	 * by this class.
	 */
	function startTrans(){

		//Increment the virtual nested transaction level
		$this->nested_transactions++;
		if($this->debug):
			var_dump("Starting transaction. Nesting level: " . $this->nested_transactions);
		endif;

		//Do we need to begin an actual transaction?
		if($this->nested_transactions === 1):
			parent::beginTransaction();
			$this->good_trans = true;
		endif;

	}

	/**
	 * Returns TRUE if the transaction will attempt to commit, and
	 * FALSE if the transaction will be rolled back upon completion.
	 */
	function isGoodTrans(){
		return $this->good_trans;
	}

	/**
	 * Marks a transaction as failed. Transaction will be rolled back
	 * upon completion.
	 */
	function failTrans(){

		if($this->nested_transactions) $this->good_trans = false;

	}

	/**
	 * @see $this->rollbackTrans()
	 */
	function rollback(){
		$this->rollbackTrans();
	}

	/**
	 * Rolls back the entire transaction and completes the current nested
	 * transaction. If there are no more nested transactions, an actual
	 * rollback is issued to the database.
	 */
	function rollbackTrans(){

		//Check to make sure we actually have a transaction open
		if($this->nested_transactions):
			$this->nested_transactions--;

			if($this->debug):
				var_dump("Rollback requested. New nesting level: " . $this->nested_transactions);
			endif;

			//Mark the transaction as failed
			$this->good_trans = false;

			//If this was the last transaction, issue the rollback
			if($this->nested_transactions === 0):
				$this->good_trans = null;
				parent::rollback();
				if($this->debug):
					var_dump("Transaction rolled back.");
				endif;
				$this->transaction_error = null;
			endif;
		endif;

	}

	/**
	 * Clears the nested transactions stack and issues a rollback to the database.
	 */
	function fullRollback(){
		while($this->nested_transactions) $this->rollbackTrans();
	}

	/**
	 * Returns the number of nested transactions:
	 * 0 - There is no transaction in progress
	 * 1 - There is one transaction pending
	 * >1 - There are nested (virtual) transactions in progress
	 */
	function pending_trans(){
		return $this->nested_transactions;
	}

	/**
	 * Commits the transaction or throws the last exception on failure
	 */
	function commit(){
		$error = $this->transaction_error;
		$ok = $this->completeTrans();
		if(!$ok) throw $error;
		return true;
	}

	/**
	 * Completes the current transaction
	 * Issues a commit or rollback to the database on the last transaction
	 * @return boolean indiciateing success
	 */
	function completeTrans(){

		//Ensure that we have at least one transaction pending
		if(!$this->nested_transactions) return;

		//Do we actually need to attempt to commit the transaction?
		if($this->nested_transactions === 1):

			//Check if transaction was marked for failure or attempt to commit it
			if(!$this->good_trans || !parent::commit()):

				if($this->debug):
					var_dump("Transaction failed: " . $this->errormsg());
				endif;

				//Roll back the failed transaction
				$this->rollbackTrans();
				return false;

			endif;

			//Transaction was committed successfully
			$this->nested_transactions--;
			$this->good_trans = null;

			if($this->debug):
				var_dump("Transaction committed.");
			endif;

			return true;

		else:

			//Don't take action just yet as we are still nested
			$this->nested_transactions--;
			if($this->debug):
				var_dump("Virtual commit. New nesting level: " . $this->nested_transactions);
			endif;

		endif;

		//Return the pending transaction status
		return $this->good_trans;

	}

	/**
	 * Returns the text of the most recently encountered error
	 */
	function errormsg(){

		//If an error for the transaction is defined, return that
		if($this->transaction_error):
			return $this->transaction_error->getMessage();
		endif;

		//Return the error message from the driver
		$msg = $this->errorInfo();
		return $msg[2];

	}

	/**
	 * Returns the number of queries processed by this connection
	 */
	function getNumQueries(){
		return $this->num_queries;
	}

	/**
	 * Returns the total number of queries processed by all connections
	 */
	static function getTotalQueries(){
		return self::$total_queries;
	}

}

/**
 * This class generates the objects that are returned from statements
 * executed using the driver above.
 */
class DatabaseStatement extends \PDOStatement implements \Countable {

	/**
	 * Binds passed parameters according to their PHP type and executes
	 * the prepared statement
	 * @see PDOStatement::execute()
	 */
	function execute($params = array()) {
		$i = 1;
		foreach($params as $k => $v):
			$mode = PDO::PARAM_STR;
			if(is_null($v)) $mode = PDO::PARAM_NULL;
			elseif(is_bool($v)) $mode = PDO::PARAM_BOOL;
			elseif(is_resource($v)) $mode = PDO::PARAM_LOB;
			elseif(preg_match('/^\d*$/', $v) == 1) $mode = PDO::PARAM_INT;
			$this->bindParam($i, $params[$k], $mode);
			$i++;
		endforeach;
		$ok = parent::execute();
		return $ok ? $this : false;
	}

	/**
	 * Returns the value of the first column of the first row
	 */
	function getOne() {
		return $this->fetchColumn(0);
	}

	/**
	 * Returns an array of values of the column found at $index
	 * position.
	 * @param $index Ordinal position of column to be returned
	 */
	function getCol($index=0) {
		return $this->fetchAll(PDO::FETCH_COLUMN, $index);
	}

	/**
	 * Returns all rows in numeric array format
	 */
	function getArray(){
		return $this->fetchAll(PDO::FETCH_NUM);
	}

	/**
	 * Returns all rows in associative array format
	 */
	function getAll(){
		return $this->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Returns all rows in associative array format
	 */
	function getAssoc() {
		return $this->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Returns rows in multi-dimensional format where the first
	 * column is the key name and all other colums are grouped
	 * into associative arrays for each row
	 */
	function getGroup() {
		return $this->fetchAll(PDO::FETCH_GROUP);
	}

	/**
	 * Returns a single row in associative format
	 */
	function getRow(){
		return $this->fetch(PDO::FETCH_ASSOC);
	}

	/**
	 * Fetches rows in array format where the first column
	 * is the key name and all other columns are values
	 */
	function getKeyPair(){
		//Emulate key pair support since PDO's FETCH_KEY_PAIR does something different
		$tmp = $this->fetchAll(PDO::FETCH_ASSOC);
		$arr = array();
		for($i = 0; $i < count($tmp); $i++){
			$arr[array_shift($tmp[$i])] = count($tmp[$i]) > 1 ? $tmp[$i] : array_shift($tmp[$i]);
		}
		return $arr;
	}

	/**
	 * Returns the number of rows returned by this statement
	 */
	function recordCount(){
		return $this->rowCount();
	}

	/**
	 * Returns the number of rows returned by this statement
	 * @see Countable::count()
	 */
	function count(){
		return $this->rowCount();
	}

	/**
	 * Returns the text of the most recently encountered error
	 */
	function errormsg(){
		$msg = $this->errorInfo();
		return $msg[2];
	}

}