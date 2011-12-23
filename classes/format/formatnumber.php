<?php
namespace Format;

/**
 * Formats a FormField as a number
 */
class FormatNumber extends Format {

	protected $precision = 0;

	protected $positive = 'positive';
	protected $negative = 'negative';
	protected $zero = 'zero';
	protected $null = 'null';

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
		if(!strlen($value)) return null;
		return round($value, $this->precision);
	}

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::html()
	 */
	function html($value){

		$value = $this->raw($value);
		if(is_null($value)) return "<span class='number ".$this->null."'>-</span>";
		$class = $this->zero;
		if($value > 0) $class = $this->positive;
		if($value < 0) $class = $this->negative;

		return "<span class='number ".$class."'>".number_format($value, $this->precision)."</span>";

	}

	/**
	 * Sets the CSS class to use for positive numbers
	 * @param unknown_type $classname
	 */
	function setPositiveClass($classname){
		$this->positive = $classname;
		return $this;
	}

	/**
	 * Sets the CSS class to use for negative numbers
	 * @param unknown_type $classname
	 */
	function setNegativeClass($classname){
		$this->negative = $classname;
		return $this;
	}

	/**
	 * Sets the CSS class to use for zero numbers
	 * @param unknown_type $classname
	 */
	function setZeroClass($classname){
		$this->zero = $classname;
		return $this;
	}

	/**
	 * Sets the CSS class to use for zero numbers
	 * @param unknown_type $classname
	 */
	function setNullClass($classname){
		$this->null = $classname;
		return $this;
	}

}