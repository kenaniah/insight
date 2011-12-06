<?php
namespace HTML\Form\Field;

use Format\Format;

class Point extends FormField {

	protected static $dialog_output = false;

	function __toString(){

		if($this->format_mode == Format::HTML):

			$out = "<a href='#' class='event' data-click-handler='openMap' data-title='".$this->getLabel()."' title='Click to view map'>";
			$out.= $this->getValue($this->format_mode);
			$out.= "</a>";
			$out.= self::outputDialog();

			return $out;

		elseif($this->format_mode == Format::FORM):

			$out = "<span>";
			$out.= "<a href='#' class='event' data-edit='1' data-click-handler='openMap' data-title='".$this->getLabel()."' title='Click to view / input location'>";
			$out.= $this->getValue($this->format_mode);
			$out.= "</a>";
			$out.= "<input type='hidden' name='" . $this->getFullName() . "' value='".$this->getValue()."' />";
			$out.= "</span>";
			$out.= self::outputDialog();

			return $out;

		endif;

		return $this->getValue($this->format_mode);

	}

	/**
	 * This function returns the HTML map dialog that this field uses
	 */
	static protected function outputDialog(){

		//Ensure that we only ouput one copy of the dialog
		if(self::$dialog_output) return "";
		self::$dialog_output = true;

		ob_start();
?>
<div class="move-me unrendered">
	<div class="dialog map-point-dialog" data-dialog-ok="saveMapLocation" title="Location" data-modal="1" data-width="600" data-height="500" data-buttons="[btnOK, btnCancel]">
		<div class='relative inline block' style='width: 100%'>
			<div class="map"></div>
			<div class='map-crosshair'></div>
		</div>
		<table>
			<tr>
				<td><label for='map-lat'>Latitude:</label></td>
				<td><input type='number' step='0.000001' name='lat' id='map-lat'></td>
			</tr>
			<tr>
				<td><label for='map-long'>Longitude:</label></td>
				<td><input type='number' step='0.000001' name='long' id='map-long'></td>
			</tr>
		</table>
	</div>
</div>
<?
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}

}