<?php
/**
 * Utilities class
 *
 * @author Kenaniah Cerny <kenaniah@gmail.com> https://github.com/kenaniah/insight
 * @license http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright Copyright (c) 2009, Kenaniah Cerny
 */
class Utils {

	static protected $unlinks = array();

	/**
	 * Returns an array with only the keys specified in $allowed
	 * @param array $array
	 * @param array $allowed
	 */
	static function applyWhitelist($array, $allowed){

		$whitelist = self::valuesToKeys($allowed);

		return array_intersect_key($array, $whitelist);

	}

	/**
	 * Returns an array not containing the keys specified in $denied
	 * @param array $array
	 * @param array $denied
	 */
	static function applyBlacklist($array, $denied){

		$blacklist = self::valuesToKeys($denied);

		return array_diff_key($array, $blacklist);

	}

   /**
	* Validates file upload and returns an array of error messages. If an empty array is returned, than
	* file upload was successful
	* @param string $file Name of file field
	*/
	static function checkUpload($file, $required=true){

		//initialize errors
		$errors = array();

		$data = $_FILES[$file];

		if(!$data):
		$errors[] = 'File was not posted with the form (or the file posted was too big).';
		return $errors;
		endif;

		switch($data['error']):
		case UPLOAD_ERR_OK:
			break;
		case UPLOAD_ERR_INI_SIZE:
			$errors[] = 'The file you tried to upload is too big. The size limit is ' . (ini_get('upload_max_filesize')) . "B.";
			break;
		case UPLOAD_ERR_PARTIAL:
			$errors[] = 'File upload did not complete.';
			break;
		case UPLOAD_ERR_NO_FILE:
			if($required):
			$errors[] = 'File upload is missing.';
			endif;
			break;
		case UPLOAD_ERR_NO_TMP_DIR:
			$errors[] = 'Could not write file upload to temp directory.';
			break;
		case UPLOAD_ERR_CANT_WRITE:
			$errors[] = 'Could not write file upload to disk.';
			break;
		case UPLOAD_ERR_EXTENSION:
			$errors[] = 'File upload was blocked by a loaded extension.';
			break;
			endswitch;

			if($errors) return $errors;

			if(!$data['size']):
			$errors[] = 'The file uploaded was empty.';
			endif;

			return $errors;
	}

	/**
	 * Converts the values of an array to keys and returns this array
	 * @param array $values
	 */
	static function valuesToKeys($values){

		$keys = array();
		foreach($values as $v) $keys[$v] = true;

		return $keys;
	}

	/**
	 * Sorts an array according to the sort definitions array
	 * Ex: Utils::arraySort($array, array('name' => SORT_DESC, 'birthday' => SORT_ASC))
	 * @param array $array
	 * @param array $order
	 */
	 static function arraySort(&$array, $order){
	 	$args = array();
	 	foreach($order as $key => &$mode):
	 		$$key = array();
	 		foreach($array as $index => $item):
	 			${$key}[$index] = $item[$key];
	 		endforeach;
	 		$args[] = &$$key;
	 		$args[] = &$mode;
	 	endforeach;
	 	$args[] = &$array;
	 	call_user_func_array('array_multisort', $args);
	 }

	 /**
	  * Computes the differences in values between two arrays.
	  * Returns an array containing all the entries from $array1 that are not present in $array2
	  * @param array $array1
	  * @param array $array2
	  */
	 static function arrayDiff($array1, $array2){
	 	$out = array();
	 	foreach($array1 as $el) if(!in_array($el, $array2, true)) $out[] = $el;
	 	return $out;
	 }

	 /**
	  * Rotates an array by taking the bottom element and putting it on top of the stack $n times.
	  * Negative values may be used
	  * @param array $array
	  * @param integer $n
	  */
	static function arrayRotate($array, $n = 1){

		$count = count($array);
		$n = $n % $count;
		return array_slice($array, $n, $count, true) + array_slice($array, 0, $n, true);

	}

	/**
	 * A quick method for date formatting regardless of input format
	 * @param mixed $string Input value (leave null for now())
	 * @param string $format
	 */
	static function date($string=null, $format = "m/d/Y"){

		if(is_integer($string)):
			$date = new DateTime();
			$date->setTimestamp($string);
			return $date->format($format);
		elseif($string):
			$date = date_create($string);
			if($date) return $date->format($format);
		endif;

		return "";

	}

