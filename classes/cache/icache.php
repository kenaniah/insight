<?php
namespace Cache;

/**
 * Cache interface definition
 *
 * @author Kenaniah Cerny <kenaniah@gmail.com> https://github.com/kenaniah/insight
 * @license http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright Copyright (c) 2009, Kenaniah Cerny
 */
interface iCache {

	/**
	 * Returns a previously saved item by $key, or NULL when not found or cached
	 * value is expired. Manages the $last_key_exists property.
	 * @param string $key string identifier
	 */
	public function get($key);

	/**
	 * Returns whether or not the last key requested was found in the cache.
	 * Used to determine whether or not the requested key needs to be recached.
	 */
	public function lastKeyExists();

	/**
	 * saves an item by $key
	 * @param string $key string identifier
	 * @param mixed $value item to be saved
	 * @param integer $expiry expiration time in seconds
	 */
	public function set($key, $value, $expiry = 60);

	/**
	 * deletes an item by $key
	 * @param string $key string identifier
	 */
	public function delete($key);

	/**
	 * deletes all items from the cache
	 */
	public function flush();

	/**
	 * Sets the prefix for the cache that will be used to uniquely identify
	 * this instance of the cache by hostname and current working directory.
	 * @param string $prefix
	 */
	public function setPrefix($prefix);

}