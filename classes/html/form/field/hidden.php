<?php
namespace HTML\Form\Field;

class Hidden extends Text {

	function __construct($name, $params = array()){
		parent::__construct($name, $params);
		$this->setAttribute('type', 'hidden');
	}

	function __toString(){
		return "<input " . $this->outputAttributes() . "/>";
	}

}