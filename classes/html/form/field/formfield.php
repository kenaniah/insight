<?php
namespace HTML\Form\Field;

use Format\Format;
use HTML\Form\Validator\ValidateRequired;
use \HTML\Form\Container\Form;
use \HTML\Element;
use \HTML\Form\iContainable;
use \HTML\Form\Validator\Validator;

/**
 * Defines the basic class for all form fields
 * @author Kenaniah Cerny <kenaniah@gmail.com>
 */
abstract class FormField extends Element implements iContainable {

	protected $value; //The field's value
	protected $label; //The text for this field's HTML label
	protected $tooltip; //Tooltip text for this field
	protected $form; //A reference to the form this field belongs to
	protected $validationMessages = array(); //Tracks message from validation errors
	protected $show_tooltip = true; //Show or hide the tooltip for this FormField
	protected $ordering; //Order in which the form field should be displayed in the form
	protected $meta_data = array(); //Stores the meta data for this field

	/**
	 * Tracks the container's value
	 * @var array
	 */
	public $container_value = array();

	/**
	 * Instance of a Format used to format the field when rendering
	 * @var Format
	 */
	protected $formatter;

	/**
	 * Determines whether or not the FormField should be displayed
	 * @var bool
	 */
	public $is_visible;

	/**
	 * Tracks whether or not the FormField has been marked as required
	 * @var bool
	 */
	protected $required;

	/**
	 *
	 * Array of validators used to check and enforce different requirements for user input
	 * @var array
	 */
	protected $validators = array();

	/**
	 * Instance of dependency injector container
	 * @var Injector
	 */
	public $injector;

	/**
	 * Tracks the current formatting mode for this element
	 * @var string
	 */
	public $format_mode = Format::FORM;

	/**
	 * Creates a form field using the metadata from the field name
	 * @param string $fqfn The fully qualified field name (eg. "contacts.first_name")
	 */
	public static function build($fqfn, \Injector $injector = null){

		if(!$injector) $injector = \Registry::get('injector');

		$meta_data = \Schema::getMetaData($injector, $fqfn);
		if(!$meta_data) user_error("Field " . $fqfn . " could not be constructed.", E_USER_ERROR);

		return self::_build($meta_data, $injector);

	}

	/**
	 * Performs the work of building a form field from metadata
	 * @param array $meta_data
	 * @param \Injector $injector
	 */
	protected static function _build($meta_data, \Injector $injector){

		$class = '\HTML\\Form\\Field\\' . $meta_data['field_class'];
		$params = array(
			'meta_data' => $meta_data,
			'injector' => $injector
		);
		$field = new $class($meta_data['field_name'], $params);

		//Use the datasource provided in the meta data as defined in the Data class
		if(!empty($meta_data['datasource']) && method_exists($field, 'setOptions')):
			$field->setOptions(function() use($meta_data){
				return \Data::get($meta_data['datasource']);
			});

		//Default to foriegn table name as the data source. Use the Data class equivalent if it exists.
		elseif(!empty($meta_data['fk_table_name']) && method_exists($field, 'setOptions')):
			if(!empty(\Data::$queries[$meta_data['fk_table_name']])):
				$field->setOptions(function() use($meta_data){
					return \Data::get($meta_data['fk_table_name']);
				});
			else:
				$db = $injector->db;
				$field->setOptions(function() use($db, $meta_data){
					return $db->getCached("SELECT id, name FROM " . $meta_data['fk_table_name'] . " ORDER BY 2");
				});
			endif;

		//Handle autocomplete sources
		elseif(!empty($meta_data['fk_table_name']) && method_exists($field, 'setHandler')):
			$field->setHandler(function($term, $id, \Injector $injector) use ($meta_data){
				return $injector->db->getAll("
					SELECT id as value, name as label
					FROM " . $meta_data['fk_table_name'] . "
					WHERE name ILIKE '%'||?||'%' OR id = ?
					ORDER BY name",
					array($term, intval($id))
				);
			});

		endif;

		return $field;

	}

