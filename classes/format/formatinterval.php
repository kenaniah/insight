<?php
namespace Format;
/**
 * Formats the value using DateInterval formatters
 */
class FormatInterval extends Format {

	/**
	 * Tracks whether or not the formatted interval is negative
	 * @var boolean
	 */
	protected $is_negative = null;

	/**
	 * Returns a string representation of an interval
	 * @param unknown_type $interval
	 */
	protected function intervalToString(\DateInterval $interval, $normalize = true){

		$doPlural = function($nb,$str){ return abs($nb) != 1 ? $str.'s' : $str; }; // adds plurals

		//Normalize values
		if($normalize):
			$date = new \DateTime;
			$date2 = clone($date);
			$date->add($interval);
			$interval = $date2->diff($date);
		else:
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
		endif;

		$format = array();
		if($interval->y !== 0) $format[] = "%y ".$doPlural($interval->y, "year");
		if($interval->m !== 0) $format[] = "%m ".$doPlural($interval->m, "month");
		if($interval->d !== 0) $format[] = "%d ".$doPlural($interval->d, "day");
		if($interval->h !== 0) $format[] = "%h ".$doPlural($interval->h, "hour");
		if($interval->i !== 0) $format[] = "%i ".$doPlural($interval->i, "minute");
		if($interval->s !== 0) $format[] = "%s ".$doPlural($interval->s, "second");

		$this->is_negative = $interval->invert;

		return $interval->format(join(" ", $format));

	}

	function raw($value, $normalize = false){

		if(!$value) return null;

		if($value instanceof \DateInterval):
			return $this->intervalToString($value, $normalize);
		else:
			try {
				$value = \DateInterval::createFromDateString($value);
				return $this->intervalToString($value, $normalize);
			} catch (\Exception $e) {
				\Errors::add("Invalid time period (" . $value . ") passed to formatter.");
				return null;
			}
		endif;

	}

	function html($value){

		$val = $this->raw($value, true);
		return $this->is_negative ? "<span class='negative'>-" . $val . "</span>" : $val;

	}

}