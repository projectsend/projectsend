<?php
/*
 * @author Andreas Åkre Solberg <andreas.solberg@uninett.no>
 * @package SimpleSAMLphp
 */
class sspmod_statistics_DateHandler {

	protected $offset;

	/**
	 * Constructor
	 *
	 * @param array $offset 	Date offset
	 */
	public function __construct($offset) {
		$this->offset = $offset;
	}
	
	protected function getDST($timestamp) {
		if (idate('I', $timestamp)) return 3600;
		return 0;
	}

	public function toSlot($epoch, $slotsize) {
		$dst = $this->getDST($epoch);
		return floor( ($epoch + $this->offset + $dst) / $slotsize);
	}

	public function fromSlot($slot, $slotsize) {
		$temp = $slot*$slotsize - $this->offset;
		$dst = $this->getDST($temp);
		return $slot*$slotsize - $this->offset - $dst;
	}

	public function prettyDateEpoch($epoch, $dateformat) {
		return date($dateformat, $epoch);
	}

	public function prettyDateSlot($slot, $slotsize, $dateformat) {
		return $this->prettyDateEpoch($this->fromSlot($slot, $slotsize), $dateformat);

	}
	
	public function prettyHeader($from, $to, $slotsize, $dateformat) {
		$text = $this->prettyDateSlot($from, $slotsize, $dateformat);
		$text .= ' to ';
		$text .= $this->prettyDateSlot($to, $slotsize, $dateformat);
		return $text;
	}
}
