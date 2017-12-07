SAML V2.0 Metadata Extensions for Login and Discovery User Interface
=============================

<!--
	This file is written in Markdown syntax.
	For more information about how to use the Markdown syntax, read here:
	http://daringfireball.net/projects/markdown/syntax
-->

  * Author: Timothy Ace [tace@synacor.com](mailto:tace@synacor.com)

<!-- {{TOC}} -->

This is a reference for the SimpleSAMLphp implemenation of the [SAML
V2.0 Attribute Extensions](http://docs.oasis-open.org/security/saml/Post2.0/sstc-saml-attribute-ext.pdf)
defined by OASIS.

The `metadata/saml20-idp-hosted.php` entries are used to define the
metadata extension items. An example of this is:

    <?php
    $metadata['entity-id-1'] = array(
        /* ... */
		'EntityAttributes' => array(
			'urn:simplesamlphp:v1:simplesamlphp' => array('is', 'really', 'cool'),
			'{urn:simplesamlphp:v1}foo'          => array('bar'),
		),
        /* ... */
    );

The OASIS specification primarily defines how to include arbitrary
`Attribute` and `Assertion` elements within the metadata for an IdP.

*Note*: SimpleSAMLphp does not support `Assertion` elements within the
metadata at this time.

Defining Attributes
--------------

The `EntityAttributes` key is used to define the attributes in the
metadata. Each item in the `EntityAttributes` array defines a new
`<Attribute>` item in the metadata. The value for each key must be an
array. Each item in this array produces a separte `<AttributeValue>`
element within the `<Attribute>` element.

		'EntityAttributes' => array(
			'urn:simplesamlphp:v1:simplesamlphp' => array('is', 'really', 'cool'),
		),

This generates:

      <saml:Attribute xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" Name="urn:simplesamlphp:v1:simplesamlphp" NameFormat="urn:oasis:names:tc:SAML:2.0:attrname-format:uri">
        <saml:AttributeValue xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xs="http://www.w3.org/2001/XMLSchema" xsi:type="xs:string">is</saml:AttributeValue>
        <saml:AttributeValue xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xs="http://www.w3.org/2001/XMLSchema" xsi:type="xs:string">really</saml:AttributeValue>
        <saml:AttributeValue xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xs="http://www.w3.org/2001/XMLSchema" xsi:type="xs:string">cool</saml:AttributeValue>
      </saml:Attribute>

Each `<Attribute>` element requires a `NameFormat` attribute. This is
specified using curly braces at the beginning of the key name:

		'EntityAttributes' => array(
			'{urn:simplesamlphp:v1}foo' => array('bar'),
		),

This generates:

      <saml:Attribute xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" Name="foo" NameFormat="urn:simplesamlphp:v1">
        <saml:AttributeValue xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xs="http://www.w3.org/2001/XMLSchema" xsi:type="xs:string">bar</saml:AttributeValue>
      </saml:Attribute>

When the curly braces are omitted, the NameFormat is automatically set
to "urn:oasis:names:tc:SAML:2.0:attrname-format:uri".

Generated XML Metadata Examples
----------------

If given the following configuration...

    $metadata['https://www.example.com/saml/saml2/idp/metadata.php'] = array(
        'host' => 'www.example.com',
        'certificate' => 'example.com.crt',
        'privatekey' => 'example.com.pem',
        'auth' => 'example-userpass',

		'EntityAttributes' => array(
			'urn:simplesamlphp:v1:simplesamlphp' => array('is', 'really', 'cool'),
			'{urn:simplesamlphp:v1}foo'          => array('bar'),
		),
	);

... will generate the following XML metadata:

	<?xml version="1.0"?>
	<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" xmlns:mdattr="urn:oasis:names:tc:SAML:metadata:attribute" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:mdui="urn:oasis:names:tc:SAML:metadata:ui" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" entityID="https://www.example.com/saml/saml2/idp/metadata.php">
	  <md:Extensions>
		<mdattr:EntityAttributes xmlns:mdattr="urn:oasis:names:tc:SAML:metadata:attribute">
		  <saml:Attribute xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" Name="urn:simplesamlphp:v1:simplesamlphp" NameFormat="urn:oasis:names:tc:SAML:2.0:attrname-format:uri">
			<saml:AttributeValue xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xs="http://www.w3.org/2001/XMLSchema" xsi:type="xs:string">is</saml:AttributeValue>
			<saml:AttributeValue xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xs="http://www.w3.org/2001/XMLSchema" xsi:type="xs:string">really</saml:AttributeValue>
			<saml:AttributeValue xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xs="http://www.w3.org/2001/XMLSchema" xsi:type="xs:string">cool</saml:AttributeValue>
		  </saml:Attribute>
		  <saml:Attribute xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" Name="foo" NameFormat="urn:simplesamlphp:v1">
			<saml:AttributeValue xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xs="http://www.w3.org/2001/XMLSchema" xsi:type="xs:string">bar</saml:AttributeValue>
		  </saml:Attribute>
		</mdattr:EntityAttributes>
	  </md:Extensions>
	  <md:IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
		<md:KeyDescriptor use="signing">
		  <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
			<ds:X509Data>
            ...

