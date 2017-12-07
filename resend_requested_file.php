<?php
/*
* resend requested files.
*/
$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');
if(!empty($_SESSION)){
		if($_SESSION['userlevel'] == '9' || $_SESSION['userlevel'] == '8' || $_SESSION['userlevel'] == '7' || $_SESSION['userlevel'] == '0'){
				$e_id = $_POST['e_id'];
				echo $e_id;
				$save = $dbh->prepare( "UPDATE tbl_drop_off_request SET requested_time=:requested_time WHERE id=:e_id" );
				$save->bindParam(':requested_time',date("Y-m-d H:i:s"));
				$save->bindParam(':e_id', $e_id);
				if($save->execute()){
					$q_sent_file = "SELECT * FROM tbl_drop_off_request WHERE id = ".$e_id;
					$sql_files = $dbh->prepare($q_sent_file);
					$sql_files->execute();
					$count = $sql_files->rowCount();
					if ($count > 0) {
						$sql_files->setFetchMode(PDO::FETCH_ASSOC);
						while( $row = $sql_files->fetch() ) {
									$randomString = $row['auth_key'];
									$from_organization = $row['from_organization'];
									$to_name_request = $row['to_name'];
									$to_email_request = $row['to_email'];
									$to_subject_request = $row['to_subject_request'];
									$to_note_request = $row['to_note_request'];
						}
					}
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
	  <p style='font-family:sans-serif;font-size:14px;font-weight:normal;margin:0;Margin-bottom:15px;'>You have asked us to send you this message so that you can drop-off some files for someone</p>
	
	  <p>
	Details:<br>
	Name : $to_name_request <br>
	Organization : $from_organization <br>
	Email : $to_email_request <br></p>
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
			
		$headers = "MIME-Version: 1.0" . "\r\n";
		$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
		// More headers
		$headers .= 'From: <user@microhealthllc.com>' . "\r\n";
		if(mail($to_email_request,$to_email_request,$message,$headers)){
	
			echo "done";
		}
		
				}
		}

}
