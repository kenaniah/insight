<?php
namespace HTML\Form\Field;

class Submit extends Button {

	function __construct($name = null, $params = array()){
		parent::__construct($name, $params);
		$this->setAttribute('type', 'submit');
		$this->setValue('Submit');
	}

}