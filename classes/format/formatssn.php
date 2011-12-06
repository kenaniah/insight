<?php
namespace Format;

/**
 * Formats a value as a social security number
 */
class FormatSsn extends Format {

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

		return "<span class='ssn'>".$value."</span>";

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

		if(strlen($value) == 9):
			$str = substr($value, 0, 3) . "-" . substr($value, 3, 2) . "-" . substr($value, 5, 4);
			$value = $str;
		endif;

		return $value;

	}

}