	/**
	 * Builds an array of form fields from the given table
	 * @param string $fqtn The fully qualified table name to build fields for (eg. "pricing_model.estimates")
	 * @param \Injector $injector
	 */
	public static function buildAll($fqtn, \Injector $injector = null){

		if(!$injector) $injector = \Registry::get('injector');

		//separate the table and field names from the fqtn
		$schema = 'public';
		$table = $fqtn;
		if(substr_count($fqtn, ".") == 1):
			list($schema, $table) = explode(".", $fqtn, 2);
		endif;

		$meta_data = \Schema::getTableData($injector, $table, $schema);

		$return = array();
		foreach($meta_data as $name => $field):
			if(!$field['is_visible']) continue;
			$return[] = self::_build($field, $injector);
		endforeach;

		return $return;

	}

	/**
	 * Constructs a form field with the given parameters. $params may include:
	 *  - string $label The label of the element
	 *  - array $attrs Attributes to be set on the element
	 *  - mixed $value The value for the element
	 *  - Injector $injector The injector to be used on this element
	 *  - boolean $required Whether or not this element is required
	 *  - array $meta_data Meta data to be passed to element (to prevent duplicate lookups)
	 * @param string $name The name of the form field
	 * @param array $params See description above for a list of parameters
	 */
	function __construct($name, $params = array()) {

		//Assume a label was passed if params is a string
		if(is_string($params)) $params = array('label' => $params);

		$this->injector = isset($params['injector']) && ($params['injector'] instanceof \Injector) ? $params['injector'] : \Registry::get('injector');
		$this->addValidator(new ValidateRequired());

		$meta_data = !empty($params['meta_data']) ? $params['meta_data'] : null;
		if(!$meta_data) $meta_data = \Schema::getMetaData($this->injector, $name);

		if($meta_data):

			$this->setAttribute('name', $meta_data['field_name']);
			$this->required = $meta_data['is_required'];
			$this->tooltip = $meta_data['tooltip'];
			$this->label = $meta_data['label'];
			$this->ordering = $meta_data['ordering'];
			$this->is_visible = $meta_data['is_visible'];

			/*
			if($meta_data['formatter_class']):
				$class = '\Format\\' . $meta_data['formatter_class'];
				$this->formatter = new $class;
			endif;
			*/

			//-------------------------------------------
			// Get ordered list of validators
			//-------------------------------------------
			$vals = explode('|', $meta_data['validators']);

			if($meta_data['validators']):

				foreach($vals as $key => $val):
					$vals[$key] = explode(',', $val);
				endforeach;

				array_multisort($vals);

				foreach($vals as $key => $val):
					$val[1] = '\HTML\Form\Validator\\' . $val[1];
					$this->addValidator(new $val[1]);
				endforeach;

			endif;

			$this->meta_data = $meta_data;

		else:

			$this->setAttribute('name', $name);

		endif;

		if(!empty($params['attrs'])) foreach($params['attrs'] as $k => $v) $this->setAttribute($k, $v);
		if(isset($params['label'])) $this->label = $params['label'];
		if(isset($params['required'])) $this->required = (bool) $params['required'];
		if(isset($params['value'])) $this->setValue($params['value']);
		$this->ensureElementID();

	}

	/**
	 * Retrieves the field's name attribute
	 */
	function getName(){
		return $this->getAttribute('name');
	}

	/**
	 * Sets the field's name attribute
	 */
	function setName($name){
		$this->setAttribute('name', $name);
		return $this;
	}

	/**
	 * Returns the field's value
	 * @param string $format_mode Returns according to this format. Null returns the original value.
	 */
	function getValue($format_mode = null){
		if(!is_null($format_mode) && $this->formatter):
			return $this->formatter->format($this->value, $format_mode, $this->container_value);
		endif;
		return $this->value;
	}

	/**
	 * Sets the field's value
	 */
	function setValue($value){
		$this->value = $value;
		return $this;
	}

	/**
	 * Returns the contents of the tooltip
	 */
	function getTooltip(){
		return $this->tooltip;
	}

	/**
	 * Sets the tooltip for this form element
	 * @param string $tooltip
	 */
	function setTooltip($tooltip){
		$this->tooltip = $tooltip;
		return $this;
	}

