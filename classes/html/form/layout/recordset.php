<?php
namespace HTML\Form\Layout;
use HTML\Form\Field\Span;
use HTML\Form\Field\Hidden;
use \HTML\Form\Field\FormField;
use \HTML\Form\Container\Container;
/**
 * Builds a group of TRs and TDs without field labels
 */
class Recordset extends LayoutManager {

	function render(Container $container) {
		$out = "";
		$t = $container->indent();
		$out .=  $t . "<tr>";

		//List of formatting classes that are automatically right-aligned
		$right_aligned = array('FormatDate', 'FormatDateTime', 'FormatMoney', 'FormatNumber', 'FormatPercentage', 'FormatInterval');

		foreach($container->getChildren() as $child):

			$t = $child->indent();
			$child->indent += 2;

			$class = array_pop(explode('\\', get_class($child->getFormatter())));
			$extra = "";
			if(in_array($class, $right_aligned)) $extra = " class='right'";

			if($child instanceof Hidden):
				$out .= $child->__toString();
			else:
				$out .= $t . "\t<td" . $extra . ">";
				$out .= $child->__toString();
				$out .= "</td>";
			endif;

		endforeach;
		$out .= $t . "</tr>";

		return $out;
	}

}