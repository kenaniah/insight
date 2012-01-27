<?php
namespace HTML\Form\Layout;
use HTML\Form\Field\Hidden;

use \HTML\Form\Field\FormField;
use \HTML\Form\Container\Container;
/**
 * Defines a table layout strategy to be used by a Container.
 * Tables will consist of $cols/2 pairings of columns, one for a label, the other for the form element.
 */
class Table extends LayoutManager {

	/**
	 * Total number of column pairs for the table.
	 * @var int
	 */
	protected $cols;

	public function __construct($cols = 1) {
		return $this->setColumns($cols);
	}

	public function setColumns($cols){
		$this->cols = (integer) $cols;
		return $this;
	}

	public function getColumns(){
		return $this->cols;
	}

	function render(Container $container) {
		$out = "";
		$count = 0;

		foreach($container->getChildren() as $child):
			$t = $child->indent();
			$child->indent += 2;

			if($child instanceof Hidden):
				$out .= $t . $child;
				continue;
			endif;


			if(!$count) $out .=  $t . "<tr>";

			$out .= $t . "\t<td>";

			if($child instanceof FormField):
				$out .= '<label for="'.$child->getAttribute('id').'">' . $child->getLabel($this->use_full_labels);
				$out .= '</label>';

			elseif($child instanceof Container):
				$out .= '<label>' . $child->getLabel($this->use_full_labels) . '</label>';
			endif;

			$out .= "</td>";
			$out .= $t . "\t<td>";
			$out .= $child->__toString();
			if($child instanceof FormField) $out .= $child->renderTooltip();
			$out .= (($child instanceof FormField) ? '' : $t . "\t") . "</td>";

			++$count;

			if($count == $this->cols):
				$out .= $t . "</tr>";
				$count = 0;
			endif;

		endforeach;

		//Balance them out
		if($count) $out .= str_repeat($t . "\t<td></td>", ($this->cols - $count) * 2) . $t . "</tr>";

		return $out;

	}
}