	/**
	 * Returns the HTML rendering of the tooltip, as long as show_tooltip is set to true
	 */
	function renderTooltip(){
		if(!$this->tooltip || !$this->show_tooltip) return "";
		return '<span class="tooltip" title="' . \Helpers::entify($this->tooltip) . '"></span>';
	}

	/**
	 * Shows or hides the tooltip for this FormField
	 * @param bool $show
	 */
	function showTooltip($show = true) {
		$this->show_tooltip = (bool) $show;
		return $this;
	}

	/**
	 * Returns the field's label
	 * @param bool $full Determines whether or not the full label including the required symbol and appended colon is returned, or simply the base label.
	 * @return string
	 */
	function getLabel($full = false){

		if(!strlen(trim($this->label))) return '';

		$required = $this->required ? '*' : '';
		$label = !in_array(substr($this->label, -1), array(':', '?')) ? ':' : '';

		return $this->label . ($full ? $label . $required : '');

	}

	/**
	 * Sets the field's label
	 */
	function setLabel($label){
		$this->label = $label;
		return $this;
	}

	/**
	 * Sets a reference to the containing form of this field
	 * Used primarly by radio buttons when names are shared.
	 * @param Form $form
	 */
	function setForm(Form $form){
		$this->form = $form;
		return $this;
	}

	/**
	 * Returns the current form this field is attached to, or NULL if not associated
	 */
	function getForm(){
		return $this->form;
	}

	/**
	 * Returns the meta data this field was constructed with.
	 */
	function getMetaData(){
		return $this->meta_data;
	}

	/**
	 * Adds a validator to the list of validators on this form field element.
	 * @param unknown_type $validator
	 */
	function addValidator(Validator $validator) {
		$this->validators[] = $validator;
		return $this;
	}

	/**
	 * Adds an error message to the messages array at the index of the error key,
	 * which is a defined constant.
	 * @param string $message
	 */
	function addValidationMessage($message) {
		$this->validationMessages[] = $message;
		return $this;
	}

	/**
	 * Returns array of all the error messages recorded by all of the validators on this form field.
	 * @return array
	 */
	function getValidationMessages() {
		return $this->validationMessages;
	}

	/**
	 * (non-PHPdoc)
	 * @see HTML.Element::validate()
	 */
	function validate() {
		$this->validationMessages = array();

		//If the field is not required, and there is no input, don't run any validators
		if(!$this->required && !strlen($this->getValue()) && $this->getValue() !== false) return true;

		foreach($this->validators as $validator):
			$ok = $validator->validate($this);
			if(!$ok):
				$this->addClass('error');
				return false;
			endif;
		endforeach;

		return true;
	}

	/**
	 * Sets whether or not the form field is required
	 * @param bool $required
	 */
	function setRequired($required) {
		$this->required = (bool) $required;
		return $this;
	}

	/**
	 * Returns whether or not the form field is marked as required
	 * @return bool
	 */
	function getRequired() {
		return $this->required;
	}

	/**
	 * Sets the formatter to be used when rendering / getting field value
	 * @param Format $formatter
	 */
	function setFormatter(Format $formatter) {
		$this->formatter = $formatter;
		return $this;
	}

	/**
	 * Returns the formatter for this field
	 * @return Format
	 */
	function getFormatter(){
		return $this->formatter;
	}

	/**
	 * Removes id attribute on clone since HTML only allows an ID to be used once per document
	 */
	function __clone(){
		$this->removeAttribute('id');
		$this->ensureElementID();
		//Clones the class attribute (since it's an object)
		if(isset($this->attributes['class'])):
			$this->attributes['class'] = clone($this->attributes['class']);
		endif;
	}

	/**
	 * Renders the form field.
	 * Potentially overloaded for other field types.
	 * Outputs HTML version when using HTML format.
	 */
	function __toString(){
		if($this->format_mode == Format::HTML):
			return (string) $this->getValue(Format::HTML);
		endif;
		$out = "<input" . $this->outputAttributes() . " />";
		return $out;
	}

	/**
	 * Sets whether or not the FormField should be visible
	 * @param bool $is_visible
	 */
	function setIsVisible($is_visible) {
		$this->is_visible = (bool) $is_visible;
		return $this;
	}

}