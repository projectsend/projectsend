<?php
/**
 * This file generates the main menu for the header on the back-end
 * and also for the default template.
 *
 * @package ProjectSend
 */

$items = array();

/**
 * Items for system users
 */
if ( in_session_or_cookies( array( 9,8,7 ) ) )
{

	/** Count inactive CLIENTS */
	$sql_inactive		= $dbh->prepare( "SELECT DISTINCT user FROM " . TABLE_USERS . " WHERE active = '0' AND level = '0'" );
	$sql_inactive->execute();
	$inactive_clients	= $sql_inactive->rowCount();

	/** Count inactive USERS */
	$sql_inactive		= $dbh->prepare( "SELECT DISTINCT user FROM " . TABLE_USERS . " WHERE active = '0' AND level != '0'" );
	$sql_inactive->execute();
	$inactive_users		= $sql_inactive->rowCount();

/*
	$items['dashboard'] = array(
								'nav'	=> 'dashboard',
								'level'	=> array( 9,8,7 ),
								'main'	=> array(
												'label'	=> __('Dashboard', 'cftp_admin'),
												'link'	=> 'home.php',
											),
							);
*/
							?>
							
							
	<div class="row sendfile1">
		<div class="sendfile col-md-11">
		<div>
		<a href="upload-from-computer.php" class="btn btn-success center-block">Send File</a>
	</div>
</div>
	<div class="sendfile col-md-11">
		<div>
		<a href="request-drop-off.php" class="btn btn-primary center-block" >Request a File</a>
		</div>
	</div>
</div>
							
							
						
							<?php

	$items['files']		= array(
								'nav'	=> 'files',
								'level'	=> array( 9,8,7 ),
								'main'	=> array(
												'label'	=> __('Files', 'cftp_admin'),
											),
								'sub'	=> array(
												/*array(
													'label'	=> __('Send File', 'cftp_admin'),
													'link'	=> 'upload-from-computer.php',
												),*/
												array(
													'label'	=> __('Inbox', 'cftp_admin'),
													'link'	=> 'inbox.php',
												),
												array(
													'label'	=> __('Draft', 'cftp_admin'),
													'link'	=> 'draft.php',
												),
												array(
													'label'	=> __('Outbox', 'cftp_admin'),
													'link'	=> 'outbox.php',
												),
												array(
													'label'	=> __('Sent', 'cftp_admin'),
													'link'	=> 'sent.php',
												),
												array(
													'label'	=> __('Public Files', 'cftp_admin'),
													'link'	=> 'public-files.php',
												),
												array(
													'label'	=> __('Requested File', 'cftp_admin'),
													'link'	=> 'requested_file.php',
												),
											/*	array(
													'label'	=> __('Request a File', 'cftp_admin'),
													'link'	=> 'request-drop-off.php',
												),*/
												array(
													'label'	=> __('Expired Files', 'cftp_admin'),
													'link'	=> 'expired.php',
												),
										        array(
													'label'	=> __('Calendar View', 'cftp_admin'),	
													'link'	=> 'calendar-view.php',
												),

											/*	array(
													'label'	=> __('Manage files', 'cftp_admin'),
													'link'	=> 'manage-files.php',
												),
												array(
													'label'	=> __('Find orphan files', 'cftp_admin'),
													'link'	=> 'upload-import-orphans.php',
												),
												array(
													'divider'	=> true,
												),
												array(
													'label'	=> __('Categories', 'cftp_admin'),
													'link'	=> 'categories.php',
												),*/
											),
							);

	$items['clients']	= array(
								'nav'	=> 'clients',
								'level'	=> array( 9,8 ),
								'main'	=> array(
												'label'	=> __('Clients', 'cftp_admin'),
												'badge'	=> $inactive_clients,
											),
								'sub'	=> array(
												/*array(
													'label'	=> __('Add new', 'cftp_admin'),
													'link'	=> 'clients-add.php',
												),*/
												array(
													'label'	=> __('Manage Clients', 'cftp_admin'),
													'link'	=> 'clients.php',
												),
												array(
													'label'	=> __('Manage Clients Organization', 'cftp_admin'),
													'link'	=> 'clientorganization.php',
													
												),

												array(
													'label'	=> __('Manage Users Organization', 'cftp_admin'),
													'link'	=> 'userorganization.php',
													
												),
												array(
													'label'	=> __('Categories', 'cftp_admin'),
													'link'	=> 'categories.php',
												),
											),
							);
	$items['admin']	= array(
								'nav'	=> 'admin',
								'level'	=> array( 9 ),
								'main'	=> array(
												'label'	=> __('Admin', 'cftp_admin'),
												
											),
								'sub'	=> array(
												array(
												'label'	=> __('Dashboard', 'cftp_admin'),
												'link'	=> 'home.php',
												),
												array(
													'label'	=> __('Manage files', 'cftp_admin'),
													'link'	=> 'manage-files.php',
												),
												array(
													'label'	=> __('Users', 'cftp_admin'),
													'link'	=> 'users.php',
												),

												array(
													'label'	=> __('Options', 'cftp_admin'),
													'link'	=> 'options.php',
												),
												array(
													'label'	=> __('Branding', 'cftp_admin'),
													'link'	=> 'branding.php',
												),
												array(
													'label'	=> __('Email Templates', 'cftp_admin'),
													'link'	=> 'email-templates.php',
												),
												array(
													'label'	=> __('Orphans files', 'cftp_admin'),
													'link'	=> 'upload-import-orphans.php',
												),
												array(
													'label'	=> __('Activities log', 'cftp_admin'),
													'link'	=> 'actions-log.php',
												),												
												array(
													'label'	=> __('Home Page Edits', 'cftp_admin'),	
													'link'	=> 'home-page-edits.php',
												),
												
												
											),
							);


	/*$items['groups']	= array(
								'nav'	=> 'groups',
								'level'	=> array( 9,8 ),
								'main'	=> array(
												'label'	=> __('Clients groups', 'cftp_admin'),
											),
								'sub'	=> array(
												array(
													'label'	=> __('Add new', 'cftp_admin'),
													'link'	=> 'groups-add.php',
												),
												array(
													'label'	=> __('Manage groups', 'cftp_admin'),
													'link'	=> 'groups.php',
												),
											),
							);

	$items['users']		= array(
								'nav'	=> 'users',
								'level'	=> array( 9 ),
								'main'	=> array(
												'label'	=> __('System Users', 'cftp_admin'),
												'badge'	=> $inactive_users,
											),
								'sub'	=> array(
												array(
													'label'	=> __('Add new', 'cftp_admin'),
													'link'	=> 'users-add.php',
												),
												array(
													'label'	=> __('Manage system users', 'cftp_admin'),
													'link'	=> 'users.php',
												),
											),
							);

	$items['options']	= array(
								'nav'	=> 'options',
								'level'	=> array( 9 ),
								'main'	=> array(
												'label'	=> __('Options', 'cftp_admin'),
											),
								'sub'	=> array(
												array(
													'label'	=> __('General options', 'cftp_admin'),
													'link'	=> 'options.php',
												),
												array(
													'divider'	=> true,
												),
												array(
													'label'	=> __('Branding', 'cftp_admin'),
													'link'	=> 'branding.php',
												),
												array(
													'label'	=> __('E-mail templates', 'cftp_admin'),
													'link'	=> 'email-templates.php',
												),
											),
							);*/
}
/**
 * Items for clients
 */
