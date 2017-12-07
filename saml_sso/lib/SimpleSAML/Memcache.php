<?php


/**
 * This file implements functions to read and write to a group of memcache
 * servers.
 *
 * The goals of this storage class is to provide failover, redudancy and load
 * balancing. This is accomplished by storing the data object to several
 * groups of memcache servers. Each data object is replicated to every group
 * of memcache servers, but it is only stored to one server in each group.
 *
 * For this code to work correctly, all web servers accessing the data must
 * have the same clock (as measured by the time()-function). Different clock
 * values will lead to incorrect behaviour.
 *
 * @author Olav Morken, UNINETT AS.
 * @package SimpleSAMLphp
 */
class SimpleSAML_Memcache
{

    /**
     * Cache of the memcache servers we are using.
     *
     * @var Memcache[]|null
     */
    private static $serverGroups = null;


    /**
     * Find data stored with a given key.
     *
     * @param string $key The key of the data.
     *
     * @return mixed The data stored with the given key, or null if no data matching the key was found.
     */
    public static function get($key)
    {
        SimpleSAML_Logger::debug("loading key $key from memcache");

        $latestInfo = null;
        $latestTime = 0.0;
        $latestData = null;
        $mustUpdate = false;
        $allDown = true;

        // search all the servers for the given id
        foreach (self::getMemcacheServers() as $server) {
            $serializedInfo = $server->get($key);
            if ($serializedInfo === false) {
                // either the server is down, or we don't have the value stored on that server
                $mustUpdate = true;
                $up = $server->getstats();
                if ($up !== false) {
                    $allDown = false;
                }
                continue;
            }
            $allDown = false;

            // unserialize the object
            $info = unserialize($serializedInfo);

            /*
             * Make sure that this is an array with two keys:
             * - 'timestamp': The time the data was saved.
             * - 'data': The data.
             */
            if (!is_array($info)) {
                SimpleSAML_Logger::warning(
                    'Retrieved invalid data from a memcache server. Data was not an array.'
                );
                continue;
            }
            if (!array_key_exists('timestamp', $info)) {
                SimpleSAML_Logger::warning(
                    'Retrieved invalid data from a memcache server. Missing timestamp.'
                );
                continue;
            }
            if (!array_key_exists('data', $info)) {
                SimpleSAML_Logger::warning(
                    'Retrieved invalid data from a memcache server. Missing data.'
                );
                continue;
            }

            if ($latestInfo === null) {
                // first info found
                $latestInfo = $serializedInfo;
                $latestTime = $info['timestamp'];
                $latestData = $info['data'];
                continue;
            }

            if ($info['timestamp'] === $latestTime && $serializedInfo === $latestInfo) {
                // this data matches the data from the other server(s)
                continue;
            }

            // different data from different servers. We need to update at least one of them to maintain sync
            $mustUpdate = true;

            // update if data in $info is newer than $latestData
            if ($latestTime < $info['timestamp']) {
                $latestInfo = $serializedInfo;
                $latestTime = $info['timestamp'];
                $latestData = $info['data'];
            }
        }

        if ($latestData === null) {
            if ($allDown) {
                // all servers are down, panic!
                $e = new SimpleSAML_Error_Error('MEMCACHEDOWN', null, 503);
                throw new SimpleSAML_Error_Exception('All memcache servers are down', 503, $e);
            }
            // we didn't find any data matching the key
            SimpleSAML_Logger::debug("key $key not found in memcache");
            return null;
        }

        if ($mustUpdate) {
            // we found data matching the key, but some of the servers need updating
            SimpleSAML_Logger::debug("Memcache servers out of sync for $key, forcing sync");
            self::set($key, $latestData);
        }

        return $latestData;
    }


    /**
     * Save a key-value pair to the memcache servers.
     *
     * @param string       $key The key of the data.
     * @param mixed        $value The value of the data.
     * @param integer|null $expire The expiration timestamp of the data.
     */
    public static function set($key, $value, $expire = null)
    {
        SimpleSAML_Logger::debug("saving key $key to memcache");
        $savedInfo = array(
            'timestamp' => microtime(true),
            'data'      => $value
        );

        if ($expire === null) {
            $expire = self::getExpireTime();
        }

        $savedInfoSerialized = serialize($savedInfo);

        // store this object to all groups of memcache servers
        foreach (self::getMemcacheServers() as $server) {
            $server->set($key, $savedInfoSerialized, 0, $expire);
        }
    }


