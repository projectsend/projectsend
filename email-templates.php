<?php
/**
 * Allows the administrator to customize the emails
 * sent by the system.
 */
require_once 'bootstrap.php';
check_access_enhanced(['edit_email_templates']);

$section = (!empty($_GET['section'])) ? $_GET['section'] : $_POST['section'];

$emails = new \ProjectSend\Classes\Emails;
if (!$emails->emailTypeExists($section)) {
    ps_redirect(BASE_URI . 'email-templates.php?section=header_footer');
}

$section_options = $emails->getDataForOptions($section);
$page_title = $section_options['title'];

$page_id = 'email_templates';
add_codemirror_assets();

$active_nav = 'emails';
include_once ADMIN_VIEWS_DIR . DS . 'header.php';

if ($_POST) {
    foreach ($section_options['checkboxes'] as $checkbox) {
        $_POST[$checkbox] = (empty($_POST[$checkbox]) || !isset($_POST[$checkbox])) ? 0 : 1;
    }

    $keys = array_keys($_POST);
    for ($j = 0; $j < count($keys); $j++) {
        $save = save_option($keys[$j], $_POST[$keys[$j]]);
    }

    /** Record the action log */
    $logger = new \ProjectSend\Classes\ActionsLog;
    $new_record_action = $logger->addEntry([
        'action' => 48,
        'owner_id' => CURRENT_USER_ID,
        'owner_user' => CURRENT_USER_USERNAME,
        'details' => [
            'section' => html_output($_POST['section']),
        ],
    ]);

    if ($save) {
        $flash->success(__('Options updated successfully.', 'cftp_admin'));
    } else {
        $flash->error(__('There was an error. Please try again.', 'cftp_admin'));
    }

    $location = BASE_URI . 'email-templates.php?section=' . html_output($_POST['section']);
    ps_redirect($location);
}
?>
<div class="row">
    <div class="col-12 col-sm-12 <?php echo ($section == 'template_selection') ? '' : 'col-lg-8'; ?>">
        <div class="ps-card">
            <div class="ps-card-body">
                <form action="email-templates.php" id="form_email_template" name="templatesform" method="post" enctype="multipart/form-data" class="form-horizontal">
                    <?php addCsrf(); ?>
                    <input type="hidden" name="section" value="<?php echo $section; ?>">

                    <?php
                    /** Template selection options */
                    if ($section == 'template_selection') {
                    ?>
                        <p><?php _e('Choose from professionally designed email templates. These themes provide different styles and layouts for your email communications.', 'cftp_admin'); ?></p>
                        <p><?php _e('Preview each themes to see how your emails will look.', 'cftp_admin'); ?></p>
                        <p class="text-warning"><?php _e('Themes can be applied directly from this page and will affect the general look and header/footer styling, but not the individual email content.', 'cftp_admin'); ?></p>

                        <div class="options_divide"></div>

                        <!-- Template Gallery -->
                        <div id="template-gallery" class="template-gallery">
                            <?php
                            $available_templates = $emails->getAvailableTemplates();
                            foreach ($available_templates as $template_id => $template_data) {
                            ?>
                                <div class="template-card" data-template-id="<?php echo $template_id; ?>">
                                    <div class="template-preview">
                                        <?php
                                        $screenshot_path = ROOT_DIR . '/emails/templates/' . $template_id . '/screenshot.png';
                                        $screenshot_url = BASE_URI . 'emails/templates/' . $template_id . '/screenshot.png';

                                        if (file_exists($screenshot_path)) { ?>
                                            <img src="<?php echo $screenshot_url; ?>" alt="<?php echo htmlspecialchars($template_data['name']); ?> Preview" style="width: 100%; height: 100%; object-fit: cover; object-position: top;">
                                        <?php } else { ?>
                                            <div class="template-preview-placeholder">
                                                <i class="fa fa-envelope-o"></i>
                                                <span><?php echo $template_data['name']; ?></span>
                                            </div>
                                        <?php } ?>
                                    </div>
                                    <div class="template-info">
                                        <h4 class="template-name"><?php echo $template_data['name']; ?></h4>
                                        <p class="template-description"><?php echo $template_data['description']; ?></p>
                                        <div class="template-features">
                                            <?php foreach ($template_data['features'] as $feature): ?>
                                                <span class="feature-badge"><?php echo $feature; ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="template-actions">
                                            <button type="button" class="btn btn-primary btn-template-preview" data-template-id="<?php echo $template_id; ?>">
                                                <i class="fa fa-eye"></i>
                                                <?php _e('Preview', 'cftp_admin'); ?>
                                            </button>
                                            <button type="button" class="btn btn-success btn-template-apply" data-template-id="<?php echo $template_id; ?>" data-template-name="<?php echo htmlspecialchars($template_data['name']); ?>">
                                                <i class="fa fa-download"></i>
                                                <?php _e('Apply', 'cftp_admin'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php
                            }
                            ?>
                        </div>
                    <?php
                    }

                    /** Header and footer options */
                    if ($section == 'header_footer') {
                    ?>
                        <p class="text-warning"><?php _e('Here you set up the header and footer of every email, or use the default ones available with the system. Use this to customize each part and include, for example, your own logo and markup.', 'cftp_admin'); ?></p>
                        <p class="text-warning"><?php _e("Do not forget to also include -and close accordingly- the basic structural HTML tags (DOCTYPE, HTML, HEADER, BODY).", 'cftp_admin'); ?></p>

                        <div class="options_divide"></div>

                        <div class="form-group row">
                            <div class="col-sm-12">
                                <label for="email_header_footer_customize">
                                    <input type="checkbox" value="1" id="email_header_footer_customize" name="email_header_footer_customize" <?php echo (get_option('email_header_footer_customize') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Use custom header / footer', 'cftp_admin'); ?>
                                </label>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-sm-12">
                                <label for="email_header_text"><?php _e('Header', 'cftp_admin'); ?></label>
                                <textarea name="email_header_text" id="email_header_text" class="form-control textarea_high"><?php echo get_option('email_header_text'); ?></textarea>
                                <p class="field_note form-text"><?php _e('You can use HTML tags here.', 'cftp_admin'); ?></p>
                            </div>
                        </div>

<?php /*                         <div class="preview_button">
                            <button type="button" class="btn btn-pslight load_default" data-textarea="email_header_text" data-file="<?php echo EMAIL_TEMPLATE_HEADER; ?>"><?php _e('Replace with default', 'cftp_admin'); ?></button>
                        </div>
 */ ?>
                        <hr />

                        <div class="form-group row">
                            <div class="col-sm-12">
                                <label for="email_footer_text"><?php _e('Footer', 'cftp_admin'); ?></label>
                                <textarea name="email_footer_text" id="email_footer_text" class="form-control textarea_high"><?php echo get_option('email_footer_text'); ?></textarea>
                                <p class="field_note form-text"><?php _e('You can use HTML tags here.', 'cftp_admin'); ?></p>
                            </div>
                        </div>

<?php /*                         <div class="preview_button">
                            <button type="button" class="btn btn-pslight load_default" data-textarea="email_footer_text" data-file="<?php echo EMAIL_TEMPLATE_FOOTER; ?>"><?php _e('Replace with default', 'cftp_admin'); ?></button>
                        </div>
 */ ?>                    <?php
                    }

                    // All other templates
                    $group = $section_options['items'];
                    if (!empty($group)) {
                    ?>
                        <p class="text-warning"><?php echo $group['description']; ?></p>

                        <div class="options_divide"></div>

                        <div class="form-group row">
                            <div class="col-sm-12">
                                <label for="<?php echo $group['subject_checkbox']; ?>">
                                    <input type="checkbox" value="1" name="<?php echo $group['subject_checkbox']; ?>" id="<?php echo $group['subject_checkbox']; ?>" class="checkbox_options" <?php echo ($group['subject_check'] == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Use custom subject', 'cftp_admin'); ?>
                                </label>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-sm-12">
                                <input type="text" name="<?php echo $group['subject']; ?>" id="<?php echo $group['subject']; ?>" class="form-control" placeholder="<?php _e('Add your custom subject', 'cftp_admin'); ?>" value="<?php echo $group['subject_text']; ?>" />
                            </div>
                        </div>

                        <div class="options_divide"></div>

                        <div class="form-group row">
                            <div class="col-sm-12">
                                <label for="<?php echo $group['body_checkbox']; ?>">
                                    <input type="checkbox" value="1" name="<?php echo $group['body_checkbox']; ?>" id="<?php echo $group['body_checkbox']; ?>" class="checkbox_options" <?php echo ($group['body_check'] == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Use custom template', 'cftp_admin'); ?>
                                </label>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-sm-12">
                                <label for="<?php echo $group['body_textarea']; ?>"><?php _e('Template text', 'cftp_admin'); ?></label>
                                <textarea name="<?php echo $group['body_textarea']; ?>" id="<?php echo $group['body_textarea']; ?>" class="form-control textarea_high textarea_tags"><?php echo $group['body_text']; ?></textarea>
                                <p class="field_note form-text"><?php _e('You can use HTML tags here.', 'cftp_admin'); ?></p>
                            </div>
                        </div>

                        <?php
                        if (!empty($group['tags'])) {
                        ?>
                            <p><strong><?php _e("The following tags can be used on this e-mails' body.", 'cftp_admin'); ?></strong></p>
                            <dl class="row" id="email_available_tags">
                                <?php
                                foreach ($group['tags'] as $tag => $description) {
                                ?>
                                    <dt class="col-6 text-end"><button type="button" class="btn btn-sm btn-pslight insert_tag" data-tag="<?php echo $tag; ?>" data-target="<?php echo $group['body_textarea']; ?>"><?php echo $tag; ?></button></dt>
                                    <dd class="col-6"><?php echo $description; ?></dd>
                                <?php
                                }
                                ?>
                            </dl>
                        <?php
                        }
                        ?>

                        <hr />
                        <div class="preview_button">
                            <button type="button" class="btn btn-pslight load_default" data-textarea="<?php echo $group['body_textarea']; ?>" data-file="<?php echo $group['default_text']; ?>"><?php _e('Replace with default', 'cftp_admin'); ?></button>
                            <button type="button" data-preview="<?php echo $section; ?>" class="btn btn-wide btn-primary preview"><?php _e('Preview this template', 'cftp_admin'); ?></button>
                            <?php
                            $message = __("Before trying this function, please save your changes to see them reflected on the preview.", 'cftp_admin');
                            echo system_message('info', $message);
                            ?>
                        </div>
                    <?php
                    }
                    ?>

                    <?php if ($section !== 'template_selection') { ?>
                    <div class="after_form_buttons">
                        <button type="submit" name="submit" class="btn btn-wide btn-primary empty"><?php _e('Save options', 'cftp_admin'); ?></button>
                    </div>
                    <?php } ?>
                </form>
            </div>
        </div>
    </div>

    <?php if ($section == 'header_footer') { ?>
    <div class="col-12 col-sm-12 col-lg-4">
        <div class="ps-card">
            <div class="ps-card-body template-suggestion-box">
                <h3><i class="fa fa-lightbulb-o text-warning"></i> <?php _e('Email Templates', 'cftp_admin'); ?></h3>
                <p><?php _e('Looking for a quick start? Choose from our professionally designed email themes that include pre-styled headers and footers.', 'cftp_admin'); ?></p>
                <p><?php _e('Themes provide consistent branding and professional layouts that you can apply instantly.', 'cftp_admin'); ?></p>

                <div class="text-center mt-4">
                    <a href="<?php echo BASE_URI; ?>email-templates.php?section=template_selection" class="btn btn-primary btn-wide">
                        <i class="fa fa-th-large"></i>
                        <?php _e('Browse Themes', 'cftp_admin'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>
</div>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