else
{
?>
	<div class="row sendfile1">
		<div class="sendfile col-md-11">
		<div>
		<a href="<?php echo BASE_URI;?>upload-from-computer.php" class="btn btn-success center-block">Send File</a>
	</div>
</div>
	<div class="sendfile col-md-11">
		<div>
		<a href="<?php echo BASE_URI;?>request-drop-off.php" class="btn btn-primary center-block" >Request a File</a>
		</div>
	</div>
</div>

<?php
		$items['upload'] = array(
									'nav'	=> 'upload',
									'level'	=> array( 9,8,7,0 ),
									'main'	=> array(
												'label'	=> __('Files', 'cftp_admin'),
											),
								'sub'	=> array(
												/*array(
													'label'	=> __('Send File', 'cftp_admin'),
													'link'	=> 'upload-from-computer.php',
												),*/
												array(
													'label'	=> __('Inbox', 'cftp_admin'),
													'link'	=> 'inbox.php',
												),
												array(
													'label'	=> __('Draft', 'cftp_admin'),
													'link'	=> 'draft.php',
												),
												array(
													'label'	=> __('Outbox', 'cftp_admin'),
													'link'	=> 'outbox.php',
												),
												array(
													'label'	=> __('Sent', 'cftp_admin'),
													'link'	=> 'sent.php',
												),
												array(
													'label'	=> __('Public Files', 'cftp_admin'),
													'link'	=> 'public-files.php',
												),
												array(
													'label'	=> __('Requested File', 'cftp_admin'),
													'link'	=> 'requested_file.php',
												),
												array(
													'label'	=> __('Expired Files', 'cftp_admin'),
													'link'	=> 'expired.php',
												),
									)
								);
/*
	if (CLIENTS_CAN_UPLOAD == 1)
	{
		$items['upload1'] = array(
									'nav'	=> 'upload',
									'level'	=> array( 9,8,7,0 ),
									'main'	=> array(
													'label'	=> __('Upload', 'cftp_admin'),
													'link'	=> 'upload-from-computer.php',
												),
								);
	}

	$items['manage_files'] = array(
								'nav'	=> 'manage',
								'level'	=> array( 9,8,7,0 ),
								'main'	=> array(
												'label'	=> __('Manage files', 'cftp_admin'),
												'link'	=> 'manage-files.php',
											),
							);

	$items['view_files'] = array(
								'nav'	=> 'template',
								'level'	=> array( 9,8,7,0 ),
								'main'	=> array(
												'label'	=> __('View my files', 'cftp_admin'),
												'link'	=> 'my_files/',
											),
							);*/
}

