<?php
/**
 * Retro90s Theme Settings Configuration
 * Defines the available settings for the retro90s theme
 *
 * Note: Footer text is handled by the render_footer_text() function
 * which is used by all themes for consistent footer display
 */

return [
    'theme_name' => 'Retro90s',
    'version' => '1.0',
    'settings' => [
        'show_entertainment' => [
            'type' => 'checkbox',
            'label' => __('Show Entertainment Section', 'cftp_admin'),
            'description' => __('Display the entertainment section with featured content on the public file listing page.', 'cftp_admin'),
            'default' => true
        ],
        'entertainment_items_count' => [
            'type' => 'number',
            'label' => __('Entertainment Items Count', 'cftp_admin'),
            'description' => __('Number of entertainment items to display in the section (1-10).', 'cftp_admin'),
            'default' => 3,
            'min' => 1,
            'max' => 10
        ],
        'entertainment_title' => [
            'type' => 'text',
            'label' => __('Entertainment Section Title', 'cftp_admin'),
            'description' => __('Custom title for the entertainment section. Leave empty for default.', 'cftp_admin'),
            'default' => ''
        ],
        'retro_color_scheme' => [
            'type' => 'select',
            'label' => __('Color Scheme', 'cftp_admin'),
            'description' => __('Choose the retro color scheme for the theme.', 'cftp_admin'),
            'default' => 'neon',
            'options' => [
                'neon' => __('Neon (Pink/Cyan)', 'cftp_admin'),
                'sunset' => __('Sunset (Orange/Purple)', 'cftp_admin'),
                'classic' => __('Classic (Blue/Yellow)', 'cftp_admin'),
                'matrix' => __('Matrix (Green/Black)', 'cftp_admin')
            ]
        ],
        'show_grid_animation' => [
            'type' => 'checkbox',
            'label' => __('Enable Grid Animation', 'cftp_admin'),
            'description' => __('Display animated grid background effect typical of 90s aesthetics.', 'cftp_admin'),
            'default' => true
        ],
        'show_visitor_counter' => [
            'type' => 'checkbox',
            'label' => __('Show Visitor Counter', 'cftp_admin'),
            'description' => __('Display a retro 90s-style visitor counter with Comic Sans font. Classic web nostalgia!', 'cftp_admin'),
            'default' => true
        ],
        'header_background_color' => [
            'type' => 'color',
            'label' => __('Header Background Color', 'cftp_admin'),
            'description' => __('Choose any color for the header section where the logo is displayed. Click to open color picker.', 'cftp_admin'),
            'default' => '#000080'
        ]
    ]
];