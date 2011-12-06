<?php
namespace HTML\Form\Layout;
use HTML\Element;
use \HTML\Form\Field\FormField;
use \HTML\Form\Container\Container;

/**
 * Defines a table layout strategy to be used by a Container.
 */
class TabSet extends LayoutManager {

	protected $use_full_labels = false;

	function render(Container $container) {

		$out = "";
		$ind = $container->indent() . "\t";

		$tabs = array();

		foreach($container->getChildren() as $child):

			$child->indent += 1;

			$id = Element::generateElementId();

			//Build the tab
			$tabs[] = array($id, $child->getLabel($this->use_full_labels));
			$out .= $ind . "<div id='".$id."'>" . $child . $ind . "</div>";

		endforeach;

		$return = $ind . "<ul>";
		foreach($tabs as $tab):
			$return .= $ind . "\t<li><a href='#" . $tab[0] . "'>" . $tab[1] . "</a></li>";
		endforeach;
		$return .= $ind . "</ul>";
		$return .= $out;

		return $return;
	}
}