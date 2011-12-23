<?php
/**
 * This class builds a URL query string based on the properties passed to the object
 *
 * @author Kenaniah Cerny <kenaniah@gmail.com> https://github.com/kenaniah/insight
 * @license http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright Copyright (c) 2009, Kenaniah Cerny
 */
class QueryString {

	private $_vars = array();
	private $_appended_string = ""; //Added to the end of the returned query string

	/**
	 * Set up the query string using the initial array if provided, or populate with $_GET
	 */
	function __construct($initial_array = NULL){

		$this->_vars = !is_null($initial_array) ? $initial_array : $_GET;

	}

	/**
	 * Set up the query string using the initial array if provided, or populate with $_GET if param is NULL
	 */
	function setArray($array){

		$this->__construct($array);
		return $this;

	}

	/**
	 * The complement to setArray(). This function returns the contents of the current query string as an array
	 */
	function getArray(){

		return $this->_vars;

	}

	/**
	 * Sets the string that will be appended to the end of the query string
	 */
	function setAppendedString($string){

		$this->_appended_string = $string;
		return $this;

	}

	/**
	 * Magic utility function
	 */
	function __get($key){

		return $this->_vars[$key];

	}

	/**
	 * Magic utility function
	 */
	function __set($key, $val){

		$this->_vars[$key] = $val;

	}

	/**
	 * Magic utility function
	 */
	function __isset($key){

		return isset($this->_vars[$key]);

	}

	/**
	 * Magic utility function
	 */
	function __unset($key){

		unset($this->_vars[$key]);

	}

	/**
	 * Magic utility function
	 */
	function __toString(){

		return $this->toString();

	}

	/**
	 * Builds the query string (called directly when attempting to print an instance of this class)
	 * @param $url_encoded Whether or not to URL encode the output
	 * @param $array An array of key-value pairs to output in the query string
	 * @param $full_url The URL to output in front of the '?'
	 */
	protected function toString($url_encoded = true, $array = NULL, $full_url = ''){

		//Reference to the class used for the anonymous function
		$self = __CLASS__;

		//Default key-value pairs to use if not provided
		$array = is_null($array) ? $this->_vars : $array;

		//Tracks an array of rendered key / value pairs
		$pairs = array();

		//Used to recursively encode values
		$deep_encode = function($array, $prefix = array()) use (&$deep_encode, &$pairs, $self){

			foreach($array as $key => $val):

				if(is_null($val)) continue;

				if(is_array($val)):

					$deep_encode($val, array_merge($prefix, array($key)));

				else:

					$val = urlencode($val);
					$val = str_replace(array("%7B", "%7D"), array("{", "}"), $val);
					$pairs[] = urlencode($self::buildArrayKey($prefix, $key))."=".$val;

				endif;

			endforeach;

		};

		$deep_encode($array);

		return $full_url . "?" . implode($url_encoded ? "&amp;" : "&", $pairs) . $this->_appended_string;

	}

	/**
	 * Outputs the query string
	 * @param $url_encoded Whether or not to URL encode the output
	 * @param $array An array of key-value pairs to output in the query string
	 * @param $full_url The URL to output in front of the '?'
	 */
	function output($url_encoded = true, $array = NULL, $full_url = ''){

		return $this->toString($url_encoded, $array, $full_url);

	}

	/**
	 * Prints normal version that can be used for HTTP redirects
	 */
	function url($replace = array()){

		return $this->replace($replace, false);

	}

	/**
	 * Prints a clean link using only params passed
	 */
	function clean($array = array(), $url_encoded = true){

		return $this->toString($url_encoded, $array);

	}

	/**
	 * Toggles a certain key between values $value1 and $value2 for a query string
	 */
	function toggle($key, $value1, $value2, $url_encoded = true, $full_url = NULL){

		$data = $this->_vars;
		$data[$key] = $data[$key] == $value1 ? $value2 : $value1;
		if(is_null($data[$key])) unset($data[$key]); //Remove if NULL
		return $this->toString($url_encoded, $data, $full_url);

	}

	/**
	 * Returns query string with array values merged
	 */
	function replace($array = array(), $url_encoded = true, $full_url = NULL){

		$vals = array_merge($this->_vars, $array);
		foreach($array as $k => $v) if(is_null($v)) $vals[$k] = NULL; //Make sure NULLs transfer
		return $this->toString($url_encoded, $vals, $full_url);

	}

	/**
	 * Prints a toggled link based off of value of key (link disappears if the key's value == $value)
	 */
	function link($text, $key, $value, $full_url = NULL, $replace = true){

		if((isset($this->_vars[$key]) && $this->_vars[$key] == $value)
			|| (!isset($this->_vars[$key]) && $value === null)) return "<span>" . $text . "</span>";

		$url = $replace ? $this->replace(array($key => $value), true, $full_url) : $full_url;

		return '<a href="'.$url.'">'.$text.'</a>';

	}

	/**
	 * Returns query string built specifically for a sorting parameter
	 * @param string $key
	 * @param string $default The default sort order (asc|desc)
	 */
	function sort($key, $default = 'asc'){

		$vars = $output = $this->_vars;

		if(strtolower($default) == 'desc'){
			$def = 'desc';
			$inv = 'asc';
		}else{
			$def = 'asc';
			$inv = 'desc';
		}

		$flipped = strtolower($default) == 'desc';

		//Set defaults
		if(!isset($vars['sort'][0])) $vars['sort'] = array(1);
		if(!isset($vars['order'][0])) $vars['order'] = array($def);
		if(!empty($_GET['page'])) $output['page'] = $_GET['page'];

		$output['sort'] = array($key);

		if($key == $vars['sort'][0]){
			$output['order'] = array($vars['order'][0] == $def ? $inv : $def);
		}else{
			$output['order'] = array($def);
		}

		return $this->toString(true, $output);

	}

	/**
	 * Returns a query string without the path using the replacement array
	 */
	function qsOnly($array = array(), $url_encoded = true){

		return $this->replace($array, $url_encoded, "");

	}

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
