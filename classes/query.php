<?php
/**
 * Builds SQL queries using a fluent interface
 * and uses data definitions when available.
 *
 * Performs * expansion and reduces columns by alias (similar to the way SQL behaves)
 *
 * Available column meta parameters:
 * - is_visible		(boolean)	Whether or not to display this column in data sets
 * - field_name / table_name	When set together, use the meta settings of the field specified
 * - formatter		(Format)	Sets the instance to be used to format this field in data sets
 * - null 			(boolean)	Whether or not data can be null
 * - is_sortable 	(boolean)	Whether or not this column can be sorted on
 *
 * @author Kenaniah Cerny <kenaniah@gmail.com> https://github.com/kenaniah/insight
 * @license http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright Copyright (c) 2009, Kenaniah Cerny
 */
use HTML\Form\Field\Span;
use HTML\Form\Field\FormField;
use HTML\Form\Container\Container;
use HTML\Form\Container\DataSet;
class Query implements Countable {

	//Database driver
	protected $db;

	//Tracks field definitions grouped by table
	protected $definitions;

	//Tracks label joins
	protected $label_counter = 0;

	//Injector instance
	protected $injector;

	//Result set
	protected $res;

	//Dynamic sorting flag
	protected $sortable = false;

	//Pagination stuff
	protected $paginate = false;
	protected $total_results; //The total number of results returned
	protected $pages = 1; //The total number of pages

	//Query parameters
	protected $params = array();

	//Query parts
	protected $parts = array(
		'select' => array(),
		'from' => array(),
		'join' => array(),
		'where' => array(),
		'group' => array(),
		'order' => array(),
		'limit' => null,
		'offset' => null,
		'_labels' => array()
	);

	function __construct(Injector $injector = null){

		if(is_null($injector)) $injector = Registry::get('injector');

		$this->injector = $injector;
		$this->db = $injector->db;

	}

	/**
	 * Returns the result set (for use in foreach)
	 */
	function getIterator(){

		if(isset($this->res)) return $this->res;
		$this->res = $this->execute();
		return $this->res;

	}

	/**
	 * Counts the resultset
	 */
	function count(){

		return count($this->getIterator());

	}

	/**
	 * Executes the database query
	 */
	function execute(){

		$this->loadDefinitions();

		//Get total result count
		if($this->paginate){

			$old_parts = $this->parts;
			$param = "*";
			if($this->parts['group']):
				$param = "DISTINCT (" . join(", ", $this->parts['group']) . ')';
			endif;
			$this->parts['select'] = array(array('count('.$param.')', 'num'));
			$this->parts['group'] = null;
			$this->parts['limit'] = null;
			$this->parts['offset'] = null;
			if($this->parts['_labels']) $this->parts['join'] = array_slice($this->parts['join'], 0, -1 * count($this->parts['_labels']));
			$this->parts['order'] = array();
			$this->paginate = false;

			$count = $this->execute();
			$this->total_results = (integer) $count->getOne();
			$this->pages = ceil($this->total_results / $old_parts['limit']);

			$this->paginate = true;
			$this->parts = $old_parts;

			return $this->db->execute($this->__toString(), $this->getParams());

		}

		//Execute the original query
		$res = $this->db->execute($this->__toString(), $this->getParams());
		if(!$this->total_results) $this->total_results = count($res);

		return $res;

	}

	/**
	 * Adds a column to the select clause
	 * @param string $column
	 * @param string $alias
	 */
	function select($column, $alias = null, $meta = array()){

		$this->parts['select'][] = array($column, $alias, 'meta' => $meta);
		return $this;

	}

	/**
	 * Adds an expression representing a column to the select clause
	 * @param string $expression
	 * @param string $column The column to act like for meta purposes
	 * @param string $alias
	 * @param array $meta
	 */
	function selectExpr($expression, $column, $alias = null, $meta = array()){
		$this->parts['select'][] = array($column, $alias, 'meta' => $meta, 'expr' => $expression);
		return $this;
	}

	/**
	 * Adds a releation to the from clause
	 * @param string $table
	 * @param string $alias
	 */
	function from($table, $alias = null){

		$this->parts['from'][] = array($table, $alias);
		return $this;

	}

	/**
	 * Adds a relation via join
	 * @param string $table
	 * @param string $alias
	 * @param string $predicate
	 * @param string $mode (INNER|OUTER|LEFT|RIGHT)
	 */
	function join($table, $alias, $predicate, $mode = "INNER"){

		$this->parts['join'][] = array($mode, $table, $alias, $predicate);
		return $this;

	}

