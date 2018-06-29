<?php
/**
 * These functions can be called to show the search,
 * filters and actions forms.
 *
 * @package		ProjectSend
 * 
 */

function show_search_form( $action = '' )
{
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

/**
 * Add any existing $_GET parameters as hidden fields on a form
 */
function form_add_existing_parameters( $ignore = array() )
{
	// Don't add the pagination parameter
	$ignore[] = 'page';

	// Remove this parameters so they only exist when the action is done
	$remove = array('action', 'batch', 'status');

	if ( !empty( $_GET ) ) {
		foreach ( $_GET as $param => $value ) {
			// Remove status and actions
			if ( in_array( $param, $remove ) ) {
				unset( $_GET[$param] );
			}
			if ( !is_array( $value ) && !in_array( $param, $ignore ) ) {
				echo '<input type="hidden" name="' . $param . '" value="' . encode_html($value) . '">';
			}
		}
	}
}