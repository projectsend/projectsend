<?php
require_once('../_include.php');

// make sure that the user has admin access rights
SimpleSAML\Utils\Auth::requireAdmin();

$config = SimpleSAML_Configuration::getInstance();

if (!empty($_FILES['xmlfile']['tmp_name'])) {
    $xmldata = file_get_contents($_FILES['xmlfile']['tmp_name']);
} elseif (array_key_exists('xmldata', $_POST)) {
    $xmldata = $_POST['xmldata'];
}

if (!empty($xmldata)) {
    \SimpleSAML\Utils\XML::checkSAMLMessage($xmldata, 'saml-meta');
    $entities = SimpleSAML_Metadata_SAMLParser::parseDescriptorsString($xmldata);

    // get all metadata for the entities
    foreach ($entities as &$entity) {
        $entity = array(
            'shib13-sp-remote'  => $entity->getMetadata1xSP(),
            'shib13-idp-remote' => $entity->getMetadata1xIdP(),
            'saml20-sp-remote'  => $entity->getMetadata20SP(),
            'saml20-idp-remote' => $entity->getMetadata20IdP(),
        );
    }

    // transpose from $entities[entityid][type] to $output[type][entityid]
    $output = SimpleSAML\Utils\Arrays::transpose($entities);

    // merge all metadata of each type to a single string which should be added to the corresponding file
    foreach ($output as $type => &$entities) {
        $text = '';
        foreach ($entities as $entityId => $entityMetadata) {

            if ($entityMetadata === null) {
                continue;
            }

            // remove the entityDescriptor element because it is unused, and only makes the output harder to read
            unset($entityMetadata['entityDescriptor']);

            $text .= '$metadata['.var_export($entityId, true).'] = '.
                var_export($entityMetadata, true).";\n";
        }
        $entities = $text;
    }
} else {
    $xmldata = '';
    $output = array();
}

$template = new SimpleSAML_XHTML_Template($config, 'metadata-converter.php', 'admin');
$template->data['clipboard.js'] = true;
$template->data['xmldata'] = $xmldata;
$template->data['output'] = $output;
$template->show();
