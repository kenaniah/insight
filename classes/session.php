<?php
/**
 * Session data management class.
 *
 * Instances of this class manage session data in namespaces defined by $path.
 * All of the data managed by this class resides under $_SESSION[self::$namespace].
 * The namespace for an instance is determined by $path, which is similar to the
 * path portion of a URL. Each namespace may contain data, as well as other child
 * namespaces (just like a folder, if a namespace is deleted, all children underneath
 * are also removed).
 *
 * A namespace consists of:
 * 	- data 		The session data found in this namespace, managed by getters / setters
 * 	- messages 	Used to store messages from the Messages interface
 * 	- is_valid	Used primarily in wizards to determine if the data contianed in this namespace is valid
 * 	- children	A container for child namespaces
 *
 * @author Kenaniah Cerny <kenaniah@gmail.com> https://github.com/kenaniah/insight
 * @license http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright Copyright (c) 2009, Kenaniah Cerny
 */
class Session {

	/**
	 * Constants used to define the different types of messages
	 */
	const MSG_SUCCESS = 'success';
	const MSG_INFO = 'info';
	const MSG_WARNING = 'warning';
	const MSG_ERROR = 'error';

	/**
	 * The namespace under which all tokens live
	 * @var string
	 */
	protected $namespace = 'tokens';

	/**
	 * Tracks the namespace path this instance points to.
	 * Intended to model the path portion of URLs.
	 * @var string
	 */
	protected $path;

	/**
	 * Real path contains an array of the actual parsed path.
	 * @var array
	 */
	protected $realpath;

	/**
	 * Reference to the session namespace described by the path
	 * @var array Subsection of the $_SESSION variable
	 */
	protected $res;

	/**
	 * Initializes a namespace with the given path, or the current url
	 * if path is ommitted
	 * @param string $path
	 */
	function __construct($path = null){

		//Determine the default path
		if(is_null($path)):

			if(!$path) $path = isset($_SERVER['REDIRECT_URL']) ? $_SERVER['REDIRECT_URL'] : null;
			if(!$path) $path = $_SERVER['PHP_SELF'];
			$path = $this->getBaseURL($path);

		endif;

		//Set the path
		$path = trim($path, " /");
		$this->path = $path;

		//Initialize the session tokens
		$this->init_struct($_SESSION[$this->namespace]);

		//Bind the path to the session variable
		$res = &$_SESSION[$this->namespace];

		$parts = explode("/", $this->path);

		$realpath = array();

		foreach($parts as $part):
			if(!trim($part)) continue;
			$realpath[] = trim($part);
			$this->init_struct($res);
			$this->init_struct($res['children'][$part]);
			$res = &$res['children'][$part];
		endforeach;
		$this->res = &$res;
		$this->realpath = $realpath;
	}

	/**
	 * Returns a session instance, clearing it out if
	 * the path is not found in the referring URL. In other words,
	 * the namespace will automatically be cleared if the referrer is not
	 * this namespace or a descendant thereof.
	 * @param string $path See constructor for more info
	 */
	static function temporary($path = null){
		$session = new self($path);
		$session->make_temporary();
		return $session;
	}

	/**
	 * Transforms an existing session into a temporary one
	 */
	function make_temporary(){
		$path = $this->getPath();
		if(isset($_SERVER['HTTP_REFERER'])):
			if(strpos($_SERVER['HTTP_REFERER'], $path) === false):
				$this->clear();
			endif;
		endif;
	}

	/**
	 * Returns the resolved path for this session token
	 */
	function getPath(){
		return implode("/", $this->realpath);
	}

	/**
	 * Returns the name of this session space (the last part of the path)
	 */
	function getName(){
		return end($this->realpath);
	}

	/**
	 * Returns a list of all the children found in this session space
	 */
	function getChildNames(){
		return array_keys((array) $this->res['children']);
	}

	/**
	 * Returns all children as session objects
	 */
	function getChildren(){
		$children = array();
		$path = $this->getPath();
		foreach($this->getChildNames() as $child):
			$children[$child] = new self($path . "/" . $child);
		endforeach;
		return $children;
	}

	/**
	 * Returns an indvidiual child
	 * @param string $child
	 * @return Session
	 */
	function getChild($child){
		return new self($this->getPath() . "/" . $child);
	}

