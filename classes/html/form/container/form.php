<?php
namespace HTML\Form\Container;

use HTML\Form\iContainable;

/**
 * Container that wraps all child elements in an HTML form.
 * Attributes can optionally be set on this form using the get/set attributes methods.
 */
class Form extends Container {
	/**
	 * Instance of database connection
	 * @var Database
	 */
	public $db;

	/**
	 * Tracks whether or not this form will clear the session data upon a successful submission
	 * @var boolean
	 */
	static $clear_on_submit = true;

	/**
	 * Unique id for this form
	 * @var string
	 */
	protected $id;

	/**
	 * Instance of the session class to use
	 * @var Session
	 */
	protected $session;


	/**
	 * URL to redirect to upon submission success
	 * @var string
	 */
	protected $success_url;

	/**
	 * Stores the callable to be used as a submit handler.
	 * Must return boolean TRUE if form submission has been handled successfully.
	 * @var callback
	 */
	protected $submit_handler;

	/**
	 * Tracks whether or not this form will use AJAX on the client side
	 * @var boolean
	 */
	protected $use_ajax = null;

	/**
	 * Determines whether or not to preserve the sorting of the dataset
	 * @var bool
	 */
	protected $preserve_sorting = false;


	/**
	 * Reflects whether or not a form has been handled
	 * @var bool
	 */
	protected $output_form_id = false;

	/**
	 * Changed signature, $id represents the unique id for the form that will be used when detecting if this form was submitted.
	 * Defaults to the base script's URL
	 * @param string $id
	 */
	public function __construct($id = null){

		parent::__construct();
		$this->db = $this->injector->db;
		$this->session =  $this->injector->session;

		$this->id = $id;
		if(!$this->id):
			if(isset($_SERVER['REDIRECT_URL'])):
				$info = pathinfo($_SERVER['REDIRECT_URL']);
				if($info['dirname'] == '/'):
					$this->id = $info['dirname'] . $info['filename'];
				else:
					$this->id = $info['dirname'] . '/' . $info['filename'];
				endif;
			else:
				$this->id = $_SERVER['PHP_SELF'];
			endif;
		endif;

	}

	/**
	 * Overloaded to set the form on all FormFields added to the container.
	 */
	function addChild(iContainable $child) {
		$child->setForm($this);
		return parent::addChild($child);
	}

	/**
	 * Contols whether or not the form will use AJAX
	 * @param boolean $enabled
	 */
	function useAjax($enabled){
		$enabled ? $this->addClass('ajax') : $this->removeClass('ajax');
		$this->use_ajax = (boolean) $enabled;
		return $this;
	}

	/**
	 * Sets the destination URL to redirect to after a successful submission.
	 * If blank, the form will redirect to itself (unless a GET request was used).
	 * @param string $url
	 */
	public function setSuccessURL($url){
		$this->success_url = $url;
		return $this;
	}

	/**
	 * Sets the callback function that will handle the form submission once it
	 * passes validation. Will take the form object as an argument, and should
	 * return a boolean indicating whether or not the form was handled correctly.
	 * @param callable $callback
	 */
	public function setSubmitHandler($callback){
		if(!is_callable($callback)) user_error("Form::setSubmitHandler() requires the callback provided to be callable.", E_USER_ERROR);
		$this->submit_handler = $callback;
		return $this;
	}

	/**
	 * Handles form submissions (and also non-submissions) for POST forms
	 * by populating and validating data.
	 * @param string $success_url URL to redirect to when submission is successful
	 * @param callable $submit_callback function to deal with form submissions when validation passes. Returns boolean indicating succes of handler.
	 * @param boolean $use_parts when true, uses formGetPart vs formGet, etc.
	 */
	public function handle($success_url = null, $submit_callback = null, $use_parts = false){

		$this->output_form_id = true;
		if(!is_null($success_url)) $this->setSuccessURL($success_url);
		if(!is_null($submit_callback)) $this->setSubmitHandler($submit_callback);
		if(!$this->session) $this->session = $this->injector->session;

		if(!$this->success_url) $this->setSuccessURL($_SERVER['REQUEST_URI']);

		$method = strtolower($this->getAttribute('method'));
		$source = $method == 'post' ? $_POST : $_GET;

		$this->handleAutocompletes();

		//Determine whether or not this form has been submitted
		if(isset($source['_form_id']) && $source['_form_id'] == md5($this->id)):

			//Detect ajax requests
			$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && in_array(strtolower($_SERVER['HTTP_X_REQUESTED_WITH']), array('xmlhttprequest'));
			if($is_ajax):
				header("Content-Type: application/json");
				ob_end_clean();
			endif;

			//Persist values?
			if($method == 'post'):
				$this->session->set($source);
			endif;

			//Perform validation
			$this->setValue($source);
			$ok = $this->validate();

			if(!$ok):
				$this->session->addError('There were issues with your submission. Please correct the fields highlighted below.', true);
				$msgs = $this->getValidationMessages();
				$it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($msgs));
				foreach($it as $msg) $this->session->addError($msg, true);
			endif;

			//Perform submit_handler callback (if it exists)
			if($ok && $this->submit_handler):
				$ok = call_user_func($this->submit_handler, $this);
			endif;

			//Record whether or not this session space was determined to be valid
			$this->session->setValidity($ok);

			//Clean the session if submission passes
			if(self::$clear_on_submit && $ok && $method == 'post'):
				$this->session->clean();
			endif;

			//Redirect to destination page if set
			if($ok && $this->success_url):
				if($is_ajax):
					print json_encode(array('success' => true, 'url' => $this->success_url));
					exit;
				else:
					\Helpers::redirect($this->success_url);
				endif;
			endif;

			if($is_ajax):
				print json_encode(array('success' => false, 'errors' => $this->getValidationMessages()));
				exit;
			endif;

			//Redirect a post request if we're still here
			if($method == 'post'):
				\Helpers::redirect();
			endif;

		else:
			//Not submitted
			$data = $method == 'post' ? $this->session->get() : $_GET;
			$this->setValue($data);
			if($data) $this->validate();
		endif;


		return $this;

	}

	/**
	 * Handles AJAX fields that may be associated with the form
	 */
	public function handleAutocompletes(){
		if(!empty($_POST['ajax_id'])):
			foreach($this->getRecursiveChildren() as $child):
				if($child instanceof \HTML\Form\Field\Autocomplete) $child->handle();
			endforeach;
		endif;
		return $this;
	}

	/**
	 * Returns the session instance this form is bound to
	 * @return Session
	 */
	public function getSession(){
		return $this->session;
	}

	/**
	 * Sets whether or not to preserve the sorting on the form
	 * @param bool $preserve_sorting
	 */
	public function preserveSorting($preserve_sorting) {
		$this->preserve_sorting = (bool) $preserve_sorting;
		return $this;
	}

	/**
	 * (non-PHPdoc)
	 * @see Container::__toString()
	 */
	public function __toString() {

		$out = self::indent() . "<form".$this->outputAttributes().">";
		$out .= parent::__toString();
		if($this->preserve_sorting) $out .= self::indent() . \Helpers::preserve_sorting($this->injector);
		if($this->output_form_id) $out .= self::indent() . "\t" . '<input type="hidden" name="_form_id" value="' . md5($this->id) .'" />';
		$out .= self::indent() . "</form>";

		return $out;

	}
}