	/**
	 * Adds a relation via left join
	 * @param string $table
	 * @param string $alias
	 * @param string $predicate
	 */
	function leftJoin($table, $alias, $predicate){
		return $this->join($table, $alias, $predicate, "LEFT");
	}

	/**
	 * Adds a where clause entry.
	 * @param string $condition
	 * @param string $mode (AND|OR)
	 */
	function where($condition, $mode = "AND"){

		$this->parts['where'][] = array($mode, $condition);
		return $this;

	}

	/**
	 * Adds a where clause entry using AND
	 * @param string $condition
	 */
	function andWhere($condition){
		return $this->where($condition, "AND");
	}

	/**
	 * Adds a where clause entry using OR
	 * @param string $condition
	 */
	function orWhere($condition){
		return $this->where($condition, "OR");
	}

	/**
	 * Adds a group by clause
	 */
	function groupBy($key){
		$this->parts['group'][] = $key;
		return $this;
	}

	/**
	 * Adds an ordering
	 */
	function orderBy($key, $dir = 'asc'){

		$dir = strtolower($dir) == 'desc' ? 'desc' : 'asc';
		$this->parts['order'][] = array($key, strtoupper($dir));
		return $this;

	}

	/**
	 * Limits a query
	 */
	function limit($num){

		if(intval($num) >= 0) $this->parts['limit'] = intval($num);
		return $this;

	}

	/**
	 * Makes the query dynamically sortable using $_GET['sort'] and $_GET['order']
	 * and adds an entry to the ORDER BY clause
	 */
	function makeSortable(){

		$this->sortable = true;

		$keys = $this->getColumnKeys();
		$sort = "";

		$qs = $this->injector->qs;

		if(!empty($qs->sort)):

			if(empty($qs->order)) $qs->order = array();
			$qs->order = (array) $qs->order;

			foreach((array) $qs->sort as $k => $v):

				if(in_array($v, $keys)):

					$order = array_key_exists($k, $qs->order) && $qs->order[$k] == 'desc' ? 'desc' : 'asc';
					$this->parts['order'][] = array($v, strtoupper($order));

				endif;

			endforeach;

		endif;

		return $this;

	}

	/**
	 * Makes the query paginate according to the number of results per page
	 */
	function paginate($per_page = 25){

		$per_page = intval($per_page);
		if($per_page > 0){
			$this->paginate = true;
		}
		$this->parts['limit'] = $per_page;
		$this->parts['offset'] = empty($_GET['page']) || $_GET['page'] < 1 ? 0 : ($_GET['page'] - 1) * $per_page;

		return $this;

	}

	/**
	 * Prints the query for usage
	 */
	function __toString(){

		$this->loadDefinitions();

		//SELECT
		$out = "SELECT";
		$buffer = array();
		foreach($this->parts['select'] as $sel){
			$tmp = "";
			$tmp .= "\n\t" . (isset($sel['expr']) ? $sel['expr'] : $sel[0]);
			if(!empty($sel[1])) $tmp .= ' AS "' . str_replace('"', '\"', $sel[1]) . '"';
			$buffer[] = $tmp;
		}
		$out .= join(",", $buffer);

		//FROM
		$out .= "\nFROM";
		$buffer = array();
		foreach($this->parts['from'] as $from){
			$tmp = "";
			$tmp .= "\n\t" . $from[0];
			if(!empty($from[1])) $tmp .= " AS " . $from[1];
			if(!empty($from[2])) $tmp .= " " . $from[2];
			$buffer[] = $tmp;
		}
		$out .= join(",", $buffer);

		//JOIN
		foreach($this->parts['join'] as $join){
			$out .= "\n\t" . $join[0] . " JOIN " . $join[1] . " AS " . $join[2];
			if(!empty($join[3])) $out .= " " . $join[3];
		}

		//WHERE
		if(count($this->parts['where'])){
			$out .= "\nWHERE";
			$first = true;
			foreach($this->parts['where'] as $where){
				$tmp = "\n\t";
				if(!$first) $tmp .= $where[0] . " ";
				$tmp .= $where[1];
				$first = false;
				$out .= $tmp;
			}
		}

		//GROUP BY
		if(count($this->parts['group'])){
			$out .= "\nGROUP BY";
			$buffer = array();
			foreach(array_merge($this->parts['group'], $this->parts['_labels']) as $group){
				$buffer[] = "\n\t" . $group;
			}
			$out .= join(",", $buffer);
		}

		//ORDER BY
		if(count($this->parts['order'])){
			$out .= "\nORDER BY";
			$buffer = array();
			foreach($this->parts['order'] as $order){
				//Order by labels if the field is labeled
				if(is_numeric($order[0])):
					//Get column at index
					list($label, $col) = each(array_slice($this->parts['select'], $order[0] - 1, 1));
					if(!empty($col['meta']['label_expanded']) && $col['meta']['label_expanded']):
						$order[0] = '"__' . $label . '__"';
					endif;
				endif;
				$buffer[] = "\n\t" . $order[0] . " " . $order[1];
			}
			$out .= join(",", $buffer);
		}

		//LIMIT
		if($this->parts['limit']) $out .= "\nLIMIT " . $this->parts['limit'];

		//OFFSET
		if($this->parts['offset']) $out .= "\nOFFSET " . $this->parts['offset'];

		return $out;

	}