	/**
	 * Returns the parent session object
	 * @return Session
	 */
	function getParent(){

		$path = $this->getPath();
		$parts = explode("/", $path);
		array_pop($parts);
		return new self(implode("/", $parts));

	}

	/**
	 * Returns a copy of the data for this session space
	 * @return array
	 */
	function get(){
		return $this->res['data'];
	}

	/**
	 * Returns an array of messages. If a $type is specified, only messages of that $type will be returned.
	 * @param string $type Determines which messages to return. If not specified, all messages are returned.
	 * @param bool $global Whether or not to return global messages or regular messages
	 * @return array An array of array(type,msg) objects
	 */
	function getMessages($type = null) {

		$messages = array_merge($this->res['messages'], isset($_SESSION['global_messages']) ? $_SESSION['global_messages'] : array());

		//If message type is not specified, return all messages
		if(!isset($type)) return $messages;

		//Else only return messages of the type specified
		$returnMessages = array();

		foreach($messages as $msg):
			if($msg[0] == $type) $returnMessages[] = $msg;
		endforeach;

		return $returnMessages;
	}

	/**
	 * Completely overwrites the data for this session space
	 * @param array $data
	 */
	function set(array $data){
		$this->res['data'] = $data;
	}

	/**
	 * Completely overwrites all of the messages for this session space
	 * @param array $messages Array of array(type,msg) elements
	 */
	function setMessages(array $messages){
		$this->res['messages'] = $messages;
	}

	/**
	 * Completely deletes this session space and everything under it
	 */
	function delete(){
		$this->res = null;
	}

	/**
	 * Completely clears out a session space and everything under it
	 */
	function clear(){
		$this->delete();
		$this->__construct($this->getPath());
	}

