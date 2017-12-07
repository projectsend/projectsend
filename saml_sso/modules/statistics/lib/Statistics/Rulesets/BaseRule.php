<?php
/*
 * @author Andreas Åkre Solberg <andreas.solberg@uninett.no>
 * @package SimpleSAMLphp
 */
class sspmod_statistics_Statistics_Rulesets_BaseRule {

	protected $statconfig;
	protected $ruleconfig;
	protected $ruleid;
	protected $available;

	/**
	 * Constructor
	 */
	public function __construct($statconfig, $ruleconfig, $ruleid, $available) {
		assert('$statconfig instanceof SimpleSAML_Configuration');
		assert('$ruleconfig instanceof SimpleSAML_Configuration');
		$this->statconfig = $statconfig;
		$this->ruleconfig = $ruleconfig;
		$this->ruleid = $ruleid;
		
		$this->available = NULL;
		if (array_key_exists($ruleid, $available)) $this->available = $available[$ruleid];	
	}
	
	public function getRuleID() {
		return $this->ruleid;
	}
	
	public function init() {

	}
	
	public function availableTimeRes() {
		$timeresConfigs = $this->statconfig->getValue('timeres');
		$available_times = array(); 
		foreach ($timeresConfigs AS $tres => $tresconfig) {
			if (array_key_exists($tres, $this->available))
				$available_times[$tres] = $tresconfig['name'];
		}
		return $available_times;
	}

	
	public function availableFileSlots($timeres) {
		$timeresConfigs = $this->statconfig->getValue('timeres');
		$timeresConfig = $timeresConfigs[$timeres];
		
		if (isset($timeresConfig['customDateHandler']) && $timeresConfig['customDateHandler'] == 'month') {
			$datehandler = new sspmod_statistics_DateHandlerMonth(0);
		} else {
			$datehandler = new sspmod_statistics_DateHandler($this->statconfig->getValue('offset', 0));
		}
		
		
		/*
		 * Get list of avaiable times in current file (rule)
		 */
		$available_times = array(); 
		foreach ($this->available[$timeres] AS $slot) {
			$available_times[$slot] = $datehandler->prettyHeader($slot, $slot+1, $timeresConfig['fileslot'], $timeresConfig['dateformat-period']);
		}
		return $available_times;
	}

	protected function resolveTimeRes($preferTimeRes) {
		$timeresavailable = array_keys($this->available);
		$timeres = $timeresavailable[0];

		// Then check if the user have provided one that is valid
		if (in_array($preferTimeRes, $timeresavailable)) {
			$timeres = $preferTimeRes;
		}
		return $timeres;
	}
	
	protected function resolveFileSlot($timeres, $preferTime) {

		// Get which time (fileslot) to use.. First get a default, which is the most recent one.
		$fileslot = $this->available[$timeres][count($this->available[$timeres])-1];
		// Then check if the user have provided one.
		if (in_array($preferTime, $this->available[$timeres])) {
			$fileslot = $preferTime;
		}
		return $fileslot;
	}
	
	
	public function getTimeNavigation($timeres, $preferTime) {
		$fileslot = $this->resolveFileSlot($timeres, $preferTime);
		
		// Extract previous and next time slots...
		$available_times_prev = NULL; $available_times_next = NULL;

		$timeslots = array_values($this->available[$timeres]);
		sort($timeslots, SORT_NUMERIC);
		$timeslotindex = array_flip($timeslots);

		if ($timeslotindex[$fileslot] > 0) 
			$available_times_prev = $timeslots[$timeslotindex[$fileslot]-1];
		if ($timeslotindex[$fileslot] < (count($timeslotindex)-1) ) 
			$available_times_next = $timeslots[$timeslotindex[$fileslot]+1];
		return array('prev' => $available_times_prev, 'next' => $available_times_next);
	}
	
	public function getDataSet($preferTimeRes, $preferTime) {
		$timeres = $this->resolveTimeRes($preferTimeRes);
		$fileslot = $this->resolveFileSlot($timeres, $preferTime);
		$dataset = new sspmod_statistics_StatDataset($this->statconfig, $this->ruleconfig, $this->ruleid, $timeres, $fileslot);
		return $dataset;
	}
	

}

