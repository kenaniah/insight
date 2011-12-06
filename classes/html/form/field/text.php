<?php
namespace HTML\Form\Field;

class Text extends FormField {

	function __construct($name, $params = array()){
		$this->setAttribute('type', 'text');
		parent::__construct($name, $params);
	}

	function setValue($value){
		$this->setAttribute('value', $value);
		return parent::setValue($value);
	}

}