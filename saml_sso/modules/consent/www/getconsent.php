<?php
/**
 * Consent script
 *
 * This script displays a page to the user, which requests that the user
 * authorizes the release of attributes.
 *
 * @package SimpleSAMLphp
 */
/**
 * Explicit instruct consent page to send no-cache header to browsers to make 
 * sure the users attribute information are not store on client disk.
 * 
 * In an vanilla apache-php installation is the php variables set to:
 *
 * session.cache_limiter = nocache
 *
 * so this is just to make sure.
 */
session_cache_limiter('nocache');

$globalConfig = SimpleSAML_Configuration::getInstance();

SimpleSAML_Logger::info('Consent - getconsent: Accessing consent interface');

if (!array_key_exists('StateId', $_REQUEST)) {
    throw new SimpleSAML_Error_BadRequest(
        'Missing required StateId query parameter.'
    );
}

$id = $_REQUEST['StateId'];
$state = SimpleSAML_Auth_State::loadState($id, 'consent:request');

if (array_key_exists('core:SP', $state)) {
    $spentityid = $state['core:SP'];
} else if (array_key_exists('saml:sp:State', $state)) {
    $spentityid = $state['saml:sp:State']['core:SP'];
} else {
    $spentityid = 'UNKNOWN';
}


// The user has pressed the yes-button
if (array_key_exists('yes', $_REQUEST)) {
    if (array_key_exists('saveconsent', $_REQUEST)) {
        SimpleSAML_Logger::stats('consentResponse remember');		
    } else {
        SimpleSAML_Logger::stats('consentResponse rememberNot');
    }

    $statsInfo = array(
        'remember' => array_key_exists('saveconsent', $_REQUEST),
    );
    if (isset($state['Destination']['entityid'])) {
        $statsInfo['spEntityID'] = $state['Destination']['entityid'];
    }
    SimpleSAML_Stats::log('consent:accept', $statsInfo);

    if (   array_key_exists('consent:store', $state) 
        && array_key_exists('saveconsent', $_REQUEST)
        && $_REQUEST['saveconsent'] === '1'
    ) {
        // Save consent
        $store = $state['consent:store'];
        $userId = $state['consent:store.userId'];
        $targetedId = $state['consent:store.destination'];
        $attributeSet = $state['consent:store.attributeSet'];

        SimpleSAML_Logger::debug(
            'Consent - saveConsent() : [' . $userId . '|' .
            $targetedId . '|' .  $attributeSet . ']'
        );	
        try {
            $store->saveConsent($userId, $targetedId, $attributeSet);
        } catch (Exception $e) {
            SimpleSAML_Logger::error('Consent: Error writing to storage: ' . $e->getMessage());
        }
    }

    SimpleSAML_Auth_ProcessingChain::resumeProcessing($state);
}

// Prepare attributes for presentation
$attributes = $state['Attributes'];
$noconsentattributes = $state['consent:noconsentattributes'];

// Remove attributes that do not require consent
foreach ($attributes AS $attrkey => $attrval) {
    if (in_array($attrkey, $noconsentattributes)) {
        unset($attributes[$attrkey]);
    }
}
$para = array(
    'attributes' => &$attributes
);

// Reorder attributes according to attributepresentation hooks
SimpleSAML_Module::callHooks('attributepresentation', $para);

// Make, populate and layout consent form
$t = new SimpleSAML_XHTML_Template($globalConfig, 'consent:consentform.php');
$t->data['srcMetadata'] = $state['Source'];
$t->data['dstMetadata'] = $state['Destination'];
$t->data['yesTarget'] = SimpleSAML_Module::getModuleURL('consent/getconsent.php');
$t->data['yesData'] = array('StateId' => $id);
$t->data['noTarget'] = SimpleSAML_Module::getModuleURL('consent/noconsent.php');
$t->data['noData'] = array('StateId' => $id);
$t->data['attributes'] = $attributes;
$t->data['checked'] = $state['consent:checked'];

// Fetch privacypolicy
if (array_key_exists('privacypolicy', $state['Destination'])) {
    $privacypolicy = $state['Destination']['privacypolicy'];
} elseif (array_key_exists('privacypolicy', $state['Source'])) {
    $privacypolicy = $state['Source']['privacypolicy'];
} else {
    $privacypolicy = false;
}
if ($privacypolicy !== false) {
    $privacypolicy = str_replace(
        '%SPENTITYID%',
        urlencode($spentityid), 
        $privacypolicy
    );
}
$t->data['sppp'] = $privacypolicy;

// Set focus element
switch ($state['consent:focus']) {
case 'yes':
    $t->data['autofocus'] = 'yesbutton';
    break;
case 'no':
    $t->data['autofocus'] = 'nobutton';
    break;
case null:
default:
    break;
}

if (array_key_exists('consent:store', $state)) {
    $t->data['usestorage'] = true;
} else {
    $t->data['usestorage'] = false;
}

if (array_key_exists('consent:hiddenAttributes', $state)) {
    $t->data['hiddenAttributes'] = $state['consent:hiddenAttributes'];
} else {
    $t->data['hiddenAttributes'] = array();
}

$t->show();
