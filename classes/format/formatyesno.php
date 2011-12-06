<?php
namespace Format;

/**
 * Formats a FormField as a yes / no
 */
class FormatYesNo extends Format {

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::raw()
	 */
	function raw($value){
		if(is_null($value)) return null;
		return (boolean) $value;
	}

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::html()
	 */
	function html($value){
		if(is_null($value)) return "";
		if($value) return "<span class='positive'>Yes</span>";
		return "<span class='negative'>No</span>";
	}

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::export()
	 */
	function export($value){
		return $value ? 1 : 0;
	}

}