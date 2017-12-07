Migrating to the `saml` module
==============================

<!-- {{TOC}} -->

This document describes how you can migrate your code to use the `saml` module for authentication against SAML 2.0 and SAML 1.1 IdPs.
It assumes that you have previously set up a SP by using redirects to `saml2/sp/initSSO.php`.

The steps we are going to follow are:

1. Create a new authentication source.
2. Add the metadata for this authentication source to the IdP.
3. Test the new authentication source.
4. Convert the application to use the new API.
5. Test the application.
6. Remove the old metadata from the IdP.
7. Disable the old SAML 2 SP.


Create a new authentication source
----------------------------------

In this step we are going to create an authentication source which uses the `saml` module for authentication.
To do this, we open `config/authsources.php`. Create the file if it does not exist.
If you create the file, it should looke like this:

    <?php
    $config = array(
        /* Here we can add entries for authentication sources we want to use. */
    );


We are going to add an entry to this file.
The entry should look something like this:

    'default-sp' => array(
        'saml:SP',

        /*
         * The entity ID of this SP.
         * Can be NULL/unset, in which case an entity ID is generated based on the metadata URL.
         */
        'entityID' => NULL,

        /*
         * The entity ID of the IdP this should SP should contact.
         * Can be NULL/unset, in which case the user will be shown a list of available IdPs.
         */
        'idp' => NULL,

        /* Here you can add other options to the SP. */
    ),

`default-sp` is the name of the authentication source.
It is used to refer to this authentication source when we use it.
`saml:SP` tells SimpleSAMLphp that authentication with this authentication source is handled by the `saml` module.

The `idp` option should be set to the same value that is set in `default-saml20-idp` in `config.php`.

To ease migration, you probably want the entity ID on the new SP to be different than on the old SP.
This makes it possible to have both the old and the new SP active on the IdP at the same time.

You can also add other options this authentication source.
See the [`saml:SP`](./saml:sp) documentation for more information.


Add the metadata for this authentication source to the IdP
----------------------------------------------------------

After adding the authentication source on the SP, you need to register the metadata on the IdP.
To retrieve the metadata, open the frontpage of your SimpleSAMLphp installation, and go to the federation tab.
You should have a list of metadata entries, and one will be marked with the name of the new authentication source.
In our case, that was `default-sp`.

Click the `Show metadata` link, and you will arrive on a web page with the metadata for that service provider.
How you proceed from here depends on which IdP you are connecting to.

If you use a SimpleSAMLphp IdP, you can use the metadata in the flat file format at the bottom of the page.
That metadata should be added to `saml20-sp-remote.php` on the IdP.
For other IdPs you probably want to use the XML metadata.


Test the new authentication source
----------------------------------

You should now be able to log in using the new authentication source.
Go to the frontpage of your SimpleSAMLphp installation and open the authentication tab.
There you will find a link to test authentication sources.
Click that link, and select the name of your authentication source (`default-sp` in our case).

You should be able to log in using that authentication source, and receive the attributes from the IdP.


Convert the application to use the new API
------------------------------------------

This section will go through some common changes that you need to do when you are using SimpleSAMLphp from a different application.

### `_include.php`

You should also no longer include `.../simplesamlphp/www/_include.php`.
Instead, you should include `.../simplesamlphp/lib/_autoload.php`.

This means that you replace lines like:

    require_once('.../simplesamlphp/www/_include.php');

with:

    require_once('.../simplesamlphp/lib/_autoload.php');

`_autoload.php` will register an autoloader function for the SimpleSAMLphp classes.
This makes it possible to access the classes from your application.
`_include.php` does the same, but also has some side-effects that you may not want in your application.

If you load any SimpleSAMLphp class files directly, you should remove those lines.
That means that you should remove lines like the following:

    require_once('SimpleSAML/Utilities.php');
    require_once('SimpleSAML/Session.php');
    require_once('SimpleSAML/XHTML/Template.php');


### Authentication API

There is a new authentication API in SimpleSAMLphp which can be used to authenticate against authentication sources.
This API is designed to handle the common operations.


#### Overview

