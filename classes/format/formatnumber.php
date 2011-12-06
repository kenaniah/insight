<?php
namespace Format;

/**
 * Formats a FormField as a number
 */
class FormatNumber extends Format {

	protected $precision = 0;

	/**
	 * Sets the precision for all numbers
	 * @param integer $precision
	 */
	function setPrecision($precision){
		$this->precision = intval($precision);
		return $this;
	}

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::raw()
	 */
	function raw($value){
		$value = preg_replace("/[^0-9.-]/", "", $value);
		if(!$value) return null;
		return round($value, $this->precision);
	}

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::html()
	 */
	function html($value){

		$value = $this->raw($value);
		if(is_null($value)) return '';
		$class = 'zero';
		if($value > 0) $class = 'positive';
		if($value < 0) $class = 'negative';

		return "<span class='number ".$class."'>".number_format($value, $this->precision)."</span>";

	}

}