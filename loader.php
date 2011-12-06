<?php
/*
 * This script allows you to load this framework into another environment with minimal changes
 */
umask(0002);

ini_set('xdebug.collect_params', 2);
ini_set('xdebug.var_display_max_data', 2048);
ini_set('xdebug.var_display_max_depth', 5);

$dir = dirname(__FILE__);
define('TOP_DIRECTORY', dirname(__FILE__) . DIRECTORY_SEPARATOR);
define('PUBLIC_DIR', TOP_DIRECTORY . 'www/');

//-------------------------------------------
// Load the configuration file
//-------------------------------------------
if(isset($_SERVER['HTTP_HOST']) && file_exists("configs/" . basename($_SERVER['HTTP_HOST']) . ".php")):
	$config_file = include "configs/" . basename($_SERVER['HTTP_HOST']) . ".php";
endif;

if(!isset($config_file)) die("Server Hostname not configured in database.");

//Allow overrides from the $config variable
$config = array_merge($config_file, $config ? $config : array());

//-------------------------------------------
// Environment initialization
//-------------------------------------------
setlocale(LC_MONETARY, 'en_US');

$url = $_SERVER['REQUEST_URI'];
if(strpos($url, '?')) $url = substr($url, 0, strpos($url, '?')); //Remove the query string

if(substr($url, -1) == "/") $url .= "index.html";
$info = pathinfo($url);
if(!isset($info['extension'])) $info['extension'] = null;

define('DIRECTORY', substr($info['dirname'], -1) == "/" ? $info['dirname'] : $info['dirname'] . "/");
define('SCRIPT', basename($info['filename'], '.' . $info['extension']) ? basename($info['filename'], '.' . $info['extension']): 'index');

//-------------------------------------------
// Class autoloaders
//-------------------------------------------
$paths = array();
$paths[] = '.';
$paths[] = TOP_DIRECTORY . "classes/";
set_include_path(implode(PATH_SEPARATOR, array_unique($paths)));
spl_autoload_extensions('.php');
spl_autoload_register();
spl_autoload_register(function($class){
	if(file_exists(strtolower($class) . '.php')) include strtolower($class) . '.php';
});

Database::setConfig($config['connections']);

//-------------------------------------------
// Fix the $_POST variable
//-------------------------------------------
if($_POST) $_POST = Utils::getRealPost();

//-------------------------------------------
// Initialize the session
//-------------------------------------------
session_start();

//-------------------------------------------
// Setup default injector
//-------------------------------------------
Registry::set('config', $config);
Registry::set('processing_time', microtime(true));
Registry::set('num_queries', 0);

$injector = new Injector();
Registry::set('injector', $injector);
$injector->session = new Session();
$injector->cache = new Cache();
$injector->db = Database::getInstance();
$injector->qs = new QueryString();

//-------------------------------------------
// Unset global variables
//-------------------------------------------
unset($config_file, $paths, $url, $info, $pos, $webroot, $cache, $injector, $config);

//-------------------------------------------
// Define global functions
//-------------------------------------------
/**
 * Returns the item passed to it. Great for constructor chaining.
 * @param $object
 * @return $object
 */
function with($object){
	return $object;
}

return;
?>