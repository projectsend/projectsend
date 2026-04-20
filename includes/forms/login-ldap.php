<form action="index.php" name="login_ldap_form" role="form" id="login_ldap_form" method="post">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>" />
    <input type="hidden" name="do" value="login_ldap">
    <fieldset>
        <div class="mb-3">
            <label for="ldap_email"><?php _e('E-mail','cftp_admin'); ?></label>
            <input type="email" name="ldap_email" id="ldap_email" value="<?php if (isset($ldap_email)) { echo htmlspecialchars($ldap_email); } ?>" class="form-control" autofocus placeholder="<?php _e('Enter your email address', 'cftp_admin'); ?>" />
        </div>

        <div class="mb-3">
            <label for="ldap_password"><?php _e('Password','cftp_admin'); ?></label>
            <input type="password" name="ldap_password" id="ldap_password" class="form-control" placeholder="<?php _e('Enter your password', 'cftp_admin'); ?>" />
        </div>

        <?php if (get_option('remember_me_enabled', null, '1')) { ?>
        <div class="mb-3">
            <div class="form-check">
                <input type="checkbox" name="remember_me" id="ldap_remember_me" class="form-check-input" value="1" />
                <label for="ldap_remember_me" class="form-check-label">
                    <i class="fa fa-shield-alt me-1"></i><?php _e('Remember me for 30 days','cftp_admin'); ?>
                </label>
            </div>
        </div>
        <?php } ?>

        <?php /*
        <div class="form-group row">
            <label for="language"><?php _e('Language','cftp_admin'); ?></label>
            <select class="form-select" name="language">
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
        </div> */ ?>

        <?php recaptcha2_render_widget(); ?>

        <div class="inside_form_buttons">
            <button type="submit" id="ldap_submit" class="btn btn-wide btn-primary" data-text="<?php echo $json_strings['login']['button_text']; ?>" data-loading-text="<?php echo $json_strings['login']['logging_in']; ?>"><?php echo $json_strings['login']['button_text']; ?></button>
        </div>
    </fieldset>
</form>

<p class="login_notes">
    <i class="fa fa-info-circle"></i> <?php _e('Use your organization email and password to sign in via LDAP/Active Directory.', 'cftp_admin'); ?>
</p>
