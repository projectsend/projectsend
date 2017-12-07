<?php

$metadata['urn:federation:localhost'] = array(
	'prp' => 'https://localhost/adfs/ls/',
	'simplesaml.nameidattribute' => 'uid',
	'authproc' => array(
		50 => array(
			'class' => 'core:AttributeLimit',
			'cn', 'mail', 'uid', 'eduPersonAffiliation',
		),
	),
);
