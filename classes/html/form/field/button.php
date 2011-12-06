<?php
namespace HTML\Form\Field;
use Format\Format;

class Button extends FormField {

	/**
	 * Whether or not this button will display when HTML formatted
	 * @var boolean
	 */
	protected $output_when_html = false;

	function __construct($name, $params = array()){
		parent::__construct($name, $params);
	}

	function setValue($value){
		return parent::setValue($value);
	}

	/**
	 * Sets whether or not this button will display when HTML formatted
	 * @param boolean $bool
	 */
	function setHtmlVisible($bool){
		$this->output_when_html = (boolean) $bool;
		return $this;
	}

	/**
	 * Outputs an HTML button. If the format mode is HTML,
	 * buttons will not be printed unless allowed by $output_when_html
	 */
	function __toString(){
		if($this->format_mode == Format::HTML && !$this->output_when_html) return;
		return "<button" . $this->outputAttributes() . ">" . $this->value . "</button>";
	}

}