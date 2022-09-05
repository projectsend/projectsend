<?php
/**
 * This file generates the header for pages shown to unlogged users and
 * clients (log in form and, if allowed, self registration form).
 *
 * @package ProjectSend
 */

/**
 * This file is shared with the installer. Let's start by checking
 * where is it being called from.
 */
if ( defined('IS_INSTALL') ) {
    $lang = ( defined('SITE_LANG') ) ? SITE_LANG : 'en';
    $title = __('Install','cftp_admin') . ' &raquo; ' . SYSTEM_NAME;

    $header_vars = array(
        'html_lang'		=> $lang,
        'title'			=> $title,
        'header_title'	=> SYSTEM_NAME . ' ' . __('setup','cftp_admin'),
    );
}

else {
    /**
     * Check if the ProjectSend is installed. Done only on the log in form
     * page since all other are inaccessible if no valid session or cookie
     * is set.
     */
    $header_vars = array(
        'html_lang'		=> SITE_LANG,
        'title'			=> $page_title . ' &raquo; ' . html_output(get_option('this_install_title')),
        'header_title'	=> html_output(get_option('this_install_title')),
    );

    if ( !is_projectsend_installed() ) {
        header("Location:install/index.php");
        exit;
    }

    /**
     * This is defined on the public download page.
     * So even logged in users can access it.
     */
    if (!isset($dont_redirect_if_logged)) {
        if (defined('CURRENT_USER_LEVEL')) {
            if (CURRENT_USER_LEVEL == 0) {
                header("location:" . CLIENT_VIEW_FILE_LIST_URL);
                exit;    
            }
        }

        /** If logged as a system user, go directly to the back-end homepage */
        if (defined('CURRENT_USER_LEVEL') && !empty($allowed_levels) && current_role_in($allowed_levels)) {
            header("Location:".BASE_URI."dashboard.php");
            exit;
        }
    }
    /**
     * Silent updates that are needed even if no user is logged in.
     */
    require_once INCLUDES_DIR . DS . 'core.update.silent.php';
}

if ( !isset( $body_class ) ) { $body_class = array(); }
?>
<!doctype html>
<html lang="<?php echo $header_vars['html_lang']; ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <?php meta_noindex(); ?>

    <title><?php echo html_output( $header_vars['title'] ); ?></title>
    <?php meta_favicon(); ?>

    <?php
        $load_theme_css = true;
        require_once INCLUDES_DIR . DS . 'assets.php';

        load_js_header_files();
        load_css_files();
    ?>
</head>

<body <?php echo add_body_class( $body_class ); ?> <?php if (!empty($page_id)) { echo add_page_id($page_id); } ?>>
    <div class="container-custom">
        <?php include_once LAYOUT_DIR . DS . 'header-top.php'; ?>

        <div class="main_content_unlogged">
            <div class="container-fluid">
                <div class="row">