	/**
	 * Recodes input into a JSON string that is safe for usage in attribute tags
	 * @param mixed $data
	 * @param array $fields If specified, output is formatted for autocomplete usage
	 */
	static function sanitizeJSON($data, $fields = array()){
		if(!$data):
			return "";
		elseif(is_string($data)):
			return json_encode(json_decode($data), JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
		else:
			if($fields):
				$tmp = array();
				foreach($data as $item) $tmp[] = array($item[$fields[0]], $item[$fields[1]]);
				$data = $tmp;
			endif;
			return json_encode($data, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
		endif;
	}

	/**
	 * Returns an array without keys recursively
	 * @param array $array
	 */
	static function arrayValuesRecursive($array){
		$temp = array();
		foreach($array as $key => $value):
			$temp[] = is_array($value) ? self::arrayValuesRecursive($value) : $value;
		endforeach;
		return $temp;
	}

	/**
	 * Outputs a file to the user and stops execution of the script.
	 *
	 * Supports partial downloads and rate buffering.
	 *
	 * @param integer $file Path to file to be downloaded
	 * @param string $filename Filename to send
	 * @param string $mime Mime type
	 * @param boolean $inline Whether or not to send inline or as an attachment
	 * @param integer $chunk the number of bytes per second to output
	 */
	static function outputFile(array $params){

		$file = $params['file'];
		$filename = !empty($params['filename']) ? $params['filename'] : basename($file);
		$mime = !empty($params['mime_type']) ? $params['mime_type'] : 'application/force-download';
		$disposition = empty($params['inline']) ? 'attachment' : 'inline';

		//Speed limit
		$chunk = null;
		if(!empty($params['chunk'])) $chunk = intval($params['chunk']);

		//Open the file
		if(!is_resource($file)) $file = fopen($file, 'rb');

		//Get filesize
		$stats = fstat($file);
		$size = $stats['size'];

		//Send the file
		while(ob_get_level()) ob_end_clean();

		//Set initial headers
		header('Content-Type: ' . $mime);
		header('Content-Disposition: '.$disposition.'; filename="' . $filename . '"');
		header('Content-Transfer-Encoding: binary');
		header('Accept-Ranges: bytes');
		header('Connection: close');

		//Initial length is the entire file
		$length = $size;

		//Allows partial downloads to be resumed
		if(!empty($_SERVER['HTTP_RANGE'])):

			//Parse the range header
			list($tmp, $range) = explode("=", $_SERVER['HTTP_RANGE'], 2);
			list($range) = explode(",", $range, 2);
			list($start, $end) = explode("-", $range);
			$start = intval($start);
			$end = intval($end) ? intval($end) : $size - 1;

			//Sets the length to the requested range
			$length = $end - $start + 1;

			//Send the partial headers
			header("HTTP/1.1 206 Partial Content");
			header("Content-Range: bytes ".$start."-".$end."/".$size);

			//Seek the file
			fseek($file, $start);

		endif;

		//Set content length header
		header("Content-Length: " . $length, true);

		if($chunk):
			while($length > 0 && !connection_aborted()):
				$read = $length > $chunk ? $chunk : $length;
				$read = $read < $length ? $read : $length;
				$length -= $read;
				print fread($file, $read);
				flush();
				sleep(1);
			endwhile;
		else:
			fpassthru($file);
		endif;

		exit;

	}

	/**
	 * Unlinks anything in the Utils::$files array at the end of the request
	 * @param string $file
	 */
	static function unlinkWhenFinished($file){

		static $registered_function = false;

		self::$unlinks[] = $file;

		if(!$registered_function):
			register_shutdown_function(array(__NAMESPACE__."\Utils", "unlinkPending"));
			$registered_function = true;
		endif;

	}

	/**
	 * Unlinks any files in the pending unlinks array
	 */
	static function unlinkPending(){

		foreach(self::$unlinks as $file) unlink($file);
		self::$unlinks = array();

	}

	/**
	 * Turns a textarea's input into an array by exploding on commas and spaces
	 * @param string $string
	 */
	static function arrayFromTextarea($string){

		$data = array();
		$parts = preg_split("/[\s,]+/", $string);
		foreach($parts as $part):
			if(strlen(trim($part))) $data[] = trim($part);
		endforeach;

		return $data;

	}

	/**
	 * Turns a table pasted from excel into a multi-dimensional array
	 * @param string $string
	 */
	static function arrayFromExcel($string){

		$data = array();
		$string = str_replace("\r", "", trim($string));
		$data = explode("\n", $string);
		foreach($data as &$row):
			$row = explode("\t", $row);
			foreach($row as &$el):
				$el = trim($el);
			endforeach;
			unset($el);
		endforeach;
		unset($row);

		return $data;

	}

	/**
	 * Combines all of the files within a directory (recursively).
	 * Supports the If-Modified-Since header
	 * @param string $directory
	 */
	static function combineFiles($directory, $extension){

		//Clean up headers that may have been set
		header_remove("Expires");
		header_remove("Cache-Control");
		header_remove("Pragma");

		$modtime = 0;

		$dir = new RecursiveDirectoryIterator($directory);
		$itr = new RecursiveIteratorIterator($dir);

		//Check the last mod time
		foreach($itr as $file):

			if(substr($file->getFilename(), -1 * strlen($extension)) == $extension):

				$modtime = max($modtime, $file->getMTime());

			endif;

		endforeach;

		//Check the If-Modified-Since header
		if(array_key_exists('HTTP_IF_MODIFIED_SINCE', $_SERVER)):

			$time = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);

			//If not modified, return a 304 header
			if($modtime <= $time):

		        header("HTTP/1.1 304 Not Modified");
		        exit;

			endif;

		endif;

		//Compile the file
		header("Last-Modified: ".gmdate('D, d M Y H:i:s', $modtime).' GMT');
		foreach($itr as $file):

			if(substr($file->getFilename(), -1 * strlen($extension)) == $extension):

				print file_get_contents($file) ."\n";

			endif;

		endforeach;

	}

	/**
	 * Creates an XFDF file using the data array provided for use with the
	 * associated PDF file.
	 * @param string $pdf_file The file name of the PDF
	 * @param array $data Form data in key-value pairs with no more than 2 dimensions
	 */
	static function generateXFDF($pdf_file, $data){

		$out = '<?=xml version="1.0" encoding="UTF-8"?>' . "\n";
		$out.= '<xfdf xmlns="http://ns.adobe.com/xfdf/" xml:space="preserve">' . "\n";
		$out.= '<fields>' . "\n";
		foreach($data as $field => $val):
			$out.= '<field name="' . $field . '">' . "\n";
			if(is_array($val)):
				foreach($val as $opt):
					$out.= '<value>' . htmlentities($opt) . '</value>' . "\n";
				endforeach;
			else:
				$out.= '<value>' . htmlentities($val) . '</value>' . "\n";
			endif;
			$out.= '</field>' . "\n";
		endforeach;
		$out.= '</fields>' . "\n";
		$out.= '<ids original="' . md5($pdf_file) . '" modified="' . time() . '" />' . "\n";
		$out.= '<f href="' . $pdf_file . '" />' . "\n";
		$out.= '</xfdf>' . "\n";
		return $out;

	}

	/**
	 * Returns the path to a temp file created in the system's default TEMP directory.
	 * The temp file will be unlinked automatically at the end of script execution.
	 */
	static function createTempFile(){

		$file = tempnam(sys_get_temp_dir(), "temp-");
		self::unlinkWhenFinished($file);
		return $file;

	}

	/**
	 * Handles uncaught exceptions
	 * @param Exception $e
	 */
	static function handleDefaultExceptions(Exception $e){

		$config = Container::get('config');
		$to = !empty($config['bug_emails']) ? $config['bug_emails'] : "bugz@wirelesscapital.com";

		if($config['environment'] == 'dev'){
			var_dump($e, $_GET, $_POST);
			exit;
		}

		$email = new Email();
		$email->addTo($to);
		$email->addFrom("bug-report@wirelesscapital.com");
		$email->setSubject($_SERVER['HTTP_HOST'] . " - exception");
		if(isset($e->xdebug_message)){

			ob_start();

			print "<table>" . $e->xdebug_message . "</table>";
			print '<h3>$_SERVER:</h3>';
			var_dump($_SERVER);
			print '<h3>$_GET:</h3>';
			var_dump($_GET);
			print '<h3>$_POST:</h3>';
			var_dump($_POST);

			$email->addPart(ob_get_clean(), "text/html");

			unset($e->xdebug_message);

		}
		$email->addPart(print_r($e, true) . "\n\nServer:\n" . print_r($_SERVER,true) . "\n\nGet:\n" . print_r($_GET,true) . "\n\nPost:\n" . print_r($_POST,true), "text/plain");
		$email->send();

		//Redirect
		if($_POST):
			Errors::add("An error has occurred. Details about the error have been sent to a system administrator.");
			Helpers::redirect();
		else:
			Helpers::redirect('/error.html');
		endif;

	}

	/**
	 * Outputs $data as a JSON object and ends the request.
	 */
	static function outputJSON($data){
		ob_end_clean();
		header('Content-Type: text/json');
		print json_encode($data);
		exit;
	}

	/**
	 * Replaces instances of macros in a string with the values found in the given array.
	 * One example for this is when composing email templates, data passed to the email
	 * can be output in the email template by using macros of the form "{VAR_NAME}". Another example is
	 * when data needed before run-time is not available until run-time. A macro can be used instead,
	 * and at run-time the proper data will be inserted in its place.
	 * @param string $string
	 * @param array $array
	 */
	static function replaceMacros($string, $array){

		return preg_replace_callback(
			'/{([^\s}]+)}/u',
			function($matches) use($array) {
				$parts = explode('.', $matches[1]);
				$item = $array;
				foreach($parts as $part):
					$part = strtolower($part);
					if(array_key_exists($part, $item)):
						if(is_array($item[$part])):
							$item = $item[$part];
						else:
							return $item[$part];
						endif;
					else:
						return $matches[0];
					endif;
				endforeach;
				return $matches[0];
			},
			$string
		);

	}

	/**
	 * Augments the provided date by a period of 1 month and treats any day past the
	 * 28th as the end of the month
	 * @param DateTime $date
	 */
	static function nextMonth(DateTime $date){

		static $month_interval;
		if(!$month_interval) $month_interval = new DateInterval('P1M');

		if($date->format("d") >= 28):
			//Assume end of month
			$temp = new DateTime($date->format("Y-m-01"));
			$temp->modify("+2 months -1 day");
			$date = $temp;
		else:
			$date->add($month_interval);
		endif;

		return $date;

	}

	/**
	 * Returns the $_POST array without PHP's character replacements.
	 * PHP replaces characters 32, 46, 91, and 128 - 159 when reading external vars
	 */
	static function getRealPost($multidimensional = true){

		//Anonymous function to set array keys via recursion
		$set_value = function(&$data, $keys, $val) use (&$set_value){
			$key = array_shift($keys);
			if(!count($keys)):
				//If this is the last key, set it
				if($key === ''):
					$data[] = $val;
				else:
					$data[$key] = $val;
				endif;
				return;
			endif;
			if(!is_array($data) || !isset($data[$key]) || !is_array($data[$key])) $data[$key] = array();
			$data = &$data[$key];
			$set_value($data, $keys, $val);
		};

		$data = array();

		//Read the POST input stream
		$vars = explode("&", file_get_contents("php://input"));
		foreach($vars as $var):

			//Decode the input
			list($key, $val) = explode("=", $var);
			$key = urldecode($key);
			$val = urldecode($val);

			//Are intentionally building a flat array?
			if(!$multidimensional):
				$data[$key] = $val;
				continue;
			endif;

			//Check for array dimenisons
			preg_match_all('/\[([^\]]*)\]/', $key, $matches);
			if(!$matches[1]):
				$data[$key] = $val;
				continue;
			endif;

			//Parse the dimensions
			$keys = $matches[1];
			array_unshift($keys, substr($key, 0, strpos($key, '[')));

			//Set the value at that key
			$set_value($data, $keys, $val);

		endforeach;

		return $data;

	}

}