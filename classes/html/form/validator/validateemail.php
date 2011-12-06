<?php
namespace HTML\Form\Validator;
use HTML\Form\Field\FormField;

/**
 * This Validator checks to make sure that the email provided by the user is of the proper email format.
 */
class ValidateEmail extends Validator {

	public function validate(FormField $field) {
		$value = $field->getValue();
		$e = "^(?=^((?!\.\.).)*$)[a-zA-Z0-9][_a-zA-Z0-9+.-]*@[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z]{2,6}$";

		if(!preg_match('/'.str_replace('/', '\/', $e) . '/', $value)):
			$field->addValidationMessage('Email address is invalid.');
			return false;
		endif;

		return true;
	}
}
