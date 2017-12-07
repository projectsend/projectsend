<?php

/**
 * Filter which show "about to expire" warning or deny access if netid is expired.
 *
 * Based on preprodwarning module by rnd.feide.no
 *
 * <code>
 * // show about2xpire warning or deny access if netid is expired
 * 10 => array(
 *     'class' => 'expirycheck:ExpiryDate',
 *     'netid_attr' => 'eduPersonPrincipalName',
 *     'expirydate_attr' => 'schacExpiryDate',
 *     'warndaysbefore' => '60',
 *     'date_format' => 'd.m.Y', # php date syntax
 * ),
 * </code>
 *
 * @author Alex Mihičinac, ARNES. <alexm@arnes.si>
 * @package SimpleSAMLphp
 */

class sspmod_expirycheck_Auth_Process_ExpiryDate extends SimpleSAML_Auth_ProcessingFilter {

	private $warndaysbefore = 0;
	private $netid_attr = NULL;
	private $expirydate_attr = NULL;
	private $date_format = 'd.m.Y';


	/**
	 * Initialize this filter.
	 *
	 * @param array $config  Configuration information about this filter.
	 * @param mixed $reserved  For future use.
	 */
	public function __construct($config, $reserved) {
		parent::__construct($config, $reserved);

		assert('is_array($config)');

		if (array_key_exists('warndaysbefore', $config)) {
			$this->warndaysbefore = $config['warndaysbefore'];
			if (!is_string($this->warndaysbefore)) {
				throw new Exception('Invalid value for number of days given to expirycheck::ExpiryDate filter.');
			}
		}

		if (array_key_exists('netid_attr', $config)) {
			$this->netid_attr = $config['netid_attr'];
			if (!is_string($this->netid_attr)) {
				throw new Exception('Invalid attribute name given as eduPersonPrincipalName to expirycheck::ExpiryDate filter.');
			}
		}

		if (array_key_exists('expirydate_attr', $config)) {
			$this->expirydate_attr = $config['expirydate_attr'];
			if (!is_string($this->expirydate_attr)) {
				throw new Exception('Invalid attribute name given as schacExpiryDate to expirycheck::ExpiryDate filter.');
			}
		}

		if (array_key_exists('date_format', $config)) {
			$this->date_format = $config['date_format'];
			if (!is_string($this->date_format)) {
				throw new Exception('Invalid date format given to expirycheck::ExpiryDate filter.');
			}
		}
	}

	/**
	 * Show expirational warning if remaining days is equal or under defined $warndaysbefore
	 * @param integer $expireOnDate
	 * @param integer $warndaysbefore
	 * @return bool
	 *
	 */
	public function shWarning(&$state, $expireOnDate, $warndaysbefore) {
		$now = time();
		$end = $expireOnDate;

		if ($expireOnDate >= $now) {
			$days = (int)(($end - $now) / (24*60*60));
			if ($days <= $warndaysbefore) {
				$state['daysleft'] = $days;
				return true;
			}
		}
		return false;
	}

	/**
	 *  Check if given date is older than today
	 *  @param integer $expireOnDate
	 *  @return bool
	 *
	 */
	public function checkDate($expireOnDate) {
		$now = time();
		$end = $expireOnDate;

		if ($now <= $end) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Apply filter
	 *
	 * @param array &$state  The current state.
	 */
	public function process(&$state) {
		/*
		 * UTC format: 20090527080352Z
		 */
		$netId = $state['Attributes'][$this->netid_attr][0];
		$expireOnDate = strtotime($state['Attributes'][$this->expirydate_attr][0]);

		if (self::shWarning($state, $expireOnDate, $this->warndaysbefore)) {
			assert('is_array($state)');
			if (isset($state['isPassive']) && $state['isPassive'] === TRUE) {
				// We have a passive request. Skip the warning.
				return;
			}

			SimpleSAML_Logger::warning('expirycheck: NetID ' . $netId .
			                           ' is about to expire!');

			// Save state and redirect
			$state['expireOnDate'] = date($this->date_format, $expireOnDate);
			$state['netId'] = $netId;
			$id = SimpleSAML_Auth_State::saveState($state, 'expirywarning:about2expire');
			$url = SimpleSAML_Module::getModuleURL('expirycheck/about2expire.php');
			\SimpleSAML\Utils\HTTP::redirectTrustedURL($url, array('StateId' => $id));
		}

		if (!self::checkDate($expireOnDate)) {
			SimpleSAML_Logger::error('expirycheck: NetID ' . $netId .
				' has expired [' . date($this->date_format, $expireOnDate) . ']. Access denied!');
			$globalConfig = SimpleSAML_Configuration::getInstance();

			/* Save state and redirect. */
			$state['expireOnDate'] = date($this->date_format, $expireOnDate);
			$state['netId'] = $netId;
			$id = SimpleSAML_Auth_State::saveState($state, 'expirywarning:expired');
			$url = SimpleSAML_Module::getModuleURL('expirycheck/expired.php');
			\SimpleSAML\Utils\HTTP::redirectTrustedURL($url, array('StateId' => $id));

		}
	}


}
