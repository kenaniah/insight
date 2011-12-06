<?php
namespace HTML\Form\Layout;

use HTML\Form\Field\Hidden;
use \HTML\Form\Field\FormField;
use \HTML\Form\Container\Container;
/**
 * Defines a vertical layout strategy to be used by a Container.
 * Elements are wrapped in div tags to create block level elements.
 */
class Vertical extends LayoutManager {

	function render(Container $container) {

		$container->addClass("vgroup");

		$out = "";
		$out .= $container->indent() . "<div".$container->outputAttributes().">";
		$elements = array();
		$after = "";
		foreach($container->getChildren() as $child):

			$t = $child->indent();
			if($child instanceof Hidden):
				$after .= $t . $child;
				continue;
			endif;

			$label = null;
			$form_field = $child instanceof FormField;
			$label = $form_field ? $child->getLabel($this->use_full_labels) : null;
			$class = array_pop(explode('\\', get_class($child)));
			$switched = in_array($class, $this->switch_positions);

			$tmp = "";
			if($form_field) $tmp .= "<label>";
				if($label && !$switched) $tmp .= $child->getLabel($this->use_full_labels) . " ";
				$tmp .= $child->__toString();
				if($label && $switched) $tmp .= " " . $child->getLabel($this->use_full_labels);
			$tmp .= $form_field ? "</label>" : $t;

			if($form_field) $tmp .= $child->renderTooltip();

			$elements[] = $tmp;

		endforeach;
		$out .= implode("<br>", $elements);
		$out .= $after;
		$out .= "</div>";
		return $out;
	}
}