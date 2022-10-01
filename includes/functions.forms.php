<?php
/**
 * These functions can be called to show the search,
 * filters and actions forms.
 */

function show_search_form( $action = '' )
{
?>
	<form action="<?php echo $action; ?>" name="form_search" method="get" class="row row-cols-lg-auto g-3 align-items-center">
		<?php form_add_existing_parameters( array('search', 'action') ); ?>
        <div class="col-6 col-md-12">
			<input type="text" name="search" id="search" value="<?php if(isset($_GET['search']) && !empty($_GET['search'])) { echo html_output($_GET['search']); } ?>" class="form-control-short form_actions_search_box form-control" />
		</div>
        <div class="col-6 col-md-12">
    		<button type="submit" id="btn_proceed_search" class="btn btn-md btn-pslight"><?php _e('Search','cftp_admin'); ?></button>
        </div>
	</form>
<?php
}

/**
 * Add any existing $_GET parameters as hidden fields on a form
 */
function form_add_existing_parameters( $ignore = [] )
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
				echo '<input type="hidden" name="' . htmlentities($param) . '" value="' . html_output($value) . '">';
			}
		}
	}
}

/**
 * Add any existing $_GET parameters to the form's action url
 */
function get_form_action_with_existing_parameters( $action = null, $ignore = [] )
{
    $use = [];

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
				$use[$param] = encode_html($value);
			}
		}
	}

    $return = $action;
    if (!empty($use)) {
        $return .= '?' . http_build_query($use);
    }

    return $return;
}

/**
 * Returns an existing or empty value to an input
 * 
 * @param string
 * @return string
 */
function format_form_value( $value )
{
    // 0 is considered empty, so just return it (used on max_file_size for clients and users)
    if ($value === '0') {
        return '0';
    }

    if ( isset($value) && !empty( $value ) )
    {
        return html_output( stripslashes( $value ) );
    }
    else {
        return;
    }
}