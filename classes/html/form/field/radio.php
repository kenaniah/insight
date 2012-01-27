<?php
namespace HTML\Form\Field;
use HTML\Form\iCascadeProperties;
use HTML\Form\Container\Form;

class Radio extends FormField implements iCascadeProperties {

	protected $checked = false;
	protected $checked_value = null;
	protected static $groups = array();
	protected $group;

	/**
	 * $attrs is overridden. A special attribute exists for checkboxes:
	 *  - checkedValue 		value reported when checkbox is checked
	 *  All other entries in the $atts array perform as expected
	 */
	function __construct($name, $params = array()){

		$this->setAttribute('type', 'radio');

		//Sets the checked value if it was defined
		if(isset($params['attrs']['checkedValue'])):
			$this->setCheckedValue($params['attrs']['checkedValue']);
		endif;

		parent::__construct($name, $params);

		$this->checkGroup();

	}

	/**
	 * Sets the checked value if this individual field
	 * @param string $value
	 */
	function setCheckedValue($value){
		$this->setAttribute('value', $value);
		$this->checked_value = $value;
		$this->updateGroup();
		return $this;
	}

	/**
	 * Updates all radio buttons in the group
	 */
	protected function updateGroup(){
		$this->checkGroup();
		foreach($this->group as $radio):
			$radio->setValue($this->value, false);
			$radio->setChecked(!is_null($this->value) && $this->value == $radio->getCheckedValue(), false);
		endforeach;
	}

	/**
	 * Returns the checked value of this field
	 */
	function getCheckedValue($format_mode = null){
		return $this->checked_value;
	}

	/**
	 * Checks or unchecks the field based on the value provided.
	 */
	function setValue($value, $cascade = true){
		$this->checkGroup();
		$this->value = $value;
		if($cascade) $this->updateGroup();
		return $this;
	}

	function getValue($format_mode = null){
		$this->checkGroup();
		return parent::getValue($format_mode);
	}

	/**
	 * Forcefully checks or unchecks the field regardless of value
	 */
	function setChecked($boolean, $cascade = true){
		$this->checkGroup();
		$this->checked = (boolean) $boolean;
		if($this->checked):
			$this->setAttribute('checked', 'checked');
		else:
			$this->removeAttribute('checked');
		endif;
		if($cascade) $this->updateGroup();
		return $this;
	}

	/**
	 * Returns the checked / unchecked status of the field
	 */
	function isChecked(){
		return $this->checked;
	}

	function setForm(Form $form){
		$res = parent::setForm($form);
		$this->checkGroup();
		return $res;
	}

	/**
	 * Ensures that this radio button is attached to a radio button group.
	 */
	function checkGroup(){

		$hash = $this->form ? spl_object_hash($this->form) : '';
		$name = $this->getFullName();

		//Initialize the group
		if(!isset(self::$groups[$hash])):
			self::$groups[$hash] = array();
		endif;
		if(!isset(self::$groups[$hash][$name])):
			self::$groups[$hash][$name] = new \ObjectStorage;
		endif;
		$group = self::$groups[$hash][$name];

		//Remove from old group
		if($this->group && $this->group !== $group) $this->group->detach($this);
		$this->group = $group;

		//Attach this radio button to a group if it doesn't already exist
		if(!$this->group->contains($this)) $this->group->attach($this);

		return $this;

	}

	/**
	 * Overridden to ensure that group membership changes if this radio button moves
	 * @see Element::setAttribute()
	 */
	function setAttribute($name, $value){
		if($name == 'name'):
			parent::setAttribute($name, $value);
			$this->checkGroup();
			return $this;
		else:
			return parent::setAttribute($name, $value);
		endif;
	}

	function __clone(){
		parent::__clone();
		//Clears the current group after cloning
		$this->group = null;
		$this->checkGroup();
	}

	function cascadeProperties(){
		$this->checkGroup();
	}

}