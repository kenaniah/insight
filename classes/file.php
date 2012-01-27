<?php
/**
 * File object class
 *
 * Manages individiual files using the database and filesystem storage
 */
class File {

	/**
	 * Instance of the database connection
	 * @var Database
	 */
	protected $db;

	/**
	 * Path to filesystem storage
	 * @var string
	 */
	protected static $path = "/tmp/filestorage";

	/**
	 * Represents unique ID from database
	 * @var integer
	 */
	protected $id;

	/**
	 * File name (without path)
	 * @var string
	 */
	protected $filename;

	/**
	 * Mime type
	 * @var string
	 */
	protected $mime_type;

	/**
	 * Size of file (in bytes)
	 * @var integer
	 */
	protected $size;

	/**
	 * MD5 hash of the file
	 * @var string
	 */
	protected $hash;

	/**
	 * Revision number of the file (starts at 1 when saved)
	 * @var integer
	 */
	protected $revision;

	/**
	 * Path to file source
	 * @var string
	 */
	protected $source;

	/**
	 * Creates a new file instance
	 * @param mixed $filedata Can be a member of $_FILES or a result from the documents table
	 */
	function __construct($filedata = null, $injector = null){

		$injector = $injector ? $injector : \Registry::get('injector');
		$this->injector = $injector;
		$this->db = $injector->db;

		if($filedata) $this->populate($filedata);

	}

	/**
	 * Populates file data from either the database or a file upload
	 * @param mixed $filedata Can be a member of $_FILES or a result / row id from the documents table
	 */
	function populate($filedata){

		//Handle nulls
		if(is_null($filedata)):
			$this->id = null;
			$this->filename = null;
			$this->mime_type = null;
			$this->size = null;
			$this->source = null;
			$this->hash = null;
		endif;

		//Handle uploaded file scenarios
		if(is_array($filedata) && isset($filedata['tmp_name'])):

			$errors = Utils::checkUpload($filedata, false);

			//Check for upload errors
			if($errors):
				foreach($errors as $error) $this->injector->session->addError($error);
				return $this;
			endif;

			if(!empty($filedata['tmp_name'])):

				$this->filename = basename($filedata['name']);
				$this->mime_type = $filedata['type'];
				$this->size = $filedata['size'];
				$this->source = $filedata['tmp_name'];
				$this->hash = md5_file($this->source);

			endif;

		endif;

		//Pull file details from an ID
		if(is_numeric($filedata)):
			$filedata = $this->db->getRow("SELECT * FROM documents WHERE id = ?", intval($filedata));
		endif;

		//Handle existing file scenarios
		if(is_array($filedata) && !empty($filedata['id'])):
			$this->id = $filedata['id'];
			$this->filename = $filedata['filename'];
			$this->mime_type = $filedata['mime_type'];
			$this->size = $filedata['size'];
			$this->source = null;
			$this->hash = $filedata['hash'];
			$this->revision = $filedata['revision'];
		endif;

		return $this;

	}

	/**
	 * Commits the file to storage
	 * @return boolean success
	 */
	function save(){

		//Don't attempt to save if we don't have a source file or id
		if(!$this->source && !$this->id) return false;

		//Commit this file to the filesystem
		if($this->source):

			$path = $this->getStoragePath();

			//Directory storage name
			$dir = dirname($path);

			//Update our umask
			$umask = umask(0022);

			//Create the directory if missing
			if(!is_dir($dir)) mkdir($dir, 0777, true);

			//Copy the file
			if(!file_exists($path)):
				copy($this->source, $path);
			endif;

			//Reset the umask
			umask($umask);

			//Switch the source
			$this->source = $path;

		endif;

		//Increment the revision number
		$this->revision++;

		//Commit to the database
		$data = array();
		$data['name'] = $this->filename;
		$data['filename'] = $this->filename;
		$data['mime_type'] = $this->mime_type;
		$data['size'] = $this->size;
		$data['hash'] = $this->hash;
		$data['revision'] = $this->revision;

		$params = array(
			'mode' => 'REPLACE',
			'where' => 'id = ?',
			'params' => array($this->id),
			'returning' => 'id',
			'return_mode' => 'getOne'
		);

		$this->id = $this->db->autoExecute("documents", $params, $data);

		return true;

	}

	/**
	 * Downloads the current file
	 * @param boolean $inline Render the file inline or force a download?
	 */
	function download($inline = true){

		if($this->id):

			\Utils::outputFile(array(
				'file' => $this->getStoragePath(),
				'filename' => $this->filename,
				'mime_type' => $this->mime_type,
				'inline' => $inline
			));

		endif;

	}

	/**
	 * Returns the storage path for this file
	 */
	protected function getStoragePath(){

		if(!$this->hash) return null;

		$chunks = explode("\r\n", chunk_split($this->hash, 2));

		//Directory storage name
		$dir = self::$path . DIRECTORY_SEPARATOR . $chunks[0] . DIRECTORY_SEPARATOR . $chunks[1];

		//Return the file path
		return $dir . DIRECTORY_SEPARATOR . $this->hash;

	}

	/**
	 * Returns details about this file
	 */
	function getDetails(){
		return array(
			'id' => $this->id,
			'filename' => $this->filename,
			'mime_type' => $this->mime_type,
			'size' => $this->size,
			'hash' => $this->hash,
			'revision' => $this->revision,
		);
	}

}