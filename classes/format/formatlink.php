<?php
namespace Format;
use Utils;
use Helpers;

/**
 * Formats a FormField as an HTML link
 */
class FormatLink extends Format {

	/**
	 * The url to be used when outputting the link.
	 * Macros encased with {} will be replaced with matching
	 * values from the $container_value property.
	 * @var string
	 */
	protected $url;

	function __construct($url){
		$this->url = $url;
	}

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::html()
	 */
	function html($value){
		if(is_null($value)) return null;
		$keys = array_map("strtolower", array_keys($this->container_value));
		$values = array_values($this->container_value);
		$data = array_combine($keys, $values);
		return "<a href='" . Utils::replaceMacros($this->url, $data) . "'>". Helpers::entify($value)."</a>";
	}

}