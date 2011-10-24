<?php
/**
 * Cache management class.
 * May be used to easily interact with a cache using multiple strategies
 */
use Cache\Filesystem;
use Cache\iCache;
class Cache {

	/**
	 * Contains the cache strategy used for this instance
	 * @var iCache
	 */
	protected $strategy;

	/**
	 * An array of array(expiry, closure) elements
	 * @var array
	 */
	protected $keys = array();

	/**
	 * The default expiry time to use for cache items in seconds.
	 * 0 means the item never expires.
	 * @var integer
	 */
	protected $default_cache_time = 900; //15 minute default cache time

	/**
	 * Instantiates a new cache object using the strategy provided.
	 * Uses a unique prefix based on the current working directory and
	 * server hostname.
	 * @param iCache $strategy
	 */
	function __construct(iCache $strategy = null){

		$prefix = md5(getcwd() . $_SERVER['HTTP_HOST']) . "-";
		$this->strategy = $strategy ?: new Filesystem;
		$this->strategy->setPrefix($prefix);

	}

	function setDefaultCacheTime($seconds){
		$this->default_cache_time = (integer) $seconds;
	}

	function getDefaultCacheTime(){
		return $this->default_cache_time;
	}

	/**
	 * Returns a value from the cache (may recache the item if expired),
	 * or null if key is not found. Note that the closure used to generate the
	 * item *must* be defined before the key may be retrieved.
	 * @param string $key
	 */
	function get($key){

		//Ensure that the requested key has a closure defined
		if(!array_key_exists($key, $this->keys)) return null;

		//Attempt to retrieve the key
		$res = $this->strategy->get($key);

		//Attempt to recache the key
		if(is_null($res) && !$this->strategy->lastKeyExists()):
			$res = call_user_func($this->keys[$key][1]); //Execute the closure
			$this->strategy->set($key, $res, $this->keys[$key][0]); //Persist to the cache
		endif;

		//Return the value
		return $res;

	}

	/**
	 * Magic accessor function for retrieving a predefined key
	 * @param string $key string identifier
	 */
	function __get($key){
		return $this->get($key);
	}

	/**
	 * Sets the closure to be used to generate the given key
	 * @param string $key string identifier
	 * @param Closure $function closure used to generate the key's cached value
	 * @param integer $expiry defaults to $default_cache_time
	 */
	function set($key, Closure $function, $expiry = null){
		if(is_null($expiry)) $expiry = $this->default_cache_time;
		$this->keys[$key] = array($expiry, $function);
	}

	/**
	 * Magic setter function for defining a key
	 * @param string $key string identifier
	 * @param Closure $value must be a closure
	 */
	function __set($key, Closure $value){
		$this->set($key, $value);
	}

	function __isset($key){
		return array_key_exists($key, $this->keys);
	}

	function __unset($key){
		$this->delete($key);
		unset($this->keys[$key]);
	}

	/**
	 * Deletes a key from the cache
	 * @param string $key string identifier
	 */
	function delete($key){
		$this->strategy->delete($key);
	}

	/**
	 * Removes all items from the cache
	 */
	function flush(){
		$this->strategy->flush();
	}

}