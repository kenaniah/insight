<?php
namespace Format;

/**
 * Formats a FormField as a money input
 */
class FormatMoney extends Format {

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::raw()
	 */
	function raw($value){
		$value = preg_replace("/[^0-9.-]/", "", $value);
		if(!$value) $value = 0;
		return money_format("%!^n", $value);
	}

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::html()
	 */
	function html($value){

		$value = $this->raw($value);
		$class = 'zero';
		if($value > 0) $class = 'positive';
		if($value < 0) $class = 'negative';

		return "<span class='money ".$class."'>".money_format("%(n", $value)."</span>";

	}

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::export()
	 */
	function export($value){
		$value = $this->raw($value);
		return money_format("%+n", $value);
	}

}