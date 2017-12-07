<?php

/*
 * This file defines "named" access control lists, which can
 * be reused in several places.
 */
$config = array(

	'adminlist' => array(
		//array('allow', 'equals', 'mail', 'admin1@example.org'),
		//array('allow', 'has', 'groups', 'admin'),
		// The default action is to deny access.
	),

	'example-simple' => array(
		array('allow', 'equals', 'mail', 'admin1@example.org'),
		array('allow', 'equals', 'mail', 'admin2@example.org'),
		// The default action is to deny access.
	),

	'example-deny-some' => array(
		array('deny', 'equals', 'mail', 'eviluser@example.org'),
		array('allow'), // Allow everybody else.
	),

	'example-maildomain' => array(
		array('allow', 'equals-preg', 'mail', '/@example\.org$/'),
		// The default action is to deny access.
	),

	'example-allow-employees' => array(
		array('allow', 'has', 'eduPersonAffiliation', 'employee'),
		// The default action is to deny access.
	),

	'example-allow-employees-not-students' => array(
		array('deny', 'has', 'eduPersonAffiliation', 'student'),
		array('allow', 'has', 'eduPersonAffiliation', 'employee'),
		// The default action is to deny access.
	),

	'example-deny-student-except-one' => array(
		array('deny', 'and',
			array('has', 'eduPersonAffiliation', 'student'),
			array('not', 'equals', 'mail', 'user@example.org'),
		),
		array('allow'),
	),

	'example-allow-or' => array(
		array('allow', 'or',
			array('equals', 'eduPersonAffiliation', 'student', 'member'),
			array('equals', 'mail', 'someuser@example2.org'),
		),
	),

	'example-allow-all' => array(
		array('allow'),
	),

);