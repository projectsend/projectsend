<?php
require_once('sys.includes.php');
include('header-unlogged.php');
?>
	<script type="text/javascript" src="<?php echo BASE_URI; ?>includes/js/jquery.1.12.4.min.js"></script>
<?php
$page_title = __('Guest Drop-off','cftp_admin');
/*$form_action="dropoff_guest_action.php"; */
	function generate_random_string($length = 30) {
	    $characters = '0123456789!@#$%^&*()_+abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	    $charactersLength = strlen($characters);
	    $randomString = '';
	    for ($i = 0; $i < $length; $i++) {
	        $randomString .= $characters[rand(0, $charactersLength - 1)];
	    }
	    return $randomString;
	}

	if($_POST){
		$randomString = generateRandomString(30);
		$your_name = $_POST['your_name'];
		$your_organization = $_POST['your_organization'];
		$your_email = $_POST['your_email'];
		if($your_name=='' ||  $your_organization=='' || $your_email=='') {
			$message = "Please Fill all the fields";
		}
		if($your_name && $your_organization && $your_email){
			$email_blocked= 0;
					$blocked_mails = $dbh->prepare("SELECT mail from tbl_blacklist");
					$blocked_mails->execute();
					if ( $blocked_mails->rowCount() > 0) {
						$blocked_mails->setFetchMode(PDO::FETCH_ASSOC);
						while( $blacklisted = $blocked_mails->fetch() ) {
							if($your_email == $blacklisted['mail'])
							{
								$email_blocked++;
							}
						}
				}
				if(	$email_blocked>0){
				$message = "Balcklisted email please contact System administrator";
				}
				else if (!filter_var($your_email, FILTER_VALIDATE_EMAIL)) {
		 			$message = "Invalid email format";
		 	 }
			else{
				$sql1 = $dbh->prepare("INSERT INTO `".TABLE_DROPOFF."` (`to_name`,`from_organization`, `to_email`, `requested_time`, `auth_key`, `status`) VALUES ('".$your_name."', '".$your_organization."', '".$your_email."', '".date("Y-m-d H:i:s")."', '".$randomString."', '0')");				
				if($sql1->execute()) {
					$e_notify = new PSend_Email();
					$e_arg = array( 
						'type'	=> 'dropoff_guest_request',
						'name'	=> $your_name,
						'your_organization'	=> $your_organization,
						'your_email'	=> $your_email,
						'token'     =>$randomString,
					);
					$notify_send = $e_notify->psend_send_drop_off_email($e_arg);	
					if($notify_send) {
						$cc_status = "<div class=\"alert alert-success cc-success\"><strong>Success!</strong>Your Request has been submitted successfully.</div>";
						$your_name='';
						$your_organization='';
						$your_email='';
					}
					else {
						$cc_status = "<div class=\"alert alert-danger cc-failed\"><strong>Oops! </strong>Something went wrong! please try after sometime.</div>";
					}
					echo "<script>$(document).ready(function(){ $('#cc-mail-status').modal('toggle');});</script>";
				}
			}
		}
	}
?>
<!----------------------------------------------------------------------------------------->
<script src='https://www.google.com/recaptcha/api.js'></script>

<!-- Modal -->
<div id="cc-mail-status" class="modal fade" role="dialog">
  <div class="modal-dialog">

    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">MicroHealth Send</h4>
      </div>
      <div class="modal-body">
		<?php echo isset($cc_status)? $cc_status : ''; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>

  </div>
</div>

<div id="main">
<div id="content" class="container">
  <div class="row">
  <h1 class="login-header-big"><?php echo $page_title; ?></h1>
<div style="border: 1px solid #adadad;padding:15px" class="col-md-6 col-md-offset-3">
  <form action="<?php $_SERVER['PHP_SELF']; ?>" name="addclient" class="addclient" method="post">
  <div class="form-group">
	<?php if(!empty($message)){?>
	<div class="message message_error" style="color:red">
        <p>
          <?php if(!empty($message)){echo $message;} ?>
        </p>
      </div>
	<?php }?>
    </div>
        <div class="form-group">
      <label for="your_name"><?php _e('Your name','cftp_admin'); ?></label>
      <input type="text" class="form-control required" name="your_name" id="your_name" value="<?php if(!empty($your_name)){echo $your_name;}?>" placeholder="Your name">
    </div>
	<div class="form-group">
		<label for="from_organization"><?php _e('Your organization','cftp_admin'); ?></label>
			<input type="text" name="your_organization" id="your_organization" class="form-control required" value="<?php if(!empty($your_organization)){echo $your_organization;}?>" placeholder="Your Organization name" />
	</div>

	<div class="form-group">
			
		<label for="to_email_request"><?php _e('Your email','cftp_admin'); ?></label>
			<input type="text" name="your_email" id="your_email" value="<?php if(!empty($your_email)){echo $your_email;}?>" class="form-control required"  placeholder="<?php _e("Must be valid email Id",'cftp_admin'); ?>" />
	</div>
	<!--<div class="form-group">
		<label class="col-sm-4 control-label"><?php _e('Verification','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<div class="g-recaptcha" data-sitekey="6LdB4RgUAAAAAAmmJtUMk020BC7OYP9WjEln-5xP"></div>
		</div>
	</div>-->

	<div class="inside_form_buttons text-rgt">
		<button type="submit" name="submit" class="btn btn-default">Send the request</button>
	</div>

</form>
</div>	
</div>
</div>
</div>

		<script src="<?php echo BASE_URI; ?>assets/bootstrap/js/bootstrap.min.js"></script>
			<script src="<?php echo BASE_URI; ?>includes/js/jquery.validations.js"></script>
			<script src="<?php echo BASE_URI; ?>includes/js/jquery.psendmodal.js"></script>
