<?php
namespace HTML\Form\Container;
use \HTML\Form\Layout\Table as TableLayout;
/**
 * Wraps child elements in an HTML table
 */
class Table extends Container {

	public function __construct($namespace = null, $cols = 1){

		//set TableLayout as the default layout
		$this->layoutManager = new TableLayout($cols);
		$this->addClass("pad");
		parent::__construct($namespace);
	}

	public function setColumns($cols){
		if(method_exists($this->layoutManager, 'setColumns')) $this->layoutManager->setColumns($cols);
		return $this;
	}

	public function getColumns(){
		if(!method_exists($this->layoutManager, 'getColumns')) return null;
		return $this->layoutManager->getColumns();
	}

	/**
	 * (non-PHPdoc)
	 * @see Container::__toString()
	 */
	public function __toString() {

		$out = self::indent() . '<table'.$this->outputAttributes().' cellpadding="0" cellspacing="0">';
		$out .= parent::__toString();
		$out .= self::indent() . "</table>";

		return $out;
	}
}