<?php
namespace HTML\Form\Field;
use \Format\Format;

class Span extends FormField {

	function __toString(){

		$out = "<span" . $this->outputAttributes() . ">";
		$out.= $this->getValue(Format::HTML);
		$out.= "</span>";
		return $out;

	}

}