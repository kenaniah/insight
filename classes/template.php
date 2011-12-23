<?php
/**
 * Simple, scoped template rendering engine
 *
 * Variables may be passed to templates by setting dynamic
 * properties of this class or by passing them at call time.
 *
 * @author Kenaniah Cerny <kenaniah@gmail.com> https://github.com/kenaniah/insight
 * @license http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright Copyright (c) 2009, Kenaniah Cerny
 */
class Template {

	protected $vars = array();

	function __get($key){
		return $this->vars[$key];
	}

	function __set($key, $val){
		$this->vars[$key] = $val;
	}

	function __isset($key){
		return array_key_exists($key, $this->vars);
	}

	function __unset($key){
		unset($this->vars[$key]);
	}

	/**
	 * Renders a template via includes
	 * The template being included will have its own scope with the variables array
	 * imported into it.
	 * @param string $template Name of the template to render
	 * @param array $vars Array of variables to be passed to the template
	 * @return mixed Whatever the included template decides it wants to return
	 */
	function render($template, $vars = array()){

		//Make sure the template exists
		if(!file_exists("templates/".$template.".php")):
			user_error("Template not found: templates/" . $template . ".php", E_USER_ERROR);
		endif;

		//Import the template's variables
		$vars = array_merge($this->vars, (array) $vars);
		extract($vars, EXTR_REFS|EXTR_OVERWRITE);

		//Load the template
		return include "templates/" . $template . ".php";

	}

}