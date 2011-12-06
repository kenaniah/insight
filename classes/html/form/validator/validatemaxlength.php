<?php
namespace HTML\Form\Validator;
use HTML\Form\Field\FormField;

/**
 * This validator ensures that the input by the user is at most a maximum length
 */
class ValidateMaxLength extends Validator {

	protected $maxlength; //Maximum length for user input

	/**
	 * Used to initialize $maxlength.
	 * @param mixed $maxlength Maximum allowed length for input
	 */
	public function __construct($maxlength = -1) {
		$this->maxlength = (integer) $maxlength;
	}

	public function validate(FormField $field) {
		if(strlen($field->getValue()) > $this->maxlength):
			$field->addValidationMessage('The maximum length for this input is '.$this->maxlength.'.');
			return false;
		endif;

		return true;
	}
}