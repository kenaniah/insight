<?php
namespace Format;

/**
 * Formats and encrypts a form field
 */
class FormatEncrypted extends Format {

	public $masked = true;

	function raw($value){
		return \Mcrypt::quickEncrypt($value);
	}

	function html($value){
		if($this->masked) return \Mcrypt::mask($value);
		return \Mcrypt::quickDecrypt($value);
	}

	function export($value){
		return self::html($value);
	}

	function form($value){
		return \Mcrypt::quickDecrypt($value);
	}

}