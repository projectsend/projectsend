<?php

/**
 * A memcache based datastore.
 *
 * @package SimpleSAMLphp
 */
class SimpleSAML_Store_Memcache extends SimpleSAML_Store
{
    /**
     * Initialize the memcache datastore.
     */

    /**
     * This variable contains the session name prefix.
     *
     * @var string
     */
    private $prefix;

    /**
     * This function implements the constructor for this class. It loads the Memcache configuration.
     */
    protected function __construct() {
        $config = SimpleSAML_Configuration::getInstance();
        $this->prefix = $config->getString('memcache_store.prefix', 'simpleSAMLphp');
    }


    /**
     * Retrieve a value from the datastore.
     *
     * @param string $type  The datatype.
     * @param string $key  The key.
     * @return mixed|NULL  The value.
     */
    public function get($type, $key)
    {
        assert('is_string($type)');
        assert('is_string($key)');

        return SimpleSAML_Memcache::get($this->prefix . '.' . $type . '.' . $key);
    }


    /**
     * Save a value to the datastore.
     *
     * @param string $type  The datatype.
     * @param string $key  The key.
     * @param mixed $value  The value.
     * @param int|NULL $expire  The expiration time (unix timestamp), or NULL if it never expires.
     */
    public function set($type, $key, $value, $expire = null)
    {
        assert('is_string($type)');
        assert('is_string($key)');
        assert('is_null($expire) || (is_int($expire) && $expire > 2592000)');

        if ($expire === null) {
            $expire = 0;
        }

        SimpleSAML_Memcache::set($this->prefix . '.' . $type . '.' . $key, $value, $expire);
    }


    /**
     * Delete a value from the datastore.
     *
     * @param string $type  The datatype.
     * @param string $key  The key.
     */
    public function delete($type, $key)
    {
        assert('is_string($type)');
        assert('is_string($key)');

        SimpleSAML_Memcache::delete($this->prefix . '.' . $type . '.' . $key);
    }
}
