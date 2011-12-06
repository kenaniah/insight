<?php
namespace HTML\Form\Layout;
use Format\Format;
use \HTML\Form\Field\FormField;
use \HTML\Form\Container\Container;

/**
 * Defines a table layout strategy to be used by addresses.
 */
class Address extends LayoutManager {

	function render(Container $container) {

		$out = "";
		$fields = array();
		$children = iterator_to_array($container->getChildren());
		foreach($children as $child) $fields[$child->getName()] = $child;

		$t = $fields['address1']->indent();
		$tt = $t . "\t";

		//shorthand layout function
		$col = function($field) use ($tt){
			$out = $tt . "<td><label for='" . $field->getAttribute('id') . "'>" . $field->getLabel($this->use_full_labels) . "</label></td>";
			$out .= $tt . "<td>" . $field . $field->renderTooltip() . "</td>";
			return $out;
		};


		//Render the address
		if($container->format_mode == Format::HTML):
			$out .= $t . "<tr>";
				$out .= $tt . "<td>" . $fields['address1'] . "</td>";
			$out .= $t . "</tr>";

			if(isset($fields['address2']) && strlen($fields['address2'])):
				$out .= $t . "<tr>";
					$out .= $tt . "<td>" . $fields['address2'] . "</td>";
				$out .= $t . "</tr>";
			endif;

			$out .= $t . "<tr>";
				$out .= $tt . "<td>" . $fields['city'] . ", " . $fields['state_id'] . " " . $fields['zipcode'] . "</td>";
			$out .= $t . "</tr>";
			return $out;
		endif;

		$out .= $t . "<tr>";
			$out .= $col($fields['address1']);
			$out .= $col($fields['address2']);
		$out .= $t . "</tr>";
		$out .= $t . "<tr>";
			$out .= $col($fields['city']);
			$out .= $col($fields['state_id']);
		$out .= $t . "</tr>";
		$out .= $t . "<tr>";
			$out .= $col($fields['zipcode']);
			$out .= $tt . "<td></td>" . $tt . "<td></td>";
		$out .= $t . "</tr>";

		return $out;
	}
}