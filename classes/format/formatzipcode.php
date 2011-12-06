<?php
namespace Format;

/**
 * Formats a value as a zip code
 */
class FormatZipcode extends Format {

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::raw()
	 */
	function raw($value){

		$value = preg_replace("/[^0-9]/", "", $value);

		//Allow only 5 or 9 digit zipcodes
		if(strlen($value) == 9):
			$value = substr($value, 0, 5) . "-" . substr($value, 5, 4);
		elseif(strlen($value) != 5):
			$value = "";
		endif;
		return $value;

	}

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::form()
	 */
	function form($value){
		$value = preg_replace("/[^0-9]/", "", $value);
		if(strlen($value) == 9) $value = substr($value, 0, 5) . "-" . substr($value, 5, 4);
		return $value;
	}

}