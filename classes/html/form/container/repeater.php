<?php
namespace HTML\Form\Container;
use HTML\Form\Field\Button;

use Format\Format;
/**
 * Displays output from a datasource by passing it through the forms interface.
 * Repeater is a specialized container, and exhibits behaviors that differ from
 * its siblings. Repeaters display a variable number of records (whereas regular
 * containers handle only a single record). Repeated containers also require a
 * namespace in order to repeat properly.
 */
class Repeater extends Container {

	/**
	 * Tracks the value set to this function
	 * @var array
	 */
	protected $value = array();

	/**
	 * Tracks whether or not the value was supplied
	 * @var boolean
	 */
	protected $null_value = false;

	/**
	 * Tracks whether or not a repeater is allowed to add new instances
	 * @var boolean
	 */
	protected $dynamic_add = false;

	/**
	 * Tracks the number of items to show by default
	 * @var integer
	 */
	protected $show_default = 1;

	public $child_indent = -1;

	/**
	 * The child container that we're repeating
	 * @var Container
	 */
	protected $proxy;
	protected $real_children;

	/**
	 * Tracks whether or not this container was validated
	 * @var boolean
	 */
	protected $validate_called = false;

	/**
	 * Builds a repeater of the given $child
	 * @param Container $child
	 * @param string $namespace
	 */
	function __construct(Container $child = null, $namespace = null){
		$this->proxy = $child ?: new HGroup;
		parent::__construct($namespace);
		parent::addChild($this->proxy);
		$this->real_children = $this->children;
		$this->children = $this->proxy->getChildren();
	}

	/**
	 * Sets the record set to be used
	 */
	function setValue($value){

		//Namespace support
		if($this->namespace):
			$value = isset($value[$this->namespace]) ? $value[$this->namespace] : array();
		endif;

		$this->null_value = false;

		if(is_array($value)):
			$this->value = $value;
		elseif($value):
			$data = array();
			foreach($value as $v) $data[] = $v;
			$this->value = $data;
		else:
			$this->value = array();
		endif;

		return $this;
	}

	/**
	 * Returns the entire value set, as formatted by the fields
	 */
	function getValue($format_mode = null){

		$data = array();

		$prefix = $this->name_prefix;
		if($this->namespace) $prefix[] = $this->namespace;

		$value = $this->value;

		//Namespace support
		if($this->proxy->namespace):
			$value = isset($value[$this->proxy->namespace]) ? $value[$this->proxy->namespace] : array();
		endif;

		//Prepare the cloned object
		$clone = clone($this->proxy);
		$clone->name_prefix = $prefix;
		if($clone->namespace):
			$clone->name_prefix[] = $clone->namespace;
			$clone->namespace = null;
		endif;

		$prefix = $clone->name_prefix;
		foreach($value as $k => $v):
			$clone->name_prefix = $prefix;
			$clone->name_prefix[] = $k;
			$data[] = $clone->setValue($v)->getValue($format_mode);
		endforeach;

		return $this->proxy->namespace ? array($this->proxy->namespace => $data) : $data;

	}

	/**
	 * Validates the child elements
	 */
	function validate(){

		$this->validate_called = true;

		$data = array();

		$prefix = $this->name_prefix;
		if($this->namespace) $prefix[] = $this->namespace;

		$value = $this->value;

		//Namespace support
		if($this->proxy->namespace):
			$value = isset($value[$this->proxy->namespace]) ? $value[$this->proxy->namespace] : array();
		endif;

		//Prepare the cloned object
		$clone = clone($this->proxy);
		$clone->name_prefix = $prefix;
		if($clone->namespace):
			$clone->name_prefix[] = $clone->namespace;
			$clone->namespace = null;
		endif;

		$ok = true;
		$prefix = $clone->name_prefix;
		foreach($value as $k => $v):
			$clone->name_prefix = $prefix;
			$clone->name_prefix[] = $k;
			$ok = $clone->setValue($v)->validate() && $ok;
		endforeach;
		return $ok;

	}

