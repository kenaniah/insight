<?php
namespace HTML\Form\Layout;
use \HTML\Form\Field\FormField;
use \HTML\Form\Container\Container;

/**
 * Defines a table layout strategy to be used by a Container.
 */
class Grid extends LayoutManager {

	/**
	 * Total number of columns in the grid
	 * @var int
	 */
	protected $cols;

	public function __construct($cols = 2) {
		return $this->setColumns($cols);
	}

	public function setColumns($cols){
		$this->cols = (integer) $cols;
	}

	public function getColumns(){
		return $this->cols;
	}

	function render(Container $container) {
		$out = "";
		$count = 0;
		$first_row = true;
		$width = round(100 / $this->cols, 2);

		foreach($container->getChildren() as $child):
			$t = $child->indent();
			$child->indent += 2;

			if(!$count) $out .=  $t . "<tr>";

			$out .= $t . "\t<td".($first_row ? " width='".$width."%'":'').">";
			$out .= $child->__toString();
			if($child instanceof FormField) $out .= $child->renderTooltip();
			$out .= (($child instanceof FormField) ? '' : $t . "\t") . "</td>";

			++$count;

			if($count == $this->cols):
				$out .= $t . "</tr>";
				$count = 0;
				$first_row = false;
			endif;

		endforeach;

		//Balance them out
		if($count) $out .= str_repeat($t . "\t<td></td>", ($this->cols - $count)) . $t . "</tr>";

		return $out;
	}
}