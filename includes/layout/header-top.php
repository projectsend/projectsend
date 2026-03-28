<header id="header" class="navbar navbar-expand-md navbar-dark fixed-top bg-dark">
    <div class="container-fluid">
        <?php if ( user_is_logged_in() ) { ?>
            <ul class="nav pull-left nav_toggler">
                <li>
                    <a href="#" class="toggle_main_menu"><i class="fa fa-bars" aria-hidden="true"></i><span><?php _e('Toggle menu', 'cftp_admin'); ?></span></a>
                </li>
            </ul>
        <?php } ?>

        <div class="navbar-header ms-3 me-auto">
            <span class="navbar-brand">
                <a href="<?php echo SYSTEM_URI; ?>" target="_blank">
                    <?php include_once ROOT_DIR.'/assets/img/ps-icon.svg'; ?>
                </a> <?php echo html_output(get_option('this_install_title')); ?></span>
        </div>

        <ul class="nav pull-right nav_account">
            <li class="nav-item">
                <a href="#" id="theme-toggle" class="theme-toggle" title="<?php _e('Toggle dark/light theme', 'cftp_admin'); ?>">
                    <i class="fa fa-moon-o theme-icon-dark" aria-hidden="true"></i>
                    <i class="fa fa-sun-o theme-icon-light" aria-hidden="true" style="display: none;"></i>
                    <span class="sr-only"><?php _e('Toggle theme', 'cftp_admin'); ?></span>
                </a>
            </li>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle" id="language_dropdown" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" data-bs-toggle="dropdown" >
                    <i class="fa fa-globe" aria-hidden="true"></i> <span><?php _e('Language', 'cftp_admin'); ?></span> <span class="caret"></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="language_dropdown">
                    <?php
                        // scan for language files
                        $available_langs = get_available_languages();
                        $return_to = make_return_to_url($_SERVER['REQUEST_URI'], true);
                        foreach ($available_langs as $filename => $lang_name) {
                    ?>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URI.'process.php?do=change_language&language='.$filename.'&return_to='.$return_to; ?>">
                                    <?php echo $lang_name; ?>
                                </a>
                            </li>
                    <?php
                        }
                    ?>
                    <?php if ( user_is_logged_in() && !current_role_in(['Client'])) { ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?php echo TRANSLATIONS_URL; ?>" target="_blank">
                                <i class="fa fa-external-link" aria-hidden="true"></i> <?php _e('Get more translations','cftp_admin'); ?>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            </li>
            <?php if ( user_is_logged_in() && defined('CURRENT_USER_NAME') && defined('CURRENT_USER_ID') ) {
                // Extract user initials for avatar
                $user_name = CURRENT_USER_NAME;
                $words = explode(' ', trim($user_name));
                $initials = '';
                if (count($words) >= 2) {
                    $initials = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
                } else {
                    $initials = strtoupper(substr($user_name, 0, 2));
                }

                // Generate consistent avatar color based on user ID
                $user_id = CURRENT_USER_ID;
                $avatar_colors = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#34495e', '#e67e22'];
                $avatar_color = $avatar_colors[$user_id % count($avatar_colors)];
            ?>
                <li class="dropdown user-dropdown">
                    <a href="#" class="dropdown-toggle user-trigger" id="user_dropdown" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" data-bs-toggle="dropdown">
                        <div class="user-avatar" style="background-color: <?php echo $avatar_color; ?>;">
                            <?php echo $initials; ?>
                        </div>
                        <span class="user-name"><?php echo html_output($user_name); ?></span>
                        <i class="fa fa-caret-down" aria-hidden="true"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="user_dropdown">
                        <li>
                            <a class="dropdown-item" href="<?php echo client_get_profile_link(); ?>">
                                <i class="fa fa-user-circle" aria-hidden="true"></i> <?php _e('My Account', 'cftp_admin'); ?>
                            </a>
                        </li>
                        <?php if ((bool)get_option('two_factor_allow_totp', null, '1')) { ?>
                        <li>
                            <a class="dropdown-item" href="<?php echo BASE_URI; ?>totp-setup.php">
                                <i class="fa fa-shield" aria-hidden="true"></i> <?php _e('Two-Factor Auth', 'cftp_admin'); ?>
                            </a>
                        </li>
                        <?php } ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?php echo BASE_URI; ?>process.php?do=logout">
                                <i class="fa fa-sign-out" aria-hidden="true"></i> <?php _e('Logout', 'cftp_admin'); ?>
                            </a>
                        </li>
                    </ul>
                </li>
            <?php } ?>
        </ul>
    </div>
</header>
