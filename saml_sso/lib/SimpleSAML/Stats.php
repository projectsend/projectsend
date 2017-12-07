<?php


/**
 * Statistics handler class.
 *
 * This class is responsible for taking a statistics event and logging it.
 *
 * @package SimpleSAMLphp
 */
class SimpleSAML_Stats
{

    /**
     * Whether this class is initialized.
     *
     * @var boolean
     */
    private static $initialized = false;


    /**
     * The statistics output callbacks.
     *
     * @var array
     */
    private static $outputs = null;


    /**
     * Create an output from a configuration object.
     *
     * @param SimpleSAML_Configuration $config The configuration object.
     *
     * @return mixed A new instance of the configured class.
     */
    private static function createOutput(SimpleSAML_Configuration $config)
    {
        $cls = $config->getString('class');
        $cls = SimpleSAML_Module::resolveClass($cls, 'Stats_Output', 'SimpleSAML_Stats_Output');

        $output = new $cls($config);
        return $output;
    }


    /**
     * Initialize the outputs.
     */
    private static function initOutputs()
    {

        $config = SimpleSAML_Configuration::getInstance();
        $outputCfgs = $config->getConfigList('statistics.out', array());

        self::$outputs = array();
        foreach ($outputCfgs as $cfg) {
            self::$outputs[] = self::createOutput($cfg);
        }
    }


    /**
     * Notify about an event.
     *
     * @param string $event The event.
     * @param array  $data Event data. Optional.
     *
     * @return void|boolean False if output is not enabled, void otherwise.
     */
    public static function log($event, array $data = array())
    {
        assert('is_string($event)');
        assert('!isset($data["op"])');
        assert('!isset($data["time"])');
        assert('!isset($data["_id"])');

        if (!self::$initialized) {
            self::initOutputs();
            self::$initialized = true;
        }

        if (empty(self::$outputs)) {
            // not enabled
            return;
        }

        $data['op'] = $event;
        $data['time'] = microtime(true);

        // the ID generation is designed to cluster IDs related in time close together
        $int_t = (int) $data['time'];
        $hd = openssl_random_pseudo_bytes(16);
        $data['_id'] = sprintf('%016x%s', $int_t, bin2hex($hd));

        foreach (self::$outputs as $out) {
            $out->emit($data);
        }
    }

}
