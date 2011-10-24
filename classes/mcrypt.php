<?php
/**
 * Simple encryption / masking class
 *
 * @author Kenaniah Cerny <kenaniah@gmail.com> https://github.com/kenaniah/insight
 * @license http://creativecommons.org/licenses/by-sa/3.0/
 * @copyright Copyright (c) 2009, Kenaniah Cerny
 */
class MCrypt {

	/**
	 * Static encryption key used for quick encrypt/decrypt
	 * Once this key is set, it should never be changed.
	 * @var string
	 */
	const KEY = "";

	/**
	 * Header used to identify whether or not data has already been encrypted
	 * @var string
	 */
	const HEADER = '$enc$';

	const MASK_LEFT = "left";
	const MASK_RIGHT = "right";

	/**
	 * Quick function that encrypts data according to the key using
	 * the Rijndael-256 algorithm.
	 * @param string $data The data to encrypt
	 */
	static function quickEncrypt($data){

		if(!self::KEY):
			user_error("Please set an encryption key before use of the Mcrypt class.", E_USER_ERROR);
		endif;

		//Don't encrypt if the header is present
		if(strpos($data, self::HEADER) === 0) return $data;

		$iv = mcrypt_create_iv(mcrypt_get_iv_size("rijndael-256", "nofb"), MCRYPT_DEV_URANDOM);
		return self::HEADER . base64_encode($iv . mcrypt_encrypt("rijndael-256", self::KEY, $data, "nofb", $iv));

	}

	/**
	 * Quick function that decrpyts data according to the key using
	 * the Rijndael-256 algorithm.
	 * @param string $encrypted The data to decrypt
	 */
	static function quickDecrypt($encrypted){

		if(!self::KEY):
			user_error("Please set an encryption key before use of the Mcrypt class.", E_USER_ERROR);
		endif;

		//Don't decrypt if the header is missing
		if(strpos($encrypted, self::HEADER) !== 0) return $encrypted;

		$encrypted = substr($encrypted, strlen(self::HEADER));  //Strip the header

		$encrypted = base64_decode($encrypted);

		$size = mcrypt_get_iv_size("rijndael-256", "nofb");
		$iv = substr($encrypted, 0, 32);
		$encrypted = substr($encrypted, 32);

		return mcrypt_decrypt("rijndael-256", self::KEY, $encrypted, "nofb", $iv);

	}

	/**
	 * Returns a masked version of the input. If visible chars is 0, the entire
	 * input will be masked.
	 *
	 * @param string $input The input to mask
	 * @param integer $visible_chars The number of characters to leave unmasked
	 * @param string $mask Which side of the input to mask
	 */
	static function mask($input, $visible_chars = 0, $mask = self::MASK_LEFT){

		//Decrypt if input is quick-encrypted
		$input = self::quickDecrypt($input);

		$length = strlen($input);
		$mask_length = $length - $visible_chars;

		//Check the short-cuts
		if($visible_chars >= $length) return $input;
		if($visible_chars <= 0) return str_repeat("*", $length);

		//Mask the input
		switch($mask):

			case self::MASK_RIGHT:
				return substr($input, 0, $mask_length * -1) . str_repeat("*", $mask_length);
				break;
			case self::MASK_LEFT:
			default:
				return str_repeat("*", $mask_length) . substr($input, $mask_length);
				break;

		endswitch;

	}

	/**
	 * Determines whether or not an input value has been modified by comparing
	 * the input with its mask. Note that you need the original (unmasked) input
	 * and the mask settings in order for this function to work correctly.
	 *
	 * @param string $input The input to mask
	 * @param integer $visible_chars The number of characters to leave unmasked
	 * @param string $mask Which side of the input to mask
	 */
	static function mask_changed($input, $visible_chars = 0, $mask = self::MASK_LEFT){

		return $input !== self::mask($input, $visible_chars, $mask);

	}

}