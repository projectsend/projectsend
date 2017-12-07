<?php
/*
 * @author Andreas Åkre Solberg <andreas.solberg@uninett.no>
 * @package SimpleSAMLphp
 */
class sspmod_statistics_Statistics_Rulesets_Ratio extends sspmod_statistics_Statistics_Rulesets_BaseRule {

	protected $refrule1;
	protected $refrule2;
	
	/**
	 * Constructor
	 */
	public function __construct($statconfig, $ruleconfig, $ruleid, $available) {
		assert('$statconfig instanceof SimpleSAML_Configuration');
		assert('$ruleconfig instanceof SimpleSAML_Configuration');
		
		parent::__construct($statconfig, $ruleconfig, $ruleid, $available);
		
		$refNames = $this->ruleconfig->getArray('ref');
		
		$statrulesConfig = $this->statconfig->getConfigItem('statrules');
		
		$statruleConfig1 = $statrulesConfig->getConfigItem($refNames[0]);
		$statruleConfig2 = $statrulesConfig->getConfigItem($refNames[1]);
		
		$this->refrule1 = new sspmod_statistics_Statistics_Rulesets_BaseRule($this->statconfig, $statruleConfig1, $refNames[0], $available);
		$this->refrule2 = new sspmod_statistics_Statistics_Rulesets_BaseRule($this->statconfig, $statruleConfig2, $refNames[1], $available);
	}
	
	public function availableTimeRes() {
		return $this->refrule1->availableTimeRes();
	}
	
	public function availableFileSlots($timeres) {
		return $this->refrule1->availableFileSlots($timeres);
	}

	protected function resolveTimeRes($preferTimeRes) {
		return $this->refrule1->resolveTimeRes($preferTimeRes);
	}
	
	protected function resolveFileSlot($timeres, $preferTime) {
		return $this->refrule1->resolveFileSlot($timeres, $preferTime);
	}
	
	
	public function getTimeNavigation($timeres, $preferTime) {
		return $this->refrule1->getTimeNavigation($timeres, $preferTime);
	}
	
	public function getDataSet($preferTimeRes, $preferTime) {
		$timeres = $this->resolveTimeRes($preferTimeRes);
		$fileslot = $this->resolveFileSlot($timeres, $preferTime);
		
		$refNames = $this->ruleconfig->getArray('ref');
		
		$dataset = new sspmod_statistics_RatioDataset($this->statconfig, $this->ruleconfig, $refNames, $timeres, $fileslot);
		return $dataset;
	}
	

}

