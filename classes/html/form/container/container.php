<?php
namespace HTML\Form\Container;

use HTML\Form\Field\Checkbox;
use HTML\Form\Layout\LayoutManager;
use HTML\Form\Field\Radio;
use HTML\Form\iCascadeProperties;
use Format\Format;
use HTML\Form\Field\Button;
use \HTML\Form\iContainable;
use \HTML\Form\Field\FormField;
use \HTML\Form\Layout\Horizontal;

/**
 * Containers are used to store instances of iContainable
 * They are rendered using the strategy provided by an instance of LayoutManager
 */
abstract class Container extends \HTML\Element implements iContainable, iCascadeProperties {

	/**
	 * Tracks the last set of values passed to this container
	 * @var array
	 */
	public $container_value = array();

	/**
	 * The instance of a LayoutManager responsible for rendering this container
	 * (LayoutManager is the 'Strategy' used)
	 * @var LayoutManager
	 */
	protected $layoutManager;

	/**
	 * Used to define a prefix for which all contained fields will use
	 * @var string
	 */
	protected $namespace;

	/**
	 * Contains a list of children contained by this container
	 * @var ObjectStorage
	 */
	protected $children;

	/**
	 * Tracks whether or not the children of this container have been marked as required
	 * through the container interface.
	 * @var bool
	 */
	protected $required;

	/**
	 * Instance of dependency injector container
	 * @var Injector
	 */
	public $injector;

	/**
	 * Defines the label for the container
	 * @var string
	 */
	protected $label;

	/**
	 * Specifies how the container should be rendered when formatted
	 * @var string
	 */
	public $format_mode = Format::FORM;

	/**
	 * Tracks whether or not the container value has been changed.
	 * This determines whether or not to push the value to the children
	 * @var boolean
	 */
	protected $container_value_changed = false;

	public function __construct($namespace = null, Injector $injector = null){

		$this->injector = is_null($injector) ? \Registry::get('injector') : $injector;

		//Initialize the children object
		$this->children = new \ObjectStorage();

		//Initialize the namespace and layoutManager objects, if set
		if(isset($namespace)) $this->namespace = $namespace;
		if(!$this->layoutManager) $this->layoutManager = new Horizontal();
	}

	/**
	 * (non-PHPdoc)
	 * @see HTML.Element::validate()
	 */
	public function validate() {
		$ok = true;
		foreach($this->children as $child):
			$ok = $child->validate() && $ok;
		endforeach;
		return $ok;
	}

	/**
	 * Sets the layout manager for the container
	 * @param LayoutManager $layoutManager
	 */
	public function setLayoutManager(LayoutManager $layoutManager){
		$this->layoutManager = $layoutManager;
		return $this;
	}

	/**
	 * (non-PHPdoc)
	 * @see iContainable::__toString()
	 */
	public function __toString(){
		//Updates all children with relevant info
		$this->cascadeProperties();
		return $this->layoutManager->render($this);
	}

	/**
	 * Adds a child to the container
	 * @param iContainable $child
	 */
	public function addChild(iContainable $child){
		$this->children->attach($child);
		return $this;
	}

	/**
	 * Adds multiple children to this container at the same time
	 * @param array $children
	 */
	public function addChildren(array $children){
		foreach($children as $child) $this->addChild($child);
		return $this;
	}

	/**
	 * Adds a child to the container at the given index
	 * @param iContainable $child
	 * @param integer $index
	 */
	public function addChildAt(iContainable $child, $index){
		$this->children->insertAt($child, $index);
		return $this;
	}

	/**
	 * Adds multiple children to this container at the given index
	 * @param array $children
	 * @param integer $index
	 */
	public function addChildrenAt(array $children, $index){
		$this->children->insertAt($children, $index);
		return $this;
	}

	/**
	 * Removes a child from the container
	 * @param iContainable $child
	 */
	public function removeChild(iContainable $child){
		$this->children->detach($child);
		return $this;
	}

	/**
	 * Removes the child at the given index from the container
	 * @param integer $index
	 */
	public function removeChildAt($index){
		$this->children->removeAt($index);
		return $this;
	}

	/**
	 * Returns the child at the given index
	 * @param integer $index
	 */
	public function getChildAt($index){
		return $this->children->itemAtIndex($index);
	}

	/**
	 * Returns the SPLObject that tracks the element's children
	 * (this will be a collection of iContainable objects)
	 */
	public function getChildren(){
		return $this->children;
	}

	/**
	 * Returns an array of all children in this container recursively
	 */
	public function getRecursiveChildren(){
		$out = array();
		foreach($this->children as $child):
			if($child instanceof self) $out = array_merge($out, $child->getRecursiveChildren());
			$out[] = $child;
		endforeach;
		return $out;
	}

	/**
	 * Returns the namespace being used for all contained fields
	 * @return string
	 */
	public function getNamespace() {
		return $this->namespace;
	}

	/**
	 * Sets the namespace to be used to define prefix for all contained fields
	 * @param string $namespace
	 */
	public function setNamespace($namespace) {
		$this->namespace = $namespace;
		return $this;
	}

	/**
	 * Convenient fluid setter for the public format_mode property
	 * @param string $mode
	 */
	public function setFormatMode($mode){
		$this->format_mode = $mode;
		return $this;
	}

	/**
	 * Convenient getter for the public format_mode property
	 */
	public function getFormatMode(){
		return $this->format_mode;
	}

