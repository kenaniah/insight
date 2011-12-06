<?php
namespace HTML\Form\Field;

use Format\Format;

class Select extends FormField {

	protected $options = array();
	protected $parsed = null;
	protected $default = null;

	/**
	 * @param string $name The name of the form field
	 * @param mixed $value The value of the form field
	 * @param array $options An array of options (generates HMTL <option> tags)
	 * @param array $attrs An array of attributes to add to the field
	 */
	function __construct($name, $params = array()){
		parent::__construct($name, $params);
		if(!is_array($params)) $params = array();
		if(isset($params['options'])) $this->setOptions($params['options']);
		$this->default = isset($params['default']) ? $params['default'] : "-- Select --" ;
	}

	/**
	 * Sets the array of options available
	 * @param array $options
	 */
	function setOptions($options){
		$this->options = $options;
		$this->parsed = null;
		return $this;
	}

	/**
	 * Sets the default option
	 * @param string $string or FALSE
	 */
	function setDefaultText($string){
		$this->default = $string;
		return $this;
	}

	/**
	 * Returns the array of options set for this field
	 */
	function getOptions(){
		if($this->options instanceof \Closure):
			$this->options = call_user_func($this->options);
		endif;
		return $this->options;
	}

	function getValue($format_mode = null){
		if(is_null($this->value) || $this->value === '') return null;
		if($format_mode == Format::EXPORT):
			//Return the field label instead of its value
			return $this->getSelectedLabel();
		endif;
		return parent::getValue($format_mode);
	}

	/**
	 * Renders the field, and returns the selected label
	 * when HTML formatted.
	 */
	function __toString(){
		if($this->format_mode == Format::HTML):
			 if($this->formatter):
			 	return $this->formatter->format($this->getSelectedLabel(), Format::HTML, $this->container_value) ?: "";
			 endif;
			 return $this->getSelectedLabel() ?: "";
		endif;
		$out = "<select" . $this->outputAttributes() . ">";
		$out.= \Helpers::dropdown($this->getOptions(), $this->value, true, $this->default);
		$out.= "</select>";
		return $out;
	}

	/**
	 * Returns a hash map (value-label) array based on the input resource
	 */
	function getParsedOptions(){
		if(!is_null($this->parsed)) return $this->parsed;
		$data = array();
		foreach($this->getOptions() as $index => $row):
			if(!is_array($row)):
				$val = $row;
				$row = array();
				$row['id'] = $index;
				$row['name'] = $val;
			elseif(!array_key_exists('id', $row)):
				$row['id'] = $index;
			endif;
			$data[$row['id']] = $row['name'];
		endforeach;
		$this->parsed = $data;
		return $data;
	}

	/**
	 * Returns the label of the currently selected element
	 */
	function getSelectedLabel(){
		if(array_key_exists("__" . $this->label . "__", $this->container_value)):
			return $this->container_value["__" . $this->label . "__"];
		endif;
		$opts = $this->getParsedOptions();
		return isset($opts[$this->value]) ? $opts[$this->value] : null;

	}

}