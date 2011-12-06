<?php
namespace HTML\Form\Field;
use Format\Format;

class Textarea extends FormField {

	function __toString(){
		if($this->format_mode == Format::HTML):
			return (string) nl2br($this->getValue(Format::HTML));
		endif;
		$out = "<textarea" . $this->outputAttributes() . ">";
		$out.= $this->getValue();
		$out.= "</textarea>";
		return $out;
	}

}