	/**
	 * Adds a parameter to the query
	 * @param mixed $param
	 */
	function addParam($param){
		$this->params[] = $param;
		return $this;
	}

	/**
	 * Adds a group of parameters to the query
	 * @param array $params
	 */
	function addParams(array $params){
		$this->params = array_merge($this->params, $params);
		return $this;
	}

	/**
	 * Returns an array of query parameters
	 */
	function getParams(){
		return $this->params;
	}

	/**
	 * Returns a key-value list of column names
	 */
	function getColumnNames(){

		$this->loadDefinitions();

		$res = array();
		$res[] = "";

		foreach($this->parts['select'] as $sel):
			$unaliased = strpos($sel[0], ".") ? substr($sel[0], strpos($sel[0], ".") + 1) : $sel[0];
			$res[] = !empty($sel[1]) ? $sel[1] : $unaliased;
		endforeach;

		unset($res[0]);

		return $res;

	}

	/**
	 * Returns the entire column definition array
	 */
	function getColumnDefinitions(){

		$this->loadDefinitions();
		return $this->parts['select'];

	}

	/**
	 * Returns a list of visible column keys
	 */
	protected function getColumnKeys(){

		$this->loadDefinitions();

		$keys = array();
		$i = 0;
		foreach($this->parts['select'] as $key => $col):
			if(!isset($col['meta']['display']) || $col['meta']['display'] !== false) $keys[] = $i + 1;
			$i++;
		endforeach;
		return $keys;

	}

	/**
	 * Returns whether or not this query is dynamically sortable
	 */
	function getSortable(){
		return $this->sortable;
	}

	/**
	 * Returns whether or not this query is paginated
	 */
	function isPaginated(){
		return $this->paginate;
	}

	/**
	 * Returns info about the current page
	 */
	function getPaginationInfo(){

		$data = array();
		$data['first_record'] = 0;
		$data['last_record'] = 0;
		$data['total_records'] = $this->total_results;
		$data['total_pages'] = $this->pages;
		$data['current_page'] = !empty($_GET['page']) ? $_GET['page'] : 1;

		//Did we return the entire dataset?
		if(!$this->paginate && $this->total_results){
			$data['first_record'] = 1;
			$data['last_record'] = $this->total_results;
		}
		//Did we return a partial dataset?
		elseif($this->paginate){
			$data['first_record'] = $this->parts['offset'] + 1;
			$data['last_record'] = min($this->total_results, $data['first_record'] + $this->parts['limit'] - 1);
		}

		return $data;

	}

