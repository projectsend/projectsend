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
            <?php if ( user_is_logged_in() ) { ?>
                <li class="nav-item" id="header_welcome">
                    <span><?php echo CURRENT_USER_NAME; ?></span>
                </li>
            <?php } ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle" id="language_dropdown" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" data-bs-toggle="dropdown" >
                    <i class="fa fa-globe" aria-hidden="true"></i> <span><?php _e('Language', 'cftp_admin'); ?></span> <span class="caret"></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="language_dropdown">
                    <?php
                        // scan for language files
                        $available_langs = get_available_languages();
                        $return_to = make_return_to_url($_SERVER['REQUEST_URI']);
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
                    <?php if ( user_is_logged_in() && CURRENT_USER_LEVEL != 0) { ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?php echo TRANSLATIONS_URL; ?>" target="_blank"><?php _e('Get more translations','cftp_admin'); ?></a>
                        </li>
                    <?php } ?>
                </ul>
            </li>
            <?php if ( user_is_logged_in() ) { ?>
                <li>
                    <?php $my_account_link = (CURRENT_USER_LEVEL == 0) ? 'clients-edit.php' : 'users-edit.php'; ?>
                    <a href="<?php echo BASE_URI.$my_account_link; ?>?id=<?php echo CURRENT_USER_ID; ?>" class="my_account"><i class="fa fa-user-circle" aria-hidden="true"></i> <span><?php _e('My Account', 'cftp_admin'); ?></span></a>
                </li>
                <li>
                    <a href="<?php echo BASE_URI; ?>process.php?do=logout" ><i class="fa fa-sign-out" aria-hidden="true"></i> <span><?php _e('Logout', 'cftp_admin'); ?></span></a>
                </li>
            <?php } ?>
        </ul>
    </div>
</header>
