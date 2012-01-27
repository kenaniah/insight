<?php
namespace Format;
use Utils;

/**
 * A chained implementation of FormatLink
 */
class FormatChainedLink extends FormatChain {

	/**
	 * The url to be used when outputting the link.
	 * Macros encased with {} will be replaced with matching
	 * values from the $container_value property.
	 * @var string
	 */
	protected $url;

	function __construct(Format $formatter, $url){
		parent::__construct($formatter);
		$this->url = $url;
	}

	function html($value){

		$chained = parent::html($value);

		$keys = array_map("strtolower", array_keys($this->container_value));
		$values = array_values($this->container_value);
		$data = array_combine($keys, $values);

		$doc = new \DOMDocument;
		$doc->loadHTML($chained);

		$search = function($node, $element) use (&$search){
			foreach($node->childNodes as $child):
				if($child->nodeType == XML_ELEMENT_NODE) $search($child, $element);
				if($child->nodeType == XML_TEXT_NODE):
					$node->replaceChild($element, $child);
					$element->appendChild($child);
				endif;
			endforeach;
		};
		$link = $doc->createElement('a');
		$link->setAttribute('href', Utils::replaceMacros($this->url, $data));
		$link->setAttribute('class', 'inherit-color');

		$search($doc, $link);

		return $doc->saveHTML(
			$doc
				->getElementsByTagName('body')
				->item(0)
					->childNodes
					->item(0)
		);

	}

}