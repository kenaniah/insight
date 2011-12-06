<?php
namespace HTML\Form\Field;
use \Format\Format;

/**
 * Renders only a tooltip in HTML / Form views
 */
class Tooltip extends FormField {

	function setValue($value){
		$this->setTooltip($value);
		return parent::setValue($value);
	}

	function __toString(){

		return $this->renderTooltip();

	}

}