<?php
namespace Cache;

/**
 * Filesystem cache driver
 *
 * @author Kenaniah Cerny <kenaniah@gmail.com> https://github.com/kenaniah/insight
 * @license http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright Copyright (c) 2009, Kenaniah Cerny
 */
class Filesystem implements iCache {

	protected $cache_path = "/tmp/";
	protected $prefix;

	/**
	 * Tracks whether or not the last key requested was actually found in the cache
	 * @var boolean
	 */
	protected $last_key_exists = false;

	/**
	 * Initialize with the cache path and the cache prefix
	 * @param string $cache_path filesystem path to the cache directory
	 */
	function __construct($cache_path = null){
		if($cache_path) $this->cache_path = $cache_path;
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

		$this->last_key_exists = false;

		$file = $this->cache_path . $this->prefix . $key . ".cache";

		//Ensure cache file exists
		$mtime = null;
		if(file_exists($file)) $mtime = filemtime($file);
		if(!$mtime) return null;

		//Read the cache file
		$contents = file_get_contents($file);
		$parts = explode("|", $contents, 2); //First part contains expiry

		//Check the file expiration time
		if($parts[0] > 0 && time() > $mtime + $parts[0]):
			unlink($file);
			return null;
		endif;

		//Mark the key as found
		$this->last_key_exists = true;

		//Return the cache file contents
		return unserialize($parts[1]);

	}

	function set($key, $value, $expiry = 60){

		$file = $this->cache_path . $this->prefix . $key . ".cache";
		$contents = serialize($value);
		file_put_contents($file, $expiry . "|" . $contents);

	}

	function delete($key){
		if(file_exists($this->cache_path . $this->prefix . $key . ".cache")):
			unlink($this->cache_path . $this->prefix . $key . ".cache");
		endif;
	}

	function flush(){
		$command = "rm " . $this->cache_path . $this->prefix . "*.cache";
		system($command);
	}

	function lastKeyExists(){
		return $this->last_key_exists;
	}

}