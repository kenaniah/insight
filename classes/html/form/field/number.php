<?php
namespace HTML\Form\Field;

class Number extends Text {

	function __construct($name, $params = array()){
		parent::__construct($name, $params);
		$this->setAttribute('type', 'number');
	}

}