authorize Module
================

<!--
	This file is written in Markdown syntax.
	For more information about how to use the Markdown syntax, read here:
	http://daringfireball.net/projects/markdown/syntax
-->

  * Author: Ernesto Revilla <erny@yaco.es>, Yaco Sistemas, Ryan Panning
  * Package: SimpleSAMLphp

This module provides a user authorization filter based on attribute matching for those applications that do not cleanly separate authentication from authorization and set some default permissions for authenticated users.


`authorize:Authorize`
: Authorize certain users based on attribute matching


`authorize:Authorize`
---------------------

There are two configuration options that can be defined; deny and regex. All other filter configuration options are considered attribute matching rules.

The users not authorized will be shown a 403 Forbidden page.

### Deny ###
The default action of the filter is to authorize only if an attribute match is found (default allow). When set to TRUE, this option reverses that rule and authorizes the user unless an attribute match is found (default deny), causing an unauthorized action.

Note: This option needs to be boolean (TRUE/FALSE) else it will be considered an attribute matching rule.

### Regex ###
Turn regex pattern matching on or off for the attribute values defined. For backwards compatibility, this option defaults to TRUE, but can be turned off by setting it to FALSE.

Note: This option needs to be boolean (TRUE/FALSE) else it will be considered an attribute matching rule.

### Attribute Rules ###
Each additional filter configuration option is considered an attribute matching rule. For each attribute, you can specify a string or array of strings to match. If one of those attributes match one of the rules (OR operator), the user is authorized/unauthorized (depending on the deny config option).

Note: If regex is enabled, you must use the preg_match format, i.e. you have to enclose it with a delimiter that does not appear inside the regex (e.g. slash (/), at sign (@), number sign (#) or underscore (`_`)).

### Problems ###
 * Once you get the forbidden page, you can't logout at the IdP directly,
   (as far as I know), you have to close the browser.

### Examples ###
To use this filter configure it in `config/config.php`:

	'authproc.sp' => array(
		60 => array(
			'class' => 'authorize:Authorize',
			'uid'   =>  array(
				'/.*@example.com/',
				'/(user1|user2|user3)@example.edu/',
			),
			'schacUserStatus' => '@urn:mace:terena.org:userStatus:' .
				'example.org:service:active.*@',
	)


An alternate way of using this filter is to deny certain users. Or even use multiple filters to create a simple ACL, by first allowing a group of users but then denying a "black list" of users.

	'authproc.sp' => array(
		60 => array(
			'class' => 'authorize:Authorize',
			'deny'  => TRUE,
			'uid'   =>  array(
				'/.*@students.example.edu/',
				'/(stu1|stu2|stu3)@example.edu/',
			)
	)

The regex pattern matching can be turned off, allowing for exact attribute matching rules. This can be helpful in cases where you know what the value should be. An example of this is with the memberOf attribute or using the ldap:AttributeAddUsersGroups filter with the group attribute.

	'authproc.sp' => array(
		60 => array(
			'class' => 'authorize:Authorize',
			'regex' => FALSE,
			'group' =>  array(
				'CN=SimpleSAML Students,CN=Users,DC=example,DC=edu',
				'CN=All Teachers,OU=Staff,DC=example,DC=edu',
			)
	)
