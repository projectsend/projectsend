<h3><?php _e('General','cftp_admin'); ?></h3>
<p><?php _e('Basic information to be shown around the site. The time format and zones values affect how the clients see the dates on their files lists.','cftp_admin'); ?></p>

<div class="form-group">
    <label for="this_install_title" class="col-sm-4 control-label"><?php _e('Site name','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="this_install_title" id="this_install_title" class="form-control" value="<?php echo html_output(get_option('this_install_title')); ?>" required />
    </div>
</div>

<div class="form-group">
    <label for="timezone" class="col-sm-4 control-label"><?php _e('Timezone','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <?php
            /**
             * Generates a select field.
             * Code is stored on a separate file since it's pretty long.
             */
            include_once 'timezones.php';
        ?>
    </div>
</div>

<div class="form-group">
    <label for="timeformat" class="col-sm-4 control-label"><?php _e('Time format','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" class="form-control" name="timeformat" id="timeformat" value="<?php echo get_option('timeformat'); ?>" required />
        <p class="field_note"><?php _e('For example, d/m/Y h:i:s will result in something like','cftp_admin'); ?> <strong><?php echo date('d/m/Y h:i:s'); ?></strong> <?php _e('(current server time)','cftp_admin'); ?>. <?php _e("This date will be used for files expiration settings.",'cftp_admin'); ?><br>
        <?php echo sprintf(__("For the full list of available values, visit %s the official PHP Manual %s",'cftp_admin'), '<a href="http://php.net/manual/en/function.date.php" target="_blank">', '</a>'); ?></p>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="footer_custom_enable">
            <input type="checkbox" value="1" name="footer_custom_enable" id="footer_custom_enable" class="checkbox_options" <?php echo (get_option('footer_custom_enable') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e("Use custom footer text",'cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <label for="footer_custom_content" class="col-sm-4 control-label"><?php _e('Footer content','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="footer_custom_content" id="footer_custom_content" class="form-control" value="<?php echo html_output(get_option('footer_custom_content')); ?>" />
    </div>
</div>

<div class="form-group">
    <label for="pagination_results_per_page" class="col-sm-4 control-label"><?php _e('Pagination results per page','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <select class="form-control" name="pagination_results_per_page" id="pagination_results_per_page" required>
            <?php
                $pagination_amounts = [10, 20, 50, 100];
                foreach ($pagination_amounts as $pagination_amount) {
            ?>
                    <option value="<?php echo $pagination_amount; ?>" <?php echo (get_option('pagination_results_per_page') == $pagination_amount) ? 'selected="selected"' : ''; ?>><?php echo $pagination_amount; ?></option>
            <?php
                }
            ?>
        </select>
        <p class="field_note"><?php _e('Applies to pagination in all administration areas','cftp_admin'); ?>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('Editor','cftp_admin'); ?></h3>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="files_descriptions_use_ckeditor">
            <input type="checkbox" value="1" name="files_descriptions_use_ckeditor" id="files_descriptions_use_ckeditor" class="checkbox_options" <?php echo (get_option('files_descriptions_use_ckeditor') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e("Use the visual editor on files descriptions",'cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('Language','cftp_admin'); ?></h3>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="use_browser_lang">
            <input type="checkbox" value="1" name="use_browser_lang" id="use_browser_lang" class="checkbox_options" <?php echo (get_option('use_browser_lang') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e("Detect user browser language",'cftp_admin'); ?>
            <p class="field_note"><?php _e("If available, will override the default one from the system configuration file. Affects all users and clients.",'cftp_admin'); ?></p>
        </label>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('Downloads','cftp_admin'); ?></h3>
<div class="form-group">
    <label for="download_method" class="col-sm-4 control-label"><?php _e('Download method','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <select class="form-control" name="download_method" id="download_method" required>
            <option value="php" <?php echo (get_option('download_method') == 'php') ? 'selected="selected"' : ''; ?>>php</option>
            <option value="apache_xsendfile" <?php echo (get_option('download_method') == 'apache_xsendfile') ? 'selected="selected"' : ''; ?>>XSendFile (apache)</option>
            <option value="nginx_xaccel" <?php echo (get_option('download_method') == 'nginx_xaccel') ? 'selected="selected"' : ''; ?>>X-Accel (nginx)</option>
        </select>
        <div class="method_note none" data-method="php">
            <p class="field_note"><?php _e("Serving files with php is the default method and does not require any changes to your webserver. However, very large files could download with errors depending on your php configuration.",'cftp_admin'); ?></p>
        </div>
        <div class="method_note none" data-method="apache_xsendfile">
            <p class="field_note"><?php _e("XSendfile improves downloads by allowing the web server to send the file directly bypassing php and it's limitations. This in an advanced feature that requires you to install and enable a module on your server.",'cftp_admin'); ?></p>
            <p class="field_note"><?php _e("Be aware that if the module is not set up correctly, downloads will trigger but the files will have a length of 0 bytes.",'cftp_admin'); ?></p>
        </div>
        <div class="method_note none" data-method="nginx_xaccel">
            <p class="field_note"><?php _e("X-Accel is a method available in nginx that allows the system to serve files directly, bypassing php and it's limitations. To configure it, you need to edit your server block and add the following code:",'cftp_admin'); ?></p>
            <pre>location <?php echo XACCEL_FILES_URL; ?> {
    internal;
    alias <?php echo UPLOADED_FILES_ROOT; ?>/;
}</pre>
        </div>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('System location','cftp_admin'); ?></h3>
<p class="text-warning"><?php _e('These options are to be changed only if you are moving the system to another place. Changes here can cause ProjectSend to stop working.','cftp_admin'); ?></p>

<div class="form-group">
    <label for="base_uri" class="col-sm-4 control-label"><?php _e('System URI','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" class="form-control" name="base_uri" id="base_uri" value="<?php echo BASE_URI; ?>" required />
    </div>
</div>
