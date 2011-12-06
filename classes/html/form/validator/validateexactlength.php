<?php
namespace HTML\Form\Validator;
use HTML\Form\Field\FormField;

/**
 * This Validator checks to make sure that the length of an input is exactly a specified length.
 */
class ValidateExactLength extends Validator {

	protected $length; //exact length required for input field

	public function __construct($length = -1) {
		$this->length = (integer) $length;
	}

	public function validate(FormField $field) {
		if(strlen($field->getValue()) != $this->length):
			$field->addValidationMessage('Input length must be exactly '.$this->length.'.');
			return false;
		endif;

		return true;
	}
}