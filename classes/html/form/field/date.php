<?php
namespace HTML\Form\Field;

class Date extends Text {

	protected $format = "n/d/Y";

	function __construct($name, $params = array()){
		parent::__construct($name, $params);
		$this->addClass('calendar');
		//Set up the formatter
		if(!($this->formatter instanceof \Format\FormatDateTime)):
			$this->formatter = new \Format\FormatDateTime;
			$this->formatter->format = $this->format;
		endif;
	}

	function setValue($value){

		if($value instanceof \DateTime){
			$this->value = $value;
			$this->setAttribute('value', $value->format($this->format));
		}else{
			try {
				if($value){
					$this->setValue(new \DateTime($value));
				}else{
					$this->value = null;
					$this->removeAttribute('value');
				}
			} catch (\Exception $e) {
				\Errors::add("Invalid time (" . $value . ") passed to field <b>" . $this->getAttribute('name') . "</b>");
				$this->value = null;
				$this->removeAttribute('value');
			}
		}

	}

	function getValue(){

		if($this->value){
			return $this->formatter->format($this->value);
		}

		return null;

	}

	/**
	 * Returns the real value for this field
	 */
	function getRealValue(){
		return $this->value;
	}


}