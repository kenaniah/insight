<?php
namespace HTML\Form\Validator;
use Format\Format;

use HTML\Form\Field\FormField;

/**
 * This Validator checks to make sure that the input is numeric.
 */
class ValidateZipcode extends Validator {

	public function validate(Formfield $field) {

		$match = preg_match("/^[0-9]{5}(-[0-9]{4})?$/", $field->getValue(Format::FORM));
		if(!$match):
			$field->addValidationMessage('Zipcode must be 5 or 9 digits.');
			return false;
		endif;

		return true;
	}
}