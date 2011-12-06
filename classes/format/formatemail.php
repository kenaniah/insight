<?php
namespace Format;

/**
 * Formats an email address
 */
class FormatEmail extends Format {

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::html()
	 */
	function html($value){
		return "<a href='mailto:" . \Helpers::entify($value) . "'>". \Helpers::entify($value)."</a>";
	}

}