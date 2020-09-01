<?php
/**
 * Contains the form that is used on the login page
 *
 * @package		ProjectSend
 * @subpackage	Files
 *
 */
?>
<form action="process.php?do=login" name="login_admin" role="form" id="login_form" method="post">
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

        <div class="form-group">
            <label for="language"><?php _e('Language','cftp_admin'); ?></label>
            <select name="language" id="language" class="form-control">
                <?php
                    // scan for language files
                    $available_langs = get_available_languages();
                    foreach ($available_langs as $filename => $lang_name) {
                ?>
                        <option value="<?php echo $filename;?>" <?php echo ( LOADED_LANG == $filename ) ? 'selected' : ''; ?>>
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

        <div class="inside_form_buttons">
            <button type="submit" id="submit" class="btn btn-wide btn-primary" data-text="<?php echo $json_strings['login']['button_text']; ?>" data-loading-text="<?php echo $json_strings['login']['logging_in']; ?>"><?php echo $json_strings['login']['button_text']; ?></button>
        </div>

        <?php include_once 'external_login.php'; ?>
    </fieldset>
</form>
