<?php
namespace HTML\Form\Field;

/**
 * Creates a javascript autocompleting field with the following options:
 *
 * - data-matching-values 	- When set, this field will only accept values that match the datasource
 * - data-proxy-values		- When set, this field will display the label, but submit the value silently. Default on.
 */
use Format\Format;

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
		$this->handler = function(){ return array("Please configure this autocomplete."); };
	}

	/**
	 * Handles the AJAX request for an autocomplete data source.
	 * Checks for $_POST['ajax_id'] = <field id> before handling the request
	 * Calls the handler closure and returns a JSON respone with the data returned from the handling closure
	 */
	function handle() {

		if(!empty($_POST['ajax_id']) && $_POST['ajax_id'] == $this->getAttribute('id')):

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

			//Find the DB label to go with this value
			$func = $this->handler;
			$res = $func(null, $val, $this->injector);
			if(count($res)):
				$row = array_shift($res);
				if($row):
					$label = $row['label'];
				endif;
			endif;

			//Remove the name from the field
			$name = $this->getFullName();
			$orig = $this->getAttribute('name');
			$this->removeAttribute('name');
			$this->setValue($label);
			$out .= parent::__toString();
			$this->setValue($val);
			$this->setAttribute('name', $orig);

			//Create a proxy field
			$field = new Hidden($name);
			$field->setAttribute('id', $this->ensureElementID() . '-proxy');
			$field->setValue($val);

			$out .= $field;
			return $out;
		}
		return parent::__toString();
	}

}