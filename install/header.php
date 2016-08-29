<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title><?php echo $page_title_install; ?> &raquo; <?php echo SYSTEM_NAME; ?></title>
	<link rel="shortcut icon" href="../favicon.ico" />
	<script src="../includes/js/jquery.1.12.4.min.js"></script>

	<link rel="stylesheet" media="all" type="text/css" href="../assets/bootstrap/css/bootstrap.min.css" />

	<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
		<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
	<![endif]-->

	<link rel="stylesheet" media="all" type="text/css" href="../css/base.css" />
	<link rel="stylesheet" media="all" type="text/css" href="../css/shared.css" />

	<link href='<?php echo PROTOCOL; ?>://fonts.googleapis.com/css?family=Open+Sans:400,700,300' rel='stylesheet' type='text/css'>
	<link href='<?php echo PROTOCOL; ?>://fonts.googleapis.com/css?family=Abel' rel='stylesheet' type='text/css'>

	<script src="../assets/bootstrap/js/bootstrap.min.js"></script>
	<script src="../includes/js/jquery.validations.js" type="text/javascript"></script>

	<script type="text/javascript">
		$(document).ready(function() {
			$('.button').click(function() {
				$(this).blur();
			});
		});
	</script>
</head>

<body>

	<header>
		<div id="header">
			<div id="lonely_logo">
				<h1><?php echo SYSTEM_NAME.' '; _e('setup','cftp_admin'); ?></h1>
			</div>
		</div>
		<div id="login_header_low">
		</div>

		<?php
			include('../includes/updates.messages.php');
		?>
	</header>
	
	<div id="main">