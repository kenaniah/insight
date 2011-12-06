<?php
namespace HTML\Form\Field;

class Timestamp extends Date {

	protected $format = "n/d/Y h:i A";

	function __construct($name, $params = array()){
		parent::__construct($name, $params);
		$this->addClass('datetime');
	}

}