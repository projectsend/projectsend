<?php
use ProjectSend\Classes\Session;

define('IS_ERROR_PAGE', true);

$allowed_levels = array(9, 8, 7, 0);
require_once 'bootstrap.php';

$error_type = (!empty($_GET['e'])) ? $_GET['e'] : '401';

switch ($error_type) {
    default:
    case '401':
        http_response_code(401);
        $page_title = __('Access denied', 'cftp_admin');
        $error_message = __("Your account type doesn't allow you to view this page. Please contact a system administrator if you need to access this function.", 'cftp_admin');
        break;
    case 'csrf':
        http_response_code(403);
        $page_title = __('Token mismatch', 'cftp_admin');
        $error_message = __("The security token could not be validated.", 'cftp_admin');
        break;
    case '404':
        http_response_code(404);
        $page_title = __('Error 404', 'cftp_admin');
        $error_message = __("Resource not found.", 'cftp_admin');
        break;
    case '403':
        http_response_code(403);
        $page_title = __('Error 403', 'cftp_admin');
        $error_message = __("Forbidden.", 'cftp_admin');
        break;
    case 'database':
        http_response_code(403);
        $page_title = __('Database error', 'cftp_admin');
        $error_message = (Session::has('database_connection_error')) ? Session::get('database_connection_error') : __("Can not connect to database", 'cftp_admin');
        Session::remove('database_connection_error');
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

<body class="backend forbidden">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h2><?php echo $page_title; ?></h2>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="whiteform whitebox">
                    <?php echo $error_message; ?>
                </div>
            </div>
        </div>
    </div>
    <?php render_custom_assets('body_bottom'); ?>
</body>

</html>
<?php
exit;
