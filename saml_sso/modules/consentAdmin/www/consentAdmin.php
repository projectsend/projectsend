<?php
/*
 * consentAdmin - Consent administration module
 *
 * This module enables the user to add and remove consents given for a given
 * Service Provider.
 *
 * The module relies on methods and functions from the Consent module and can
 * not be user without it.
 *
 * Author: Mads Freek <freek@ruc.dk>, Jacob Christiansen <jach@wayf.dk>
 */

/*
 * Runs the processing chain and ignores all filter which have user
 * interaction.
 */
function driveProcessingChain(
    $idp_metadata,
    $source,
    $sp_metadata,
    $sp_entityid,
    $attributes,
    $userid,
    $hashAttributes = false
) {

    /*
     * Create a new processing chain
     */
    $pc = new SimpleSAML_Auth_ProcessingChain($idp_metadata, $sp_metadata, 'idp');

    /*
     * Construct the state.
     * REMEMBER: Do not set Return URL if you are calling processStatePassive
     */
    $authProcState = array(
        'Attributes'  => $attributes,
        'Destination' => $sp_metadata,
        'Source'      => $idp_metadata,
        'isPassive'   => true,
    );

    /*
     * Call processStatePAssive.
     * We are not interested in any user interaction, only modifications to the attributes
     */
    $pc->processStatePassive($authProcState);

    $attributes = $authProcState['Attributes'];

    /*
     * Generate identifiers and hashes
     */
    $destination = $sp_metadata['metadata-set'].'|'.$sp_entityid;

    $targeted_id = sspmod_consent_Auth_Process_Consent::getTargetedID($userid, $source, $destination);
    $attribute_hash = sspmod_consent_Auth_Process_Consent::getAttributeHash($attributes, $hashAttributes);

    SimpleSAML_Logger::info('consentAdmin: user: '.$userid);
    SimpleSAML_Logger::info('consentAdmin: target: '.$targeted_id);
    SimpleSAML_Logger::info('consentAdmin: attribute: '.$attribute_hash);

    // Return values
    return array($targeted_id, $attribute_hash, $attributes);
}

// Get config object
$config = SimpleSAML_Configuration::getInstance();
$cA_config = SimpleSAML_Configuration::getConfig('module_consentAdmin.php');
$authority = $cA_config->getValue('authority');

$as = new SimpleSAML_Auth_Simple($authority);

// If request is a logout request
if (array_key_exists('logout', $_REQUEST)) {
    $returnURL = $cA_config->getValue('returnURL');
    $as->logout($returnURL);
}

$hashAttributes = $cA_config->getValue('attributes.hash');

// Check if valid local session exists
$as->requireAuth();

// Get released attributes
$attributes = $as->getAttributes();

// Get metadata storage handler
$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();

/*
 * Get IdP id and metadata
 */


$local_idp_entityid = $metadata->getMetaDataCurrentEntityID('saml20-idp-hosted');
$local_idp_metadata = $metadata->getMetaData($local_idp_entityid, 'saml20-idp-hosted');

if ($as->getAuthData('saml:sp:IdP') !== null) {
    // from a remote idp (as bridge)
    $idp_entityid = $as->getAuthData('saml:sp:IdP');
    $idp_metadata = $metadata->getMetaData($idp_entityid, 'saml20-idp-remote');
} else {
    // from the local idp
    $idp_entityid = $local_idp_entityid;
    $idp_metadata = $local_idp_metadata;
}

// Get user ID
$userid_attributename = (isset($local_idp_metadata['userid.attribute']) && is_string($local_idp_metadata['userid.attribute'])) ? $local_idp_metadata['userid.attribute'] : 'eduPersonPrincipalName';

$userids = $attributes[$userid_attributename];

if (empty($userids)) {
    throw new Exception('Could not generate useridentifier for storing consent. Attribute ['.
        $userid_attributename.'] was not available.');
}

$userid = $userids[0];

// Get all SP metadata
$all_sp_metadata = $metadata->getList('saml20-sp-remote');

// Parse action, if any
$action = null;
$sp_entityid = null;
if (!empty($_GET['cv'])) {
    $sp_entityid = $_GET['cv'];
}
if (!empty($_GET['action'])) {
    $action = $_GET["action"];
}

SimpleSAML_Logger::critical('consentAdmin: sp: '.$sp_entityid.' action: '.$action);

// Remove services, whitch have consent disabled
if (isset($idp_metadata['consent.disable'])) {
    foreach ($idp_metadata['consent.disable'] AS $disable) {
        if (array_key_exists($disable, $all_sp_metadata)) {
            unset($all_sp_metadata[$disable]);
        }
    }
}

SimpleSAML_Logger::info('consentAdmin: '.$idp_entityid);

// Calc correct source
$source = $idp_metadata['metadata-set'].'|'.$idp_entityid;

