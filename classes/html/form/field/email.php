<?php
namespace HTML\Form\Field;

use Format\FormatEmail;

class Email extends Text {

	function __construct($name, $params = array()){
		$this->formatter = new FormatEmail;
		parent::__construct($name, $params);
	}

}