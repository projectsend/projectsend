<?php
/**
 * This file generates the main menu for the header on the back-end
 * and also for the default template.
 */
$items = [];

/**
 * Items for system users
 */
if (current_role_in(array(9, 8, 7))) {
    $items['dashboard'] = array(
        'nav' => 'dashboard',
        'level' => array(9, 8, 7),
        'main' => array(
            'label' => __('Dashboard', 'cftp_admin'),
            'icon' => 'tachometer',
            'link' => 'dashboard.php',
        ),
    );

    $items[] = 'separator';

    $items['files'] = array(
        'nav' => 'files',
        'level' => array(9, 8, 7),
        'main' => array(
            'label' => __('Files', 'cftp_admin'),
            'icon' => 'file',
        ),
        'sub' => array(
            array(
                'label' => __('Upload', 'cftp_admin'),
                'link' => 'upload.php',
            ),
            array(
                'divider' => true,
            ),
            array(
                'label' => __('Manage files', 'cftp_admin'),
                'link' => 'manage-files.php',
            ),
            array(
                'label' => __('Find orphan files', 'cftp_admin'),
                'link' => 'import-orphans.php',
            ),
            array(
                'divider' => true,
            ),
            array(
                'label' => __('Categories', 'cftp_admin'),
                'link' => 'categories.php',
            ),
        ),
    );

    $items['clients'] = array(
        'nav' => 'clients',
        'level' => array(9, 8),
        'main' => array(
            'label' => __('Clients', 'cftp_admin'),
            'icon' => 'address-card',
            'badge' => count_account_requests(),
        ),
        'sub' => array(
            array(
                'label' => __('Add new', 'cftp_admin'),
                'link' => 'clients-add.php',
            ),
            array(
                'label' => __('Manage clients', 'cftp_admin'),
                'link' => 'clients.php',
                //'badge'	=> COUNT_CLIENTS_INACTIVE,
            ),
            array(
                'divider' => true,
            ),
            array(
                'label' => __('Account requests', 'cftp_admin'),
                'link' => 'clients-requests.php',
                'badge' => count_account_requests(),
            ),
        ),
    );

    $items['groups'] = array(
        'nav' => 'groups',
        'level' => array(9, 8),
        'main' => array(
            'label' => __('Clients groups', 'cftp_admin'),
            'icon' => 'th-large',
            'badge' => count_groups_requests_for_existing_clients(),
        ),
        'sub' => array(
            array(
                'label' => __('Add new', 'cftp_admin'),
                'link' => 'groups-add.php',
            ),
            array(
                'label' => __('Manage groups', 'cftp_admin'),
                'link' => 'groups.php',
            ),
            array(
                'divider' => true,
            ),
            array(
                'label' => __('Membership requests', 'cftp_admin'),
                'link' => 'clients-membership-requests.php',
                'badge' => count_groups_requests_for_existing_clients(),
            ),
        ),
    );

    $items['users'] = array(
        'nav' => 'users',
        'level' => array(9),
        'main' => array(
            'label' => __('System Users', 'cftp_admin'),
            'icon' => 'users',
        ),
        'sub' => array(
            array(
                'label' => __('Add new', 'cftp_admin'),
                'link' => 'users-add.php',
            ),
            array(
                'label' => __('Manage system users', 'cftp_admin'),
                'link' => 'users.php',
                //'badge' => COUNT_USERS_INACTIVE,
            ),
        ),
    );

    $items[] = 'separator';

    $items['templates'] = array(
        'nav' => 'templates',
        'level' => array(9),
        'main' => array(
            'label' => __('Templates', 'cftp_admin'),
            'icon' => 'desktop',
        ),
        'sub' => array(
            array(
                'label' => __('Templates', 'cftp_admin'),
                'link' => 'templates.php',
            ),
        ),
    );

    $items['options'] = array(
        'nav' => 'options',
        'level' => array(9),
        'main' => array(
            'label' => __('Options', 'cftp_admin'),
            'icon' => 'cog',
        ),
        'sub' => array(
            array(
                'label' => __('General options', 'cftp_admin'),
                'link' => 'options.php?section=general',
            ),
            array(
                'label' => __('Clients', 'cftp_admin'),
                'link' => 'options.php?section=clients',
            ),
            array(
                'label' => __('Privacy', 'cftp_admin'),
                'link' => 'options.php?section=privacy',
            ),
            array(
                'label' => __('E-mail notifications', 'cftp_admin'),
                'link' => 'options.php?section=email',
            ),
            array(
                'label' => __('Security', 'cftp_admin'),
                'link' => 'options.php?section=security',
            ),
            array(
                'label' => __('Branding', 'cftp_admin'),
                'link' => 'options.php?section=branding',
            ),
            array(
                'label' => __('External Login', 'cftp_admin'),
                'link' => 'options.php?section=external_login',
            ),
            array(
                'label' => __('Scheduled tasks (cron)', 'cftp_admin'),
                'link' => 'options.php?section=cron',
            ),
        ),
    );

    $items['emails'] = array(
        'nav' => 'emails',
        'level' => array(9),
        'main' => array(
            'label' => __('E-mail templates', 'cftp_admin'),
            'icon' => 'envelope',
        ),
        'sub' => array(
            array(
                'label' => __('Header / footer', 'cftp_admin'),
                'link' => 'email-templates.php?section=header_footer',
            ),
            array(
                'label' => __('New file by user', 'cftp_admin'),
                'link' => 'email-templates.php?section=new_files_by_user',
            ),
            array(
                'label' => __('New file by client', 'cftp_admin'),
                'link' => 'email-templates.php?section=new_files_by_client',
            ),
            array(
                'label' => __('New client (welcome)', 'cftp_admin'),
                'link' => 'email-templates.php?section=new_client',
            ),
            array(
                'label' => __('New client (self-registered)', 'cftp_admin'),
                'link' => 'email-templates.php?section=new_client_self',
            ),
            array(
                'label' => __('Approve client account', 'cftp_admin'),
                'link' => 'email-templates.php?section=account_approve',
            ),
            array(
                'label' => __('Deny client account', 'cftp_admin'),
                'link' => 'email-templates.php?section=account_deny',
            ),
            array(
                'label' => __('Client updated memberships', 'cftp_admin'),
                'link' => 'email-templates.php?section=client_edited',
            ),
            array(
                'label' => __('New user (welcome)', 'cftp_admin'),
                'link' => 'email-templates.php?section=new_user',
            ),
            array(
                'label' => __('Password reset', 'cftp_admin'),
                'link' => 'email-templates.php?section=password_reset',
            ),
            array(
                'label' => __('Login authorization code', 'cftp_admin'),
                'link' => 'email-templates.php?section=2fa_code',
            ),
        ),
    );

    $items[] = 'separator';

    $items['tools'] = array(
        'nav' => 'tools',
        'level' => array(9),
        'main' => array(
            'label' => __('Tools', 'cftp_admin'),
            'icon' => 'wrench',
        ),
        'sub' => array(
            array(
                'label' => __('Actions log', 'cftp_admin'),
                'link' => 'actions-log.php',
            ),
            array(
                'label' => __('Cron log', 'cftp_admin'),
                'link' => 'cron-log.php',
            ),
            array(
                'label' => __('Test email configuration', 'cftp_admin'),
                'link' => 'email-test.php',
            ),
            array(
                'label' => __('Unblock IP', 'cftp_admin'),
                'link' => 'unblock-ip.php',
            ),
            array(
                'label' => __('Custom HTML/CSS/JS', 'cftp_admin'),
                'link' => 'custom-assets.php',
            ),
        ),
    );
}

