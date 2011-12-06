<?php
namespace HTML\Form;
/**
 * Denotes objects that can be contained by instances of iContainer
 */
interface iContainable {

	/**
	 * Renders the individual object
	 * @return string of the rendered HTML element
	 */
	function __toString();

	/**
	 * Returns the form value
	 */
	function getValue();

	/**
	 * Sets the form value
	 */
	function setValue($value);

}