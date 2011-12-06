<?php
namespace HTML\Form\Container;
/**
 * Container that wraps child elements in an HTML fieldset.
 * A legend can optionally be set for this fieldset.
 */
class Fieldset extends Container {

	/**
	 * Optional legend for this fieldset
	 * @var string
	 */
	protected $legend;

	/**
	 * The URL to be used for an add link
	 * @var string
	 */
	protected $add_link;

	/**
	 * The URL to be used for an edit link
	 * @var string
	 */
	protected $edit_link;

	/**
	 * The URL to be used for a remove link
	 * @var string
	 */
	protected $remove_link;

	/**
	 * Used to fix a FF absolute positioning bug involving relative fieldsets
	 * @var boolean
	 */
	protected $wrap_with_div = false;

	public function setLegend($legend) {
		$this->legend = $legend;
		return $this;
	}

	public function getLegend() {
		return $this->legend;
	}

	public function addClass($class){
		if($class == "relative"):
			$this->wrap_with_div = true;
			return $this;
		endif;
		return parent::addClass($class);
	}

	public function removeClass($class){
		if($class == "relative"):
			$this->wrap_with_div = false;
			return $this;
		endif;
		return parent::removeClass($class);
	}

	/**
	 * Used to display a link in the top-right of the field set container for adding
	 */
	public function setAddLink($link = null){

		if($link) $this->addClass("relative"); //Ensure relative positioning of the fieldset
		$this->add_link = $link;
		return $this;

	}

	/**
	 * Used to display a link in the top-right of the field set container for editing
	 */
	public function setEditLink($link = null){

		if($link) $this->addClass("relative"); //Ensure relative positioning of the fieldset
		$this->edit_link = $link;
		return $this;

	}

	/**
	 * Used to display a link in the top-right of the field set container for removing
	 */
	public function setRemoveLink($link = null){

		if($link) $this->addClass("relative"); //Ensure relative positioning of the fieldset
		$this->remove_link = $link;
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see Container::__toString()
	 */
	public function __toString() {

		$out = "";

		if($this->legend):
			$hash = str_replace(" ", "-", strtolower($this->legend));
			$out .= self::indent() . "<a name='" . \Helpers::entify($hash) . "'></a>";
		endif;

		if($this->wrap_with_div) $out .= self::indent() . "<div class='relative'>";

		$out .= self::indent() . "<fieldset".$this->outputAttributes().">";

		if($this->legend):
			$out .= self::indent() . "\t<legend>".$this->legend."</legend>";
		endif;

		//Build the top row of links
		if($this->add_link || $this->edit_link || $this->remove_link):
			$out .= self::indent() . "\t<span class='fieldset-links'>";
		endif;

		if($this->add_link) $out .= "<a class='icon add' title='Add' href='" . \Helpers::entify($this->edit_link) . "'>Add</a>";
		if($this->edit_link) $out .= "<a class='icon edit' title='Edit' href='" . \Helpers::entify($this->edit_link) . "'>Edit</a>";
		if($this->remove_link) $out .= "<a class='icon remove' title='Remove' href='" . \Helpers::entify($this->edit_link) . "'>Remove</a>";

		if($this->add_link || $this->edit_link || $this->remove_link):
			$out .= "</span>";
		endif;

		$out .= parent::__toString();
		$out .= self::indent() . "</fieldset>";

		if($this->wrap_with_div) $out .= self::indent() . "</div>";

		return $out;
	}


}