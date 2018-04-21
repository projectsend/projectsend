<?php
/**
 * Shows the current company logo and a form to upload
 * a new one.
 * This image is used on the files list templates later.
 *
 * @package ProjectSend
 * @subpackage Upload
 */
$allowed_levels = array(9);
require_once('sys.includes.php');

$page_title = __('Branding','cftp_admin');

$active_nav = 'options';
$cc_active_page = 'Branding';
include('header.php');

$logo_file_info = generate_logo_url();
$favico_file_info = generate_favico_url();
?>
<div id="main">
<div id="content"> 
    <!-- Added by B) -------------------->
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-12">
          <h1 class="page-title txt-color-blueDark"><i class="fa-fw fa fa-flag"></i>&nbsp;<?php echo $page_title; ?></h1>
	<?php
	if ($_POST) {
		/** Valid file extensions (images) */
		$image_file_types = "/^\.(jpg|jpeg|gif|png|ico){1}$/i";
		if (is_uploaded_file($_FILES['select_logo']['tmp_name']) || is_uploaded_file($_FILES['select_favico']['tmp_name']))  {
			$this_upload = new PSend_Upload_File();
			$safe_filename = $this_upload->safe_rename($_FILES['select_logo']['name']);
			$safe_filename_fav = $this_upload->safe_rename($_FILES['select_favico']['name']);
			/**
			* Check the file type for allowed extensions.
			*
			* @todo Use the file upload class file type validation function.
			*/
			if (preg_match($image_file_types, strrchr($safe_filename, '.')) || preg_match($image_file_types, strrchr($safe_filename_fav, '.'))) 
			{
				/**
				 * Move the file to the destination defined on sys.vars.php. If ok, add the
				 * new file name to the database.
				 */
				 if (move_uploaded_file($_FILES['select_logo']['tmp_name'],LOGO_FOLDER.$safe_filename)) 
				 {
					$sql = $dbh->prepare( "UPDATE " . TABLE_OPTIONS . " SET value=:value WHERE name='logo_filename'" );
					$sql->execute(array(':value'	=> $safe_filename));
					$status = '1';
					/** Record the action log */
					$new_log_action = new LogActions();
					$log_action_args = array(
											'action' => 29,
											'owner_id' => $global_id
										);
					$new_record_action = $new_log_action->log_action_save($log_action_args);
				}
				if (move_uploaded_file($_FILES['select_favico']['tmp_name'],LOGO_FOLDER.$safe_filename_fav))
				{
					$sql = $dbh->prepare( "UPDATE " . TABLE_OPTIONS . " SET value=:value WHERE name='favicon_filename'" );
					$sql->execute(array(':value'	=> $safe_filename_fav));
					$status = '1';			
				}
				else {
					//$status = '2';
				}
			}
			else 
			{
				$status = '3';
			}
		}
		else 
		{
			$status = '4';
		}
	/** Redirect so the options are reflected immediatly */
	while (ob_get_level()) ob_end_clean();
	$location = BASE_URI . 'branding.php?status=' . $status;
	header("Location: $location");
	die();
}
?>

	<script type="text/javascript">
		$(document).ready(function() {
			$("form").submit(function() {
				clean_form(this);

				is_complete(this.select_logo,'<?php _e('Please select an image file to upload','cftp_admin'); ?>');

				// show the errors or continue if everything is ok
				if (show_form_errors() == false) { return false; }
			});		
			$(".b_name_change").click(function(e) {
			//console.log($("#b_name").val());
			 e.preventDefault();
			  $.ajax({
            url: "change_branding_name.php",
            type: "POST",
            async: true, 
            data: { bname:$("#b_name").val() }, //your form data to post goes here as a json object
            dataType: "json",
            success: function(data) {
                if(data == 1) {
					$("#brand_status").html('<div class="alert alert-success fade in" style="margin-top: 15px;"><strong>Success!</strong> brand name successfully updated.</div>');
					location.reload();
				}
				else {
					$("#brand_status").html('<div class="alert alert-danger fade in" style="margin-top: 15px;"><strong>Opps!</strong> something went wrong!.</div>');
				}
            },  
        	});
		});
		});

	</script>

	
		<div class="container">
        <div class="row">
        <div class="col-xs-12 col-xs-offset-0 col-sm-8 col-sm-offset-2 col-md-6 col-md-offset-3 white-box">
        <div id="brand_status" ></div>
        <div class="white-box-interior" style="display: flex;">
        <div class="col-md-4">
                    <label for="b_name">Brand Name :</label>
                    </div>
        <div class="col-xs-8">
                      <form class="form-inline" method="post">
                        <div class="form-group">
                          
                          <input type="text" value="<?php echo BRAND_NAME; ?>" class="form-control" name="b_name" id="b_name" placeholder="Brand Name">
                        </div>
    				<button type="submit" class="btn btn-default b_name_change">Change Name</button>
  					</form>
                    </div>
        </div>
        </div>
        </div>
			<div class="row">
				<div class="col-xs-12 col-xs-offset-0 col-sm-8 col-sm-offset-2 col-md-6 col-md-offset-3 white-box">
					<div class="white-box-interior">
