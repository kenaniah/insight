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
			array(with(new Radio($name, array('label' => 'Yes')))->setCheckedValue(1), with(new Radio($name, array('label' => 'No')))->setCheckedValue(0))
			)
			->setLayoutManager(new RadioCheck())
		;

		parent::__construct($name, $params);

		$this->cascadeProperties();

	}

	function setValue($value){
		$this->cascadeProperties();
		foreach($this->container->getChildren() as $child):
			$child->setValue($value);
			break;
		endforeach;
		$this->value = $value;
		return $this;
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