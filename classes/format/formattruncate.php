<?php
namespace Format;
use Helpers;

/**
 * Truncates a form field to a certain length
 */
class FormatTruncate extends Format {

	protected $length = null;

	function __construct($length = null){
		$this->length = $length;
	}

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::html()
	 */
	function html($value){
		if(is_null($value)) return null;
		return Helpers::truncateTo($value, $this->length);
	}

}