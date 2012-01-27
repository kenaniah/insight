<?php
namespace HTML\Form\Field;

use Format\Format;

/**
 * Creates a javascript autocompleting field with the following options:
 *
 * - data-matching-values 	- When set, this field will only accept values that match the datasource
 * - data-proxy-values		- When set, this field will display the label, but submit the value silently. Default on.
 */
class Autocomplete extends Text {

	/**
	 * Responsible for handling the behavior of the field
	 * @var closure
	 */
	protected $handler;

	function __construct($name, $params = array()){
		parent::__construct($name, $params);
		$this->addClass('autocomplete');
		$this->setAttribute('data-proxy-values', 1);
		if(!empty($params['meta_data']['datasource'])) $this->setAttribute('data-datasource', $params['meta_data']['datasource']);
		$this->handler = function(){ return array("Please configure this autocomplete."); };
	}

	/**
	 * Handles the AJAX request for an autocomplete data source.
	 * Checks for $_POST['ajax_id'] = <field id> or <datasource id> before handling the request
	 * Calls the handler closure and returns a JSON respone with the data returned from the handling closure
	 */
	function handle() {

		if(!empty($_POST['ajax_id']) && in_array($_POST['ajax_id'], array($this->getAttribute('id'), $this->getAttribute('data-datasource')))):

			//Detect ajax requests
			$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && in_array(strtolower($_SERVER['HTTP_X_REQUESTED_WITH']), array('xmlhttprequest'));
			if($is_ajax):
				header("Content-Type: application/json");
				ob_end_clean();
			endif;

			$func = $this->handler;
			print json_encode($func(isset($_POST['term']) ? $_POST['term'] : null, isset($_POST['term_id']) ? $_POST['term_id'] : null, $this->injector));

			exit;

		endif;

	}

	/**
	 * Sets the handler for the autocomplete field.
	 * Closure will be passed (<term>, <id value>, Injector)
	 * <term> is the term entered into the autocomplete by the user
	 * <id value> is a reversed lookup based on ID
	 * Injector is an instance of the Injector class
	 * @param closure $handler
	 */
	function setHandler($handler) {
		$this->handler = $handler;
		return $this;
	}


	function __toString(){
		if($this->getAttribute('data-proxy-values')){
			$out = "";

			$val = $this->getValue();
			$label = null;

			//Find the label to go with this value
			if(array_key_exists("__" . $this->label . "__", $this->container_value)):
				$label = $this->container_value["__" . $this->label . "__"];
			else:
			$func = $this->handler;
			$res = $func(null, $val, $this->injector);
			if(count($res)):
				$row = array_shift($res);
				if($row):
						$label = $row['name'];
					endif;
				endif;
			endif;

			//Remove the name from the field
			$name = $this->getFullName(false);
			$orig = $this->getAttribute('name');
			$this->removeAttribute('name');
			$this->setValue($label);
			$out .= parent::__toString();
			$this->setValue($val);
			$this->setAttribute('name', $orig);

			//Create a proxy field
			$field = new Hidden($name);
			$field->setAttribute('id', 'proxy-' . $this->ensureElementID());
			$field->setValue($val);

			$out .= $field;
			return $out;
		}
		return parent::__toString();
	}

	/**
	 * Value is modified by reference! Sanitizes a submitted value by transforming it into an autocomplete value.
	 * May insert data into the database using the given parameters if a non-id is given.
	 *
	 * Note: Autocomplete fields may NOT contain numbers as labels, as they will be treated as an id.
	 *
	 * @param mixed $value The autocompleted value
	 * @param Database $db The database connection
	 * @param string $table The name of the table
	 * @param array $set_data Additional data to set when creating a new entry
	 * @param string $extra_where Where clause to use for determining new entries
	 * @param array $extra_where_params Parameters provided to where clause for determining new entries
	 */
	static function convertValue(&$value, \Database $db, $table, array $set_data = array(), $extra_where = null, array $extra_where_params = array()){

		$value = trim($value);
		if(is_numeric($value)) return intval($value);
		if(!strlen($value)) return null;

		//Add name search to WHERE predicate
		$where = "name = ? " .  $extra_where;
		$where_params = array_merge(array($value), $extra_where_params);

		//Append the name as a datapoint
		$set_data['name'] = $value;

		//Perform the query
		$value = $db->autoExecute(
			$table,
			array(
				"mode" => "REPLACE",
				"returning" => "id",
				"return_mode" => "getOne",
				"where" => $where,
				"params" => $where_params
			),
			$set_data
		);

	}

}