<form action="process.php?do=login_ldap" name="login_ldap" role="form" id="login_ldap" method="post">
    <input type="hidden" name="do" value="login_ldap">
    <fieldset>
        <div class="form-group">
            <label for="username"><?php _e('E-mail','cftp_admin'); ?></label>
            <input type="text" name="email" id="email" value="<?php if (isset($ldap_email)) { echo htmlspecialchars($ldap_email); } ?>" class="form-control" />
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
    </fieldset>
</form>
