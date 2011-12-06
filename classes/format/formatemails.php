<?php
namespace Format;

/**
 * Formats multiple email addresses from the contact_has_emails XML result
 */
class FormatEmails extends Format {

	/**
	 * Parses the $value as xml and returns email information in array format
	 * @see Format.Format::raw()
	 */
	function raw($value){

		//Return if value appears to already have been parsed
		if(is_array($value)) return $value;

		$data = array();
		$xml = simplexml_load_string($value);
		foreach($xml->row as $row):
			$data[] = array(
				'id' => intval($row->id) ?: null,
				'contact_id' => intval($row->contact_id),
				'email' => (string) $row->email,
				'is_primary' => $row->is_primary == 'true'
			);
		endforeach;

		return $data;

	}

	/**
	 * (non-PHPdoc)
	 * @see Format.Format::html()
	 */
	function html($value){
		$data = self::raw($value);
		$emails = array();
		foreach($data as $email):
			$tmp = "<a href='mailto:" . \Helpers::entify($email['email']) . "'>". \Helpers::entify($email['email'])."</a>";
			if($email['is_primary']) $tmp .= "<span class='subtitle'>(Primary)</span>";
			$emails[] = $tmp;
		endforeach;
		return join("<br>", $emails);
	}

}