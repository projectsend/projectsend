<?php
/*
 * @author Andreas Åkre Solberg <andreas.solberg@uninett.no>
 * @package SimpleSAMLphp
 */
class sspmod_statistics_Aggregator {

	private $statconfig;
	private $statdir;
	private $inputfile;
	private $statrules;
	private $offset;
	private $metadata;
	private $fromcmdline;
	
	private $starttime;

	/**
	 * Constructor
	 */
	public function __construct($fromcmdline = FALSE) {
	
		$this->fromcmdline = $fromcmdline;
		$this->statconfig = SimpleSAML_Configuration::getConfig('module_statistics.php');
		
		$this->statdir = $this->statconfig->getValue('statdir');
		$this->inputfile = $this->statconfig->getValue('inputfile');
		$this->statrules = $this->statconfig->getValue('statrules');
		$this->timeres = $this->statconfig->getValue('timeres');
		$this->offset = $this->statconfig->getValue('offset', 0);
		$this->metadata = NULL;
		
		$this->starttime = time();
	}
	
	public function dumpConfig() {
		echo 'Statistics directory   : ' . $this->statdir . "\n";
		echo 'Input file             : ' . $this->inputfile . "\n";
		echo 'Offset                 : ' . $this->offset . "\n";
	}
	
	public function debugInfo() {
		echo 'Memory usage           : ' . number_format(memory_get_usage() / (1024*1024), 2) . " MB\n";
	}
	
	public function loadMetadata() {
		$filename = $this->statdir . '/.stat.metadata';
		$metadata = NULL;
		if (file_exists($filename)) {
			$metadata = unserialize(file_get_contents($filename));
		}
		$this->metadata = $metadata;
	}
	
	public function getMetadata() {
		return $this->metadata;
	}
	
	public function saveMetadata() {
		$this->metadata['time'] = time() - $this->starttime;
		$this->metadata['memory'] = memory_get_usage();
		$this->metadata['lastrun'] = time();
		
		$filename = $this->statdir . '/.stat.metadata';
		file_put_contents($filename, serialize($this->metadata), LOCK_EX);
	}
	
	public function aggregate($debug = FALSE) {
		
		$this->loadMetadata();
		
		if (!is_dir($this->statdir)) 
			throw new Exception('Statistics module: output dir do not exists [' . $this->statdir . ']');
		
		if (!file_exists($this->inputfile)) 
			throw new Exception('Statistics module: input file do not exists [' . $this->inputfile . ']');
		
		$file = fopen($this->inputfile, 'r');

		if ($file === FALSE)
			throw new Exception('Statistics module: unable to open file [' . $this->inputfile . ']');
		
		$logparser = new sspmod_statistics_LogParser(
			$this->statconfig->getValue('datestart', 0), $this->statconfig->getValue('datelength', 15), $this->statconfig->getValue('offsetspan', 44)
		);
		$datehandler = array(
			'default' => new sspmod_statistics_DateHandler($this->offset),
			'month' => new  sspmod_statistics_DateHandlerMonth($this->offset),
		);
		
		
		$notBefore = 0; $lastRead = 0; $lastlinehash = '-';
		if (isset($this->metadata)) {
			$notBefore = $this->metadata['notBefore'];
			$lastlinehash = $this->metadata['lastlinehash'];
		}
		
		$lastlogline = 'sdfsdf'; 
		$lastlineflip = FALSE;
		$results = array();
		
		$i = 0;
		// Parse through log file, line by line
		while (!feof($file)) {
			
			$logline = fgets($file, 4096);
			
			// Continue if STAT is not found on line
			if (!preg_match('/STAT/', $logline)) continue;
			$i++; $lastlogline = $logline;
			
			// Parse log, and extract epoch time and rest of content.
			$epoch = $logparser->parseEpoch($logline);
			$content = $logparser->parseContent($logline);
			$action = trim($content[5]);

			if ($this->fromcmdline && ($i % 10000) == 0) {
				echo("Read line " . $i . "\n");
			}
			
			if ($debug) {
				echo("----------------------------------------\n");
				echo('Log line: ' . $logline . "\n");
				echo('Date parse [' . substr($logline, 0, $this->statconfig->getValue('datelength', 15)) . '] to [' . date(DATE_RFC822, $epoch) . ']' . "\n");
				echo htmlentities(print_r($content, true));
				if ($i >= 13) exit;
			}
			
			if ($epoch > $lastRead) $lastRead = $epoch;
			if ($epoch === $notBefore) {
				if(!$lastlineflip) {
					if (sha1($logline) === $lastlinehash) { 
						$lastlineflip = TRUE;
					}
					continue;
				}
			}
			if ($epoch < $notBefore) continue;
			
			// Iterate all the statrules from config.
			foreach ($this->statrules AS $rulename => $rule) {
				
				$type = 'aggregate';
				if (array_key_exists('type', $rule)) $type = $rule['type'];
				if ($type !== 'aggregate') continue;
				
				foreach($this->timeres AS $tres => $tresconfig ) {

					$dh = 'default';
					if (isset($tresconfig['customDateHandler'])) $dh = $tresconfig['customDateHandler'];
			
					$timeslot = $datehandler['default']->toSlot($epoch, $tresconfig['slot']);
					$fileslot = $datehandler[$dh]->toSlot($epoch, $tresconfig['fileslot']);
				
					if (isset($rule['action']) && ($action !== $rule['action'])) continue;

					$difcol = self::getDifCol($content, $rule['col']);
		
					if (!isset($results[$rulename][$tres][$fileslot][$timeslot]['_'])) $results[$rulename][$tres][$fileslot][$timeslot]['_'] = 0;
					if (!isset($results[$rulename][$tres][$fileslot][$timeslot][$difcol])) $results[$rulename][$tres][$fileslot][$timeslot][$difcol] = 0;
		
					$results[$rulename][$tres][$fileslot][$timeslot]['_']++;
					$results[$rulename][$tres][$fileslot][$timeslot][$difcol]++;
				}
			}
		}
		$this->metadata['notBefore'] = $lastRead;
		$this->metadata['lastline'] = $lastlogline;
		$this->metadata['lastlinehash'] = sha1($lastlogline);
		return $results;
	}
	
