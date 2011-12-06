<?php
namespace Format;
/**
 * Formats the value using DateInterval formatters
 */
class FormatInterval extends Format {

	/**
	 * Returns a string representation of an interval
	 * @param unknown_type $interval
	 */
	protected function intervalToString(\DateInterval $interval){
		$doPlural = function($nb,$str){return $nb>1?$str.'s':$str;}; // adds plurals

		//Normalize values
		if($interval->s > 60):
			$interval->i += floor($interval->s / 60);
			$interval->s %= 60;
		endif;

		if($interval->i > 60):
			$interval->h += floor($interval->i / 60);
			$interval->i %= 60;
		endif;

		if($interval->h > 24):
			$interval->d += floor($interval->h / 24);
			$interval->h %= 24;
		endif;

		if($interval->m > 12):
			$interval->y += floor($interval->m / 12);
			$interval->m %= 12;
		endif;

		$format = array();
		if($interval->y !== 0) $format[] = "%y ".$doPlural($interval->y, "year");
		if($interval->m !== 0) $format[] = "%m ".$doPlural($interval->m, "month");
		if($interval->d !== 0) $format[] = "%d ".$doPlural($interval->d, "day");
		if($interval->h !== 0) $format[] = "%h ".$doPlural($interval->h, "hour");
		if($interval->i !== 0) $format[] = "%i ".$doPlural($interval->i, "minute");
		if($interval->s !== 0) $format[] = "%s ".$doPlural($interval->s, "second");
		return $interval->format(join(" ", $format));
	}

	function raw($value){

		if(!$value) return null;

		if($value instanceof \DateInterval):
			return $this->intervalToString($value);
		else:
			try {
				$value = \DateInterval::createFromDateString($value);
				return $this->intervalToString($value);
			} catch (\Exception $e) {
				\Errors::add("Invalid time period (" . $value . ") passed to formatter.");
				return null;
			}
		endif;

	}

}