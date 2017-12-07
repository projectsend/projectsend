Consent module
==============

<!-- {{TOC}} -->


The consent module is implemented as an Authentication Processing Filter. That 
means it can be configured in the global config.php file or the SP remote or 
IdP hosted metadata.

It is recommended to run the consent module at the IdP, and configure the 
filter to run after all attribute mangling filters is completed, to show the 
user the exact same attributes that are sent to the SP.

  * [Read more about processing filters in SimpleSAMLphp](simplesamlphp-authproc)


How to setup the consent module
-------------------------------

In order to generate the privacy preserving hashes in the consent module, you 
need to name one attribute that always is available and that is unique to all 
users. An example of such an attribute is eduPersonPrincipalName.

In your `saml20-idp-hosted.php` add the name of the user ID attribute:

	'userid.attribute' => 'uid', 

If the attribute defined above is not available for a user, an error message 
will be shown, and the user will not be allowed through the filter. So make 
sure that you select an attribute that is available to all users.

Next you need to enable the consent module, touch an `enable` file, in the
consent module:
	
    touch modules/consent/enable

The simplest way to setup the consent module is to not use any storage at 
all. This means that the user will always be asked to give consent each time 
the user logs in.

Example:

    90 => array(
        'class:Consent',
    ),

Using storage
-------------

The consent module is shipped with two storage options, Cookie and Database.


### Using cookies as storage ###

In order to use a storage backend, you need to set the `store` option. To use
cookies as storage you need to set the `store` option to `consent:Cookie`.

Example: 

	90 => array(
		'class' 	=> 'consent:Consent', 
		'store' 	=> 'consent:Cookie', 
	),


### Using a database as storage ###

In order to use a database backend storage, you first need to setup the
database. 

Here is the initialization SQL script:

	CREATE TABLE consent (
		consent_date TIMESTAMP NOT NULL,
		usage_date TIMESTAMP NOT NULL,
		hashed_user_id VARCHAR(80) NOT NULL,
		service_id VARCHAR(255) NOT NULL,
		attribute VARCHAR(80) NOT NULL,
		UNIQUE (hashed_user_id, service_id)
	);

The `consent:Database` backend storage has the following options

`class`
:   Must be set to `consent:Database`.

`dsn`
:   Data Source Name must comply to the syntax for the PHP PDO layer.

`username`
:   Username for the database user to be used for the connection.

`password`
:   Password for the database user used for the connection.

`table`
:   Name of the table used for storing the consents. This option is optional
and defaults to `consent`.

`timeout`
:   The number of seconds to wait for a connection to the database server. This option is optional. If unset, it uses the default from the database-driver.

Example config using PostgreSQL database:

	90 => array(
		'class'	=> 'consent:Consent', 
		'store'	=> array(
			'consent:Database', 
			'dsn' => 'pgsql:host=sql.example.org;dbname=consent',
			'username' => 'simplesaml',
			'password' => 'sdfsdf',
		),
	),

Example config using MySQL database:

	90 => array(
		'class'	=> 'consent:Consent', 
		'store'	=> array(
			'consent:Database', 
            'dsn' => 'mysql:host=db.example.org;dbname=simplesaml',
			'username' => 'simplesaml',
			'password' => 'sdfsdf',
		),
	),


Options
-------

The following options can be used when configuring the Consent module

`includeValues`
:   Boolean value that indicate whether the values of the attributes should be 
    used in calculating the unique hashes that identifies the consent. If 
    includeValues is set and the value of an attribute changes, then the 
    consent becomes invalid. This option is optional and defaults to FALSE. 

`checked`
:   Boolean value that indicate whether the "Remember" consent checkbox is
    checked by default. This option is optional and defaults to FALSE. 

`focus`
:   Indicates whether the "Yes" or "No" button is in focus by default. This
    option is optional and can take the value 'yes' or 'no' as strings. If 
    omitted neither will receive focus.

`store`
:   Configuration of the Consent storage backend. The store option is given in 
    the format <module>:<class> and refers to the class 
    sspmod_<module>_Consent_Store_<class>. The consent module comes with two 
    build in storages backends 'consnet:Cookie' and 'consent:Database'. See 
    separate section on setting up consent using different storage methods. 
    This option is optional. If option is not set, then the user is asked to 
    consent, but the consent is not saved.

`hiddenAttributes`
:   Whether the value of the attributes should be hidden. Set to an array of
    the attributes that should have it value hidden. Default behaviour is that 
    all attribute values are shown

`showNoConsentAboutService`
:   Whether we will show a link to more information about the service from the
    no consent page. Defaults to `TRUE`.

External options
----------------

The following options can/ be set in other places in SimpleSAMLphp

`privacypolicy`
:   This is an absolute URL for where an user can find a privacy policy for SP. 
    If set, this will be shown on the consent page. %SPENTITYID% in the URL 
    will be replaced with the entityID of the service provider.

    This option can be set in 
    [SP-remote metadata](./simplesamlphp-reference-sp-remote) and in 
    [IdP-hosted metadata](./simplesamlphp-reference-idp-hosted). The entry in 
    the SP-remote metadata overrides the option in the IdP-hosted metadata.

`consent.disable`
:   Disable consent for a set of services. See section `Disabling consent`

`userid.attribute`
:   Unique identifier that is released for all users. See section `Configure
    the user ID`.


Disabling consent
-----------------

Consent can be disabled either in the IdP metadata or in the SP metadata.
To disable consent for one or more SPs for a given IdP, add the
`consent.disable`-option to the IdP metadata. To disable consent for one or
more IdPs for a given SP, add the `consent.disable`-option to the SP metadata.

### Examples ###

Disable consent for a given IdP:

    $metadata['https://idp.example.org/'] = array(
        [...],
        'consent.disable' => TRUE,
    );

Disable consent for some SPs connected to a given IdP:

    $metadata['https://idp.example.org/'] = array(
        [...],
        'consent.disable' => array(
            'https://sp1.example.org/',
            'https://sp2.example.org/',
        ),
    );


Disable consent for a given SP:

    $metadata['https://sp.example.org'] = array(
        [...]
        'consent.disable' => TRUE,
    ),

Disable consent for some IdPs for a given SP:

    $metadata['https://sp.example.org'] = array(
        [...]
        'consent.disable' => array(
            'https://idp1.example.org/',
            'https://idp2.example.org/',
        ),
    ),


Attribute presentation
----------------------
 
It is possible to change the way the attributes are represented in the consent
page. This is done by implementing an attribute array reordering function.

To create this function, you have to create a file named

    hook_attributepresentation.php 

and place it under

    <module_name>/hooks 

directory. To be found and called, the function must be named 

    <module_name>_hook_attributepresentation(&$para).

The parameter $para is an reference to the attribute array. By manipulating 
this array you can change the way the attribute are presented to the user on 
the consent and status page. 

If you want the attributes to be listed in more than one level, you can make 
the function add a child_ prefix to the root node attribute name in a recursive 
attribute tree.


### Examples ###

These values will be listed as an bullet list
    
    Array (
        [objectClass] => Array (
            [0] => top
            [1] => person
        )
    )

This array has two child arrays. These will be listed in two separate sub
tables.

    Array (
        [child_eduPersonOrgUnitDN] => Array (
            [0] => Array (
                [ou] => Array (
                    [0] => ET
                )
                [cn] => Array (
                    [0] => Eksterne tjenester
                )
            )
            [1] => Array (
                [ou] => Array (
                    [0] => TA
                )
                [cn] => Array (
                    [0] => Tjenesteavdeling
                )
            )
        )
    )
