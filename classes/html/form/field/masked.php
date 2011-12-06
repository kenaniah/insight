<?php
namespace HTML\Form\Field;

use Format\FormatEncrypted;

class Masked extends FormField {

	function __construct($name, $params = array()){
		$this->setAttribute('type', 'text');
		$this->setFormatter(new FormatEncrypted);
		parent::__construct($name, $params);
	}

	function setValue($value){
		$value = \MCrypt::quickDecrypt($value);
		$this->setAttribute('value', $value);
		return parent::setValue($value);
	}

}