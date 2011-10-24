<?php
/**
 * Dependency injection class
 *
 * Injector is a dependency injection wrapper that allows one to
 * get and set resources using PHP's magic get/set functionality.
 *
 * Injectors only accept objects and/or closures as valid properties.
 * Attempting to set a property to anything else will result in an error.
 *
 * @author Kenaniah Cerny <kenaniah@gmail.com> https://github.com/kenaniah/insight
 * @license http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright Copyright (c) 2009, Kenaniah Cerny
 */
class Injector {

	/**
	 * List of properties for this injector
	 * @var array of objects and/or closures
	 */
	protected $objects = array();

	/**
	 * Set closure to create a given object, or set given object directly
	 * @param string $name
	 * @param mixed $closure
	 */
	public function __set($name, $closure) {

		if(!is_object($closure)):
			user_error("Only objects / closures may be passed to inejectors");
		endif;

		$this->objects[$name] = $closure;

	}

	/**
	 * Retrieve object of given name (or returns the call result if the object is a Closure)
	 * @param string $name
	 */
	public function __get($name) {

		if(!isset($this->objects[$name])):
			user_error("Object $name does not exist.");
		endif;

		//Either call the closure or return the object
		return $this->objects[$name] instanceof Closure ? $this->objects[$name]($this) : $this->objects[$name];

	}

	/**
	 * Create a new object with paremeters from the injector
	 * @param string $name
	 * @param array(mixed) $arguments
	 */
	public function __call($name, array $arguments) {

		if(!isset($this->objects[$name]))
			user_error("Object $name does not exist.");

		if(!is_callable($this->objects[$name]))
			user_error('Cannot instantiate non-closure object with arguments.');

		return $this->objects[$name]( $this, $arguments );

	}

}