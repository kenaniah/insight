<?php
namespace Format;

/**
 * This class defines the basic contract to which formatters must adhere.
 * All formatters should extend from this class.
 *
 * By convention, any non-raw formatter ought to call the raw formatter before
 * additional processing to ensure the value is in an acceptable format for later
 * transformations. Good practice would be to call '$value = $this->raw($value)'
 * first in every overloaded formatting method.
 */
class Format {

	/**
	 * Raw formatting returns a basic value, ideal for database operations
	 * @var string
	 */
	const RAW    = 'raw';

	/**
	 * HTML formatting is used to display a value, but not in an editing context
	 * @var string
	 */
	const HTML   = 'html';

	/**
	 * Form formatting is used to display HTML form fields and their values
	 * @var string
	 */
	const FORM   = 'form';

	/**
	 * Export formats the value for CSV output
	 * @var string
	 */
	const EXPORT = 'export';

	/**
	 * Tracks the set of values passed into the container
	 * @var array
	 */
	protected $container_value = array();

	/**
	 * Formats the given $value according to $format_mode
	 * @param mixed $value the value to be formatted
	 * @param string $format_mode optional, defaults to mode already set
	 */
	final function format($value, $format_mode = self::RAW, $container_value = array()){
		$strategy = in_array($format_mode, self::getAvailableModes()) ? $format_mode : self::RAW;
		$this->container_value = $container_value;
		return $this->$strategy($value);
	}

	/**
	 * Boils the given value down to raw form
	 * @param mixed $value
	 */
	function raw($value){
		return $value;
	}

	/**
	 * Formats the given value for HTML display purposes
	 * @param mixed $value
	 */
	function html($value){
		return $this->raw($value);
	}

	/**
	 * Formats the given value for use in an HTML input field
	 * @param mixed $value
	 */
	function form($value){
		return $this->raw($value);
	}

	/**
	 * Formats the given value for CSV exports
	 * @param mixed $value
	 */
	function export($value){
		return $this->raw($value);
	}

	/**
	 * Returns a list of available format modes
	 */
	static function getAvailableModes(){
		return array(self::FORM, self::HTML, self::RAW, self::EXPORT);
	}

}