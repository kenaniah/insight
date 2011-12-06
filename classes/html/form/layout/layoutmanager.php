<?php
namespace HTML\Form\Layout;

use HTML\Form\Container\Container;

/**
 * Base class used to build layout managers
 */
abstract class LayoutManager {

	/**
	 * Determines whether or not the full label should be rendered.
	 * (Full labels add a colon to the end of the label when missing)
	 * @var boolean
	 */
	protected $use_full_labels = true;

	/**
	 * List of form field types that should have their labels / elements switched
	 * by layout managers that support it.
	 * @var array
	 */
	protected $switch_positions = array('Checkbox', 'Radio');

	/**
	 * Returns the rendered contents of the container
	 * @param Container $container
	 */
	abstract function render(Container $container);

	/**
	 * Whether or not to render the full label for fields
	 * @param boolean
	 */
	function useFullLabels($boolean){

		$this->use_full_labels = (boolean) $boolean;
		return $this;

	}

}