<form action="branding.php" name="logoupload" method="post" enctype="multipart/form-data">
						<?php
							if (isset($_GET['status'])) {
								switch ($_GET['status']) {
									case '1':
										$msg = __('The image was uploaded correctly.','cftp_admin');
										echo system_message('ok',$msg);
										break;
									case '2':
										$msg = __('The file could not be moved to the corresponding folder.','cftp_admin');
										$msg .= __("This is most likely a permissions issue. If that's the case, it can be corrected via FTP by setting the chmod value of the",'cftp_admin');
										$msg .= ' '.LOGO_FOLDER.' ';
										$msg .= __('directory to 755, or 777 as a last resource.','cftp_admin');
										$msg .= __("If this doesn't solve the issue, try giving the same values to the directories above that one until it works.",'cftp_admin');
										echo system_message('error',$msg);
										break;
									case '3':
										$msg = __('The file you selected is not an allowed image format. Please upload your logo as a jpg, gif or png file.','cftp_admin');
										echo system_message('error',$msg);
										break;
									case '4':
										$msg = __('There was an error uploading the file. Please try again.','cftp_admin');
										echo system_message('error',$msg);
										break;
								}
							}
					
						?>
			
						<p><?php _e('Use this page to upload your company logo, or update the currently assigned one. This image will be shown to your clients when they access their file list.','cftp_admin'); ?></p>
					
						<div id="current_logo">
							<div id="current_logo_left">
								<p><strong><?php _e('Current logo','cftp_admin'); ?></strong></p>
								<p class="logo_note"><?php _e("The picture on the right is not an actual representation of what they will see. The size on this preview is fixed, but remember that you can change the display size and picture quality for your client's pages on the",'cftp_admin'); ?> <a href="options.php"><?php _e("options",'cftp_admin'); ?></a> <?php _e("section.",'cftp_admin'); ?></p>
							</div>
							<div id="current_logo_right">
								<div id="current_logo_img">
									<?php
										if ($logo_file_info['exists'] === true) {
											
									?>
											<img src="<?php echo $logo_file_info['url']; ?>" alt="<?php _e('Logo Placeholder','cftp_admin'); ?>" class="img-responsive"/>
									<?php
										}
									?>
								</div>
							</div>
						</div>
				
						<div id="form_upload_logo">
							<input type="hidden" name="MAX_FILE_SIZE" value="1000000000">
							<div class="form-group">
								<label class="col-sm-4 control-label"><?php _e('Select image to upload','cftp_admin'); ?></label>
								<div class="col-sm-8">
									<input type="file" name="select_logo" />
								</div>
							</div>
						</div>
						<!-- To add favicon for the site -->						
						<div id="site_favico">
							<input type="hidden" name="MAX_FILE_SIZE" value="1000000000">
							<label class="col-sm-4 control-label"><?php _e('Select favicon to upload','cftp_admin'); ?></label>
							<div class="col-sm-8">
								<input type="file" name="select_favico" />
							</div>
							<div id="current_logo_img">
							<?php 
							if ($favico_file_info['exists'] === true) { 
							$ext = pathinfo($favico_file_info['url'], PATHINFO_EXTENSION); 
							if($ext=='ico')
							{
							?> 
							<img class="favico_img_src" src="<?php echo $favico_file_info['url']; ?>" alt="<?php _e('Logo Placeholder','cftp_admin'); ?>" />	
							<?php 
							} else {
							?>
							<img src="<?php echo $favico_file_info['url']; ?>" alt="<?php _e('Logo Placeholder','cftp_admin'); ?>" />	
							<?php } } ?>
								</div>
						</div>						
						<!-- To add favicon for the site ends -->
						<div class="after_form_buttons txt-rgt">
							<button type="submit" name="submit" class="btn btn-wide btn-primary empty"><?php _e('Upload','cftp_admin'); ?></button>
						</div>
					</div>
				</div>
			</div>

			

			

		</div>
	</form>

</div></div></div></div></div>

<?php include('footer.php'); ?>