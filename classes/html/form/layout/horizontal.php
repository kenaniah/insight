<?php
namespace HTML\Form\Layout;
use HTML\Form\Field\Hidden;

use \HTML\Form\Field\FormField;
use \HTML\Form\Container\Container;

/**
 * Defines a horizontal layout strategy to be used by a Container
 */
class Horizontal extends LayoutManager {

	function render(Container $container) {

		$out = "";
		$out .= $container->indent() . "<span".$container->outputAttributes().">";

		foreach($container->getChildren() as $child):

			$class = array_pop(explode('\\', get_class($child)));
			$switched = in_array($class, $this->switch_positions);

			$label = null;

			if($child instanceof Hidden):
				$out .= $child->indent() . $child;
				continue;
			endif;

			if($child instanceof FormField):
				$out .= $child->indent();
				$label = $child->getLabel($this->use_full_labels);
			endif;

			if($label) $out .= "<label>";
				if($label && !$switched) $out .= $label . " ";
				$out .= $child->__toString();
				if($label && $switched) $out .= " " . $label;
			if($label) $out .= "</label>";

			if($child instanceof FormField) $out .= $child->renderTooltip();

		endforeach;

		$out .= "</span>";

		return $out;

	}
}