<?php
function get_asset_languages()
{
    return [
        'css' => __('CSS'),
        'js' => __('JavaScript'),
        'html' => __('HTML'),
    ];
}

function get_asset_locations()
{
    return [
        'public' => __('Public pages'),
        'private' => __('Administration pages'),
        'template' => __('Files list template'),
        'all' => __('All locations'),
        'all_no_template' => __('All except files template'),
    ];
}

function get_asset_positions()
{
    $positions = [
        'head' => __('In <head>'),
        'body_top' => __('Before <body>'),
        'body_bottom' => __('After </body>'),
    ];

    return str_replace([
        '<',
        '>',
    ], [
        '&lt',
        '&gt',
    ], $positions);
}

function format_asset_language_name($name)
{
    $languages = get_asset_languages();
    return $languages[$name];
}

function format_asset_location_name($name)
{
    $locations = get_asset_locations();
    return $locations[$name];
}

function format_asset_position_name($name)
{
    $positions = get_asset_positions();
    return $positions[$name];
}


function add_asset($type, $name, $url, $version = null, $position = null, $arguments = [])
{
    if (!in_array($type, ['js', 'css'])) {
        return;
    }

    global $assets_loader;
    $assets_loader->addAsset($type, $name, $url, $position, $version, $arguments);
}

function render_assets($type, $location)
{
    global $assets_loader;
    $assets_loader->renderAssets($type, $location);
}

function render_custom_assets($position = null)
{
    global $dbh;

    if (!table_exists(TABLE_CUSTOM_ASSETS)) {
        return null;
    }

    // Get assets
    $params = [];
    $query = "SELECT * FROM " . TABLE_CUSTOM_ASSETS . " WHERE enabled = 1";
    switch (get_current_view_type()) {
        case 'public':
        case 'private':
            $query .= " AND FIND_IN_SET(location, :location)";
            $params[':location'] = implode(',', [get_current_view_type(), 'all', 'all_no_template']);
            break;
        case 'template':
            $query .= " AND FIND_IN_SET(location, :location)";
            $params[':location'] = implode(',', [get_current_view_type(), 'all']);
            break;
        break;
    }

    if (!empty($position)) {
        $query .= " AND position = :position";
        $params[':position'] = $position;
    }

    // echo $query;
    // print_r($params);
    $assets = $dbh->prepare( $query );
    $assets->execute($params);
    $count = $assets->rowCount();
    $assets->setFetchMode(PDO::FETCH_ASSOC);
    if ($count > 0) {
        while ( $row = $assets->fetch() ) {
            $asset = new \ProjectSend\Classes\CustomAsset($row["id"]);
            $properties = $asset->getProperties();
            if ($properties['language'] == 'css') { echo '<style>'; }
            if ($properties['language'] == 'js') { echo '<script>'; }
            echo $properties['content'];
            if ($properties['language'] == 'css') { echo '</style>'; }
            if ($properties['language'] == 'js') { echo '</script>'; }
        }
    }
}

function add_codemirror_assets()
{
    add_asset('css', 'cm_main', BASE_URI.'assets/lib/codemirror/lib/codemirror.css');
    add_asset('js', 'cm_main', BASE_URI.'assets/lib/codemirror/lib/codemirror.js');
    add_asset('js', 'cm_mode_js', BASE_URI.'assets/lib/codemirror/mode/javascript/javascript.js');
    add_asset('js', 'cm_mode_css', BASE_URI.'assets/lib/codemirror/mode/css/css.js');
    add_asset('js', 'cm_mode_xml', BASE_URI.'assets/lib/codemirror/mode/xml/xml.js');
    add_asset('js', 'cm_mode_multiplex', BASE_URI.'assets/lib/codemirror/addon/mode/multiplex.js');
    add_asset('js', 'cm_mode_htmlmixed', BASE_URI.'assets/lib/codemirror/mode/htmlmixed/htmlmixed.js');
    add_asset('js', 'cm_addon_autoclose_tags', BASE_URI.'assets/lib/codemirror/addon/edit/closetag.js');
    add_asset('js', 'cm_addon_autoclose_brackets', BASE_URI.'assets/lib/codemirror/addon/edit/closebrackets.js');
    add_asset('js', 'cm_addon_match_brackets', BASE_URI.'assets/lib/codemirror/addon/edit/matchbrackets.js');
}