	/**
	 * Sets whether or not this repeater will allow dynamic additions to it
	 * @param boolean $allowed
	 */
	function allowDynamicAdditions($allowed){

		$this->dynamic_add = (boolean) $allowed;
		return $this;

	}

	/**
	 * Outputs the contents of the repeater
	 */
	function __toString(){

		$out = "";

		//Cascade properties to the actual children (including repeated container)
		$tmp = $this->children;
		$this->children = $this->real_children;
		$this->cascadeProperties();
		$this->children = $tmp;

		$prefix = $this->name_prefix;
		if($this->namespace) $prefix[] = $this->namespace;

		$value = $this->value;

		//Namespace support
		if($this->proxy->namespace):
			$value = isset($value[$this->proxy->namespace]) ? $value[$this->proxy->namespace] : array();
		endif;

		//Prepare the cloned object
		$clone = clone($this->proxy);
		$clone->name_prefix = $prefix;
		if($clone->namespace):
			$clone->name_prefix[] = $clone->namespace;
			$clone->namespace = null;
		endif;
		foreach($clone->getChildren() as $child) $child->removeAttribute('id');
		$clone->removeAttribute('id');

		//Track the dynamic status
		$dynamic = $this->dynamic_add && $this->format_mode == Format::FORM;

		//Create a remove item button
		if($dynamic):
			$clone->ensureElementID();
			$clone->addChild(with(new Button(""))
				->addClass("event")
				->removeAttribute('name')
				->setAttribute("data-click-handler", "removeRepeatedItem")
				->setAttribute("data-icons", '{"primary": "ui-icon-minus"}')
				->setValue("remove")
			);
		endif;

		//Output each instance of the valueset
		$count = 0;
		$name_prefix = $clone->name_prefix;

		//Concatenate to $value the number of default items to display
		if($this->show_default > 0 && count($value) < $this->show_default):
			$value = array_merge($value, array_fill(0, $this->show_default - count($value), array()));
		endif;

		foreach($value as $k => $v):
			$count++;
			//We may reuse the same container instance when not displaying a form
			$clone->removeAttribute('id');
			$container = $this->format_mode == Format::FORM ? clone($clone) : $clone;
			$container->name_prefix = $name_prefix;
			$container->name_prefix[] = $k;
			if($dynamic):
				foreach($container->getChildren() as $button); //Iterate to the last element
				$button->setAttribute("data-which", $container->ensureElementID());
			endif;
			$container->setValue($v);
			if($this->validate_called) $container->validate();
			$out .= $container;
		endforeach;

		//Output the ability to dynamically add fields
		if($dynamic):
			$id = $this->ensureElementID();
			$replace = '$|$'.$id.'$|$'; //string to be replaced in the JS
			foreach($clone->getChildren() as $button); //Iterate to the last element
			//Replace child ids
			foreach($clone->getRecursiveChildren() as $child):
				$child_id = $child->getAttribute('id');
				if(!$child_id) continue;
				$child->setAttribute('id', $child_id . "-" . $replace);
			endforeach;
			//Update the remove button
			$clone->setAttribute('id', $clone->ensureElementID() . '-replace-'.$replace);
			$button->setAttribute("data-which", $clone->ensureElementID());
			$clone->setValue(null);
			$clone->name_prefix[] = $replace;
			$out .= $this->indent() . "<div id='".$id."-add-item' class='hidden move-me'>" . $clone . "</div>";
			$out .= $this->indent() . "<div><button class='event' data-click-handler='addRepeatedItem' data-count='".$count."' data-which='".$id."' data-icons='{\"primary\": \"ui-icon-plus\"}'>add new</button></div>";
		endif;

		return $out;

	}

	/**
	 * The number of items to show by default
	 * @param integer $number
	 */
	function setDefaultNumber($number){
		$this->show_default = intval($number);
		return $this;
	}

	/**
	* Overloaded to provide a pass through to the proxy container
	*/
	function getLabel($full = false) {
		return $this->proxy->getLabel($full);
	}

}