// Items for clients
else {
    if (get_option('clients_can_upload') == 1) {
        $items['upload'] = array(
            'nav' => 'upload',
            'level' => array(9, 8, 7, 0),
            'main' => array(
                'label' => __('Upload', 'cftp_admin'),
                'link' => 'upload.php',
                'icon' => 'cloud-upload',
            ),
        );
    }

    $items['manage_files'] = array(
        'nav' => 'manage',
        'level' => array(9, 8, 7, 0),
        'main' => array(
            'label' => __('Manage files', 'cftp_admin'),
            'link' => 'manage-files.php',
            'icon' => 'file',
        ),
    );

    $items['view_files'] = array(
        'nav' => 'template',
        'level' => array(9, 8, 7, 0),
        'main' => array(
            'label' => __('View my files', 'cftp_admin'),
            'link' => CLIENT_VIEW_FILE_LIST_URL_PATH,
            'icon' => 'th-list',
        ),
    );
}

// Build the menu
$current_filename = parse_url(basename($_SERVER['REQUEST_URI']));
$menu_output = "
    <div class='main_side_menu'>
        <ul class='main_menu' role='menu'>\n";

foreach ($items as $item) {
    if (!is_array($item) && $item == 'separator') {
        $menu_output .= '<li class="separator"></li>';
        continue;
    }

    if (current_role_in($item['level'])) {
        $current = (!empty($active_nav) && $active_nav == $item['nav']) ? 'current_nav' : '';
        $badge = (!empty($item['main']['badge'])) ? ' <span class="badge rounded-pill text-bg-dark">' . $item['main']['badge'] . '</span>' : '';
        $icon = (!empty($item['main']['icon'])) ? '<i class="fa fa-' . $item['main']['icon'] . ' fa-fw" aria-hidden="true"></i>' : '';

        /** Top level tag */
        if (!isset($item['sub'])) {
            $format = "<li class='%s'>\n\t<a href='%s' class='nav_top_level'>%s<span class='menu_label'>%s%s</span></a>\n</li>\n";
            $menu_output .= sprintf($format, $current, BASE_URI . $item['main']['link'], $icon, $badge, $item['main']['label']);
        } else {
            $first_child = $item['sub'][0];
            $top_level_link = (!empty($first_child)) ? $first_child['link'] : '#';
            $format = "<li class='has_dropdown %s'>\n\t<a href='%s' class='nav_top_level'>%s<span class='menu_label'>%s%s</span></a>\n\t<ul class='dropdown_content'>\n";
            $menu_output .= sprintf($format, $current, $top_level_link, $icon, $item['main']['label'], $badge);
            /**
             * Submenu
             */
            foreach ($item['sub'] as $subitem) {
                $badge = (!empty($subitem['badge'])) ? ' <span class="badge rounded-pill text-bg-dark">' . $subitem['badge'] . '</span>' : '';
                $icon = (!empty($subitem['icon'])) ? '<i class="fa fa-' . $subitem['icon'] . ' fa-fw" aria-hidden="true"></i>' : '';
                if (!empty($subitem['divider'])) {
                    $menu_output .= "\t\t<li class='divider'></li>\n";
                } else {
                    $sub_active = ($subitem['link'] == $current_filename['path']) ? 'current_page' : '';

                    if (isset($_GET['section'])) {
                        $parse = parse_url($subitem['link'], PHP_URL_QUERY);
                        if (!empty($parse)) {
                            parse_str($parse, $subitem_query);
                            if (isset($subitem_query['section'])) {
                                if ($subitem_query['section'] == $_GET['section']) { $sub_active = 'current_page'; }
                            }
                        }
                    }

                    $format = "\t\t<li class='%s'>\n\t\t\t<a href='%s'>%s<span class='submenu_label'>%s%s</span></a>\n\t\t</li>\n";
                    $menu_output .= sprintf($format, $sub_active, BASE_URI . $subitem['link'], $icon, $subitem['label'], $badge);
                }
            }
            $menu_output .= "\t</ul>\n</li>\n";
        }
    }
}

$menu_output .= "</ul></div>\n";

$menu_output = str_replace("'", '"', $menu_output);

// Print to screen
echo $menu_output;
