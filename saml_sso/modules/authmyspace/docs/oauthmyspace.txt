Using the MySpace authentication source with SimpleSAMLphp
==========================================================

Remember to configure `authsources.php`, with both your Client ID and Secret key.

To get an API key and a secret, register the application at:

 * <http://developer.myspace.com/Modules/Apps/Pages/CreateAppAccount.aspx>

Create a MySpace ID App and set the callback evaluation URL to be:

 * `http://sp.example.org/`

Replace `sp.example.org` with your hostname.

## Testing authentication

On the SimpleSAMLphp frontpage, go to the *Authentication* tab, and use the link:

  * *Test configured authentication sources*

Then choose the *myspace* authentication source.

Expected behaviour would then be that you are sent to MySpace, and asked to login.
There is no consent screen for attribute release.
