#xmlseclibs 

xmlseclibs is a library written in PHP for working with XML Encryption and Signatures.

The author of xmlseclibs is Rob Richards.

# Branches
Both the master and the 1.4 branches are actively maintained. The 1.3 branch is only updated for security related issues.
* master: Contains namespace support requiring 5.3+.
* 1.4: Contains auto-loader support while also maintaining backwards compatiblity with the 1.3 version using the xmlseclibs.php file. Supports PHP 5.2+

# Requirements

xmlseclibs requires PHP version 5.2 or greater.


## How to Install

Install with [`composer.phar`](http://getcomposer.org).

```sh
php composer.phar require "robrichards/xmlseclibs"
```


## Use cases

xmlseclibs is being used in many different software.

* [SimpleSAMLPHP](https://github.com/simplesamlphp/simplesamlphp)
* [LightSAML](https://github.com/lightsaml/lightsaml)

## Basic usage

The example below shows basic usage of xmlseclibs, with a SHA-256 signature.

```php
// Load the XML to be signed
$doc = new DOMDocument();
$doc->load('./path/to/file/tobesigned.xml');

// Create a new Security object 
$objDSig = new XMLSecurityDSig();
// Use the c14n exclusive canonicalization
$objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
// Sign using SHA-256
$objDSig->addReference(
    $doc, 
    XMLSecurityDSig::SHA256, 
    array('http://www.w3.org/2000/09/xmldsig#enveloped-signature')
);

// Create a new (private) Security key
$objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, array('type'=>'private'));
// Load the private key
$objKey->loadKey('./path/to/privatekey.pem', TRUE);
/* 
If key has a passphrase, set it using 
$objKey->passphrase = '<passphrase>';
*/

// Sign the XML file
$objDSig->sign($objKey);

// Add the associated public key to the signature
$objDSig->add509Cert(file_get_contents('./path/to/file/mycert.pem'));

// Append the signature to the XML
$objDSig->appendSignature($doc->documentElement);
// Save the signed XML
$doc->save('./path/to/signed.xml');
```

## How to Contribute

* [Open Issues](https://github.com/robrichards/xmlseclibs/issues)
* [Open Pull Requests](https://github.com/robrichards/xmlseclibs/pulls)

Mailing List: https://groups.google.com/forum/#!forum/xmlseclibs
