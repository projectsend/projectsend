<?php
/*
 * @author Andreas Åkre Solberg <andreas.solberg@uninett.no>
 * @package SimpleSAMLphp
 */
class sspmod_statistics_Ruleset {

	private $statconfig;
	private $availrulenames;
	private $availrules;
	private $available;

	/**
	 * Constructor
	 */
	public function __construct($statconfig) {
		$this->statconfig = $statconfig;
		$this->init();
	}

	private function init() {
		
		$statdir = $this->statconfig->getValue('statdir');
		$inputfile = $this->statconfig->getValue('inputfile');
		$statrules = $this->statconfig->getValue('statrules');
		$timeres = $this->statconfig->getValue('timeres');

		/*
		 * Walk through file lists, and get available [rule][fileslot]...
		 */
		if (!is_dir($statdir))
			throw new Exception('Statisics output directory [' . $statdir . '] does not exists.');
		$filelist = scandir($statdir);
		$this->available = array();
		foreach ($filelist AS $file) {
			if (preg_match('/([a-z0-9_]+)-([a-z0-9_]+)-([0-9]+)\.stat/', $file, $matches)) {
				if (array_key_exists($matches[1], $statrules)) {
					if (array_key_exists($matches[2], $timeres)) 
						$this->available[$matches[1]][$matches[2]][] = $matches[3];
				}
			}
		}
		if (empty($this->available)) 
			throw new Exception('No aggregated statistics files found in [' . $statdir . ']');

		/*
		 * Create array with information about available rules..
		 */
		$this->availrules = array_keys($statrules);
		$available_rules = array();
		foreach ($this->availrules AS $key) {
			$available_rules[$key] = array('name' => $statrules[$key]['name'], 'descr' => $statrules[$key]['descr']);
		}
		$this->availrulenames = $available_rules;
		
	}
	
	public function availableRules() {
		return $this->availrules;
	}
	
	public function availableRulesNames() {
		return $this->availrulenames;
	}
	
	/**
	 * Resolve which rule is selected. Taking user preference and checks if it exists.
	 */
	private function resolveSelectedRule($preferRule = NULL) {
		$rule = $this->statconfig->getString('default', $this->availrules[0]);
		if(!empty($preferRule)) {
			if (in_array($preferRule, $this->availrules)) {
				$rule = $preferRule;
			}
		}
		return $rule;
	}
	
	public function getRule($preferRule) {
		$rule = $this->resolveSelectedRule($preferRule);
		$statrulesConfig = $this->statconfig->getConfigItem('statrules');
		$statruleConfig = $statrulesConfig->getConfigItem($rule);
		
		$presenterClass = SimpleSAML_Module::resolveClass($statruleConfig->getValue('presenter', 'statistics:BaseRule'), 'Statistics_Rulesets');
		$statrule = new $presenterClass($this->statconfig, $statruleConfig, $rule, $this->available);
		return $statrule;
	}

}

