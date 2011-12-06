<?php
namespace HTML\Form\Field;

use Format\FormatPhone;

class Phone extends Text {

	function __construct($name, $params = array()){
		$this->formatter = new FormatPhone;
		parent::__construct($name, $params);
	}

}