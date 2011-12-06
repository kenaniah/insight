<?php
namespace HTML\Form\Validator;
/**
 * Checks form field elements to see if user input data meets business requirements
 *
 */
use HTML\Form\Field\FormField;

abstract class Validator {

	/**
	 * Returns true if $value meets the business requirements.
	 * If the requirements are not met and the validation fails,
	 * this function will return false and error messages will be set.
	 * @return boolean
	 */
	public abstract function validate(FormField $field);


}