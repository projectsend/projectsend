<?php
/**
 * Uploading files from computer, step 1
 * Shows the plupload form that handles the uploads and moves
 * them to a temporary folder. When the queue is empty, the user
 * is redirected to step 2, and prompted to enter the name,
 * description and client for each uploaded file.
 *
 * @package ProjectSend
 * @subpackage Upload
 */
$load_scripts	= array(
						'plupload',
					);

require_once('sys.includes.php');

$active_nav = 'files';
$page_title = __('Access Denied', 'cftp_admin');

$allowed_levels = array(9,8,7);

if (CLIENTS_CAN_UPLOAD == 1) {
	$allowed_levels[] = 0;
}
include('header.php');
/**
 * Get the user level to determine if the uploader is a
 * system user or a client.
 */
$current_level = get_current_user_level();
?>

<div id="main">
<div id="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-md-12">
        <h2><i class="fa fa-times-circle " aria-hidden="true"></i>&nbsp;<?php echo $page_title; ?></h2>

        <p>
          <?php
				$msg ="You don't have permission to download this file";
				echo system_message('error', $msg);
			?>
        </p>


      </div>
    </div>
  </div>
</div>
</div>
<?php
	include('footer.php');
?>
