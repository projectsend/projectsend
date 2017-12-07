<?php


/**
 * This file defines a class for metadata handling.
 *
 * @author Andreas Åkre Solberg, UNINETT AS. <andreas.solberg@uninett.no>
 * @package SimpleSAMLphp
 */
class SimpleSAML_Metadata_MetaDataStorageHandler
{


    /**
     * This static variable contains a reference to the current
     * instance of the metadata handler. This variable will be null if
     * we haven't instantiated a metadata handler yet.
     *
     * @var SimpleSAML_Metadata_MetaDataStorageHandler
     */
    private static $metadataHandler = null;


    /**
     * This is a list of all the metadata sources we have in our metadata
     * chain. When we need metadata, we will look through this chain from start to end.
     *
     * @var SimpleSAML_Metadata_MetaDataStorageSource[]
     */
    private $sources;


    /**
     * This function retrieves the current instance of the metadata handler.
     * The metadata handler will be instantiated if this is the first call
     * to this function.
     *
     * @return SimpleSAML_Metadata_MetaDataStorageHandler The current metadata handler instance.
     */
    public static function getMetadataHandler()
    {
        if (self::$metadataHandler === null) {
            self::$metadataHandler = new SimpleSAML_Metadata_MetaDataStorageHandler();
        }

        return self::$metadataHandler;
    }


    /**
     * This constructor initializes this metadata storage handler. It will load and
     * parse the configuration, and initialize the metadata source list.
     */
    protected function __construct()
    {
        $config = SimpleSAML_Configuration::getInstance();

        $sourcesConfig = $config->getArray('metadata.sources', null);

        // for backwards compatibility, and to provide a default configuration
        if ($sourcesConfig === null) {
            $type = $config->getString('metadata.handler', 'flatfile');
            $sourcesConfig = array(array('type' => $type));
        }

        try {
            $this->sources = SimpleSAML_Metadata_MetaDataStorageSource::parseSources($sourcesConfig);
        } catch (Exception $e) {
            throw new Exception(
                "Invalid configuration of the 'metadata.sources' configuration option: ".$e->getMessage()
            );
        }
    }


    /**
     * This function is used to generate some metadata elements automatically.
     *
     * @param string $property The metadata property which should be auto-generated.
     * @param string $set The set we the property comes from.
     *
     * @return string The auto-generated metadata property.
     * @throws Exception If the metadata cannot be generated automatically.
     */
    public function getGenerated($property, $set)
    {
        // first we check if the user has overridden this property in the metadata
        try {
            $metadataSet = $this->getMetaDataCurrent($set);
            if (array_key_exists($property, $metadataSet)) {
                return $metadataSet[$property];
            }
        } catch (Exception $e) {
            // probably metadata wasn't found. In any case we continue by generating the metadata
        }

        // get the configuration
        $config = SimpleSAML_Configuration::getInstance();
        assert($config instanceof SimpleSAML_Configuration);

        $baseurl = \SimpleSAML\Utils\HTTP::getSelfURLHost().'/'.
            $config->getBaseURL();

        if ($set == 'saml20-sp-hosted') {
            if ($property === 'SingleLogoutServiceBinding') {
                return SAML2_Const::BINDING_HTTP_REDIRECT;
            }
        } elseif ($set == 'saml20-idp-hosted') {
            switch ($property) {
                case 'SingleSignOnService':
                    return $baseurl.'saml2/idp/SSOService.php';

                case 'SingleSignOnServiceBinding':
                    return SAML2_Const::BINDING_HTTP_REDIRECT;

                case 'SingleLogoutService':
                    return $baseurl.'saml2/idp/SingleLogoutService.php';

                case 'SingleLogoutServiceBinding':
                    return SAML2_Const::BINDING_HTTP_REDIRECT;
            }
        } elseif ($set == 'shib13-idp-hosted') {
            if ($property === 'SingleSignOnService') {
                return $baseurl.'shib13/idp/SSOService.php';
            }
        }

        throw new Exception('Could not generate metadata property '.$property.' for set '.$set.'.');
    }


    /**
     * This function lists all known metadata in the given set. It is returned as an associative array
     * where the key is the entity id.
     *
     * @param string $set The set we want to list metadata from.
     *
     * @return array An associative array with the metadata from from the given set.
     */
    public function getList($set = 'saml20-idp-remote')
    {
        assert('is_string($set)');

        $result = array();

        foreach ($this->sources as $source) {
            $srcList = $source->getMetadataSet($set);

            foreach ($srcList as $key => $le) {
                if (array_key_exists('expire', $le)) {
                    if ($le['expire'] < time()) {
                        unset($srcList[$key]);
                        SimpleSAML_Logger::warning(
                            "Dropping metadata entity ".var_export($key, true).", expired ".
                            SimpleSAML\Utils\Time::generateTimestamp($le['expire'])."."
                        );
                    }
                }
            }

            /* $result is the last argument to array_merge because we want the content already
             * in $result to have precedence.
             */
            $result = array_merge($srcList, $result);
        }

        return $result;
    }


    /**
     * This function retrieves metadata for the current entity based on the hostname/path the request
     * was directed to. It will throw an exception if it is unable to locate the metadata.
     *
     * @param string $set The set we want metadata from.
     *
     * @return array An associative array with the metadata.
     */
    public function getMetaDataCurrent($set)
    {
        return $this->getMetaData(null, $set);
    }


