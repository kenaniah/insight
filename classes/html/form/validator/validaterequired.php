<?php
namespace HTML\Form\Validator;
use HTML\Form\Field\Hidden;

use HTML\Form\Field\FormField;

/**
 * This validator will check to make sure a form field has received any input from the user,
 * and if not it will generate an error message.
 * Returns true if validiation is successful, false otherwise.
 * @return boolean
 */
class ValidateRequired extends Validator {

	public function validate(FormField $field) {
		$value = $field->getValue();

		if(!strlen($value) && !($field instanceof Hidden)):
			$field->addValidationMessage('This is a required field.');
			return false;
		endif;

		return true;
	}
}