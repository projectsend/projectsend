<?php
/**
 * These functions can be called to show the search,
 * filters and actions forms.
 */

function show_search_form( $action = '' )
{
?>
	<form action="<?php echo $action; ?>" name="form_search" method="get" class="row row-cols-lg-auto g-3 align-items-center">
		<?php echo form_add_existing_parameters( array('search', 'action') ); ?>
        <div class="col-6 col-md-12">
			<input type="text" name="search" id="search" value="<?php if(isset($_GET['search']) && !empty($_GET['search'])) { echo html_output($_GET['search']); } ?>" class="form-control-short form_actions_search_box form-control" />
		</div>
        <div class="col-6 col-md-12">
    		<button type="submit" id="btn_proceed_search" class="btn btn-md btn-pslight"><?php _e('Search','cftp_admin'); ?></button>
        </div>
	</form>
<?php
}

function show_filters_form($arguments)
{
    if (empty($arguments)) {
        return;
    }

    if (empty($arguments['items'])) {
        return;
    }

    $ignore = (!empty($arguments['ignore_form_parameters'])) ? $arguments['ignore_form_parameters'] : array_keys($arguments['items']);
    if (!empty($arguments['hidden_inputs'])) {
        $ignore = array_merge($ignore, array_keys($arguments['hidden_inputs']));
    }
?>
    <form action="<?php echo $arguments['action']; ?>" name="actions_filters" method="get" class="row row-cols-lg-auto g-3 align-items-end justify-content-end form_filters mt-4 mt-md-0">
        <?php echo form_add_existing_parameters($ignore); ?>
        <?php
            if (!empty($arguments['hidden_inputs'])) {
                foreach ($arguments['hidden_inputs'] as $name => $value) {
        ?>
                    <input type="hidden" name="<?php echo $name; ?>" value="<?php echo $value; ?>">
        <?php
                }
            }
        ?>
        <?php foreach ($arguments['items'] as $name => $data) { ?>
            <div class="col-4 col-md-12">
                <select class="form-select" name="<?php echo $name; ?>" id="<?php echo $name; ?>">
                    <?php
                        if (!empty($data['placeholder'])) {
                    ?>
                            <option value="<?php echo $data['placeholder']['value']; ?>"><?php echo $data['placeholder']['label']; ?></option>
                    <?php
                        }

                        foreach ($data['options'] as $value => $option) {
                            if (is_array($option)) {
                                $name = $option['name'];
                            } else {
                                $name = $option;
                            }
                    ?>
                            <option
                                value="<?php echo $value; ?>" <?php if (isset($data['current']) && $data['current'] == $value) { echo 'selected="selected"'; } ?>
                                <?php
                                    if (is_array($option) && !empty($option['attributes'])) {
                                        foreach ($option['attributes'] as $attribute => $attr_value) {
                                            echo ' '.$attribute.'="'.$attr_value.'"';
                                        }
                                    }
                                ?>
                            >
                                <?php echo $name; ?>
                            </option>
                    <?php
                        }
                    ?>
                </select>
            </div>
        <?php } ?>
        <div class="col-4 col-md-12">
            <button type="submit" class="btn btn-md btn-pslight"><?php _e('Filter', 'cftp_admin'); ?></button>
        </div>
    </form>
<?php
}

function show_actions_form($actions)
{
?>
    <div class="col-6 col-md-12">
        <select class="form-select" name="action" id="action">
            <?php
                foreach ($actions as $value => $label) {
            ?>
                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
            <?php
                }
            ?>
        </select>
    </div>
    <div class="col-6 col-md-12">
        <button type="submit" id="do_action" class="btn btn-md btn-pslight"><?php _e('Proceed', 'cftp_admin'); ?></button>
    </div>
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

    $return = '';
	if ( !empty( $_GET ) ) {
		foreach ( $_GET as $param => $value ) {
			// Remove status and actions
			if ( in_array( $param, $remove ) ) {
				unset( $_GET[$param] );
			}
			if ( !is_array( $value ) && !in_array( $param, $ignore ) ) {
				$return .= '<input type="hidden" name="' . htmlentities($param) . '" value="' . html_output($value) . '">';
			}
		}
	}

    return $return;
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