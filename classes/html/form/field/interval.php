<?php
namespace HTML\Form\Field;

class Interval extends Text {

	function __construct($name, $params = array()){
		parent::__construct($name, $params);

		//Set up the formatter
		if(!($this->formatter instanceof \Format\FormatInterval)):
			$this->formatter = new \Format\FormatInterval;
		endif;

	}

	function setValue($value){

		if($value instanceof \DateInterval){
			$this->value = $value;
			$this->setAttribute('value', $this->formatter->format($this->value));
		}else{
			try {
				if($value){
					//PostgreSQL interval compat transformations
					$rep = array(
						'y' => 'years',
						'ys' => 'years',
						'yr' => 'years',
						'yrs' => 'years',
						'm' => 'months',
						'mon' => 'months',
						'mons' => 'months',
						'd' => 'days'
					);
					$value = str_replace(array_keys($rep), array_values($rep), $value);
					$value = preg_replace('/([0-9]{2}):([0-9]{2}):([0-9]{2})/', '$1 hours $2 minutes $3 seconds', $value);
					$this->setValue(\DateInterval::createFromDateString($value));
				}else{
					$this->value = null;
					$this->removeAttribute('value');
				}
			} catch (\Exception $e) {
				\Errors::add("Invalid time period (" . $value . ") passed to field <b>" . $this->getAttribute('name') . "</b>");
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