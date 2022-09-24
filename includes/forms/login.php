<?php
/**
 * Contains the form that is used on the login page
 *
 * @package		ProjectSend
 */
?>
<form action="index.php" name="login_admin" role="form" id="login_form" method="post">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>" />
    <input type="hidden" name="do" value="login">
    <fieldset>
        <div class="form-group">
            <label for="username"><?php _e('Username','cftp_admin'); ?> / <?php _e('E-mail','cftp_admin'); ?></label>
            <input type="text" name="username" id="username" value="<?php if (isset($sysuser_username)) { echo htmlspecialchars($sysuser_username); } ?>" class="form-control" autofocus />
        </div>

        <div class="form-group">
            <label for="password"><?php _e('Password','cftp_admin'); ?></label>
            <input type="password" name="password" id="password" class="form-control" />
        </div>

        <?php /*
        <div class="form-group">
            <label for="language"><?php _e('Language','cftp_admin'); ?></label>
            <select name="language" id="language" class="form-control">
                <?php
                    // scan for language files
                    $available_langs = get_available_languages();
                    $current_lang = LOADED_LANG;
                    if (!empty($_POST['language'])) {
                        $current_lang = $_POST['language'];
                    }
                    foreach ($available_langs as $filename => $lang_name) {
                ?>
                        <option value="<?php echo $filename;?>" <?php echo ( $current_lang == $filename ) ? 'selected' : ''; ?>>
                            <?php
                                echo $lang_name;
                                if ( $filename == SITE_LANG ) {
                                    echo ' [' . __('default','cftp_admin') . ']';
                                }
                            ?>
                        </option>
                <?php
                    }
                ?>
            </select>
        </div>
        */ ?>

        <?php recaptcha2_render_widget(); ?>

        <div class="inside_form_buttons">
            <button type="submit" id="btn_submit" class="btn btn-wide btn-primary" data-text="<?php echo $json_strings['login']['button_text']; ?>" data-loading-text="<?php echo $json_strings['login']['logging_in']; ?>"><?php echo $json_strings['login']['button_text']; ?></button>
        </div>

        <?php include_once 'external_login.php'; ?>
    </fieldset>
</form>

<div class="login_form_links">
    <p id="reset_pass_link"><?php _e("Forgot your password?",'cftp_admin'); ?> <a href="<?php echo BASE_URI; ?>reset-password.php"><?php _e('Set up a new one.','cftp_admin'); ?></a></p>
    <?php
        if (get_option('clients_can_register') == '1') {
    ?>
            <p id="register_link"><?php _e("Don't have an account yet?",'cftp_admin'); ?> <a href="<?php echo BASE_URI; ?>register.php"><?php _e('Register as a new client.','cftp_admin'); ?></a></p>
    <?php
        } else {
    ?>
            <p><?php _e("This server does not allow self registrations.",'cftp_admin'); ?></p>
            <p><?php _e("If you need an account, please contact a server administrator.",'cftp_admin'); ?></p>
    <?php
        }
    ?>
</div>