	/**
	 * Loads table definitions from the cache and modifies the existing query to match
	 */
	protected function loadDefinitions(){

		$conn = $this->db->getInstanceName();

		//Compile a list of potential table names
		$names = array();
		foreach($this->parts['from'] as $el) $names[$el[1]] = $el[0];
		foreach($this->parts['join'] as $el) $names[$el[2]] = $el[1];

		//Load tables that aren't loaded
		foreach($names as $alias => $name):
			$parts = explode(".", $name);
			$table = isset($parts[1]) ? $parts[1] : $parts[0];
			$schema = isset($parts[1]) ? $parts[0] : 'public';
			//Skip tables we have already loaded or don't have definitions for
			isset($this->definitions[$name]) || $this->definitions[$name] = Schema::getTableData($this->injector, $table, $schema);
		endforeach;

		//Iterate through fields and perform * expansion
		$offset = 0;
		foreach($this->parts['select'] as $i => $col):

			if(!preg_match("/^([a-z0-9_]+\.)?\*$/", $col[0], $matches)) continue;

			//What table(s) does this field expand?
			if($col[0] == "*"):
				$tables = array_values($names);
			else:
				$tables = array($names[substr($matches[0], 0, -2)]);
			endif;

			//Replacement fields
			$fields = array();

			foreach($tables as $t):
				$alias = array_search($t, $names) ?: $t; //Get the table's alias
				foreach($this->definitions[$t] as $field => $data):
					$fields[] = array($alias . "." . $field, $data['label'] ?: $field, 'meta' => $data);
				endforeach;
			endforeach;

			//Replace the * field
			array_splice($this->parts['select'], $i + $offset, 1, $fields);
			$offset += count($fields) - 1;

		endforeach;

		//Emulate * expansion for group by clause
		$offset = 0;
		foreach((array) $this->parts['group'] as $i => $col):

			if(!preg_match("/^([a-z0-9_]+\.)?\*$/", $col, $matches)) continue;

			//What table(s) does this field expand?
			if($col == "*"):
				$tables = array_values($names);
			else:
				$tables = array($names[substr($matches[0], 0, -2)]);
			endif;

			//Replacement fields
			$fields = array();

			foreach($tables as $t):
				$alias = array_search($t, $names) ?: $t; //Get the table's alias
				foreach($this->definitions[$t] as $field => $data):
					$fields[] = $alias . "." . $field;
				endforeach;
			endforeach;

			//Replace the * field
			array_splice($this->parts['group'], $i + $offset, 1, $fields);
			$offset += count($fields) - 1;

		endforeach;

		//Iterate through field names and attach column definition to SELECT data
		foreach($this->parts['select'] as &$col):

			//Make sure this is a basic column we are selecting
			if(!preg_match("/[a-z0-9_]+\.[a-z0-9_]/", $col[0])) continue;

			//Split the name into parts
			$parts = preg_split("/\./", $col[0]);

			//Ensure we have a table definition mapped to the table's alias
			if(!array_key_exists($parts[0], $names)) continue;

			//Make sure the table definition exists
			if(!isset($this->definitions[$names[$parts[0]]])) continue;

			$table = $this->definitions[$names[$parts[0]]];

			//Ensure the column definition exists
			if(!array_key_exists($parts[1], $table)) continue;

			$column = $table[$parts[1]];

			if(array_key_exists('meta', $col)):
				//Merge the data
				$col['meta'] = array_merge($column, $col['meta']);
			else:
				//Add the data
				$col['meta'] = $column;
			endif;

			//Set the column alias
			if(!isset($col[1])) $col[1] = $column['label'];

		endforeach;

		unset($col);

		//Iterate through fields and ensure that multiple columns with the same alias are reduced
		$reduced = array();
		foreach($this->parts['select'] as $col):
			if(isset($col[1])):
				$alias = $col[1];
			else:
				$alias = array_pop(explode('.', $col[0]));
			endif;
			$reduced[$alias] = $col;
		endforeach;

		$this->parts['select'] = $reduced;

		//Expand dropdown columns to also return their name
		$classes = array('Select');
		foreach($this->parts['select'] as &$col):

			if(!isset($col['meta']['field_class'])) continue;
			if(!in_array($col['meta']['field_class'], $classes)) continue;
			if(!empty($col['meta']['label_expanded'])) continue;

			$lbl = 'lbl' . ++$this->label_counter;

			//Build a label field
			$label = array(
				0 => $lbl . ".name",
				1 => "__" . $col[1] . "__",
				'meta' => array(
					'is_visible' => false,
					'label_for' => $col[0]
				)
			);

			$this->parts['select'][$label[1]] = $label;

			$this->parts['_labels'][] = $label[0];

			//Add the join
			$this->leftJoin($col['meta']['fk_table_name'], $lbl, "ON " . $lbl . ".id = " . $col[0]);

			$col['meta']['label_expanded'] = true;

		endforeach;

		unset($col);

	}

	/**
	 * Returns CASE sql that can be used to sort a field via list position.
	 * @param string $field The name of the field
	 * @param array $sortables A key-value array of items to be sorted
	 */
	function sortField($field, $sortables){

		if(empty($sortables)) return $field;
		$sql = "CASE";
		foreach($sortables as $k => $val):
			$sql .= " WHEN " . $field . " = " . $this->db->quote($val) . " THEN " . $this->db->quote($k);
		endforeach;
		$sql .= " END";
		return $sql;

	}

