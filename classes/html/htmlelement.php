<?php
namespace HTML;

use HTML\Form\iContainable;

/**
 * Creates an HTML element that can be used in containers and such
 */
class HTMLElement extends Element implements iContainable {

	/**
	 * Tracks the tag name for this element
	 * @var string
	 */
	protected $tag;

	/**
	 * Stores the contents of the HTML tag
	 * @var string
	 */
	protected $value;

	function __construct($tag_name, $value = null){

		$this->tag = $tag_name;
		if(isset($value)) $this->setValue($value);

	}

	function validate(){
		return true;
	}

	function setValue($value){
		$this->value = $value;
	}

	function getValue(){
		return $this->value;
	}

	function __toString(){

		$out = "<" . $this->tag . $this->outputAttributes() . ">";
		$out.= $this->getValue();
		$out.= "</" . $this->tag  . ">";

		return $out;

	}

}