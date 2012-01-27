<?php
namespace HTML\Form\Field;
use \Format\Format;

class Span extends FormField {

	function __toString(){

		$out = "<span" . $this->outputAttributes() . ">";
		$out.= $this->getValue(Format::HTML);
		$out.= "</span>";

		if($this->format_mode == Format::FORM):
			$out .= with(new Hidden($this->getFullName(false)))->setValue($this->getValue(Format::HTML));
		endif;

		return $out;

	}

}