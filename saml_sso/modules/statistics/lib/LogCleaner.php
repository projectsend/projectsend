<?php
/*
 * @author Andreas Åkre Solberg <andreas.solberg@uninett.no>
 * @package SimpleSAMLphp
 */
class sspmod_statistics_LogCleaner {

	private $statconfig;
	private $statdir;
	private $inputfile;
	private $statrules;
	private $offset;

	/**
	 * Constructor
	 */
	public function __construct($inputfile = NULL) {
	
		$this->statconfig = SimpleSAML_Configuration::getConfig('module_statistics.php');
		
		$this->statdir = $this->statconfig->getValue('statdir');
		$this->inputfile = $this->statconfig->getValue('inputfile');
		$this->statrules = $this->statconfig->getValue('statrules');
		$this->offset = $this->statconfig->getValue('offset', 0);
		
		if (isset($inputfile)) $this->inputfile = $inputfile;
	}
	
	public function dumpConfig() {
		
		echo 'Statistics directory   : ' . $this->statdir . "\n";
		echo 'Input file             : ' . $this->inputfile . "\n";
		echo 'Offset                 : ' . $this->offset . "\n";
		
	}
	


	public function clean($debug = FALSE) {
		
		if (!is_dir($this->statdir)) 
			throw new Exception('Statistics module: output dir do not exists [' . $this->statdir . ']');
		
		if (!file_exists($this->inputfile)) 
			throw new Exception('Statistics module: input file do not exists [' . $this->inputfile . ']');
		
		
		$file = fopen($this->inputfile, 'r');

		$logparser = new sspmod_statistics_LogParser(
			$this->statconfig->getValue('datestart', 0), $this->statconfig->getValue('datelength', 15), $this->statconfig->getValue('offsetspan', 44)
		);
		$datehandler = new sspmod_statistics_DateHandler($this->offset);
		
		$results = array();
		
		$sessioncounter = array();
		
		$i = 0;
		// Parse through log file, line by line
		while (!feof($file)) {
			
			$logline = fgets($file, 4096);
			
			// Continue if STAT is not found on line
			if (!preg_match('/STAT/', $logline)) continue;
			$i++;
			
			// Parse log, and extract epoch time and rest of content.
			$epoch = $logparser->parseEpoch($logline);
			$content = $logparser->parseContent($logline);
			$action = trim($content[5]);

			if (($i % 10000) == 0) {
				echo("Read line " . $i . "\n");
			}
			
			$trackid = $content[4];
			
			if(!isset($sessioncounter[$trackid])) $sessioncounter[$trackid] = 0;
			$sessioncounter[$trackid]++;

			if ($debug) {
			
				echo("----------------------------------------\n");
				echo('Log line: ' . $logline . "\n");
				echo('Date parse [' . substr($logline, 0, $this->statconfig->getValue('datelength', 15)) . '] to [' . date(DATE_RFC822, $epoch) . ']' . "\n");
				echo htmlentities(print_r($content, true));
				if ($i >= 13) exit;
			}

		}

		$histogram = array();
		foreach($sessioncounter AS $trackid => $sc) {
			if(!isset($histogram[$sc])) $histogram[$sc] = 0;
			$histogram[$sc]++;
		}
		ksort($histogram);
		
		$todelete = array();
		foreach($sessioncounter AS $trackid => $sc) {
			if($sc > 200) $todelete[] = $trackid;
		}

		return $todelete;
	}
	
	
	public function store($todelete, $outputfile) {
		
		echo "Preparing to delete [" .count($todelete) . "] trackids\n";
		
		if (!is_dir($this->statdir)) 
			throw new Exception('Statistics module: output dir do not exists [' . $this->statdir . ']');
		
		if (!file_exists($this->inputfile)) 
			throw new Exception('Statistics module: input file do not exists [' . $this->inputfile . ']');
		
		$file = fopen($this->inputfile, 'r');

		// Open the output file in a way that guarantees that we will not overwrite a random file.
		if (file_exists($outputfile)) {
			// Delete existing output file.
			unlink($outputfile);
		}
		$outfile = fopen($outputfile, 'x'); /* Create the output file. */

		
		$logparser = new sspmod_statistics_LogParser(
			$this->statconfig->getValue('datestart', 0), $this->statconfig->getValue('datelength', 15), $this->statconfig->getValue('offsetspan', 44)
		);

		$i = 0;
		// Parse through log file, line by line
		while (!feof($file)) {
			
			$logline = fgets($file, 4096);
			
			// Continue if STAT is not found on line.
			if (!preg_match('/STAT/', $logline)) continue;
			$i++;
			
			$content = $logparser->parseContent($logline);
			
			$action = trim($content[5]);

			if (($i % 10000) == 0) {
				echo("Read line " . $i . "\n");
			}
			
			$trackid = $content[4];
			
			if (in_array($trackid, $todelete)) {
				continue;
			}
			
			fputs($outfile, $logline);

		}
		fclose($file);
		fclose($outfile);
		
	}


}
