<?php
namespace Format;

/**
 * Formats a value as a telephone number
 */
class FormatPhones extends Format {

	/**
	 * Parses the $value as xml and returns phone information in array format
	 * @see Format.Format::raw()
	 */
	function raw($value){

		//Return if value appears to already have been parsed
		if(is_array($value)) return $value;

		$fmt = new FormatPhone();

		$data = array();
		$xml = simplexml_load_string($value);
		foreach($xml->row as $row):

			$data[] = array(
				'id' => intval($row->id) ?: null,
				'contact_id' => intval($row->contact_id),
				'phone' => $fmt->form((string) $row->phone),
				'is_primary' => $row->is_primary == 'true',
				'type' => (string) $row->type
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
		$phones = array();
		foreach($data as $phone):
			$tmp = "<td style='padding-right: 1em'><span class='phone'>" . $phone['phone'] . "</span></td>";
			$tmp .= "<td>";
			if($phone['type']) $tmp .= $phone['type'];
			if($phone['is_primary']) $tmp .= "<span class='subtitle'>(Primary)</span>";
			$tmp .= "</td>";
			$phones[] = $tmp;
		endforeach;

		$out = "";
		if($phones):
			$out .= "<table><tr>";
			$out .= join("</tr><tr>", $phones);
			$out .= "</tr></table>";
		endif;
		return $out;

	}

}