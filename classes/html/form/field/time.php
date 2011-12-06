<?php
namespace HTML\Form\Field;

class Time extends Date {

	protected $format = "h:i a";

	function __construct($name, $params = array()){
		parent::__construct($name, $params);
		$this->addClass('time');
	}

}