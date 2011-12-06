<?php
namespace HTML\Form\Validator;
use HTML\Form\Field\FormField;

/**
 * This Validator ensures that two FormField elements' values match.
 */
class ValidateMatches extends Validator {

	protected $toMatch; //FormField element to match

	public function __construct(FormField $toMatch = null) {
		$this->toMatch = $toMatch;
	}

	public function validate(FormField $field) {
		if($field->getValue() !== $this->toMatch->getValue()):
			$field->addValidationMessage('Input fields do not match.'); //Need to improve this error message
			return false;
		endif;

		return true;
	}
}