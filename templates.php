<?php
use \Tamtamchik\SimpleFlash\Flash;

/**
 * List of available client's templates
 *
 * @package ProjectSend
 * @subpackage Design
 */
$allowed_levels = array(9);
require_once 'bootstrap.php';

$page_title	= __("Templates",'cftp_admin');

$active_nav = 'templates';
include_once ADMIN_VIEWS_DIR . DS . 'header.php';

/**
 * Changing the client's template
 */
if ( isset($_GET['activate_template']) ) {
    $save = save_option('selected_clients_template', $_GET['activate_template']);

    if ($save) {
        Flash::success(__('Options updated successfully.', 'cftp_admin'));
    } else {
        Flash::error(__('There was an error. Please try again.', 'cftp_admin'));
    }

    /** Redirect so the options are reflected immediately */
    while (ob_get_level()) ob_end_clean();
    $section_redirect = 'templates';
    $location = BASE_URI . 'templates.php';

    header("Location: $location");
    exit;
}
?>
<div class="row">
    <div class="col-xs-12 col-sm-12 col-lg-12">
        <div class="template_selector">
            <div class="row">
                <?php
                    $templates = look_for_templates();
                    foreach ($templates as $template) {
                ?>
                    <div class="col-xs-12 col-sm-6 col-md-3">
                        <div class="template <?php if ( $template['location'] == get_option('selected_clients_template') ) { echo 'current_template'; } ?>">
                            <div class="col-xs-12">
                                <div class="images">
                                    <?php
                                        if ( !empty( $template['cover'] ) ) {
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
                            <div class="col-xs-12">
                                <h4>
                                    <?php echo $template['name']; ?>
                                </h4>
                            </div>
                            <div class="col-xs-8">
                                <div class="info">
                                    <div class="description">
                                        <?php echo $template['description']; ?>
                                    </div>
                                    
                                    <h5><?php _e('Author','cftp_admin'); ?></h5>
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
                                        if ( $template['location'] == get_option('selected_clients_template') ) {
                                    ?>
                                            <a href="#" class="btn btn-default disabled">
                                                <?php _e('Active','cftp_admin'); ?>
                                            </a>
                                    <?php
                                        }
                                        else {
                                    ?>
                                            <a href="templates.php?activate_template=<?php echo $template['location']; ?>" class="btn btn-primary">
                                                <?php _e('Activate','cftp_admin'); ?>
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
</div>
<?php
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php';