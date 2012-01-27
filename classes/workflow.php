<?php
use HTML\Form\Container\Form;
/**
 * Workflow management class
 *
 * This class allows you to define workflows, which are a set of screens that a
 * user must interact with in a certain order (very similar to the concept of a
 * wizard).
 *
 * @author Kenaniah Cerny <kenaniah@gmail.com> https://github.com/kenaniah/insight
 * @license http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright Copyright (c) 2009, Kenaniah Cerny
 */
class Workflow {

	/**
	 * The url file extension
	 * @var string $extension
	 */
	protected $extension = "";

	/**
	 * A collection of workflow screens. Pages are the file names of other pages
	 * in the same directory, minus the extensions.
	 * @var array $pages
	 */
	protected $pages = array();

	/**
	 * Stores a session instance
	 * @var Session
	 */
	protected $session;

	/**
	 * Instantiates a workflow given an ordered array of $pages
	 * This is meant to be called within a directory's _header.php file, in order to
	 * govern all pages stored within that directory.
	 * @param unknown_type $pages
	 * @param unknown_type $session
	 */
	function __construct(array $pages, Injector $injector = null){

		if(is_null($injector)) $injector = Registry::get('injector');

		//Alert all forms that they are NOT to clear their data upon submit
		Form::clearOnSubmit(false);

		//Session needs to default to the current directory
		$this->session = $injector->session->getParent();
		$this->pages = $pages;

	}

	/**
	 * Returns the next page in the workflow, or null if it does not exist
	 */
	function nextPage($extension = true){

		$found = false;

		foreach($this->pages as $page):

			if($found) return $page . ($extension ? $this->extension : '');
			if($page === SCRIPT) $found = true;

		endforeach;

		return null;

	}

	/**
	 * Returns the previous page in the workflow, or null if it does not exist
	 */
	function previousPage($extension = true){

		$found = false;

		foreach(array_reverse($this->pages) as $page):

			if($found) return $page . ($extension ? $this->extension : '');
			if($page === SCRIPT) $found = true;

		endforeach;

		return null;

	}

	/**
	 * Ensures that the user is on the correct page and redirects them if not
	 */
	function checkCurrentPage(){

		foreach($this->pages as $page):

			$valid = $this->session->getChild($page)->getValidity();

			if(!$valid && SCRIPT === $page) return;
			if(!$valid && SCRIPT !== $page) Helpers::redirect($page . $this->extension);

		endforeach;

	}

	/**
	 * Returns the user to the previous step in the process
	 */
	function goBack(){

		$prev = $this->previousPage(false);

		$this->session->getChild(SCRIPT)->setValidity(false);
		$this->session->getChild($prev)->setValidity(false);

		Helpers::redirect($this->previousPage() ?: SCRIPT); //previousPage() may return null

	}

	/**
	 * Redirects the user's request to either the next page or the current page
	 * based on the page's validity
	 */
	function redirect(){

		if($this->session->getChild(SCRIPT)->getValidity()):
			Helpers::redirect($this->nextPage());
		else:
			Helpers::redirect();
		endif;

	}

	/**
	 * Returns the session instance attached to this workflow
	 * @return Session
	 */
	function getSession(){

		return $this->session;

	}

}