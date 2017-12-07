<?php
require_once('sys.includes.php');
include('header-unlogged.php');
?>
	<script type="text/javascript" src="https://msend.microhealthllc.com/includes/js/jquery.1.12.4.min.js"></script>
<?php
$page_title = __('Guest Drop-off','cftp_admin');
//$form_action="dropoff_guest_action.php";

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
	//echo "<pre>";print_r($_POST);echo "</pre>";
	$your_name = $_POST['your_name'];
	$your_organization = $_POST['your_organization'];
	$your_email = $_POST['your_email'];
	if($your_name && $your_organization && $your_email){
		if (!filter_var($your_email, FILTER_VALIDATE_EMAIL)) {
			$message = "Invalid email format";
			
		}else{
			//Form Success
			/*$statement = $dbh->prepare("INSERT INTO tbl_drop_off_request (to_name,from_organization,to_email,requested_time,auth_key,status) VALUES ( :to_name, :from_organization, :to_email, :requested_time, :auth_key, :status )");
				
				$statement->bindValue(':to_name', $your_name);
				$statement->bindValue(':from_organization', $your_organization);
				$statement->bindValue(':to_email', $your_email);
				$statement->bindValue(':requested_time', date("Y-m-d H:i:s"));
				$statement->bindValue(':auth_key', $randomString);
				$statement->bindParam(':status', '0');*/
		$sql1 = $dbh->prepare("INSERT INTO `".TABLE_DROPOFF."` (`to_name`,`from_organization`, `to_email`, `requested_time`, `auth_key`, `status`) VALUES ('".$your_name."', '".$your_organization."', '".$your_email."', '".date("Y-m-d H:i:s")."', '".$randomString."', '0')");
		if($sql1->execute()) {
			//echo "inserted";
			$message1 ="<html>
	  <head>
		<meta name='viewport' content='width=device-width'>
		<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>
		<title>Simple Transactional Email</title>
		<style type='text/css'>
		/* -------------------------------------
			INLINED WITH https://putsmail.com/inliner
		------------------------------------- */
		/* -------------------------------------
			RESPONSIVE AND MOBILE FRIENDLY STYLES
		------------------------------------- */
		@media only screen and (max-width: 620px) {
		  table[class=body] h1 {
			font-size: 28px !important;
			margin-bottom: 10px !important; }
		  table[class=body] p,
		  table[class=body] ul,
		  table[class=body] ol,
		  table[class=body] td,
		  table[class=body] span,
		  table[class=body] a {
			font-size: 16px !important; }
		  table[class=body] .wrapper,
		  table[class=body] .article {
			padding: 10px !important; }
		  table[class=body] .content {
			padding: 0 !important; }
		  table[class=body] .container {
			padding: 0 !important;
			width: 100% !important; }
		  table[class=body] .main {
			border-left-width: 0 !important;
			border-radius: 0 !important;
			border-right-width: 0 !important; }
		  table[class=body] .btn table {
			width: 100% !important; }
		  table[class=body] .btn a {
			width: 100% !important; }
		  table[class=body] .img-responsive {
			height: auto !important;
			max-width: 100% !important;
			width: auto !important; }}
		/* -------------------------------------
			PRESERVE THESE STYLES IN THE HEAD
		------------------------------------- */
		@media all {
		  .ExternalClass {
			width: 100%; }
		  .ExternalClass,
		  .ExternalClass p,
		  .ExternalClass span,
		  .ExternalClass font,
		  .ExternalClass td,
		  .ExternalClass div {
			line-height: 100%; }
		  .apple-link a {
			color: inherit !important;
			font-family: inherit !important;
			font-size: inherit !important;
			font-weight: inherit !important;
			line-height: inherit !important;
			text-decoration: none !important; }
		  .btn-primary table td:hover {
			background-color: #34495e !important; }
		  .btn-primary a:hover {
			background-color: #34495e !important;
			border-color: #34495e !important; } }
		</style>
		<head>
			  <title>You are trying to drop-off some files</title>
		</head>
	  </head>
	  
	  <body class='' style='background-color:#f6f6f6;font-family:sans-serif;-webkit-font-smoothing:antialiased;font-size:14px;line-height:1.4;margin:0;padding:0;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;'>
		<table border='0' cellpadding='0' cellspacing='0' class='body' style='border-collapse:separate;mso-table-lspace:0pt;mso-table-rspace:0pt;background-color:#f6f6f6;width:100%;'>
		  <tr>
			<td style='font-family:sans-serif;font-size:14px;vertical-align:top;'>&nbsp;</td>
			<td class='container' style='font-family:sans-serif;font-size:14px;vertical-align:top;display:block;max-width:580px;padding:10px;width:580px;Margin:0 auto !important;'>
			  <div class='content' style='box-sizing:border-box;display:block;Margin:0 auto;max-width:580px;padding:10px;'>
				<!-- START CENTERED WHITE CONTAINER -->
				<span class='preheader' style='color:transparent;display:none;height:0;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;visibility:hidden;width:0;'>This is preheader text. Some clients will show this text as a preview.</span>
				<table class='main' style='border-collapse:separate;mso-table-lspace:0pt;mso-table-rspace:0pt;background:#fff;border-radius:3px;width:100%;'>
				  <!-- START MAIN CONTENT AREA -->
				  <tr>
					<td class='wrapper' style='font-family:sans-serif;font-size:14px;vertical-align:top;box-sizing:border-box;padding:20px;'>
					  <table border='0' cellpadding='0' cellspacing='0' style='border-collapse:separate;mso-table-lspace:0pt;mso-table-rspace:0pt;width:100%;'>
						<tr>
						  <td style='font-family:sans-serif;font-size:14px;vertical-align:top;'>
<p style='font-family:sans-serif;font-size:14px;font-weight:normal;margin:0;Margin-bottom:15px;'>Hi $your_name</p>
	  <p style='font-family:sans-serif;font-size:14px;font-weight:normal;margin:0;Margin-bottom:15px;'>This is an automated message sent to you by the MicroHealth Send Service</p>
							<p style='font-family:sans-serif;font-size:14px;font-weight:normal;margin:0;Margin-bottom:15px;'>Hi $your_name</p>
	  <p style='font-family:sans-serif;font-size:14px;font-weight:normal;margin:0;Margin-bottom:15px;'>You have asked us to send you this message so that you can drop-off some files for someone</p>
	
	  <p>
	Details:<br>
	Name : $your_name <br>
	Organization : $you_organization <br>
	Email : $your_email<br></p>

	  <p>IGNORE THIS MESSAGE IF YOU WERE NOT IMMEDIATELY EXPECTING IT!</p>
	  <p>Otherwise, continue the process by clicking here!</p>
	
							<table border='0' cellpadding='0' cellspacing='0' class='btn btn-primary' style='border-collapse:separate;mso-table-lspace:0pt;mso-table-rspace:0pt;box-sizing:border-box;width:100%;'>
							  <tbody>
								<tr>
								  <td align='left' style='font-family:sans-serif;font-size:14px;vertical-align:top;padding-bottom:15px;'>
									<table border='0' cellpadding='0' cellspacing='0' style='border-collapse:separate;mso-table-lspace:0pt;mso-table-rspace:0pt;width:100%;width:auto;'>
									  <tbody>
										<tr>
										  <td style='font-family:sans-serif;font-size:14px;vertical-align:top;background-color:#ffffff;border-radius:5px;text-align:center;background-color:#3498db;'> <a href='".BASE_URI."dropoff_guest_process.php?auth=".$randomString."' target='_blank' style='text-decoration:underline;background-color:#ffffff;border:solid 1px #3498db;border-radius:5px;box-sizing:border-box;color:#3498db;cursor:pointer;display:inline-block;font-size:14px;font-weight:bold;margin:0;padding:12px 25px;text-decoration:none;text-transform:capitalize;background-color:#3498db;border-color:#3498db;color:#ffffff;'>go</a> </td>
										</tr>
									  </tbody>
									</table>
								  </td>
								</tr>
							  </tbody>
							</table>
							
							<p style='font-family:sans-serif;font-size:14px;font-weight:normal;margin:0;Margin-bottom:15px;'>Good luck!</p>
						  </td>
						</tr>
					  </table>
					</td>
				  </tr>
				  <!-- END MAIN CONTENT AREA -->
				</table>
				<!-- START FOOTER -->
				<div class='footer' style='clear:both;padding-top:10px;text-align:center;width:100%;'>
				  <table border='0' cellpadding='0' cellspacing='0' style='border-collapse:separate;mso-table-lspace:0pt;mso-table-rspace:0pt;width:100%;'>
					<tr>
					  <td class='content-block' style='font-family:sans-serif;font-size:14px;vertical-align:top;color:#999999;font-size:12px;text-align:center;'>
						<span class='apple-link' style='color:#999999;font-size:12px;text-align:center;'>MicroHealth Send</span>
						<br>
						 Don't like these emails? <a href='' style='color:#3498db;text-decoration:underline;color:#999999;font-size:12px;text-align:center;'>Unsubscribe</a>.
					  </td>
					</tr>
					<tr>
					  <td class='content-block powered-by' style='font-family:sans-serif;font-size:14px;vertical-align:top;color:#999999;font-size:12px;text-align:center;'>
					   
					  </td>
					</tr>
				  </table>
				</div>
				<!-- END FOOTER -->
				<!-- END CENTERED WHITE CONTAINER -->
			  </div>
			</td>
			<td style='font-family:sans-serif;font-size:14px;vertical-align:top;'>&nbsp;</td>
		  </tr>
		</table>
	  </body>
	</html>";
			
		$headers = "MIME-Version: 1.0" . "\r\n";
		$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
		// More headers
		$headers .= 'From: <user@microhealthllc.com>' . "\r\n";
		if(mail($your_email,$your_email,$message1,$headers)){
		//echo "Message sent";
				$cc_status = "<div class=\"alert alert-success cc-success\"><strong>Success!</strong>Your Request has been submitted successfully.</div>";
		}else{
				$cc_status = "<div class=\"alert alert-danger cc-failed\"><strong>Oops! </strong>Something went wrong! please try after sometime.</div>";
		}
		
			echo "<script>$(document).ready(function(){ $('#cc-mail-status').modal('toggle');});</script>";
/*	}
	else {
		$cc_status = "<div class=\"alert alert-danger cc-failed\"><strong>Oops! </strong>capcha Error! please try again .</div>";
		echo "<script>$(document).ready(function(){ $('#cc-mail-status').modal('toggle');});</script>";
	}*/
	}}
?>
<!----------------------------------------------------------------------------------------->

<?php
	$your_name = '';
	$your_organization = '';
	$your_email = '';
		
			}else{
			$message = "Please enter all fields";		
			}

		}
	

?>
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
  <form action="<?php $_SERVER['PHP_SELF']; ?>" name="addclient" method="post">
  <div class="form-group">
	<?php if(!empty($message)){?>
	<div class="message message_error">
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

		<script src="https://msend.microhealthllc.com/assets/bootstrap/js/bootstrap.min.js"></script>
			<script src="https://msend.microhealthllc.com/includes/js/jquery.validations.js"></script>
			<script src="https://msend.microhealthllc.com/includes/js/jquery.psendmodal.js"></script>
