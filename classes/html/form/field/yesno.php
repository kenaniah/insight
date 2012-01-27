<?php
namespace HTML\Form\Field;

use Format\FormatYesNo;
use HTML\Form\Layout\RadioCheck;
use HTML\Form\iCascadeProperties;
use HTML\Form\Container\HGroup;
use Format\Format;

class YesNo extends FormField implements iCascadeProperties {

	protected $container;

	function __construct($name, $params = array()) {

		$this->formatter = new FormatYesNo;
		$this->container = with(new HGroup())
			->addChildren(
				array(
					with(new Radio($name, array('label' => 'Yes')))->setCheckedValue(1),
					with(new Radio($name, array('label' => 'No')))->setCheckedValue(0))
			)
			->setLayoutManager(new RadioCheck())
		;

		parent::__construct($name, $params);

		$this->cascadeProperties();

	}

	function setName($name){
		$this->cascadeProperties();
		foreach($this->container->getChildren() as $child) $child->setName($name);
		return parent::setName($name);
	}

	function setValue($value){
		$this->cascadeProperties();
		$this->container->getChildAt(0)->setValue($value);
		//$this->container->setValue(array($this->getName() => $value));
		return parent::setValue($value);
	}

	function getValue($format_mode = null){
		$this->cascadeProperties();
		return parent::getValue($format_mode);
	}

	function cascadeProperties(){
		$container = $this->container;
		$container->form = $this->form;
		$container->container_value = $this->container_value;
		$container->indent = $this->indent;
		$container->name_prefix = $this->name_prefix;
		$container->format_mode = $this->format_mode;
		$container->cascadeProperties();
	}

	/**
	 * When clone is used, clone all children as well
	 */
	public function __clone(){

		$this->container = clone $this->container;

	}

	/**
	 * Renders the form field.
	 * Potentially overloaded for other field types.
	 * Outputs HTML version when using HTML format.
	 */
	function __toString(){

		$this->cascadeProperties();

		if($this->format_mode == Format::HTML):
			return (string) $this->getValue(Format::HTML);
		endif;

		return (string) $this->container;

	}
}