This is a quick overview of the API:

    /* Get a reference to our authentication source. */
    $as = new SimpleSAML_Auth_Simple('default-sp');

    /* Require the user to be authentcated. */
    $as->requireAuth();
    /* When that function returns, we have an authenticated user. */

    /*
     * Retrieve attributes of the user.
     *
     * Note: If the user isn't authenticated when getAttributes() is
     * called, an empty array will be returned.
     */
    $attributes = $as->getAttributes();

    /* Log the user out. */
    $as->logout();


#### `$config` and `$session`

Generally, if you have:

    $config = SimpleSAML_Configuration::getInstance();
    $session = SimpleSAML_Session::getSessionFromRequest();

you should replace it with this single line:

    $as = new SimpleSAML_Auth_Simple('default-sp');


#### Requiring authentication

Blocks of code like the following:

    /* Check if valid local session exists.. */
    if (!isset($session) || !$session->isValid('saml2') ) {
      SimpleSAML_Utilities::redirect(
        '/' . $config->getBaseURL() .
        'saml2/sp/initSSO.php',
        array('RelayState' => SimpleSAML_Utilities::selfURL())
        );
    }

should be replaced with a single call to `requireAuth()`:

    $as->requireAuth();


#### Fetching attributes

Where you previously called:

    $session->getAttributes();

you should now call:

    $as->getAttributes();


#### Logging out

Redirecting to the initSLO-script:

    SimpleSAML_Utilities::redirect(
        '/' . $config->getBaseURL() .
        'saml2/sp/initSLO.php',
        array('RelayState' => SimpleSAML_Utilities::selfURL())
        );

should be replaced with a call to `logout()`:

    $as->logout();

If you want to return to a specific URL after logging out, you should include that URL as a parameter to the logout function:

    $as->logout('https://example.org/');

Please make sure the URL is trusted. If you obtain the URL from the user input, make sure it is trusted before
calling $as->logout(), by using the SimpleSAML_Utilities::checkURLAllowed() method.


#### Login link

If you have any links to the initSSO-script, those links must be replaced with links to a new script.
The URL to the new script is `https://.../simplesaml/module.php/core/as_login.php`.
It has two mandatory parameters:

  * `AuthId`: The id of the authentication source.
  * `ReturnTo`: The URL the user should be redirected to after authentication.


#### Logout link

Any links to the initSLO-script must be replaced with links to a new script.
The URL to the new script is `https://.../simplesaml/module.php/core/as_logout.php`.
It has two mandatory parameters:

  * `AuthId`: The id of the authentication source.
  * `ReturnTo`: The URL the user should be redirected to after logout.


Test the application
--------------------

How you test the application is highly dependent on the application, but here are the elements you should test:


### SP initiated login

Make sure that it is still possible to log into the application.


### IdP initiated login

If you use a SimpleSAMLphp IdP, and you want users to be able to bookmark the login page, you need to test IdP initiated login.
To test IdP initiated login from a SimpleSAMLphp IdP, you can access:

    https://.../simplesaml/saml2/idp/SSOService.php?spentityid=<entity ID of your SP>&RelayState=<URL the user should be sent to after login>

Note that the RelayState parameter is only supported if the IdP runs version 1.5 of SimpleSAMLphp.
If it isn't supported by the IdP, you need to configure the `RelayState` option in the authentication source configuration.


### SP initiated logout

Make sure that logging out of your application also logs out of the IdP.
If this does not work, users who log out of your application can log in again without entering any username or password.


### IdP initiated logout

This is used by the IdP if the user logs out of a different SP connected to the IdP.
In this case, the user should also be logged out of your application.

The easiest way to test this is if you have two SPs connected to the IdP.
You can then log out of one SP and check that you are also logged out of the other.


Remove the old metadata from the IdP
------------------------------------

Once the new SP works correctly, you can remove the metadata for the old SP from the IdP.
How you do that depends on the IdP.
If you are running a SimpleSAMLphp IdP, you can remove the entry for the old SP in `metadata/saml20-sp-remote.php`.


Disable the old SAML 2 SP
-------------------------

You may also want to disable the old SP code in SimpleSAMLphp.
To do that, open `config/config.php`, and change the `enable.saml20-sp` option to `FALSE`.

