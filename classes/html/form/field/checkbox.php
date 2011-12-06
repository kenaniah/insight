<?php
namespace HTML\Form\Field;

class Checkbox extends FormField {

	protected $checked = false;
	protected $unchecked_value = null;

	/**
	 * $attrs is overridden. Two special attributes exist for checkboxes:
	 *  - checkedValue 		value reported when checkbox is checked
	 *  - uncheckedValue	value reported when checkbox is not checked
	 *  All other entries in the $atts array perform as expected
	 */
	function __construct($name, $params = array()){

		$this->setAttribute('type', 'checkbox');

		if(isset($params['attrs']['checkedValue'])) $this->setCheckedValue($params['attrs']['checkedValue']);
		if(isset($params['attrs']['uncheckedValue'])) $this->setUncheckedValue($params['attrs']['uncheckedValue']);

		parent::__construct($name, $params);

	}

	/**
	 * Sets the form value of the field
	 * @param string $value
	 */
	function setCheckedValue($value){
		$this->setAttribute('value', $value);
		return parent::setValue($value);
	}

	/**
	 * Returns the form value of the field
	 */
	function getCheckedValue(){
		return parent::getValue();
	}

	/**
	 * Sets the value to report when the field is unchecked
	 * @param mixed $value
	 */
	function setUncheckedValue($value){
		$this->unchecked_value = $value;
		return $this;
	}

	/**
	 * Returns the value used when the field is unchecked
	 */
	function getUncheckedValue(){
		return $this->unchecked_value;
	}

	/**
	 * Returns either the checked or unchecked value of the field
	 */
	function getValue(){
		return $this->checked ? parent::getValue() : $this->unchecked_value;
	}

	/**
	 * Checks or unchecks the field based on the value provided.
	 */
	function setValue($value){
		$this->setChecked($value == parent::getValue());
		return $this;
	}

	/**
	 * Forcefully checks or unchecks the field regardless of value
	 */
	function setChecked($boolean){
		$this->checked = (boolean) $boolean;
		if($this->checked) $this->setAttribute('checked', 'checked');
		else $this->removeAttribute('checked');
		return $this;
	}

	/**
	 * Returns the checked / unchecked status of the field
	 */
	function isChecked(){
		return $this->checked;
	}

}