/**
 * Build the menu
 */
$menu_output = "<ul>\n";

foreach ( $items as $item )
{
	if ( in_session_or_cookies( $item['level'] ) )
	{
		$current	= ( !empty( $active_nav ) && $active_nav == $item['nav'] ) ? 'active' : '';
		$badge		= ( !empty( $item['main']['badge'] ) ) ? ' <span class="badge">' . $item['main']['badge'] . '</span>' : '';

		/** Top level tag */
		if ( !isset( $item['sub'] ) )
		{
			$format			= "<li class='%s'>\n\t<a href='%s'>%s%s</a>\n</li>\n";
			$menu_output 	.= sprintf( $format, $current, BASE_URI . $item['main']['link'], $badge, $item['main']['label'] );
		}

		else
		{
			$format			= "<li class='cc-dropdown $current'>\n\t<a href='#'>%s%s <b class='caret'></b></a>\n\t<ul>\n";
			$menu_output 	.= sprintf( $format, $item['main']['label'], $badge );
			/**
			 * Submenu
			*/
			foreach ( $item['sub'] as $subitem )
			{
				if ( !empty( $subitem['divider'] ) )
				{
					$menu_output .= "\t\t<li class='divider'></li>\n";
				}
				else
				{
/*					if(isset($subitem['subnav']) != '') {
						if($subitem['subnav'] == $cc_active_page) {
							$cc_active_sub = 'cc-active-subpage';
						}
						else {
							$cc_active_sub = '';
						}
					}*/
					if($subitem['label'] == $cc_active_page) {
						$cc_active_sub = 'cc-active-subpage';
					}
					else {
						$cc_active_sub = '';
					}
					//$cc_active_sub = isset($cc_active_sub) ? $cc_active_sub : '';
					$format			= "\t\t<li class='$cc_active_sub'>\n\t\t\t<a href='%s'>%s</a>\n\t\t</li>\n";
					$cc_active_sub ='';
					$menu_output 	.= sprintf( $format, BASE_URI . $subitem['link'], $subitem['label'] );
				}
			}
			$menu_output 	.= "\t</ul>\n</li>\n";
		}
	}
}

$menu_output .= "</ul>\n";

$menu_output = str_replace( "'", '"', $menu_output );

/**
 * Print to screen
 */
echo $menu_output;
?>