	/**
	 * Clears the session when the page referrer does not match the destination page
	 */
	function clear_when_referred(){
		if(!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $_SERVER['REQUEST_URI']) === false):
			$this->clear();
		endif;
	}

	/**
	 * Completely deletes the data within this namespace
	 */
	function clean(){
		$this->res['data'] = array();
		$this->res['is_valid'] = null;
	}

	/**
	 * Deletes all messages of a given type from within this namespace.
	 * If no type is provided, all messages are deleted.
	 * @param string $type
	 */
	function cleanMessages($type = null){

		if(!isset($type)):
			$this->res['messages'] = array();
			$_SESSION['global_messages'] = array();
		else:

			$messages = $this->res['messages'];
			$global_messages = $_SESSION['global_messages'];

			//delete messages matching $type
			foreach($messages as $key => $msg):
				if($msg[0] == $type) unset($messages[$key]);
			endforeach;

			foreach($global_messages as $key => $msg):
				if($msg[0] == $type) unset($global_messages[$key]);
			endforeach;

			//re-index the array
			$messages = array_values($messages);
			$global_messages = array_values($global_messages);

			//save updates messages to the session
			$this->res['messages'] = $messages;
			$_SESSION['global_messages'] = $global_messages;

		endif;
	}

	/**
	 * Sets the validity flag for this namespace (used mainly in wizards)
	 * @param boolean $valid Whether or not this namespace's data is considered valid
	 */
	function setValidity($valid = null){
		if(is_null($valid)) $valid = !$this->errorsExist();
		$this->res['is_valid'] = (boolean) $valid;
		return $this;
	}

	/**
	 * Returns the data validity status of this namespace (used mainly in wizards)
	 */
	function getValidity(){
		return (boolean) $this->res['is_valid'];
	}

	/**
	 * Builds the initial structure of session tokens
	 * @param array $array Reference to session space to initialize
	 */
	protected function init_struct(&$array){

		if(!is_array($array)) $array = array();
		if(!array_key_exists('data', $array)) $array['data'] = array();
		if(!array_key_exists('messages', $array)) $array['messages'] = array();
		if(!array_key_exists('is_valid', $array)) $array['is_valid'] = null;
		if(!array_key_exists('children', $array)) $array['children'] = array();

	}

	/**
	 * Returns the base path of a URL.
	 * Added due to the way apache 2.2.15 and 2.2.17 regresses when reporting the
	 * $_SERVER['REDIRECT_URL'] variable (one has .php extension when other omits it)
	 */
	protected function getBaseURL($url){

		$info = pathinfo($url);
		if($info['dirname'] == '/') return $info['dirname'] . $info['filename'];
		return $info['dirname'] . '/' . $info['filename'];

	}

	/**
	 * Adds a message of type $type to the messages array, in the form of an array(type, msg)
	 * @param string $type One of four const values: WARNING, ERROR, INFO, SUCCESS
	 * @param string $msg
	 * @param bool $is_global Whether or not the message should be global
	 */
	function addMessage($type, $msg, $is_global = false) {
		if($is_global):
			$_SESSION['global_messages'][] = array($type, $msg);
			return $this;
		endif;
		$this->res['messages'][] = array($type, $msg);
		return $this;
	}

	/**
	 * Adds an error message to the messages part of the session token
	 * @param string $msg
	 */
	function addError($msg, $is_global = false) {
		$this->addMessage(self::MSG_ERROR, $msg, $is_global);
		return $this;
	}

	/**
	 * Adds a warning message to the messages part of the session token
	 * @param string $msg
	 */
	function addWarning($msg, $is_global = false) {
		$this->addMessage(self::MSG_WARNING, $msg, $is_global);
		return $this;
	}

	/**
	 * Adds a success message to the messages part of the session token
	 * @param string $msg
	 */
	function addSuccess($msg, $is_global = false) {
		$this->addMessage(self::MSG_SUCCESS, $msg, $is_global);
		return $this;
	}

	/**
	 * Adds an info message to the messages part of the session token
	 * @param string $msg
	 */
	function addInfo($msg, $is_global = false) {
		$this->addMessage(self::MSG_INFO, $msg, $is_global);
		return $this;
	}

	/**
	 * Checks to see if any messages exist. If no type is specified, checks all messages.
	 * @param string $type Determines which type of message to check for existence.
	 * @param boolean $clear Cleans the messages from the session after display
	 * @return Returns the amount of messages that exist
	 */
	function messagesExist($type = null){
		return count($this->getMessages($type));
	}

	/**
	 * Returns whether or not errors exist
	 */
	function errorsExist(){
		return $this->messagesExist(self::MSG_ERROR) ? true : false;
	}


	/**
	 * Returns all messages contained in the session
	 * @param string $type
	 * @param bool $clear
	 * @param bool $global Determines whether to return regular messages or global messages
	 * @return void|Ambigous <string, void>
	 */
	function displayMessages($type = null, $clear = true, $global = false) {

		if(!$this->messagesExist($type)) return; //No messages to display

		$out = '';

		if(!isset($type)):
			$out .= $this->displayMessageGroup(self::MSG_ERROR);
			$out .= $this->displayMessageGroup(self::MSG_WARNING);
			$out .= $this->displayMessageGroup(self::MSG_INFO);
			$out .= $this->displayMessageGroup(self::MSG_SUCCESS);
		else:
			if(in_array($type, array(self::MSG_ERROR, self::MSG_WARNING, self::MSG_INFO, self::MSG_SUCCESS))):
				$out .= $this->displayMessageGroup($type);
			else:
				trigger_error('Invalid message type requested to be displayed', E_USER_ERROR);
			endif;
		endif;

		//If clear is true, delete all messages of requested type
		if($clear) $this->cleanMessages($type);

		return $out;

	}


	protected function displayMessageGroup($type){

		if(!$this->messagesExist($type)) return; //No messages to display

		$icon = 'info';
		if($type == self::MSG_ERROR) $icon = 'alert';
		if($type == self::MSG_WARNING) $icon = 'warning';
		if($type == self::MSG_SUCCESS) $icon = 'check';

		$messages = $this->getMessages($type);

		$out = '';

		$out .= '<div class="ui-widget messages '.$type.'">';
		$out .= '<div class="ui-state-' . ($type == self::MSG_ERROR ? 'error' : 'highlight') . ' ui-corner-all">';

		$error = '<p><span class="ui-icon ui-icon-'.$icon.' float-left"></span>';

		foreach($messages as $msg):
			$out .= $error . $msg[1] . '</p>';
		endforeach;

		$out .= '</div>';
		$out .= '</div>';

		return $out;

	}

	function __get($key){
		return $this->__isset($key) ? $this->res['data'][$key] : null;
	}

	function __set($key, $val){
		$this->res['data'][$key] = $val;
	}

	function __isset($key){
		return isset($this->res['data'][$key]);
	}

	function __unset($key){
		unset($this->res['data'][$key]);
	}

}