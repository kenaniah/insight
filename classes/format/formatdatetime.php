<?php
namespace Format;
/**
 * Formats the value using DateTime formatters
 */
class FormatDateTime extends Format {

	public $format = "n/j/Y h:i A";

	/**
	 * Sets the formatting string
	 * @param string $format
	 */
	function setFormat($format){
		$this->format = $format;
		return $this;
	}

	function raw($value){

		if(!$value) return null;

		if($value instanceof \DateTime):
			return $value->format($this->format);
		else:
			try {
				$value = new \DateTime($value);
				return $value->format($this->format);
			} catch (\Exception $e) {
				\Errors::add("Invalid time (" . $value . ") passed to formatter.");
				return null;
			}
		endif;

	}

}