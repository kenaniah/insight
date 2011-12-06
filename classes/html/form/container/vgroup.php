<?php
namespace HTML\Form\Container;
use \HTML\Form\Layout\Vertical;
/**
 * A vertical grouping Container, used to contain a set of Containable elements
 */
class VGroup extends Container {

	public function __construct($namespace = null){

		//set VerticalLayout as the default layout
		$this->layoutManager = new Vertical();
		parent::__construct($namespace);
	}

}

