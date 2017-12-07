<?php
/* 
 * Configuration for the DiscoPower module.
 */

$config = array (

	// Which tab should be set as default. 0 is the first tab
	'defaulttab' => 0,
	
	/*
	 * List a set of tags (Tabs) that should be listed in a specific order.
	 * All other available tabs will be listed after the ones specified below.
	 */
	'taborder' => array('norway'),
	/*
	 * the 'tab' parameter allows you to limit the tabs to a specific list. (excluding unlisted tags)
	 *
	 * 'tabs' => array('norway', 'finland'),
	 */
	 
	 /**
	  * If you want to change the scoring algorithm to a more google suggest like one
	  * (filters by start of words) uncomment this ... 
	  *
	  * 'score' => 'suggest', 
	  */

	/*
	 * The domain to use for common domain cookie support.
	 * This must be a parent domain of the domain hosting the discovery service.
	 *
	 * If this is NULL (the default), common domain cookie support will be disabled.
	 */
	'cdc.domain' => NULL,

	/*
	 * The lifetime of the common domain cookie, in seconds.
	 *
	 * If this is NULL (the default), the common domain cookie will be deleted when the browser closes.
	 *
	 * Example: 'cdc.lifetime' => 180*24*60*60, // 180 days
	 */
	'cdc.lifetime' => NULL,

);
