<?php
/**
 * Registry class used for dependency injection and tracking of shared variables
 *
 * While this class may not be instantiated, it may be cloned to allow for non-global modifications
 * before an injection takes place.
 *
 * Can be used to store:
 *   - Objects directly
 *   - Names of objects to be instantiated upon retrieval
 *   - Closures that construct objects upon retrieval
 *   - Constructing closures wrapped by $this->asShared() to ensure a global instance upon retrieval
 *
 * @author Kenaniah Cerny <kenaniah@gmail.com> https://github.com/kenaniah/insight
 * @license http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright Copyright (c) 2009, Kenaniah Cerny
 */
class Registry {

	protected $values = array();

	final public static function getInstance() {

		static $instance;

		if($instance) return $instance;

		return $instance = new self;

	}

	function __set($id, $value){

		$this->values[$id] = $value;

	}

	static function set($id, $value){

		$instance = self::getInstance();
		$instance->__set($id, $value);

	}

	/**
	 * Returns the value (or runs and returns if a closure)
	 */
	function __get($id){

		if(!isset($this->values[$id])){
			throw new InvalidArgumentException(sprintf('Value "%s" is not defined.', $id));
		}

		if($this->values[$id] instanceof Closure){
			return $this->values[$id]($this);
		}else{
			return $this->values[$id];
		}

	}

	static function get($id){

		$instance = self::getInstance();
		return $instance->__get($id);

	}

	function __isset($id){

		return array_key_exists($id, $this->values);

	}

	function __unset($id){

		unset($this->values[$id]);

	}

}