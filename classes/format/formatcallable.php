<?php
namespace Format;

/**
 * Formats a FormField using a callable
 */
class FormatCallable extends Format {

	protected $callable = null;

	function __construct($callable){
		$this->callable = $callable;
		if(!is_callable($callable)) user_error("Argument passed to " . __CLASS__ . " must be callable.");
	}

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::raw()
	 */
	function raw($value){
		return call_user_func($this->callable, $value);
	}

}