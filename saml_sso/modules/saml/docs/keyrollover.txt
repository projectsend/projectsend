Key rollover with SimpleSAMLphp
===============================

This document gives a quick guide to doing key rollover with a SimpleSAMLphp service provider or identity provider.


Create the new key and certificate
----------------------------------

First you must create the new key that you are going to use.
To create a self signed certificate, you may use the following command:

    cd cert
    openssl req -newkey rsa:2048 -new -x509 -days 3652 -nodes -out new.crt -keyout new.pem


Add the new key to SimpleSAMLphp
--------------------------------

Where you add the new key depends on whether you are doing key rollover for a service provider or an identity provider.
If you are doing key rollover for a service provider, the new key must be added to `config/authsources.php`.
To do key rollover for an identity provider, you must add the new key to `metadata/saml20-idp-hosted.php` and/or `metadata/shib13-idp-hosted.php`.
If you are changing the keys for both an service provider and identity provider at the same time, you must update both locations.

The new certificate and key is added to the configuration with the prefix `new_`:

When the new key is added, SimpleSAMLphp will attempt to use both the new key and the old key for decryption of messages, but only the old key will be used for signing messages.
The metadata will be updated to list the new key for signing and encryption, and the old key will no longer listed as available for encryption.
This ensures that both those entities that use your old metadata and those that use your new metadata will be able to send and receive messages from you.


### Examples

In `config/authsources.php`:

    'default-sp' => array(
        'saml:SP',
        'privatekey' => 'old.pem',
        'certificate' => 'old.crt',
        'new_privatekey' => 'new.pem',
        'new_certificate' => 'new.crt',
    ),

In `metadata/saml20-idp-hosted.php`:

    $metadata['__DYNAMIC:1__'] = array(
        'host' => '__DEFAULT__',
        'auth' => 'example-userpass',
        'privatekey' => 'old.pem',
        'certificate' => 'old.crt',
        'new_privatekey' => 'new.pem',
        'new_certificate' => 'new.crt',
    );


Distribute your new metadata
----------------------------

Now, you need to make sure that all your peers are using your new metadata.
How you go about this depends on how your peers have added your metadata.
If your peers are configured to automatically fetch the metadata directly from you, all you need to do is to wait for all of them to fetch the new metadata.
If you are part of an federation, you would probably either send it to the federation operators or use a federation tool to ask for the metadata to be updated.

Once the peers are using your new metadata, they will accept messages from you signed with either your old or your new key.
If they send encrypted messages to you, they will use your new key for encryption.


Remove the old key
------------------

Once you are certain that all your peers are using the new metadata, you must remove the old key.
Replace the existing `privatekey` and `certificate` options in your configuration with the `new_privatekey` and `new_certificate` options.
This will cause your old key to be removed from your metadata.

### Examples

In `config/authsources.php`:

    'default-sp' => array(
        'saml:SP',
        'privatekey' => 'new.pem',
        'certificate' => 'new.crt',
    ),

In `metadata/saml20-idp-hosted.php`:

    $metadata['__DYNAMIC:1__'] = array(
        'host' => '__DEFAULT__',
        'auth' => 'example-userpass',
        'privatekey' => 'new.pem',
        'certificate' => 'new.crt',
    );



Distribute your final metadata
------------------------------

Now you need to update the metadata of all your peers again, so that your old signing certificate is removed.
This will cause those entities to no longer accept messages signed using your old key.
