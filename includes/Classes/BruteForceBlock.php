<?php
namespace ProjectSend\Classes;
use \PDO;

/**
 * Modified from https://github.com/ejfrancis/brute-force-block
*/
/**
 * Brute Force Block class
 *
 * 	Implementation by Evan Francis for use in AlpineAuth library, 2014. 
 *  Inspired by work of Corey Ballou, http://stackoverflow.com/questions/2090910/how-can-i-throttle-user-login-attempts-in-php.
 * 	MIT License http://opensource.org/licenses/MIT
 *
 */
//brute force block
class BruteForceBlock {
    private $dbh;
    private $auto_clear;
    private $default_throttle_settings;
    private $time_frame_minutes;
    private $ip_whitelist;
    private $ip_blacklist;
    
    public function __construct($dbh)
    {
        $this->dbh = $dbh;
        $this->auto_clear = true;

        // array of throttle settings. # failed_attempts => response (delay in seconds)
        $this->default_throttle_settings = [
            1 => 1, 
            2 => 2,
            3 => 15,
            4 => 60,
            5 => 300,
            6 => 600,
            7 => 'error_403',
        ];
        
        //time frame to use when retrieving the number of recent failed logins from database
        $this->time_frame_minutes = 10;

        $this->ip_whitelist = $this->makeIpList(get_option('ip_whitelist'));
        $this->ip_blacklist = $this->makeIpList(get_option('ip_blacklist'));
    }

    public function getThrottleSettings()
    {
        return $this->default_throttle_settings;
    }

    private function makeIpList($list = null)
    {
        if (empty($list)) {
            return null;
        }

        $list = explode(PHP_EOL, $list);

        return array_map(function($item) {
            return htmlentities(trim($item));
        }, $list);
    }
	
    //add a failed login attempt to database. returns true, or error 
	public function addFailedLoginAttempt($username, $ip_address){
        //get current timestamp
		$timestamp = date('Y-m-d H:i:s');

        try {
            $statement = $this->dbh->prepare("INSERT INTO " . TABLE_LOGINS_FAILED . " (ip_address, username, attempted_at)"
                    ."VALUES (:ip_address, :username, :attempted_at)");
            $statement->bindParam(':ip_address', $ip_address);
            $statement->bindParam(':username', $username);
            $statement->bindParam(':attempted_at', $timestamp);
            $statement->execute();
        } catch(\PDOException $e) {
            return $e;
        }
	}

    //get the current login status. either safe, delay, catpcha, or error
	public function getLoginStatus($ip_address, $options = null){
		//setup response array
		$response_array = array(
			'status' => 'safe',
			'message' => null
		);

        // Check if IP is whitelisted
        if (!empty($this->ip_whitelist) && in_array($ip_address, $this->ip_whitelist)) {
            return $response_array;
        }

        // Check if IP is blacklisted
        if (!empty($this->ip_blacklist) && in_array($ip_address, $this->ip_blacklist)) {
            $response_array['status'] = 'error';
			$response_array['message'] = __('IP address blacklisted', 'cftp_admin');
            return $response_array;
        }
        
		//attempt to retrieve latest failed login attempts
		$stmt = null;
		$latest_failed_logins = null;
		$row = null;
		$latest_failed_attempt_datetime = null;
		try{
			$stmt = $this->dbh->query('SELECT MAX(attempted_at) AS attempted_at FROM '.TABLE_LOGINS_FAILED.'');
			$latest_failed_logins = $stmt->rowCount();
			$row = $stmt-> fetch();
			//get latest attempt's timestamp
			$latest_failed_attempt_datetime = (int) date('U', strtotime($row['attempted_at']));
		} catch(\PDOException $ex){
			//return error
			$response_array['status'] = 'error';
			$response_array['message'] = $ex;
		}
        
		
		//get local var of throttle settings. check if options parameter set
		if($options == null){
			$throttle_settings = $this->default_throttle_settings;
		}else{
			//use options passed in
			$throttle_settings = $options;
		}
		//grab first throttle limit from key
		reset($throttle_settings);
		$first_throttle_limit = key($throttle_settings);

		//attempt to retrieve latest failed login attempts
		try{
			//get all failed attempst within time frame
			$get_number = $this->dbh->query('SELECT * FROM '.TABLE_LOGINS_FAILED.' WHERE attempted_at > DATE_SUB(NOW(), INTERVAL '.$this->time_frame_minutes.' MINUTE)');
			$number_recent_failed = $get_number->rowCount();
			//reverse order of settings, for iteration
			krsort($throttle_settings);
			
			//if number of failed attempts is >= the minimum threshold in throttle_settings, react
			if($number_recent_failed >= $first_throttle_limit ){				
				//it's been decided the # of failed logins is troublesome. time to react accordingly, by checking throttle_settings
				foreach ($throttle_settings as $attempts => $delay) {
					if ($number_recent_failed > $attempts) {
						// we need to throttle based on delay
						if (is_numeric($delay)) {
							//find the time of the next allowed login
							$next_login_minimum_time = $latest_failed_attempt_datetime + $delay;
							
							//if the next allowed login time is in the future, calculate the remaining delay
							if(time() < $next_login_minimum_time){
								$remaining_delay = $next_login_minimum_time - time();
								// add status to response array
								$response_array['status'] = 'delay';
								$response_array['message'] = $remaining_delay;
							}else{
								// delay has been passed, safe to login
								$response_array['status'] = 'safe';
							}
							//$remaining_delay = $delay - (time() - $latest_failed_attempt_datetime); //correct
							//echo 'You must wait ' . $remaining_delay . ' seconds before your next login attempt';
						} else {
							// add status to response array
							$response_array['status'] = $delay;
						}
						break;
					}
				}  
				
			}

            //clear database if config set
			if ($this->auto_clear == true){
				//attempt to delete all records that are no longer recent/relevant
				try{
					//get current timestamp
					$now = date('Y-m-d H:i:s');
					$stmt = $this->dbh->query('DELETE from '.TABLE_LOGINS_FAILED.' WHERE attempted_at < DATE_SUB(NOW(), INTERVAL '.($this->time_frame_minutes * 2).' MINUTE)');
					$stmt->execute();
					
				} catch(\PDOException $ex){
					$response_array['status'] = 'error';
					$response_array['message'] = $ex;
				}
			}
			
		} catch(\PDOException $ex){
			//return error
			$response_array['status'] = 'error';
			$response_array['message'] = $ex;
		}
		
		//return the response array containing status and message 
		return $response_array;
	}
	
	//clear the database
	public function clearDatabase(){
		//attempt to delete all records
		try{
			$stmt = $this->dbh->query('DELETE from '.TABLE_LOGINS_FAILED);
			return true;
		} catch(\PDOException $ex){
			//return errors
			return $ex;
		}
	}
}