	/**
	 * Cascades values to children.
	 * @see iContainable::setValue()
	 */
	public function setValue($value) {

		//Cast to an array
		if(!is_array($value)) $value = (array) $value;

		//Namespace support
		if($this->namespace):
			$value = isset($value[$this->namespace]) ? $value[$this->namespace] : array();
		endif;

		//Keep track of the container value
		$this->container_value = $value;
		$this->container_value_changed = true;

		//Updates all children with relevant info
		$this->cascadeProperties();

		foreach($this->children as $child):

			//Children by definition are at least instances of iContainer
			if($child instanceof FormField):

				//Don't set the value of buttons
				if($child instanceof Button) continue;

				$val = $value;

				//Set value for form fields
				$name = $child->getName();

				//Don't set a value if the element's name is null
				if(!isset($name)) continue;

				$dimensions = self::parseArrayDimensions($name);

				//Search the array dimensions
				foreach($dimensions as $dimension):
					if($dimension === '') break;
					if(array_key_exists($dimension, $val)):
						$val = $val[$dimension];
					else:
						$val = null;
						break;
					endif;
				endforeach;

				//Handle checkbox arrays
				if(is_array($val) && $child instanceof Checkbox):
					$child->setChecked(in_array($child->getCheckedValue(), $val));
					continue;
				endif;

				$child->setValue($val);

			else:
				//Set value for containers
				$child->setValue($value);
			endif;

		endforeach;

		return $this;

	}

	/**
	 * Returns an array of values indexed by field name. Namespaced
	 * containers will subgroup their fields in a sub array.
	 * @param string $format_mode Returns values according to this format. Null returns unformatted values.
	 */
	public function getValue($format_mode = null){

		$data = array();

		foreach($this->children as $child):
			if($child instanceof FormField):
				//Form element
				$name = $child->getName();
				if(!$name) continue;
				$data[$name] = $child->getValue($format_mode);
			elseif($child instanceof Container):
				//Container
				$ns = $child->getNamespace();
				$vals = $child->getValue($format_mode);
				if($ns):
					if(!isset($data[$ns]) || !is_array($data[$ns])) $data[$ns] = array();
					$data[$ns] = array_merge($data[$ns], $vals);
				else:
					$data = array_merge($data, $vals);
				endif;
			endif;
		endforeach;

		return $data;

	}

	/**
	 * When clone is used, clone all children as well
	 */
	public function __clone(){

		$clone = new \ObjectStorage;
		foreach($this->children as $child):
			$clone->attach(clone $child);
		endforeach;
		$this->children = $clone;

	}

	/**
	 * Updates children by setting name prefixes, indentation, formatting mode, etc.
	 * Name prefixes are used to logically group form fields into arrays.
	 * Indentation is used to format the HTML source code.
	 */
	public function cascadeProperties(){
		$prefix = $this->name_prefix;
		if($this->namespace) $prefix[] = $this->namespace;
		foreach($this->children as $child):
			if($this->container_value_changed) $child->container_value = $this->container_value;
			$child->indent = $this->indent + $this->child_indent;
			$child->name_prefix = $prefix;
			$child->format_mode = $this->format_mode;
			$child->injector = $this->injector;
			if($child instanceof iCascadeProperties) $child->cascadeProperties();
		endforeach;
	}

	/**
	 * Returns an array of error messages for every validator contained in every child of this container.
	 * @return array
	 */
	public function getValidationMessages() {

		$messages = array();

		foreach ($this->children as $child):
			if($child instanceof FormField):
				$msgs = $child->getValidationMessages();

				$label = $child->getLabel() ? '<b>' . $child->getLabel() . ':</b> ' : '';
				foreach($msgs as $i => $msg) $msgs[$i] = $label . $msg;

				if($msgs):
					$messages[$child->getAttribute('id')] = $msgs;
				endif;

			else:
				$messages = array_merge($messages, $child->getValidationMessages());
			endif;
		endforeach;

		return $messages;
	}

	/**
	 * Sets the required property on children, optionally cascading through child containers
	 * @param bool $required
	 * @param bool $cascade
	 */
	public function setRequired($required, $cascade = false) {

		$this->required = $required;

		foreach($this->getChildren() as $child):
			if($cascade) $child->setRequired($required, true);
			elseif($child instanceof FormField) $child->setRequired($required, false);
		endforeach;

		return $this;
	}

	/**
	 * Shows or hides all tooltips in the container
	 * @param bool $show
	 */
	public function showTooltip($show) {
		foreach($this->getChildren() as $child) $child->showTooltip($show);
		return $this;
	}

	/**
	 * Set the Form on all of the children inside the Container
	 * @param Form $form
	 */
	function setForm(Form $form) {
		foreach($this->getChildren() as $child):
			$child->setForm($form);
		endforeach;
	}

	/**
	 * Returns the container's label
	 * @return string
	 */
	function getLabel($full = false) {
		if(!strlen(trim($this->label))) return '';

		$required = $this->required ? '*' : '';
		$label = !in_array(substr($this->label, -1), array(':', '?')) ? ':' : '';

		return $this->label . ($full ? $label . $required : '');
	}

	/**
	 * Sets the container's label
	 * @param string $label
	 */
	function setLabel($label) {
		$this->label = $label;
		return $this;
	}

	/**
	 * Handles AJAX fields that may be associated with the form
	 */
	function handleAutocompletes(){
		if(!empty($_POST['ajax_id'])):
			foreach($this->getRecursiveChildren() as $child):
				if($child instanceof \HTML\Form\Field\Autocomplete) $child->handle();
			endforeach;
		endif;
		return $this;
	}

	/**
	 * Handles document uploads that may be associated with the form
	 */
	function handleFileUploads(){
		if(!empty($_FILES)):
			foreach($this->getRecursiveChildren() as $child):
				if($child instanceof \HTML\Form\Field\Document) $child->handle();
			endforeach;
		endif;
		return $this;
	}

}

