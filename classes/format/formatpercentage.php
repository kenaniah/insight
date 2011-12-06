<?php
namespace Format;

/**
 * Formats a FormField as a percentage field
 */
class FormatPercentage extends Format {

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::raw()
	 */
	function raw($value){
		$value = round((float) preg_replace("/[^0-9.-]/", "", $value), 3);

		return number_format($value, 2, '.', '');
	}

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::html()
	 */
	function html($value){

		$value = $this->raw($value);
		$value = number_format($value, 2, '.', ',');

		return "<span class='percentage'>".$value." %</span>";

	}

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::form()
	 */
	function form($value){
		$value = $this->raw($value);

		return (float) $value;
	}

}