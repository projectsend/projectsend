<?php
use ProjectSend\Classes\Session;

define('IS_ERROR_PAGE', true);

require_once 'bootstrap.php';

$error_type = (!empty($_GET['e'])) ? $_GET['e'] : '401';

switch ($error_type) {
    default:
    case '401':
        http_response_code(401);
        $error_icon = 'fa-lock';
        $page_title = __('Access denied', 'cftp_admin');
        $error_subtitle = __('You are not authorized to view this page.', 'cftp_admin');
        $error_message = __("Your account type doesn't allow you to view this page. Please contact a system administrator if you need to access this function.", 'cftp_admin');
        break;
    case 'csrf':
        http_response_code(403);
        $error_icon = 'fa-shield';
        $page_title = __('Token mismatch', 'cftp_admin');
        $error_subtitle = __('The security token could not be validated.', 'cftp_admin');
        $error_message = '';
        break;
    case '400':
        http_response_code(400);
        $error_icon = 'fa-exclamation-circle';
        $page_title = __('Bad request', 'cftp_admin');
        $error_subtitle = __('The request could not be understood or was missing required parameters.', 'cftp_admin');
        $error_message = '';
        break;
    case '403':
        http_response_code(403);
        $error_icon = 'fa-ban';
        $page_title = __('Forbidden', 'cftp_admin');
        $error_subtitle = __("You don't have access to this resource.", 'cftp_admin');
        $error_message = '';
        break;
    case '404':
        http_response_code(404);
        $error_icon = 'fa-search';
        $page_title = __('Not found', 'cftp_admin');
        $error_subtitle = __('The content you requested could not be found.', 'cftp_admin');
        $error_message = '';
        break;
    case '410':
        http_response_code(410);
        $error_icon = 'fa-times-circle';
        $page_title = __('Gone', 'cftp_admin');
        $error_subtitle = __('This resource is no longer available.', 'cftp_admin');
        $error_message = '';
        break;
    case '500':
        http_response_code(500);
        $error_icon = 'fa-wrench';
        $page_title = __('Server error', 'cftp_admin');
        $error_subtitle = __('An unexpected error occurred. Please try again later or contact your administrator.', 'cftp_admin');
        $error_message = '';
        break;
    case 'database':
        http_response_code(500);
        $error_icon = 'fa-database';
        $page_title = __('Database error', 'cftp_admin');
        $error_subtitle = __('A database connection error occurred.', 'cftp_admin');
        $error_message = '';
        Session::remove('database_connection_error');
        break;
    case 'requirements':
        http_response_code(500);
        $error_icon = 'fa-cogs';
        $page_title = __('Requirements error', 'cftp_admin');
        $error_subtitle = __('The server does not meet the minimum requirements.', 'cftp_admin');
        $error_message = '';
        $errors = get_server_requirements_errors();
        foreach ($errors as $error) {
            $error_message .= html_output($error) . '<br>';
        }
        break;
}
?>
<!doctype html>
<html lang="<?php echo SITE_LANG; ?>">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php echo html_output($page_title . ' &raquo; ' . get_option('this_install_title')); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <?php meta_favicon(); ?>

    <?php
    render_assets('js', 'head');
    render_assets('css', 'head');

    render_custom_assets('head');
    ?>
</head>

<body class="backend error_page">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <i class="fa <?php echo $error_icon; ?> error-page-icon mt-4 mb-3"></i>
                <h2 class="mb-1"><?php echo html_output($page_title); ?></h2>
                <?php if (!empty($error_subtitle)) { ?>
                <p class="lead text-muted mb-4"><?php echo html_output($error_subtitle); ?></p>
                <?php } ?>
            </div>
        </div>
        <?php if (!empty($error_message)) { ?>
        <div class="row">
            <div class="col-12">
                <div class="white-box">
                    <div class="white-box-interior">
                        <?php echo $error_message; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>
        <div class="row mt-3">
            <div class="col-12">
                <a href="<?php echo BASE_URI; ?>" class="btn btn-secondary">&larr; <?php _e('Return to homepage', 'cftp_admin'); ?></a>
            </div>
        </div>
    </div>
    <?php render_custom_assets('body_bottom'); ?>
</body>

</html>
<?php
exit;
