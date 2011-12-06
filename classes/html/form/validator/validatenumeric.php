<?php
namespace HTML\Form\Validator;
use HTML\Form\Field\FormField;

/**
 * This Validator checks to make sure that the input is numeric.
 */
class ValidateNumeric extends Validator {

	public function validate(Formfield $field) {

		if(!(is_numeric($field->getValue()))):
			$field->addValidationMessage('Input field must be numeric');
			return false;
		endif;

		return true;
	}
}