    /**
     * This function locates the current entity id based on the hostname/path combination the user accessed.
     * It will throw an exception if it is unable to locate the entity id.
     *
     * @param string $set The set we look for the entity id in.
     * @param string $type Do you want to return the metaindex or the entityID. [entityid|metaindex]
     *
     * @return string The entity id which is associated with the current hostname/path combination.
     * @throws Exception If no default metadata can be found in the set for the current host.
     */
    public function getMetaDataCurrentEntityID($set, $type = 'entityid')
    {
        assert('is_string($set)');

        // first we look for the hostname/path combination
        $currenthostwithpath = \SimpleSAML\Utils\HTTP::getSelfHostWithPath(); // sp.example.org/university

        foreach ($this->sources as $source) {
            $index = $source->getEntityIdFromHostPath($currenthostwithpath, $set, $type);
            if ($index !== null) {
                return $index;
            }
        }

        // then we look for the hostname
        $currenthost = \SimpleSAML\Utils\HTTP::getSelfHost(); // sp.example.org
        if (strpos($currenthost, ":") !== false) {
            $currenthostdecomposed = explode(":", $currenthost);
            $currenthost = $currenthostdecomposed[0];
        }

        foreach ($this->sources as $source) {
            $index = $source->getEntityIdFromHostPath($currenthost, $set, $type);
            if ($index !== null) {
                return $index;
            }
        }

        // then we look for the DEFAULT entry
        foreach ($this->sources as $source) {
            $entityId = $source->getEntityIdFromHostPath('__DEFAULT__', $set, $type);
            if ($entityId !== null) {
                return $entityId;
            }
        }

        // we were unable to find the hostname/path in any metadata source
        throw new Exception(
            'Could not find any default metadata entities in set ['.$set.'] for host ['.$currenthost.' : '.
            $currenthostwithpath.']'
        );
    }


    /**
     * This method will call getPreferredEntityIdFromCIDRhint() on all of the
     * sources.
     *
     * @param string $set Which set of metadata we are looking it up in.
     * @param string $ip IP address
     *
     * @return string The entity id of a entity which have a CIDR hint where the provided
     *        IP address match.
     */
    public function getPreferredEntityIdFromCIDRhint($set, $ip)
    {
        foreach ($this->sources as $source) {
            $entityId = $source->getPreferredEntityIdFromCIDRhint($set, $ip);
            if ($entityId !== null) {
                return $entityId;
            }
        }

        return null;
    }


    /**
     * This function looks up the metadata for the given entity id in the given set. It will throw an
     * exception if it is unable to locate the metadata.
     *
     * @param string $index The entity id we are looking up. This parameter may be NULL, in which case we look up
     * the current entity id based on the current hostname/path.
     * @param string $set The set of metadata we are looking up the entity id in.
     *
     * @return array The metadata array describing the specified entity.
     * @throws Exception If metadata for the specified entity is expired.
     * @throws SimpleSAML_Error_MetadataNotFound If no metadata for the entity specified can be found.
     */
    public function getMetaData($index, $set)
    {
        assert('is_string($set)');

        if ($index === null) {
            $index = $this->getMetaDataCurrentEntityID($set, 'metaindex');
        }

        assert('is_string($index)');

        foreach ($this->sources as $source) {
            $metadata = $source->getMetaData($index, $set);

            if ($metadata !== null) {

                if (array_key_exists('expire', $metadata)) {
                    if ($metadata['expire'] < time()) {
                        throw new Exception(
                            'Metadata for the entity ['.$index.'] expired '.
                            (time() - $metadata['expire']).' seconds ago.'
                        );
                    }
                }

                $metadata['metadata-index'] = $index;
                $metadata['metadata-set'] = $set;
                assert('array_key_exists("entityid", $metadata)');
                return $metadata;
            }
        }

        throw new SimpleSAML_Error_MetadataNotFound($index);
    }


    /**
     * Retrieve the metadata as a configuration object.
     *
     * This function will throw an exception if it is unable to locate the metadata.
     *
     * @param string $entityId The entity ID we are looking up.
     * @param string $set The metadata set we are searching.
     *
     * @return SimpleSAML_Configuration The configuration object representing the metadata.
     * @throws SimpleSAML_Error_MetadataNotFound If no metadata for the entity specified can be found.
     */
    public function getMetaDataConfig($entityId, $set)
    {
        assert('is_string($entityId)');
        assert('is_string($set)');

        $metadata = $this->getMetaData($entityId, $set);
        return SimpleSAML_Configuration::loadFromArray($metadata, $set.'/'.var_export($entityId, true));
    }


    /**
     * Search for an entity's metadata, given the SHA1 digest of its entity ID.
     *
     * @param string $sha1 The SHA1 digest of the entity ID.
     * @param string $set The metadata set we are searching.
     *
     * @return null|SimpleSAML_Configuration The metadata corresponding to the entity, or null if the entity cannot be
     * found.
     */
    public function getMetaDataConfigForSha1($sha1, $set)
    {
        assert('is_string($sha1)');
        assert('is_string($set)');

        $result = array();

        foreach ($this->sources as $source) {
            $srcList = $source->getMetadataSet($set);

            /* $result is the last argument to array_merge because we want the content already
             * in $result to have precedence.
             */
            $result = array_merge($srcList, $result);
        }
        foreach ($result as $remote_provider) {

            if (sha1($remote_provider['entityid']) == $sha1) {
                $remote_provider['metadata-set'] = $set;

                return SimpleSAML_Configuration::loadFromArray(
                    $remote_provider,
                    $set.'/'.var_export($remote_provider['entityid'], true)
                );
            }
        }

        return null;
    }
}
