<?php
namespace HTML\Form\Field;

use Format\FormatPercentage;

/**
 * Used for fields that are meant to be percentages
 */
class Percentage extends Number {


	function __construct($name, $params = array()){
		parent::__construct($name, $params);
		$this
			->addClass('percentage')
			->addClass('right')
			->setAttribute('step', '0.001')
		;
		$this->formatter = new FormatPercentage($this->value);
	}

}