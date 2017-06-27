<?php
/**
 * These functions can be called to show the search,
 * filters and actions forms.
 *
 * @package		ProjectSend
 * 
 */

function show_search_form( $action = '' ) {
?>
	<form action="<?php echo $action; ?>" name="form_search" method="get" class="form-inline">
		<?php form_add_existing_parameters( array('search', 'action') ); ?>
		<div class="form-group group_float">
			<input type="text" name="search" id="search" value="<?php if(isset($_GET['search']) && !empty($_GET['search'])) { echo html_output($_GET['search']); } ?>" class="txtfield form_actions_search_box form-control" />
		</div>
		<button type="submit" id="btn_proceed_search" class="btn btn-sm btn-default"><?php _e('Search','cftp_admin'); ?></button>
	</form>
<?php
}