<?php
namespace HTML\Form\Layout;
use \HTML\Form\Field\FormField;
use \HTML\Form\Container\Container;

/**
 * Defines a horizontal layout strategy to be used by a Container
 */
class RadioCheck extends LayoutManager {

	protected $use_full_labels = false;

	function render(Container $container) {

		$out = "<span class='radiocheck'>";
		foreach($container->getChildren() as $child):

			$label = null;
			if($child instanceof FormField):
				$out .= $child->indent();
				$label = $child->getLabel($this->use_full_labels);
			endif;

			if($label) $out .= "<label>";
			$out .= $child->__toString();
			if($label) $out .= " " . $label . "</label>";
			//if($child instanceof FormField) $out .= $child->renderTooltip();

		endforeach;
		$out.= "</span>";
		return $out;

	}
}