<?php
/**
 * Privacy options form configuration
 * Refactored to use array-based configuration - matches original exactly
 */

// Define the form sections and fields
$form_sections = [
    [
        'title' => __('Privacy', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'privacy_noindex_site',
                'label' => __("Prevent search engines from indexing this site", 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'enable_landing_for_all_files',
                'label' => __("Enable information page for private files", 'cftp_admin'),
                'note' => __("If enabled, the file information landing page will be available even for files that are not marked as private. Downloading them will stay restricted.", 'cftp_admin')
            ]
        ]
    ],
    [
        'title' => __('Downloads', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'select',
                'name' => 'privacy_record_downloads_ip_address',
                'label' => __('Log IP address and host for:', 'cftp_admin'),
                'options' => [
                    'all' => __('All downloads', 'cftp_admin'),
                    'anonymous' => __('Anonymous users only', 'cftp_admin'),
                    'none' => __('Never record IP address and host', 'cftp_admin')
                ],
                'required' => true
            ]
        ]
    ],
    [
        'title' => __('Public groups and files listings page', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'public_listing_page_enable',
                'label' => __('Enable page', 'cftp_admin'),
                'note' => __('The url for the listings page is', 'cftp_admin') . '<br><a href="' . PUBLIC_LANDING_URI . '" target="_blank" id="public_landing_uri">' . PUBLIC_LANDING_URI . '</a> <i class="fa fa-copy copy_text" data-target="public_landing_uri"></i>'
            ],
            [
                'type' => 'checkbox',
                'name' => 'public_listing_logged_only',
                'label' => __('Only for logged in clients', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'public_listing_show_all_files',
                'label' => __('Inside groups show all files, including those that are not marked as public.', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'public_listing_use_download_link',
                'label' => __('On public files, show the download link.', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'public_listing_enable_preview',
                'label' => __('Enable files previews', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'public_listing_home_show_link',
                'label' => __('Show a link to the public page under the log in form', 'cftp_admin')
            ]
        ],
        'divider' => false // No divider at the end
    ]
];

// Render the form sections
render_options_form_sections($form_sections);
