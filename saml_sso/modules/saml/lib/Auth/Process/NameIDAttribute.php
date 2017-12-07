<?php


/**
 * Authentication processing filter to create an attribute from a NameID.
 *
 * @package SimpleSAMLphp
 */
class sspmod_saml_Auth_Process_NameIDAttribute extends SimpleSAML_Auth_ProcessingFilter
{

    /**
     * The attribute we should save the NameID in.
     *
     * @var string
     */
    private $attribute;


    /**
     * The format of the NameID in the attribute.
     *
     * @var array
     */
    private $format;


    /**
     * Initialize this filter, parse configuration.
     *
     * @param array $config Configuration information about this filter.
     * @param mixed $reserved For future use.
     */
    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);
        assert('is_array($config)');

        if (isset($config['attribute'])) {
            $this->attribute = (string) $config['attribute'];
        } else {
            $this->attribute = 'nameid';
        }

        if (isset($config['format'])) {
            $format = (string) $config['format'];
        } else {
            $format = '%I!%S!%V';
        }

        $this->format = self::parseFormat($format);
    }


    /**
     * Parse a NameID format string into an array.
     *
     * @param string $format The format string.
     * @return array The format string broken into its individual components.
     *
     * @throws SimpleSAML_Error_Exception if the replacement is invalid.
     */
    private static function parseFormat($format)
    {
        assert('is_string($format)');

        $ret = array();
        $pos = 0;
        while (($next = strpos($format, '%', $pos)) !== false) {
            $ret[] = substr($format, $pos, $next - $pos);

            $replacement = $format[$next + 1];
            switch ($replacement) {
                case 'F':
                    $ret[] = 'Format';
                    break;
                case 'I':
                    $ret[] = 'NameQualifier';
                    break;
                case 'S':
                    $ret[] = 'SPNameQualifier';
                    break;
                case 'V':
                    $ret[] = 'Value';
                    break;
                case '%':
                    $ret[] = '%';
                    break;
                default:
                    throw new SimpleSAML_Error_Exception('NameIDAttribute: Invalid replacement: "%'.$replacement.'"');
            }

            $pos = $next + 2;
        }
        $ret[] = substr($format, $pos);

        return $ret;
    }


    /**
     * Convert NameID to attribute.
     *
     * @param array &$state The request state.
     */
    public function process(&$state)
    {
        assert('is_array($state)');
        assert('isset($state["Source"]["entityid"])');
        assert('isset($state["Destination"]["entityid"])');

        if (!isset($state['saml:sp:NameID'])) {
            return;
        }

        $rep = $state['saml:sp:NameID'];
        assert('isset($rep["Value"])');

        $rep['%'] = '%';
        if (!isset($rep['Format'])) {
            $rep['Format'] = SAML2_Const::NAMEID_UNSPECIFIED;
        }
        if (!isset($rep['NameQualifier'])) {
            $rep['NameQualifier'] = $state['Source']['entityid'];
        }
        if (!isset($rep['SPNameQualifier'])) {
            $rep['SPNameQualifier'] = $state['Destination']['entityid'];
        }

        $value = '';
        $isString = true;
        foreach ($this->format as $element) {
            if ($isString) {
                $value .= $element;
            } else {
                $value .= $rep[$element];
            }
            $isString = !$isString;
        }

        $state['Attributes'][$this->attribute] = array($value);
    }
}
