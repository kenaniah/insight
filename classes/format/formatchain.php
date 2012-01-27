<?php
namespace Format;
use Utils;
use Helpers;

/**
 * Abstract class that allows a formatter to extend another via chaining
 */
abstract class FormatChain extends Format {

	/**
	 * The formatter to be chained
	 * @var Format
	 */
	protected $formatter;

	function __construct(Format $formatter){
		$this->formatter = $formatter;
	}

	function raw($value){
		return $this->formatter->format($value, Format::RAW, $this->container_value);
	}

	function html($value){
		return $this->formatter->format($value, Format::HTML, $this->container_value);
	}

	function form($value){
		return $this->formatter->format($value, Format::FORM, $this->container_value);
	}

	function export($value){
		return $this->formatter->format($value, Format::EXPORT, $this->container_value);
	}

}