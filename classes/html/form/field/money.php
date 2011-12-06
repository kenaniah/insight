<?php
namespace HTML\Form\Field;

use Format\FormatMoney;

/**
 * Used for fields that are meant to represent currency
 */
class Money extends Number {

	function __construct($name, $params = array()){
		parent::__construct($name, $params);
		$this
			->addClass('money')
			->addClass('right')
			->setAttribute('step', '0.01')
		;
		$this->formatter = new FormatMoney($this->value);
	}

}