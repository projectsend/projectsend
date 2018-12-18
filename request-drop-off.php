<?php
/**
 * Show the form to request a drop-off
 *
 * @package		ProjectSend
 * @subpackage	Request a drop-off
 *
 */

// Report all PHP errors

// Same as error_reporting(E_ALL);
//ini_set('error_reporting', E_ALL);

$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');
$form_action = "request-drop-off.php";
$active_nav = 'request-a-drop-off';
$cc_active_page = 'Request a File';

$page_title = __('Request a Drop-off','cftp_admin');

include('header.php');
//include('header-unlogged.php');
if(!empty($_SESSION['loggedin_id'])){

	$logged_in_id = $_SESSION['loggedin_id'];
	$sql = $dbh->prepare( "SELECT * FROM " . TABLE_USERS . " WHERE id = ".$logged_in_id );
	$sql->execute();
	$sql->setFetchMode(PDO::FETCH_ASSOC);
	while ( $grow = $sql->fetch() ) {
		$logged_in_email = $grow["email"];
		$logged_in_name = $grow["name"];
	}
}

function generate_random_string($length = 30) {
    $characters = '0123456789!@#$%^&*()_+abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
/**
 * Set checkboxes as 1 to defaul them to checked when first entering
 * the form
 */

if ($_POST) {
	//print_r($_POST);
	
	/* Insert into DB and check with request during drop */
	/*if($_POST['g-recaptcha-response']) {*/
		$randomString = generateRandomString(30);
		$from_email = $_POST['from_email'];
		$from_organization = $_POST['from_organization'];
		$to_name_request = $_POST['to_name_request'];
		$to_email_request = $_POST['to_email_request'];
		$to_subject_request = $_POST['to_subject_request'];
		$to_note_request = $_POST['to_note_request'];
		
		//$to_subject_request = $_POST['to_subject_request'];	
		//validation start --------------------
		$validate_err['count'] = 0;
		if(!preg_match("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$^",$to_email_request))
		{ 
			
			$to_emailErr  = "<div class=\"alert alert-danger cc-failed\">Invalid E-mail</div>";
			
		}else{
			
			$to_emailErr = '';
			
		}
		if($to_subject_request == '')
		{ 
			
			$to_subErr  = "<div class=\"alert alert-danger cc-failed\">Invalid Subject</div>";
			
		}else{
			
			$to_subErr = '';
			
		}
		if($to_name_request == '')
		{ 
			
			$to_nameErr  = "<div class=\"alert alert-danger cc-failed\">Invalid To Name</div>";
			
		}else{
			$to_nameErr = '';
			
		}
		
		
		if($to_email_request!='' && $to_subject_request != '' && $to_name_request != '' ) { 
		
				if (!filter_var($to_email_request, FILTER_VALIDATE_EMAIL) === false) {
					
				  
		$statement = $dbh->prepare("INSERT INTO `tbl_drop_off_request` (`from_id`, `to_name`, `to_subject_request`, `from_organization`, `to_email`, `to_note_request`, `requested_time`, `auth_key`, `status`) VALUES ('".$logged_in_id."', '".$to_name_request."', '".$to_subject_request."', '".$from_organization."', '".$to_email_request."', '".$to_note_request."', '".date("Y-m-d H:i:s")."', '".$randomString."', '0')");

		
				//$statement = $dbh->prepare("INSERT INTO ".TABLE_DROPOFF." (from_id,to_name,to_subject_request,from_organization,to_email,to_note_request,requested_time,auth_key,status) VALUES (:from_id, :to_name, :to_subject_request, :from_organization, :to_email, :to_note_request, :requested_time, :auth_key, :status )");
				$statement->bindParam(':from_id', $logged_in_id);
				$statement->bindValue(':to_name', $to_name_request);
				$statement->bindValue(':to_subject_request', $to_subject_request);
				$statement->bindValue(':from_organization', $from_organization);
				$statement->bindValue(':to_email', $to_email_request);
				$statement->bindValue(':to_note_request', $to_note_request);
				$statement->bindValue(':requested_time', date("Y-m-d H:i:s"));
				$statement->bindValue(':auth_key', $randomString);
				$statement->bindValue(':status', '0');
		
				$statement->execute();

		/*$message = '
			<html>
			<head>
			  <title>'.$to_subject_request.'</title>
			</head>
			<body>
			  <p>'.$to_subject_request.'</p>
	
			  <table>
				<tr>
				  <td>Name </td><td>:</td><td>'.$to_name_request.'</td>
				</tr>
				<tr>
				  <td>Organization </td><td>:</td><td>'.$from_organization.'</td>
				</tr>
				<tr>
				  <td>Email : </td><td>:</td><td>'.$to_email_request.'</td>
				</tr>
			  </table>
			  <p>You have asked us to send you this message so that you can drop-off some files for someone</p>
			  <p>IGNORE THIS MESSAGE IF YOU WERE NOT IMMEDIATELY EXPECTING IT!</p>
			  <p>Otherwise, continue the process by clicking the following link (or copying and pasting it into your web browser):</p>
	
	
			<span><a target="_blank" href="'.BASE_URI.'dropoff.php?auth='.$randomString.'">'.BASE_URI.'dropoff.php?auth='.$randomString.'</a> </span><br>
			<span>'.$to_note_request.'</span>
			</body>
			</html>
			';*/
			$message ="<html>
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
			  <title>$to_subject_request</title>
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
							<p style='font-family:sans-serif;font-size:14px;font-weight:normal;margin:0;Margin-bottom:15px;'>Hi $to_name_request</p>
    <p style='font-family:sans-serif;font-size:14px;font-weight:normal;margin:0;Margin-bottom:15px;'>This message was sent to request that you drop-off some files to MicroHealth Send.</p>
    
      <p>
    Your Details:<br>
    Name : $to_name_request <br>
    Organization : $from_organization <br>
    Email : $to_email_request <br></p>
      <p>
    Requester Details:<br>
    Name : $logged_in_name <br>
    Email: $from_email <br></p>
	<p><em>Note: ".$to_note_request."</em></p>
	  <p>IGNORE THIS MESSAGE IF YOU WERE NOT IMMEDIATELY EXPECTING IT!</p>
	  <p>Otherwise, continue the process by clicking here!</p>
	
							<table border='0' cellpadding='0' cellspacing='0' class='btn btn-primary' style='border-collapse:separate;mso-table-lspace:0pt;mso-table-rspace:0pt;box-sizing:border-box;width:100%;'>
							  <tbody>
								<tr>
								  <td align='left' style='font-family:sans-serif;font-size:14px;vertical-align:top;padding-bottom:15px;'>
									<table border='0' cellpadding='0' cellspacing='0' style='border-collapse:separate;mso-table-lspace:0pt;mso-table-rspace:0pt;width:100%;width:auto;'>
									  <tbody>
										<tr>
										  <td style='font-family:sans-serif;font-size:14px;vertical-align:top;background-color:#ffffff;border-radius:5px;text-align:center;background-color:#3498db;'> <a href='".BASE_URI."dropoff.php?auth=".$randomString."' target='_blank' style='text-decoration:underline;background-color:#ffffff;border:solid 1px #3498db;border-radius:5px;box-sizing:border-box;color:#3498db;cursor:pointer;display:inline-block;font-size:14px;font-weight:bold;margin:0;padding:12px 25px;text-decoration:none;text-transform:capitalize;background-color:#3498db;border-color:#3498db;color:#ffffff;'>go</a> </td>
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

		/**
		 * phpMailer
		 */
		require_once(ROOT_DIR.'/includes/phpmailer/class.phpmailer.php');
		if (!spl_autoload_functions() OR (!in_array('PHPMailerAutoload', spl_autoload_functions()))) {
			require_once(ROOT_DIR.'/includes/phpmailer/PHPMailerAutoload.php');
		}

		$send_mail = new PHPMailer();
		switch (MAIL_SYSTEM) {
			case 'smtp':
					$send_mail->IsSMTP();
					$send_mail->SMTPAuth = true;
					$send_mail->Host = SMTP_HOST;
					$send_mail->Port = SMTP_PORT;
					$send_mail->Username = SMTP_USER;
					$send_mail->Password = SMTP_PASS;
					
					if ( defined('SMTP_AUTH') && SMTP_AUTH != 'none' ) {
						$send_mail->SMTPSecure = SMTP_AUTH;
					}
				break;
			case 'gmail':
					$send_mail->IsSMTP();
					$send_mail->SMTPAuth = true;
					$send_mail->SMTPSecure = "tls";
					$send_mail->Host = 'smtp.gmail.com';
					$send_mail->Port = 587;
					$send_mail->Username = SMTP_USER;
					$send_mail->Password = SMTP_PASS;
				break;
			case 'sendmail':
					$send_mail->IsSendmail();
				break;
		}
		
		$send_mail->CharSet = EMAIL_ENCODING;
//
		$send_mail->Subject = "DROP OFF REQUEST";
//
		$send_mail->MsgHTML($message);
		$send_mail->AltBody = __('This email contains HTML formatting and cannot be displayed right now. Please use an HTML compatible reader.','cftp_admin');

		$send_mail->SetFrom(ADMIN_EMAIL_ADDRESS, MAIL_FROM_NAME);
		$send_mail->AddReplyTo(ADMIN_EMAIL_ADDRESS, MAIL_FROM_NAME);
//
		$send_mail->AddAddress($to_email_request);
		
		/**
		 * Check if BCC is enabled and get the list of
		 * addresses to add, based on the email type.
		 */
		if (COPY_MAIL_ON_CLIENT_UPLOADS == '1') {
					$try_bcc = true;
				}
		if ($try_bcc === true) {
			$add_bcc_to = array();
			if (COPY_MAIL_MAIN_USER == '1') {
				$add_bcc_to[] = ADMIN_EMAIL_ADDRESS;
			}
			$more_addresses = COPY_MAIL_ADDRESSES;
			if (!empty($more_addresses)) {
				$more_addresses = explode(',',$more_addresses);
				foreach ($more_addresses as $add_bcc) {
					$add_bcc_to[] = $add_bcc;
				}
			}


			/**
			 * Add the BCCs with the compiled array.
			 * First, clean the array to make sure the admin
			 * address is not written twice.
			 */

			if (!empty($add_bcc_to)) {
				$add_bcc_to = array_unique($add_bcc_to);
				foreach ($add_bcc_to as $set_bcc) {
					$send_mail->AddBCC($set_bcc);
				}
			}
			 
		}


	
		/**
		 * Finally, send the e-mail.
		 */

		if($send_mail->Send()) {
			$cc_status = "<div class=\"alert alert-success cc-success\"><strong>Success!</strong>Your Request has been submitted successfully.</div>";
		}
		else {
			$cc_status = "<div class=\"alert alert-danger cc-failed\"><strong>Oops! </strong>Something went wrong! please try after sometime.</div>";
		}
		
		echo '<script>$(document).ready(function(){$("#cc-mail-status").modal("toggle");});</script>';
			
						/*	}	

						}

					}
				}*/
			}
		}
	}
?>
<!----------------------------------------------------------------------------------------->
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
<!----------------------------------------------------------------------------------------->

<div id="main">
	
	<div id="content"> 
    
    <!-- Added by B) -------------------->
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-12">
        <h2><i class="fa fa-paper-plane" aria-hidden="true"></i>&nbsp;<?php echo $page_title; ?></h2>
        </div>
        </div>
        </div>
       
	<div class="container">
		<div class="row">
			<div class="col-xs-12 col-xs-offset-0 col-sm-8 col-sm-offset-2 col-md-6 col-md-offset-3 white-box">
				<div class="white-box-interior">
				
					<?php
							include('request-drop-off-form.php');
					?>
				</div>
			</div>
		</div>
	</div>
     </div>
</div>

<?php
	include('footer.php');
?>