	/**
	 * Returns an array of form fields from the query
	 */
	function getFormFields(){
		$fields = array();
		foreach($this->getColumnDefinitions() as $f => $col):
			$meta = $col['meta'];
			if(!empty($meta['label_for'])) continue;
			if(!$meta || !isset($meta['schema_name'])):
				//Build a span field if no metadata was found
				$field = new Span($f);
				$field->setLabel($col[1]);
				if(isset($meta['formatter'])) $field->setFormatter($meta['formatter']);
				$fields[] = $field;
				continue;
			endif;
			if(isset($meta['is_visible']) && !$meta['is_visible']) continue;
			$field = FormField::build($meta['schema_name'] . '.' . $meta['table_name'] . '.' . $meta['field_name']);
			$field->setLabel($col[1]);
			$field->setName($f);
			if(isset($meta['formatter'])) $field->setFormatter($meta['formatter']);
			$fields[] = $field;
		endforeach;
		return $fields;
	}

	/**
	 * Creates a dataset out of the existing query using the field metadata
	 * @return DataSet
	 */
	function returnDataSet(){
		$dataset = new DataSet;
		$i = 0;
		foreach($this->getColumnDefinitions() as $f => $col):
			$i++;
			$meta = isset($col['meta']) ? $col['meta'] : null;

			$field = isset($meta['field_name']) ? $meta['schema_name'] . '.' . $meta['table_name'] . '.' . $meta['field_name'] : $f;
			$visible = isset($meta['is_visible']) ? $meta['is_visible'] : true;

			$sortable = isset($meta['is_sortable']) ? $meta['is_sortable'] : true;
			$sortable = $sortable && $this->sortable;

			if($visible):
				$child = isset($meta['schema_name']) ? FormField::build($meta['schema_name'] . '.' . $meta['table_name'] . '.' . $meta['field_name']) : new \HTML\Form\Field\Span($field);
				$child->setName($f);
				if(isset($col[1])) $child->setLabel($col[1]);
				$child->ordinal_position = $i; //Tracks the ordinal position of the column from the query
				$child->is_sortable = $sortable; //Can this column sort?
				$dataset->addChild($child);
				if(!empty($meta['formatter'])) $child->setFormatter($meta['formatter']);
			endif;

		endforeach;

		$dataset->setValue($this->execute());
		$dataset->pagination_info = $this->getPaginationInfo();
		$dataset->is_paginated = $this->isPaginated();

		return $dataset;
	}

	/**
	 * Converts form data as labeled by getFormFields() into an array of data
	 * per original source tables.
	 * @param array $input set of data to translate (like $_POST)
	 */
	function translateNamestoFields(array $input){

		$out = array();

		foreach($this->getColumnDefinitions() as $f => $col):
			if(!$col['meta'] || !array_key_exists($f, $input)) continue;
			$meta = $col['meta'];
			$out[$meta['schema_name'] . '.' . $meta['table_name']][$meta['field_name']] = $input[$f];
		endforeach;

		return $out;

	}

	/**
	 * Saves data back to the database given a set of tables and data per each table.
	 * Uses the query's where predicate and join structure to find rows to modify
	 * @param array $form_data should be similar to the result of translateNamesToFields()
	 */
	function saveData(array $form_data){

		$this->loadDefinitions();

		$id_query = clone $this;
		$id_query->clearSelect();

		//Get IDs for tables mentioned in from / join list
		foreach($this->parts['from'] as $table):
			if(count(explode(".", $table[0])) != 2) $table[0] = "public." . $table[0];
			$id_query->select($table[1] . ".id", $table[0]);
		endforeach;

		//Don't include label joins
		$joins = $this->parts['join'];
		if($this->parts['_labels']) $joins = array_slice($this->parts['join'], 0, -1 * count($this->parts['_labels']));
		foreach($joins as $table):
			if(count(explode(".", $table[1])) != 2) $table[1] = "public." . $table[1];
			$id_query->select($table[2] . ".id", $table[1]);
		endforeach;

		//Retrieve the IDs
		$ids = $id_query->execute()->getRow();

		$mode = array(
			"mode" => "UPDATE",
			"where" => "id = ?"
		);

		//Perform database updates
		foreach($form_data as $table => $data):

			$mode['params'] = $ids[$table];
			$this->db->autoExecute($table, $mode, $data);

		endforeach;

	}

	/**
	 * Clears out the select part of the query
	 * Also eliminates label joins if present
	 */
	function clearSelect(){

		$this->parts['select'] = array();

		if($this->parts['_labels']):
			$this->parts['join'] = array_slice($this->parts['join'], 0, -1 * count($this->parts['_labels']));
			$this->parts['_labels'] = array();
		endif;

		return $this;

	}

	/**
	 * Returns the database connection used for by this query
	 */
	function getDB(){

		return $this->db;

	}

}