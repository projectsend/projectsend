Using the LinkedIn authentication source with SimpleSAMLphp
===========================================================

Remember to configure `authsources.php`, with both Consumer key and secret.

To get an API key and a secret, register the application at:

 * <https://www.linkedin.com/secure/developer>

Set the callback URL to be:

 * `http://sp.example.org/simplesaml/module.php/authlinkedin/linkback.php`

Replace `sp.example.org` with your hostname.

## Testing authentication

On the SimpleSAMLphp frontpage, go to the *Authentication* tab, and use the link:

  * *Test configured authentication sources*

Then choose the *linkedin* authentication source.

Expected behaviour would then be that you are sent to LinkedIn and asked to login.
There is no consent screen for attribute release.