    /**
     * Delete a key-value pair from the memcache servers.
     *
     * @param string $key The key we should delete.
     */
    public static function delete($key)
    {
        assert('is_string($key)');
        SimpleSAML_Logger::debug("deleting key $key from memcache");

        // store this object to all groups of memcache servers
        foreach (self::getMemcacheServers() as $server) {
            $server->delete($key);
        }
    }


    /**
     * This function adds a server from the 'memcache_store.servers'
     * configuration option to a Memcache object.
     *
     * The server parameter is an array with the following keys:
     *  - hostname
     *    Hostname or ip address to the memcache server.
     *  - port (optional)
     *    port number the memcache server is running on. This
     *    defaults to memcache.default_port if no value is given.
     *    The default value of memcache.default_port is 11211.
     *  - weight (optional)
     *    The weight of this server in the load balancing
     *    cluster.
     *  - timeout (optional)
     *    The timeout for contacting this server, in seconds.
     *    The default value is 3 seconds.
     *
     * @param Memcache $memcache The Memcache object we should add this server to.
     * @param array    $server An associative array with the configuration options for the server to add.
     *
     * @throws Exception If any configuration option for the server is invalid.
     */
    private static function addMemcacheServer($memcache, $server)
    {
        // the hostname option is required
        if (!array_key_exists('hostname', $server)) {
            throw new Exception(
                "hostname setting missing from server in the 'memcache_store.servers' configuration option."
            );
        }

        $hostname = $server['hostname'];

        // the hostname must be a valid string
        if (!is_string($hostname)) {
            throw new Exception(
                "Invalid hostname for server in the 'memcache_store.servers' configuration option. The hostname is".
                ' supposed to be a string.'
            );
        }

        // check if we are told to use a socket
        $socket = false;
        if (strpos($hostname, 'unix:///') === 0) {
            $socket = true;
        }

        // check if the user has specified a port number
        if ($socket) {
            // force port to be 0 for sockets
            $port = 0;
        } elseif (array_key_exists('port', $server)) {
            // get the port number from the array, and validate it
            $port = (int) $server['port'];
            if (($port <= 0) || ($port > 65535)) {
                throw new Exception(
                    "Invalid port for server in the 'memcache_store.servers' configuration option. The port number".
                    ' is supposed to be an integer between 0 and 65535.'
                );
            }
        } else {
            // use the default port number from the ini-file
            $port = (int) ini_get('memcache.default_port');
            if ($port <= 0 || $port > 65535) {
                // invalid port number from the ini-file. fall back to the default
                $port = 11211;
            }
        }

        // check if the user has specified a weight for this server
        if (array_key_exists('weight', $server)) {
            // get the weight and validate it
            $weight = (int) $server['weight'];
            if ($weight <= 0) {
                throw new Exception(
                    "Invalid weight for server in the 'memcache_store.servers' configuration option. The weight is".
                    ' supposed to be a positive integer.'
                );
            }
        } else {
            // use a default weight of 1
            $weight = 1;
        }

        // check if the user has specified a timeout for this server
        if (array_key_exists('timeout', $server)) {
            // get the timeout and validate it
            $timeout = (int) $server['timeout'];
            if ($timeout <= 0) {
                throw new Exception(
                    "Invalid timeout for server in the 'memcache_store.servers' configuration option. The timeout is".
                    ' supposed to be a positive integer.'
                );
            }
        } else {
            // use a default timeout of 3 seconds
            $timeout = 3;
        }

        // add this server to the Memcache object
        $memcache->addServer($hostname, $port, true, $weight, $timeout, $timeout, true);
    }


    /**
     * This function takes in a list of servers belonging to a group and
     * creates a Memcache object from the servers in the group.
     *
     * @param array $group Array of servers which should be created as a group.
     *
     * @return Memcache A Memcache object of the servers in the group
     *
     * @throws Exception If the servers configuration is invalid.
     */
    private static function loadMemcacheServerGroup(array $group)
    {
        if (!class_exists('Memcache')) {
            throw new Exception('Missing Memcache class. Is the memcache extension installed?');
        }

        // create the Memcache object
        $memcache = new Memcache();

        // iterate over all the servers in the group and add them to the Memcache object
        foreach ($group as $index => $server) {
            // make sure that we don't have an index. An index would be a sign of invalid configuration
            if (!is_int($index)) {
                throw new Exception(
                    "Invalid index on element in the 'memcache_store.servers' configuration option. Perhaps you".
                    ' have forgotten to add an array(...) around one of the server groups? The invalid index was: '.
                    $index
                );
            }

            // make sure that the server object is an array. Each server is an array with name-value pairs
            if (!is_array($server)) {
                throw new Exception(
                    'Invalid value for the server with index '.$index.
                    '. Remeber that the \'memcache_store.servers\' configuration option'.
                    ' contains an array of arrays of arrays.'
                );
            }

            self::addMemcacheServer($memcache, $server);
        }

        return $memcache;
    }


