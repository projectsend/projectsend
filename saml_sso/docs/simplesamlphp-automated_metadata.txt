Automated Metadata Management
=============================

<!-- 
	This file is written in Markdown syntax. 
	For more information about how to use the Markdown syntax, read here:
	http://daringfireball.net/projects/markdown/syntax
-->


<!-- {{TOC}} -->

Introduction
------------

If you want to connect an Identity Provider, or a Service Provider to a **federation**, you need to setup metadata for the entries that you trust. In many federations, in particular federations based upon the Shibboleth software, it is normal to setup automated distribution of metadata using the SAML 2.0 Metadata XML Format.

Some central administration or authority, provides a URL with a SAML 2.0 document including metadata for all entities in the federation.

The present document explains how to setup automated downloading and parsing of a metadata document on a specific URL.



Preparations
------------

You need to enable the following modules:

 1. cron
 2. metarefresh

The cron module allows you to do tasks regularly, by setting up a cron job that calls a hook in SimpleSAMLphp.

The metarefresh module will download and parse the metadata document and store it in metadata files cached locally.

First, you will need to copy the `config-templates` files of the two modules above into the global `config/` directory.

	[root@simplesamlphp] cd /var/simplesamlphp
	[root@simplesamlphp simplesamlphp] touch modules/cron/enable
	[root@simplesamlphp simplesamlphp] cp modules/cron/config-templates/*.php config/
	[root@simplesamlphp simplesamlphp] touch modules/metarefresh/enable
	[root@simplesamlphp simplesamlphp] cp modules/metarefresh/config-templates/*.php config/



Testing it manually
-------------------

It is often useful to verify that the metadata sources we want to use can be parsed and verified by metarefresh, before actually
configuring it. We can do so in the command line, by invoking metarefresh with the URL of the metadata set we want to check. For
instance, if we want to configure the metadata of the SWITCH AAI Test Federation:

	cd modules/metarefresh/bin
	./metarefresh.php -s http://metadata.aai.switch.ch/metadata.aaitest.xml

The `-s` option sends the output to the console (for testing purposes). If the output makes sense, continue. If you get a lot of error messages, try to read them and fix the problems that might be causing them. If you are having problems and you can't figure out the cause, you can always send an e-mail to the SimpleSAMLphp mailing list and ask for advice.



Configuring the metarefresh module
----------------------------------


Now we are going to proceed to configure the metarefresh module. First, edit the appropriate configuration file:


	[root@simplesamlphp simplesamlphp]# vi config/config-metarefresh.php

Here's an example of a possible configuration for both the Kalmar Federation and UK Access Management Federation:

	$config = array(
		'sets' => array(
			'kalmar' => array(
				'cron'		=> array('hourly'),
				'sources'	=> array(
					array(
						'src' => 'https://kalmar.feide.no/simplesaml/module.php/aggregator/?id=kalmarcentral&mimetype=text/plain&exclude=norway',
						'certificates' => array(
							'current.crt',
							'rollover.crt',
						),
						'template' => array(
							'tags'	=> array('kalmar'),
							'authproc' => array(
								51 => array('class' => 'core:AttributeMap', 'oid2name'),
							),
						),
					),
				),
				'expireAfter' 		=> 60*60*24*4, // Maximum 4 days cache time.
				'outputDir' 	=> 'metadata/metarefresh-kalmar/',
				'outputFormat' => 'flatfile',
			),
			'uk' => array(
				'cron'		=> array('hourly'),
				'sources'	=> array(
					array(
						'src' => 'http://metadata.ukfederation.org.uk/ukfederation-metadata.xml',
						'validateFingerprint' => 'D0:E8:40:25:F0:B1:2A:CC:74:22:ED:C3:87:04:BC:29:BB:7B:9A:40',
					),
				),
				'expireAfter' 		=> 60*60*24*4, // Maximum 4 days cache time.
				'outputDir' 	=> 'metadata/metarefresh-ukaccess/',
				'outputFormat' => 'serialize',
			),
		)
	);


The configuration consists of one or more metadata sets. Each metadata set has its own configuration, representing a metadata set of sources.
Some federations will provide you with detailed instructions on how to configure metarefresh to fetch their metadata automatically, like,
for instance, [the InCommon federation in the US](https://spaces.internet2.edu/x/eYHFAg). Whenever a federation provides you with specific
instructions to configure metarefresh, be sure to use them from the authoritative source.

The metarefresh module supports the following configuration options:

`cron`
:   Which cron tags will refresh this metadata set.

`sources`
:   An array of metadata sources that will be included in this
    metadata set. The contents of this option will be described later in more detail.

`expireAfter`
:   The maximum number of seconds a metadata entry will be valid.

`outputDir`
:   The directory where the generated metadata will be stored. The path
    is relative to the SimpleSAMLphp base directory.

`outputFormat`
:   The format of the generated metadata files. This must match the
    metadata source added in `config.php`.

`types`
:	The sets of entities to load. An array containing strings identifying the different types of entities that will be
	loaded. Valid types are:

	* saml20-idp-remote
	* saml20-sp-remote
	* shib13-idp-remote
	* shib13-sp-remote
	* attributeauthority-remote

	All entity types will be loaded by default.

Each metadata source has the following options:

`src`
:   The source URL where the metadata will be fetched from.

`certificates`
:   An array of certificate files, the filename is relative to the `cert/`-directory,
    that will be used to verify the signature of the metadata. The public key will
    be extracted from the certificate and everything else will be ignored. So it is
    possible to use a self signed certificate that has expired. Add more than one
    certificate to be able to handle key rollover. This takes precedence over
    validateFingerprint.

`validateFingerprint`
:   The fingerprint of the certificate used to sign the metadata. You
    don't need this option if you don't want to validate the signature
    on the metadata.

`template`
:   This is an array which will be combined with the metadata fetched to
    generate the final metadata array.

`types`
:	Same as the option with the same name at the metadata set level. This option has precedence when both are specified,
	allowing a more fine grained configuration for every metadata source.


After you have configured the metadata sources, you need to give the
web-server write access to the output directories. Following the previous example:

	chown www-data /var/simplesamlphp/metadata/metarefresh-kalmar/
	chown www-data /var/simplesamlphp/metadata/metarefresh-ukaccess/

Now you can configure SimpleSAMLphp to use the metadata fetched by metarefresh. Edit the main
config.php file, and modify the `metadata.sources` directive accordingly: 

	'metadata.sources' => array(
		array('type' => 'flatfile'),
		array('type' => 'flatfile', 'directory' => 'metadata/metarefresh-kalmar'),
		array('type' => 'serialize', 'directory' => 'metadata/metarefresh-ukaccess'),
	),

Remember that the `type` parameter here must match the `outputFormat` in the configuration of the module.



Configuring the cron module
---------------------------


Once we have configured metarefresh, we can edit the configuration file for the cron module:

	[root@simplesamlphp simplesamlphp]# vi config/module_cron.php

The configuration should look similar to this:

	$config = array (
	       'key' => 'RANDOM_KEY',
	       'allowed_tags' => array('daily', 'hourly', 'frequent'),
	       'debug_message' => TRUE,
	       'sendemail' => TRUE,
	
	);

Bear in mind that the key is used as a security feature, to restrict access to your cron. Therefore, you need to make sure that the string here is a random key available to no one but you. Additionally, make sure that you include here the appropriate tags that you previously told metarefresh
to use in the `cron` directive.

Next, use your web browser to go to `https://YOUR_SERVER/simplesaml/module.php/cron/croninfo.php`. Make sure to properly set your server's address, as well as use HTTP or HTTPS accordingly, and also to specify the correct path to the root of your SimpleSAMLphp installation.

Now, copy the cron configuration suggested:

	# Run cron [daily]
	02 0 * * * curl --silent "https://YOUR_SERVER/simplesaml/module.php/cron/cron.php?key=RANDOM_KEY&tag=daily" > /dev/null 2>&1
	# Run cron [hourly]
	01 * * * * curl --silent "https://YOUR_SERVER/simplesaml/module.php/cron/cron.php?key=RANDOM_KEY&tag=hourly" > /dev/null 2>&1

Finally, add it to your crontab by going back to the terminal, and editing with:

	[root@simplesamlphp config]# crontab -e

This will open up your favourite editor. If an editor different than the one you use normally appears, exit, and configure the `EDITOR` variable
to tell the command line which editor it should use:

	[root@simplesamlphp config]# export EDITOR=emacs

If you want to force the metadata to be refreshed manually, you can do so by going back to the cron page in the web interface. Then, just follow
the appropriate links to execute the cron jobs you want. The page will take a while loading, and eventually show a blank page. It is so because
the commands are intended to be run from cron, and therefore they produce no output. If this operation seems to run fine, navigate to the **SimpleSAMLphp Front page** › **Federation**. Here you will see a list of all the Identity Providers trusted. They will be listed with information about the maximum duration of their cached version, such as *(expires in 96.0 hours)*.



Metadata duration
-----------------

SAML metadata may supply a `cacheDuration` attribute which indicates the maximum time to keep metadata cached. Because this module is run from cron, it cannot decide how often it is run and enforce this duration on its own. Make sure to run metarefresh from cron at least as often as the shortest `cacheDuration` in your metadata sources.

