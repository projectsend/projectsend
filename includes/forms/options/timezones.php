<?php
/**
 * Returns timezone options grouped by continent for use in a select field.
 *
 * @package ProjectSend
 */
function get_timezone_options()
{
    $valid_continents = [
        'Africa', 'America', 'Antarctica', 'Arctic',
        'Asia', 'Atlantic', 'Australia', 'Europe',
        'Indian', 'Pacific'
    ];

    $grouped = [];

    foreach (timezone_identifiers_list() as $identifier) {
        $parts = explode('/', $identifier, 3);
        $continent = $parts[0];

        if (!in_array($continent, $valid_continents)) {
            continue;
        }

        if (!isset($parts[1])) {
            continue;
        }

        $label = str_replace('_', ' ', isset($parts[2]) ? $parts[1] . '/' . $parts[2] : $parts[1]);
        $grouped[$continent][$identifier] = $label;
    }

    return $grouped;
}
