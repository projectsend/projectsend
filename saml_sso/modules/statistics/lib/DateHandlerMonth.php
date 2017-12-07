<?php
/*
 * @author Andreas Åkre Solberg <andreas.solberg@uninett.no>
 * @package SimpleSAMLphp
 */
class sspmod_statistics_DateHandlerMonth extends sspmod_statistics_DateHandler {

	/**
	 * Constructor
	 *
	 * @param array $offset 	Date offset
	 */
	public function __construct($offset) {
		$this->offset = $offset;
	}
	

	public function toSlot($epoch, $slotsize) {
		$dsttime = $this->getDST($epoch) + $epoch;
		$parsed = getdate($dsttime);
		$slot = (($parsed['year'] - 2000) * 12) + $parsed['mon'] - 1;
		return $slot;
	}

	public function fromSlot($slot, $slotsize) {
		
		$month = ($slot % 12);
		$year = 2000 + floor($slot / 12);
		
		$epoch = mktime(0, 0, 0, $month + 1, 1, $year, FALSE);
		return $epoch;
	}

	public function prettyHeader($from, $to, $slotsize, $dateformat) {
		
		$month = ($from % 12) + 1;
		$year = 2000 + floor($from / 12);
		
		return $year . '-' . $month;
	}
}
