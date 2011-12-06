<?php
namespace HTML\Form\Validator;
use HTML\Form\Field\FormField;

/**
 * This validator ensures that the input by the user is at least a minimum length
 */
class ValidateMinLength extends Validator {

	protected $minlength; //Minimum length for user input

	/**
	 * Used to initialize $minlength.
	 * @param mixed $minlength Minimum required length
	 */
	public function __construct($minlength = -1) {
		$this->minlength = (integer) $minlength;
	}

	public function validate(FormField $field) {
		if(strlen($field->getValue()) < $this->minlength):
			$field->addValidationMessage('The minimum length for this input is '.$this->minlength.'.');
			return false;
		endif;

		return true;
	}
}