<?php
namespace HTML\Form\Field;

class Password extends Text {

	function __construct($name, $params = array()){
		parent::__construct($name, $params);
		$this->setAttribute('type', 'password');
	}

}