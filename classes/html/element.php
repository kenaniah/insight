<?php
namespace HTML;
use \Format\Format;
/**
 * This class is extended by other classes that represent HTML elements,
 * such as the Form and DataSet classes. This class defines the interface
 * used to manage HTML attributes for the parent tag.
 *
 * @author Kenaniah Cerny <kenaniah@gmail.com>
 */
abstract class Element {

	/**
	 * Used to autogenerate element IDs
	 * @var integer
	 */
	static $id_counter = 0;

	/**
	 * Used to calculate the number of tabs to print for source code formatting
	 * @var integer
	 */
	public $indent = 0;

	/**
	 * Tracks the full prefix to be used for the name property.
	 * If a namespace is set on a Container, the namespace will be appended
	 * to the prefix as it is passed to the container's children.
	 * @var array
	 */
	public $name_prefix = array();

	/**
	 * Tracks the formatting mode to be used for this field
	 * @var string
	 */
	public $format_mode = Format::FORM;

	/**
	 * This is added to the $indent above and passed to child elements
	 * @var integer
	 */
	protected $child_indent = 1;

	/**
	 * Contains a list of HTML element attributes
	 * @var array
	 */
	protected $attributes = array();

	/**
	 * Returns a unique element id
	 */
	static function generateElementId(){

		return 'element' . ++self::$id_counter;

	}

	/**
	 * Returns the value of the requested attribute.
	 *
	 * @param string $name
	 */
	function getAttribute($name){
		if(array_key_exists($name, $this->attributes)){
			return $this->attributes[$name];
		}
		return null;
	}

	/**
	 * Returns the entire attributes array
	 */
	function getAttributes(){
		return $this->attributes;
	}

	/**
	 * Sets an HTML attribute for the form.
	 * When setting the class attribute, use addClass() instead
	 * @param string $name
	 * @param string $value
	 */
	function setAttribute($name, $value){
		if($name == 'class'):
			user_error("Use addClass() / removeClass() to manage the class attribute.", E_USER_WARNING);
			return $this;
		endif;
		$this->attributes[$name] = $value;
		return $this;
	}

	/**
	 * Removes an HTML attribute
	 * @param string $name
	 */
	function removeAttribute($name){
		unset($this->attributes[$name]);
		return $this;
	}

	/**
	 * Clears all attributes
	 */
	function clearAttributes(){
		$this->attributes = array();
	}

	/**
	 * Adds additional classes to the HTML class attribute
	 * @param string|array $class standalone HTML class(es)
	 */
	function addClass($class){
		$this->ensureClassAttributeExists();
		$array = (array) $this->attributes["class"];
		$array = array_merge($array, (array) $class);
		$this->attributes["class"]->exchangeArray($array);
		return $this;
	}

	/**
	 * Removes an HTML class
	 * @param string|array $class standalone HTML class(es)
	 */
	function removeClass($class){
		$this->ensureClassAttributeExists();
		$array = (array) $this->attributes["class"];
		$array = array_diff($array, (array) $class);
		$this->attributes["class"]->exchangeArray($array);
	}

	/**
	 * Initializes the class attribute when called
	 */
	protected function ensureClassAttributeExists(){
		if(!array_key_exists("class", $this->attributes)):
			$obj = new \ArrayObjectExtended();
			$obj->setCallable(function($array){ return implode(" ", $array); });
			$this->attributes["class"] = $obj;
		endif;
	}

	/**
	 * Returns the full name, including namespace, of the field
	 * @param boolean $replace_dots_and_spaces When true, replaces dots and spaces with other chars
	 */
	protected function getFullName($replace_dots_and_spaces = true) {

		$attrs = $this->attributes;

		//Name prefixing support
		if(!empty($attrs['name']) && $this->name_prefix):

			$attrs['name'] = self::buildArrayKey($this->name_prefix, $attrs['name']);

		endif;

		//Replace dots and spaces to work around an input variable name limiation in PHP
		if($replace_dots_and_spaces && !empty($attrs['name'])):
			$replacements = array('.' => '$dot$', ' ' => '$space$');
			$attrs['name'] = str_replace(array_keys($replacements), array_values($replacements), $attrs['name']);
		endif;

		return isset($attrs['name']) ? $attrs['name'] : null;

	}

	/**
	 * Returns a string of attributes in HTML form.
	 * The value attribute of form fields will always use the
	 * form format if a formatter is set.
	 */
	function outputAttributes(){

		$attrs = $this->attributes;

		//The name attribute should return the full name of this element
		if($this->format_mode == Format::FORM):
			$name = $this->getFullName();
			if($name):
				$attrs['name'] = $name;
			endif;
		else:
			unset($attrs['name']);
		endif;

		$out = "";
		foreach($attrs as $name => $value){
			if($name == 'value' && isset($this->formatter)):
				//Ensure that form field values are always form formatted
				$value = $this->formatter->form($value);
			endif;
			if(substr($name, 0, 5) !== "data-") $value = \Helpers::entify($value);
			$out .= " " . $name . "='" . $value . "'";
		}
		return $out;

	}

	/**
	 * Returns the HTML id attribute, or autogenerates it when missing
	 */
	protected function ensureElementID(){
		if(!isset($this->attributes['id'])):
			$this->attributes['id'] = self::generateElementId();
		endif;
		return $this->attributes['id'];
	}

	/**
	 * Outputs the number of tabs equal to the current $indent
	 */
	function indent(){
		return "\n" . str_repeat("\t", ($this->indent < 0) ? 0 : $this->indent);
	}

	/**
	 * This function checks whether an element meets certain business requirements.
	 * @return boolean
	 */
	abstract function validate();

	/**
	 * Returns an array tracking the dimensions found in an array
	 * @param string $name Something like "name[foo][bar][]"
	 * @return array
	 */
	static function parseArrayDimensions($string){

		//Parse array dimensions
		$tmp = preg_replace('/^([^\[]+)/', '[$1]', $string);
		preg_match_all('/\[([^\[]*)\]/', $tmp, $matches);
		return $matches[1];

	}

	/**
	 * Returns a string name reconstructed from the given parts
	 * @param mixed $prefix can be a string or array
	 * @param mixed $name can be a string or array
	 * @return string like "name[foo][bar][]"
	 */
	static function buildArrayKey($prefix, $name = null){

		if(!is_array($prefix)) $prefix = self::parseArrayDimensions($prefix);
		if(!is_null($name) && !is_array($name)) $name = self::parseArrayDimensions($name);
		$parts = is_null($name) ? $prefix : array_merge($prefix, $name);

		$out = array_shift($parts);
		while(($val = array_shift($parts)) || isset($val)) $out .= "[" . $val . "]";

		return $out;

	}

}