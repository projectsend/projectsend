<?php
/*
 * @author Andreas Åkre Solberg <andreas.solberg@uninett.no>
 * @package SimpleSAMLphp
 */
class sspmod_statistics_LogParser {

	private $datestart;
	private $datelength;
	private $offset;

	/**
	 * Constructor
	 *
	 * @param $datestart   At which char is the date starting
	 * @param $datelength  How many characters is the date (on the b
	 * @param $offset      At which char is the rest of the entries starting
	 */
	public function __construct($datestart, $datelength, $offset) {
		$this->datestart = $datestart;
		$this->datelength = $datelength;
		$this->offset = $offset;
	}

	public function parseEpoch($line) {
		$epoch = strtotime(substr($line, 0, $this->datelength));
		if ($epoch > time() + 60*60*24*31) {
			/*
			 * More than a month in the future - probably caused by
			 * the log files missing the year.
			 * We will therefore subtrackt one year.
			 */
			$hour = gmdate('H', $epoch);
			$minute = gmdate('i', $epoch);
			$second = gmdate('s', $epoch);
			$month = gmdate('n', $epoch);
			$day = gmdate('j', $epoch);
			$year = gmdate('Y', $epoch) - 1;
			$epoch = gmmktime($hour, $minute, $second, $month, $day, $year);
		}
		return $epoch;
	}

	public function parseContent($line) {
		$contentstr = substr($line, $this->offset);
		$content = explode(' ', $contentstr);
		return $content;
	}
	
	
	# Aug 27 12:54:25 ssp 5 STAT [5416262207] saml20-sp-SSO urn:mace:feide.no:services:no.uninett.wiki-feide sam.feide.no NA
	# 
	#Oct 30 11:07:14 www1 simplesamlphp-foodle[12677]: 5 STAT [200b4679af] saml20-sp-SLO spinit urn:mace:feide.no:services:no.feide.foodle sam.feide.no
	
	function parse15($str) {
		$di = date_parse($str);
		$datestamp = mktime($di['hour'], $di['minute'], $di['second'], $di['month'], $di['day']);	
		return $datestamp;
	}
	
	function parse23($str) {
		$timestamp = strtotime($str);
		return $timestamp;
	}


}
