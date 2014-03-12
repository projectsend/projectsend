<?php
/**
 * Define the common header and footer markup used on all sent e-mails.
 *
 * @package ProjectSend
 */

/**
 * Styles that can be applied to images to prevent display issues on
 * webmail readers.
 */
$img_safe_style = 'display:block; margin:0; border:none;';

/**
 * Define the header. A table cell remains open and the content of the
 * e-mail is inserted there.
 */
$email_template_header = '
<body style="background:#f4f4f4; margin:40px 0; padding:40px 0;" bgcolor="#f4f4f4">
<table width="550" border="0" cellspacing="0" cellpadding="0" style="background:#fff;	border:1px solid #ccc; -moz-border-radius:5px; -moz-box-shadow:3px 3px 5px #dedede; -webkit-border-radius:5px; -webkit-box-shadow:3px 3px 5px #dedede; border-radius:5px; box-shadow:3px 3px 5px #dedede;" bgcolor="#FFFFFF" align="center">
	<tr>
		<td style="padding:20px; font-family:Arial, Helvetica, sans-serif; font-size:12px;">
			<h3 style="font-family:Arial, Helvetica, sans-serif; font-size:19px; font-weight:normal; margin-bottom:20px; margin-top:0; color:#333333;">
				<font face="Arial, Helvetica, sans-serif" color="#333333">
					%SUBJECT%
				</font>
			</h3>';

/**
 * Define the footer
 */
$email_template_footer = '</td>
	</tr>
	<tr>
		<td style="padding:20px; border-top:1px dotted #ccc;">
			<a href="'.SYSTEM_URI.'" target="_blank">
				<img src="'.BASE_URI.'img/icon-footer-email.jpg" alt="" style="'.$img_safe_style.'" />
			</a>
		</td>
	</tr>
</table>
</body>
</html>';
?>