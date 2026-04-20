<?php
/**
 * General options form configuration
 * Refactored to use array-based configuration - matches original exactly
 */
include_once __DIR__ . '/timezones.php';

// Define the form sections and fields
$form_sections = [
    [
        'title' => __('Site Information', 'cftp_admin'),
        'description' => __('Basic information to be shown around the site. The time format and zones values affect how the clients see the dates on their files lists.', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'text',
                'name' => 'this_install_title',
                'label' => __('Site name', 'cftp_admin'),
                'required' => true
            ],
            [
                'type' => 'select',
                'name' => 'timezone',
                'label' => __('Timezone', 'cftp_admin'),
                'options' => get_timezone_options(),
                'required' => true
            ],
            [
                'type' => 'text',
                'name' => 'timeformat',
                'label' => __('Time format', 'cftp_admin'),
                'required' => true,
                'note' => sprintf(__('For example, %s will display the current date and time like this: %s', 'cftp_admin'), 'd/m/Y h:i:s', date('d/m/Y h:i:s')) . '<br>' .
                         sprintf(__("For the full list of available values, visit %s the official PHP Manual %s", 'cftp_admin'), '<a href="https://php.net/manual/en/function.date.php" target="_blank">', '</a>') . '<br>' .
                         __("This date will be considered for files expiration.", 'cftp_admin') . '<br>' .
                         __("You can adjust your timezone if your local date/time does not match your server's settings.", 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'footer_custom_enable',
                'label' => __("Use custom footer text", 'cftp_admin')
            ],
            [
                'type' => 'text',
                'name' => 'footer_custom_content',
                'label' => __('Footer content', 'cftp_admin')
            ],
            [
                'type' => 'select',
                'name' => 'pagination_results_per_page',
                'label' => __('Pagination results per page', 'cftp_admin'),
                'options' => array_combine([10, 20, 50, 100], [10, 20, 50, 100]),
                'required' => true,
                'note' => __('Applies to pagination in all administration areas', 'cftp_admin')
            ]
        ]
    ],
    [
        'title' => __('Language', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'use_browser_lang',
                'label' => __("Detect user browser language", 'cftp_admin'),
                'note' => __("If available, will override the default one from the system configuration file. Affects all users and clients.", 'cftp_admin')
            ]
        ]
    ],
    [
        'title' => __('System location', 'cftp_admin'),
        'description' => __('These options are to be changed only if you are moving the system to another place. Changes here can cause ProjectSend to stop working.', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'text',
                'name' => 'base_uri',
                'label' => __('System URI', 'cftp_admin'),
                'value' => BASE_URI,
                'required' => true
            ]
        ]
    ],
    [
        'title' => __('Custom download URI', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'text',
                'name' => 'custom_download_uri',
                'label' => __('Custom download URI base', 'cftp_admin'),
                'note' => sprintf(__("The default URL base is %s. If you set up a custom domain that acts as shortener set the URL here.", 'cftp_admin'), BASE_URI.'custom-download.php?link=') . '<br>' .
                         sprintf(__('When setting up your vhost, make sure to redirect to %s', 'cftp_admin'), BASE_URI.'custom-download.php?link=$file_alias')
            ]
        ],
        'divider' => false // No divider at the end
    ]
];

// Render the form sections
render_options_form_sections($form_sections);