	private static function getDifCol($content, $colrule) {
		if (is_int($colrule)) {
			return trim($content[$colrule]);
		} elseif(is_array($colrule)) {
			$difcols = array();
			foreach($colrule AS $cr) {
				$difcols[] = trim($content[$cr]);
			}
			return join('|', $difcols);
		} else {
			return 'NA';
		}
	}
	
	private function cummulateData($previous, $newdata) {
		$dataset = array();
		foreach($previous AS $slot => $dataarray) {
			if (!array_key_exists($slot, $dataset)) $dataset[$slot] = array();
			foreach($dataarray AS $key => $data) {
				if (!array_key_exists($key, $dataset[$slot])) $dataset[$slot][$key] = 0;
				$dataset[$slot][$key] += $data;
			}
		}
		foreach($newdata AS $slot => $dataarray) {
			if (!array_key_exists($slot, $dataset)) $dataset[$slot] = array();
			foreach($dataarray AS $key => $data) {
				if (!array_key_exists($key, $dataset[$slot])) $dataset[$slot][$key] = 0;
				$dataset[$slot][$key] += $data;
			}
		}
		return $dataset;
	}
	
	
	public function store($results) {

		$datehandler = array(
			'default' => new sspmod_statistics_DateHandler($this->offset),
			'month' => new  sspmod_statistics_DateHandlerMonth($this->offset),
		);
	
		// Iterate the first level of results, which is per rule, as defined in the config.
		foreach ($results AS $rulename => $timeresdata) {

			// Iterate over time resolutions
			foreach($timeresdata AS $tres => $resres) {

				$dh = 'default';
				if (isset($this->timeres[$tres]['customDateHandler'])) $dh = $this->timeres[$tres]['customDateHandler'];
			
				$filenos = array_keys($resres);
				$lastfile = $filenos[count($filenos)-1];
			
				// Iterate the second level of results, which is the fileslot.
				foreach ($resres AS $fileno => $fileres) {
					
					
					// Slots that have data.
					$slotlist = array_keys($fileres);
				
					// The last slot.
					$maxslot = $slotlist[count($slotlist)-1];
		
					// Get start and end slot number within the file, based on the fileslot.
					$start = (int)$datehandler['default']->toSlot(
							$datehandler[$dh]->fromSlot($fileno, $this->timeres[$tres]['fileslot']), 
							$this->timeres[$tres]['slot']);
					$end = (int)$datehandler['default']->toSlot(
							$datehandler[$dh]->fromSlot($fileno+1, $this->timeres[$tres]['fileslot']), 
							$this->timeres[$tres]['slot']);

					// Fill in missing entries and sort file results
					$filledresult = array();
					for ($slot = $start; $slot < $end; $slot++) {
						if (array_key_exists($slot,  $fileres)) {
							$filledresult[$slot] = $fileres[$slot];
						} else {
							if ($lastfile == $fileno && $slot > $maxslot) {
								$filledresult[$slot] = array('_' => NULL);
							} else {
								$filledresult[$slot] = array('_' => 0);
							}
						}
					}
					
					$filename = $this->statdir . '/' . $rulename . '-' . $tres . '-' . $fileno . '.stat';
					if (file_exists($filename)) {
						$previousData = unserialize(file_get_contents($filename));
						$filledresult = $this->cummulateData($previousData, $filledresult);	
					}
				
					// store file
					file_put_contents($filename, serialize($filledresult), LOCK_EX);
				}
				
			}
			
		}
		$this->saveMetadata();
	
	}

}
