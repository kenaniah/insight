<?php
class Helpers{

	/**
	 * Redirects the user's browser to a new location
	 * @param string $url
	 */
	static function redirect($url=''){
		$config = Registry::get('config');
		if(!$url) $url = $_SERVER['REQUEST_URI'];
		if($config['debug']):
			var_dump("Forwarding to: " . $url);
			exit;
		endif;
		header("Location: ".$url);
		exit;
	}

	/**
	 * Redirects the browser to one level above the current path
	 */
	static function redirect_up(){
		$path = explode("/", substr(DIRECTORY, 0, -1));
		array_pop($path);
		self::redirect(implode("/", $path));
	}

	/**
	 * Generates the options for a dropdown from a traversable input
	 * @param Traversable $data
	 * @param boolean $escaped Whether or not to htmlspecialchars() output
	 * @param string $default Blank option text
	 */
	static function dropdown($data, $current_value, $escaped = true, $default = NULL){

		$out = "";

		if($default) $out .= "<option value=''>".$default."</option>";

		foreach($data as $index => $row){

			if(!is_array($row)){
				$val = $row;
				$row = array();
				$row['id'] = $index;
				$row['name'] = $val;
			}elseif(!array_key_exists('id', $row)){
				$row['id'] = $index;
			}

			$text = $escaped ? Helpers::entify($row['name']) : $row['name'];

			if(is_array($current_value)):
				$selected = in_array($row['id'], $current_value) ? 'selected' : '';
			else:
				$selected = $current_value == $row['id'] ? 'selected' : '';
			endif;

			$text = Helpers::truncateTo($text, 100);

			$out .= "<option value='".$row['id']."' ".$selected.">".$text."</option>";

		}

		return $out;

	}

	/**
	 * Formats a string for output in a form
	 * @param string $input
	 */
	static function entify($input){
		return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Returns only numeric characters from string input.
	 * Accounts for negatives using either - or () notation.
	 * @param string $string
	 */
	static function toNum($string){

		$polarity = 1;
		$string = str_replace(array(" ", "$"), '', $string); //Remove the dollar sign
		if(substr($string, 0, 1) == '(' && substr($string, -1) == ')'):
			$polarity = -1;
		endif;

		return preg_replace('/[^0-9.-]+/', '', $string) * $polarity;
	}

	/**
	 * Truncates a string to a specified length and adds <span title=''> for full title
	 * @param string $string
	 * @param integer $chars
	 */
	static function truncateTo($string, $chars = 15){

		if(!$string) return '&nbsp;';

		if(is_null($chars)) $chars = 15;

		if(strlen($string) <= $chars || $chars < 0) return $string;

		$output = '<span title="' . Helpers::entify($string) . '">';
		$output.= Helpers::entify(substr($string, 0, $chars)) . "<span class='null'>...</span>";
		$output.= '</span>';

		return $output;

	}

	/**
	 * Returns string as a date in the format specified
	 * @param string $string
	 * @param string $format When null, returns the original DateTime object
	 */
	static function date($string, $format = "Y-m-d"){

		try {
			$time = new DateTime($string);
		} catch (Exception $e){
			return null;
		}
		if(!$time->getTimestamp() || $string == '0000-00-00') return null;
		if(is_null($format)) return $time;
		return $time->format($format);

	}

	/**
	 * Formats a number as currency
	 * @param string $string
	 * @param string $format
	 */
	static function money($string, $format = "%n"){

		if($string < 0):
			return '<span class="red">' . money_format($format, $string) . '</span>';
		endif;

		return money_format($format, $string);

	}

	/**
	 * Returns a color-formatted yes / no
	 * @param boolean $bool
	 */
	static function bool($bool){
		return $bool ? '<span class="green">Yes</span>' : '<span class="red">No</span>';
	}

	/**
	 * Allows a form to preserve sort ordering
	 */
	static function preserve_sorting(Injector $injector = null){

		if(!$injector) $injector = Registry::get('injector');

		$out = "";

		if(!empty($injector->qs->sort)):
			foreach((array) $injector->qs->sort as $sort):
				$out.= '<input type="hidden" name="sort[]" value="'.$sort.'" />';
			endforeach;
		endif;
		if(!empty($injector->qs->order)):
			foreach((array) $injector->qs->order as $order):
				$out.= '<input type="hidden" name="order[]" value="'.$order.'" />';
			endforeach;
		endif;

		return $out;

	}

	/**
	 * Returns a file size in KB, MB, or GB
	 * @param integer $bytes
	 */
	static function filesize($bytes){

		if($bytes < pow(1024, 2)) return round($bytes / pow(1024, 1), 2) . " KB";
		if($bytes < pow(1024, 3)) return round($bytes / pow(1024, 2), 2) . " MB";
		return round($bytes / pow(1024, 3), 2) . " GB";

	}

	/**
	 * Returns an info box in HTML format
	 * @param string $content
	 */
	static function info($content){

$out = <<<EOT
<div class="ui-widget nosize info">
	<div class="ui-state-highlight ui-corner-all">
		<p>
			<span class="ui-icon ui-icon-info icon-inline"></span>
			<strong>Info:</strong>
			{$content}
		</p>
	</div>
</div>
<br />
EOT;

		return $out;

	}

}
?>