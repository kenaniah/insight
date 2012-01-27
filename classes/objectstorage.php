<?php
/**
 * Extends the SplObjectStorage class to provide index functions
 *
 * @author Kenaniah Cerny <kenaniah@gmail.com>
 */
class ObjectStorage extends SplObjectStorage {

	/**
	 * Returns the index of a given object, or false if not found
	 * @param object $object
	 */
	function indexOf($object){

		if(!$this->contains($object)) return false;

		foreach($this as $index => $obj) if($obj === $object) return $index;

	}

	/**
	 * Returns the object at the given index
	 */
	function itemAtIndex($index){

		$it = new LimitIterator($this, $index, 1);
		foreach($it as $obj) return $obj;

	}

	/**
	 * Returns the sequence of objects as specified by the offset and length
	 * @param int $offset
	 * @param int $length
	 */
	function slice($offset, $length){

		$out = array();
		$it = new LimitIterator($this, $offset, $length);
		foreach($it as $obj) $out[] = $obj;
		return $out;

	}

	/**
	 * Inserts an object (or an array of objects) at a certain point
	 * @param mixed $object A single object or an array of objects
	 * @param integer $index
	 */
	function insertAt($object, $index){

		if(!is_array($object)) $object = array($object);

		//Check to ensure that objects don't already exist in the collection
		foreach($object as $k => $obj):
			if($this->contains($obj)) unset($object[$k]);
		endforeach;

		//Do we have any objects left?
		if(!$object) return;

		//Detach any objects at or past this index
		$remaining = array();
		if($index < $this->count()):
			$remaining = $this->slice($index, $this->count() - $index);
			foreach($remaining as $obj) $this->detach($obj);
		endif;

		//Add the new objects we're splicing in
		foreach($object as $obj) $this->attach($obj);

		//Attach the objects we previously detached
		foreach($remaining as $obj) $this->attach($obj);

	}

	/**
	 * Removes the object at the given index
	 * @param integer $index
	 */
	function removeAt($index){

		$this->detach($this->itemAtIndex($index));

	}

}