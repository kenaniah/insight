<?php
namespace HTML\Form\Container;
use HTML\Form\Layout\TabSet as TabSetLayout;
/**
 * Container that creates a jQueryUI tab set from containers
 * Uses the label of the container as the tab name
 */
class TabSet extends Container {

	public function __construct($namespace = null){

		//Set the default layout
		$this->layoutManager = new TabSetLayout;
		$this->addClass("tabs");
		parent::__construct($namespace);

	}

	/**
	 * (non-PHPdoc)
	 * @see Container::__toString()
	 */
	public function __toString() {

		$out = self::indent() . '<div'.$this->outputAttributes().'>';
		$out .= parent::__toString();
		$out .= self::indent() . "</div>";

		return $out;
	}

}