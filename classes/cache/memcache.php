<?php
namespace Cache;

/**
 * Memcache cache driver
 *
 * @author Kenaniah Cerny <kenaniah@gmail.com> https://github.com/kenaniah/insight
 * @license http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright Copyright (c) 2009, Kenaniah Cerny
 * @requires Memcached
 */
class Memcache implements iCache {

	protected $prefix;

	/**
	 * Stores the memcached instance
	 * Enter description here ...
	 * @var \Memcached
	 */
	public $memcached;

	function __construct(\Memcached $memcached){
		$this->memcached = $memcached;
	}

	/**
	 * Sets the cache prefix that uniquely identifies it from other caches running
	 * on the same server. Should be hashed based on current working directory and
	 * hostname.
	 * @param string $prefix
	 */
	function setPrefix($prefix){
		$this->prefix = $prefix;
	}

	function get($key){

		$res = $this->memcached->get($this->prefix . $key);
		if(!$this->memcached->getResultCode()) return $res;
		return null;

	}

	function set($key, $value, $expiry = 60){

		$this->memcached->set($this->prefix . $key, $value, $expiry);

	}

	function delete($key){
		$this->memcached->delete($this->prefix . $key);
	}

	function flush(){
		$this->memcached->flush();
	}

	function lastKeyExists(){
		return !$this->memcached->getResultCode();
	}

}