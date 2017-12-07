<?php
/*
 * @author Andreas Åkre Solberg <andreas.solberg@uninett.no>
 * @package SimpleSAMLphp
 */
class sspmod_metarefresh_ARP {


	private $metadata;
	private $attributes;
	private $prefix;
	private $suffix;

	/**
	 * Constructor
	 *
	 * @param 
	 */
	public function __construct($metadata, $attributemap, $prefix, $suffix) {
		$this->metadata = $metadata;
		
		$this->prefix = $prefix;
		$this->suffix = $suffix;
		
		if (isset($attributemap)) $this->loadAttributeMap($attributemap);
	}
	
	private function loadAttributeMap($attributemap) {
		$config = SimpleSAML_Configuration::getInstance();
		include($config->getPathValue('attributemap', 'attributemap/') . $attributemap . '.php');
		$this->attributes = $attributemap;
	}

	private function surround($name) {
		$ret = '';
		if (!empty($this->prefix)) $ret .= $this->prefix;
		$ret .= $name;
		if (!empty($this->suffix)) $ret .= $this->suffix;
		return $ret;
	}

	private function getAttributeID($name) {
		if (empty($this->attributes)) {
			return $this->surround($name);
		} 
		if (array_key_exists($name, $this->attributes)) {
			return $this->surround($this->attributes[$name]);
		}
		return $this->surround($name);
	}

	public function getXML() {
		
		$xml = '<?xml version="1.0" encoding="UTF-8"?>
<AttributeFilterPolicyGroup id="urn:mace:funet.fi:haka:kalmar" xmlns="urn:mace:shibboleth:2.0:afp"
    xmlns:basic="urn:mace:shibboleth:2.0:afp:mf:basic" xmlns:saml="urn:mace:shibboleth:2.0:afp:mf:saml"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="urn:mace:shibboleth:2.0:afp classpath:/schema/shibboleth-2.0-afp.xsd
                        urn:mace:shibboleth:2.0:afp:mf:basic classpath:/schema/shibboleth-2.0-afp-mf-basic.xsd
                        urn:mace:shibboleth:2.0:afp:mf:saml classpath:/schema/shibboleth-2.0-afp-mf-saml.xsd">
';
		
		
		foreach($this->metadata AS $metadata) {
			$xml .= $this->getEntryXML($metadata['metadata']);
		}
		
		$xml .= '</AttributeFilterPolicyGroup>';
		return $xml;
	}

	private function getEntryXML($entry) {
		$entityid = $entry['entityid'];
		return '	<AttributeFilterPolicy id="' . $entityid . '">
		<PolicyRequirementRule xsi:type="basic:AttributeRequesterString" value="' . $entityid . '" />
' . $this->getEntryXMLcontent($entry) . '
	</AttributeFilterPolicy>
';
	}
	
	private function getEntryXMLcontent($entry) {
		$ids = array();
		if (!array_key_exists('attributes', $entry)) 
			return '';
		
		$ret = '';
		foreach($entry['attributes'] AS $a) {
			
			$ret .= '			<AttributeRule attributeID="' . $this->getAttributeID($a) . '">
				<PermitValueRule xsi:type="basic:ANY" />
			</AttributeRule>
';
			
		}
		return $ret;
	}

}

