Using the Windows Live ID authentication source with SimpleSAMLphp
==================================================================

Remember to configure `authsources.php`, with both your Client ID and Secret key.

To get an API key and a secret, register the application at:

 * <http://msdn.microsoft.com/en-us/library/ff751474.aspx>
 * <https://manage.dev.live.com>

## Testing authentication

On the SimpleSAMLphp frontpage, go to the *Authentication* tab, and use the link:

  * *Test configured authentication sources*

Then choose the *windowsliveid* authentication source.

Expected behaviour would then be that you are sent to Windows Live ID and asked to login.