// Parse consent config
$consent_storage = sspmod_consent_Store::parseStoreConfig($cA_config->getValue('consentadmin'));

// Calc correct user ID hash
$hashed_user_id = sspmod_consent_Auth_Process_Consent::getHashedUserID($userid, $source);

// If a checkbox have been clicked
if ($action !== null && $sp_entityid !== null) {
    // Get SP metadata
    $sp_metadata = $metadata->getMetaData($sp_entityid, 'saml20-sp-remote');

    // Run AuthProc filters
    list($targeted_id, $attribute_hash, $attributes_new) = driveProcessingChain($idp_metadata, $source, $sp_metadata,
        $sp_entityid, $attributes, $userid, $hashAttributes);

    // Add a consent (or update if attributes have changed and old consent for SP and IdP exists)
    if ($action == 'true') {
        $isStored = $consent_storage->saveConsent($hashed_user_id, $targeted_id, $attribute_hash);
        if ($isStored) {
            $res = "added";
        } else {
            $res = "updated";
        }
        // Remove consent
    } else {
        if ($action == 'false') {
            // Got consent, so this is a request to remove it
            $rowcount = $consent_storage->deleteConsent($hashed_user_id, $targeted_id, $attribute_hash);
            if ($rowcount > 0) {
                $res = "removed";
            }
            // Unknown action (should not happen)
        } else {
            SimpleSAML_Logger::info('consentAdmin: unknown action');
            $res = "unknown";
        }
    }
    // init template to enable translation of status messages
    $template = new SimpleSAML_XHTML_Template($config, 'consentAdmin:consentadminajax.php', 'consentAdmin:consentadmin');
    $template->data['res'] = $res;
    $template->show();
    exit;
}

// Get all consents for user
$user_consent_list = $consent_storage->getConsents($hashed_user_id);

// Parse list of consents
$user_consent = array();
foreach ($user_consent_list as $c) {
    $user_consent[$c[0]] = $c[1];
}

$template_sp_content = array();

// Init template
$template = new SimpleSAML_XHTML_Template($config, 'consentAdmin:consentadmin.php', 'consentAdmin:consentadmin');
$sp_empty_name = $template->getTag('sp_empty_name');
$sp_empty_description = $template->getTag('sp_empty_description');

// Process consents for all SP
foreach ($all_sp_metadata as $sp_entityid => $sp_values) {
    // Get metadata for SP
    $sp_metadata = $metadata->getMetaData($sp_entityid, 'saml20-sp-remote');

    // Run attribute filters
    list($targeted_id, $attribute_hash, $attributes_new) = driveProcessingChain($idp_metadata, $source, $sp_metadata,
        $sp_entityid, $attributes, $userid, $hashAttributes);

    // Check if consent exists
    if (array_key_exists($targeted_id, $user_consent)) {
        $sp_status = "changed";
        SimpleSAML_Logger::info('consentAdmin: changed');
        // Check if consent is valid. (Possible that attributes has changed)
        if ($user_consent[$targeted_id] == $attribute_hash) {
            SimpleSAML_Logger::info('consentAdmin: ok');
            $sp_status = "ok";
        }
        // Consent does not exists
    } else {
        SimpleSAML_Logger::info('consentAdmin: none');
        $sp_status = "none";
    }

    // Set name of SP
    if (isset($sp_values['name']) && is_array($sp_values['name'])) {
        $sp_name = $sp_metadata['name'];
    } else {
        if (isset($sp_values['name']) && is_string($sp_values['name'])) {
            $sp_name = $sp_metadata['name'];
        } elseif (isset($sp_values['OrganizationDisplayName']) && is_array($sp_values['OrganizationDisplayName'])) {
            $sp_name = $sp_metadata['OrganizationDisplayName'];
        } else {
            $sp_name = $sp_empty_name;
        }
    }

    // Set description of SP
    if (empty($sp_metadata['description']) || !is_array($sp_metadata['description'])) {
        $sp_description = $sp_empty_description;
    } else {
        $sp_description = $sp_metadata['description'];
    }

    // Add a URL to the service if present in metadata
    $sp_service_url = isset($sp_metadata['ServiceURL']) ? $sp_metadata['ServiceURL'] : null;

    // Fill out array for the template
    $sp_list[$sp_entityid] = array(
        'spentityid'       => $sp_entityid,
        'name'             => $sp_name,
        'description'      => $sp_description,
        'consentStatus'    => $sp_status,
        'consentValue'     => $sp_entityid,
        'attributes_by_sp' => $attributes_new,
        'serviceurl'       => $sp_service_url,
    );
}

$template->data['header'] = 'Consent Administration';
$template->data['spList'] = $sp_list;
$template->data['showDescription'] = $cA_config->getValue('showDescription');
$template->show();
