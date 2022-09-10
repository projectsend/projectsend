<header id="header" class="navbar navbar-static-top navbar-fixed-top">
    <?php if ( check_for_session( false ) ) { ?>
        <ul class="nav pull-left nav_toggler">
            <li>
                <a href="#" class="toggle_main_menu"><i class="fa fa-bars" aria-hidden="true"></i><span><?php _e('Toggle menu', 'cftp_admin'); ?></span></a>
            </li>
        </ul>
    <?php } ?>

    <div class="navbar-header">
        <span class="navbar-brand"><a href="<?php echo SYSTEM_URI; ?>" target="_blank"><?php include_once ROOT_DIR.'/assets/img/ps-icon.svg'; ?></a> <?php echo html_output(get_option('this_install_title')); ?></span>
    </div>

    <ul class="nav pull-right nav_account">
        <?php if ( check_for_session( false ) ) { ?>
            <li id="header_welcome">
                <span><?php echo CURRENT_USER_NAME; ?></span>
            </li>
        <?php } ?>
        <li class="dropdown">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fa fa-globe" aria-hidden="true"></i> <?php _e('Language', 'cftp_admin'); ?> <span class="caret"></span></a>
                <ul class="dropdown-menu pull-right">
                    <?php
                        // scan for language files
                        $available_langs = get_available_languages();
                        foreach ($available_langs as $filename => $lang_name) {
                    ?>
                            <li>
                                <a href="<?php echo BASE_URI.'process.php?do=change_language&language='.$filename.'&return_to='.BASE_URI.urlencode(basename($_SERVER['REQUEST_URI'])); ?>">
                                    <?php echo $lang_name; ?>
                                </a>
                            </li>
                    <?php
                        }
                    ?>
                </ul>
        </li>
        <?php if ( check_for_session( false ) ) { ?>
            <li>
                <?php $my_account_link = (CURRENT_USER_LEVEL == 0) ? 'clients-edit.php' : 'users-edit.php'; ?>
                <a href="<?php echo BASE_URI.$my_account_link; ?>?id=<?php echo CURRENT_USER_ID; ?>" class="my_account"><i class="fa fa-user-circle" aria-hidden="true"></i> <?php _e('My Account', 'cftp_admin'); ?></a>
            </li>
            <li>
                <a href="<?php echo BASE_URI; ?>process.php?do=logout" ><i class="fa fa-sign-out" aria-hidden="true"></i> <?php _e('Logout', 'cftp_admin'); ?></a>
            </li>
        <?php } ?>
    </ul>
</header>
