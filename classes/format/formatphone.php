<?php
namespace Format;

/**
 * Formats a value as a telephone number
 */
class FormatPhone extends Format {

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::raw()
	 */
	function raw($value){
		$value = preg_replace("/[^0-9]/", "", $value);
		return $value;
	}

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::html()
	 */
	function html($value){

		$value = $this->form($value);

		return "<span class='phone'>".$value."</span>";

	}

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::export()
	 */
	function export($value){
		return $this->form($value);
	}

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::form()
	 */
	function form($value){
		$value = $this->raw($value);
		if(!$value) return "";
		$str = "(" . substr($value, 0, 3) . ") " . substr($value, 3, 3) . "-" . substr($value, 6, 4);
		if(strlen($value) > 10) $str .= " x" . substr($value, 10);

		return $str;

	}

}