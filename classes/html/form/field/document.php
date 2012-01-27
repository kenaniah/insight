<?php
namespace HTML\Form\Field;
use \Format\Format;
use File;
use Helpers;

/**
 * Represents file upload fields and integrates with the document management system
 */
class Document extends FormField {

	/**
	 * References an instance of the file class
	 * @var File
	 */
	protected $file;

	/**
	 * Instance of the database
	 * @var Database
	 */
	protected $db;

	function __construct($name, $params = array()){

		parent::__construct($name, $params);
		$this->file = new File(null, $this->injector);
		$this->setAttribute('type', 'file');

	}

	function setValue($value){
		$this->file->populate($value);
		//Do we need to save the file?
		if(is_array($value) && empty($value['id'])):
			$this->file->save();
		endif;
		$details = $this->file->getDetails();
		return parent::setValue($details['id']);
	}

	function __toString(){

		$out = "";

		//Build a file upload field
		if($this->format_mode == Format::FORM):
			$out .= "<input" . $this->outputAttributes() . "/>";
		endif;

		$details = $this->file->getDetails();

		if($details['id']):
			$out .= "<a href='".WEB_PATH."download?file=".$details['id']."&amp;hash=".$details['hash']."'>" . $details['filename'] . "</a>";
			$out .= " <span class='null'>(" . Helpers::filesize($details['size']) . ")</span>";
		endif;

		return $out;

	}

	/**
	 * Handles files that were uploaded during a request
	 */
	function handle(){

		$file = $_FILES;
		$search = $this->name_prefix;
		array_push($search, $this->getName());

		//Search for this field in the files array, or break
		while(!is_null($path = array_shift($search))):
			if(empty($file[$path])) return;
			$file = $file[$path];
		endwhile;

		//If the file was found, load it
		$this->setValue($file);

	}

	function __clone(){

		$this->file = new File(null, $this->injector);

	}

}