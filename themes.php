<?php
/**
 * List of available client's templates
 */
require_once 'bootstrap.php';
check_access_enhanced(['change_template']);

$page_title = __("Themes", 'cftp_admin');

$active_nav = 'themes';
include_once ADMIN_VIEWS_DIR . DS . 'header.php';

$templates = look_for_templates();
$valid_templates = array_map(function($t) { return $t['location']; }, $templates);

/**
 * Changing the client's template
 */
if (isset($_GET['activate_template'])) {
    if (!in_array($_GET['activate_template'], $valid_templates)) {
        exit_with_error_code(403);
    }

    $save = save_option('selected_clients_template', $_GET['activate_template']);

    global $flash;
    if ($save) {
        $flash->success(__('Options updated successfully.', 'cftp_admin'));
    } else {
        $flash->error(__('There was an error. Please try again.', 'cftp_admin'));
    }

    /** Redirect so the options are reflected immediately */
    $section_redirect = 'themes';

    ps_redirect(BASE_URI . 'themes.php');
}
?>
<div class="row">
    <div class="col-12 col-sm-12 col-lg-8">
        <div class="template_selector">
            <div class="row">
                <?php
                    foreach ($templates as $template) {
                ?>
                    <div class="col-12 col-sm-6 col-md-4">
                        <div class="template <?php if ($template['location'] == get_option('selected_clients_template')) { echo 'current_template';} ?>">
                            <div class="col-12">
                                <div class="images">
                                    <?php
                                    if (!empty($template['cover'])) {
                                    ?>
                                        <div class="cover">
                                            <img src="<?php echo html_output($template['cover']); ?>" alt="<?php echo html_output($template['name']); ?>">
                                        </div>
                                    <?php
                                    }
                                    ?>
                                    <div class="screenshot">
                                        <img src="<?php echo html_output($template['screenshot']); ?>" alt="<?php echo html_output($template['name']); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <h4>
                                    <?php echo $template['name']; ?>
                                </h4>
                            </div>
                            <div class="col-xs-8">
                                <div class="info">
                                    <div class="description">
                                        <?php echo $template['description']; ?>
                                    </div>

                                    <!-- Features badges -->
                                    <?php if (isset($template['features'])): ?>
                                    <div class="template-features">
                                        <h5><?php _e('Features', 'cftp_admin'); ?></h5>
                                        <div class="feature-badges">
                                            <span class="feature-badge <?php echo $template['features']['client_files'] ? 'active' : 'inactive'; ?>" 
                                                  title="<?php echo $template['features']['client_files'] ? __('Client files view is available', 'cftp_admin') : __('Client files view is not implemented', 'cftp_admin'); ?>">
                                                <span class="icon"><?php echo $template['features']['client_files'] ? '✓' : '○'; ?></span>
                                                <?php _e('Client Files', 'cftp_admin'); ?>
                                            </span>
                                            
                                            <span class="feature-badge <?php echo $template['features']['public_files'] ? 'active' : 'inactive'; ?>"
                                                  title="<?php echo $template['features']['public_files'] ? __('Public files view is available', 'cftp_admin') : __('Public files view is not implemented', 'cftp_admin'); ?>">
                                                <span class="icon"><?php echo $template['features']['public_files'] ? '✓' : '○'; ?></span>
                                                <?php _e('Public Files', 'cftp_admin'); ?>
                                            </span>
                                            
                                            <span class="feature-badge <?php echo $template['features']['download_page'] ? 'active' : 'inactive'; ?>"
                                                  title="<?php echo $template['features']['download_page'] ? __('Custom download page is available', 'cftp_admin') : __('Custom download page is not implemented', 'cftp_admin'); ?>">
                                                <span class="icon"><?php echo $template['features']['download_page'] ? '✓' : '○'; ?></span>
                                                <?php _e('Download Page', 'cftp_admin'); ?>
                                            </span>

                                            <span class="feature-badge <?php echo $template['features']['has_settings'] ? 'active' : 'inactive'; ?>"
                                                  title="<?php echo $template['features']['has_settings'] ? __('Theme has configurable settings', 'cftp_admin') : __('Theme does not have configurable settings', 'cftp_admin'); ?>">
                                                <span class="icon"><?php echo $template['features']['has_settings'] ? '⚙' : '○'; ?></span>
                                                <?php _e('Customizable', 'cftp_admin'); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <h5><?php _e('Author', 'cftp_admin'); ?></h5>
                                    <p>
                                        <a href="<?php echo $template['authoruri']; ?>" target="_blank">
                                            <?php echo $template['author']; ?>
                                        </a><br>
                                        <?php echo $template['authoremail']; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="col-xs-4">
                                <div class="buttons">
                                    <?php
                                    if ($template['location'] == get_option('selected_clients_template')) {
                                    ?>
                                        <a href="#" class="btn btn-pslight disabled">
                                            <?php _e('Active', 'cftp_admin'); ?>
                                        </a>
                                        <?php if (!empty($template['features']['has_settings'])): ?>
                                        <a href="theme-settings.php?theme=<?php echo urlencode($template['location']); ?>" class="btn btn-secondary">
                                            <i class="fa fa-cog"></i> <?php _e('Settings', 'cftp_admin'); ?>
                                        </a>
                                        <?php endif; ?>
                                    <?php
                                    } else {
                                    ?>
                                        <a href="themes.php?activate_template=<?php echo $template['location']; ?>" class="btn btn-primary">
                                            <?php _e('Activate', 'cftp_admin'); ?>
                                        </a>
                                    <?php
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php
                }
                ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-12 col-lg-4">
        <div class="ps-card">
            <div class="ps-card-body template-suggestion-box">
                <h3><i class="fa fa-lightbulb-o text-warning"></i> <?php _e('Client Area Themes', 'cftp_admin'); ?></h3>
                <p><?php _e('Choose a theme that best represents your brand and provides the best experience for your clients.', 'cftp_admin'); ?></p>
                <p><?php _e('You can switch themes at any time without losing any data.', 'cftp_admin'); ?></p>
                <p><?php _e('Each theme offers different layouts and features:', 'cftp_admin'); ?></p>
                <ul class="list-unstyled">
                    <li><span class="feature-badge active"><span class="icon">✓</span> <?php _e('Client Files', 'cftp_admin'); ?></span> - <?php _e('Personal file browsing for logged-in users', 'cftp_admin'); ?></li>
                    <li><span class="feature-badge active"><span class="icon">✓</span> <?php _e('Public Files', 'cftp_admin'); ?></span> - <?php _e('Public file listings for all visitors', 'cftp_admin'); ?></li>
                    <li><span class="feature-badge active"><span class="icon">✓</span> <?php _e('Download Page', 'cftp_admin'); ?></span> - <?php _e('Custom download experience', 'cftp_admin'); ?></li>
                    <li><span class="feature-badge active"><span class="icon">⚙</span> <?php _e('Customizable', 'cftp_admin'); ?></span> - <?php _e('Configurable theme options and preferences', 'cftp_admin'); ?></li>
                </ul>
                <p class="text-warning"><small><i class="fa fa-info-circle"></i> <?php _e('Note: If a theme does not include a specific feature, the layout and style from the default theme will be used instead.', 'cftp_admin'); ?></small></p>
            </div>
        </div>
    </div>
</div>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
