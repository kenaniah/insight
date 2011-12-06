<?php
namespace HTML\Form\Field;

class Reset extends Button {

	function __construct($name = null, $params = array()){
		parent::__construct($name, $params);
		$this->addClass('reset');
		$this->setValue("Reset");
	}

}