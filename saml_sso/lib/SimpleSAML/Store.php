<?php


/**
 * Base class for data stores.
 *
 * @package SimpleSAMLphp
 */
abstract class SimpleSAML_Store
{

    /**
     * Our singleton instance.
     *
     * This is false if the data store isn't enabled, and null if we haven't attempted to initialize it.
     *
     * @var SimpleSAML_Store|false|null
     */
    private static $instance;


    /**
     * Retrieve our singleton instance.
     *
     * @return SimpleSAML_Store|false  The data store, or false if it isn't enabled.
     */
    public static function getInstance()
    {

        if (self::$instance !== null) {
            return self::$instance;
        }

        $config = SimpleSAML_Configuration::getInstance();
        $storeType = $config->getString('store.type', null);
        if ($storeType === null) {
            $storeType = $config->getString('session.handler', 'phpsession');
        }

        switch ($storeType) {
            case 'phpsession':
                // we cannot support advanced features with the PHP session store
                self::$instance = false;
                break;
            case 'memcache':
                self::$instance = new SimpleSAML_Store_Memcache();
                break;
            case 'sql':
                self::$instance = new SimpleSAML_Store_SQL();
                break;
            default:
                // datastore from module
                $className = SimpleSAML_Module::resolveClass($storeType, 'Store', 'SimpleSAML_Store');
                self::$instance = new $className();
        }

        return self::$instance;
    }


    /**
     * Retrieve a value from the data store.
     *
     * @param string $type The data type.
     * @param string $key The key.
     *
     * @return mixed|null The value.
     */
    abstract public function get($type, $key);


    /**
     * Save a value to the data store.
     *
     * @param string   $type The data type.
     * @param string   $key The key.
     * @param mixed    $value The value.
     * @param int|null $expire The expiration time (unix timestamp), or null if it never expires.
     */
    abstract public function set($type, $key, $value, $expire = null);


    /**
     * Delete a value from the data store.
     *
     * @param string $type The data type.
     * @param string $key The key.
     */
    abstract public function delete($type, $key);

}