    /**
     * This function gets a list of all configured memcache servers. This list is initialized based
     * on the content of 'memcache_store.servers' in the configuration.
     *
     * @return Memcache[] Array with Memcache objects.
     *
     * @throws Exception If the servers configuration is invalid.
     */
    private static function getMemcacheServers()
    {
        // check if we have loaded the servers already
        if (self::$serverGroups != null) {
            return self::$serverGroups;
        }

        // initialize the servers-array
        self::$serverGroups = array();

        // load the configuration
        $config = SimpleSAML_Configuration::getInstance();


        $groups = $config->getArray('memcache_store.servers');

        // iterate over all the groups in the 'memcache_store.servers' configuration option
        foreach ($groups as $index => $group) {
            // make sure that the group doesn't have an index. An index would be a sign of invalid configuration
            if (!is_int($index)) {
                throw new Exception(
                    "Invalid index on element in the 'memcache_store.servers'".
                    ' configuration option. Perhaps you have forgotten to add an array(...)'.
                    ' around one of the server groups? The invalid index was: '.$index
                );
            }

            /*
             * Make sure that the group is an array. Each group is an array of servers. Each server is
             * an array of name => value pairs for that server.
             */
            if (!is_array($group)) {
                throw new Exception(
                    "Invalid value for the server with index ".$index.
                    ". Remeber that the 'memcache_store.servers' configuration option".
                    ' contains an array of arrays of arrays.'
                );
            }

            // parse and add this group to the server group list
            self::$serverGroups[] = self::loadMemcacheServerGroup($group);
        }

        return self::$serverGroups;
    }


    /**
     * This is a helper-function which returns the expire value of data
     * we should store to the memcache servers.
     *
     * The value is set depending on the configuration. If no value is
     * set in the configuration, then we will use a default value of 0.
     * 0 means that the item will never expire.
     *
     * @return integer The value which should be passed in the set(...) calls to the memcache objects.
     *
     * @throws Exception If the option 'memcache_store.expires' has a negative value.
     */
    private static function getExpireTime()
    {
        // get the configuration instance
        $config = SimpleSAML_Configuration::getInstance();
        assert($config instanceof SimpleSAML_Configuration);

        // get the expire-value from the configuration
        $expire = $config->getInteger('memcache_store.expires', 0);

        // it must be a positive integer
        if ($expire < 0) {
            throw new Exception(
                "The value of 'memcache_store.expires' in the configuration can't be a negative integer."
            );
        }

        /* If the configuration option is 0, then we should return 0. This allows the user to specify that the data
         * shouldn't expire.
         */
        if ($expire == 0) {
            return 0;
        }

        /* The expire option is given as the number of seconds into the future an item should expire. We convert this
         * to an actual timestamp.
         */
        $expireTime = time() + $expire;

        return $expireTime;
    }


    /**
     * This function retrieves statistics about all memcache server groups.
     *
     * @return array Array with the names of each stat and an array with the value for each server group.
     *
     * @throws Exception If memcache server status couldn't be retrieved.
     */
    public static function getStats()
    {
        $ret = array();

        foreach (self::getMemcacheServers() as $sg) {
            $stats = $sg->getExtendedStats();
            foreach ($stats as $server => $data) {
                if ($data === false) {
                    throw new Exception('Failed to get memcache server status.');
                }
            }

            $stats = SimpleSAML\Utils\Arrays::transpose($stats);

            $ret = array_merge_recursive($ret, $stats);
        }

        return $ret;
    }


    /**
     * Retrieve statistics directly in the form returned by getExtendedStats, for
     * all server groups.
     *
     * @return array An array with the extended stats output for each server group.
     */
    public static function getRawStats()
    {
        $ret = array();

        foreach (self::getMemcacheServers() as $sg) {
            $stats = $sg->getExtendedStats();
            $ret[] = $stats;
        }

        return $ret;
    }

}
