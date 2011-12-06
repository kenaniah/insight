<?php
namespace HTML\Form\Field;
use \Format\Format;

class Link extends FormField {

	protected $url = null;

	function __toString(){

		if(is_null($this->value)) return "";
		if(is_null($this->url)) return $this->getValue(Format::HTML);

		$out = "<a href='" . \Utils::replaceMacros($this->url, $this->container_value) . "'" . $this->outputAttributes().">";
		$out.= $this->getValue(Format::HTML);
		$out.= "</a>";
		return $out;

	}

	function setUrl($url){
		$this->url = $url;
		return $this;
	}

}