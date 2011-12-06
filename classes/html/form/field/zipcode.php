<?php
namespace HTML\Form\Field;

use HTML\Form\Validator\ValidateZipcode;
use Format\FormatZipcode;

class Zipcode extends Text {

	function __construct($name, $params = array()){
		$this->formatter = new FormatZipcode;
		parent::__construct($name, $params);
		$this->addValidator(new ValidateZipcode);
	}

}