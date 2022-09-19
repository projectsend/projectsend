<?php
/**
 * Shows the list of public groups and files
 *
 * @package		ProjectSend
 * @subpackage	Files
 *
 */
$allowed_levels = array(9,8,7,0);
require_once 'bootstrap.php';

/**
 * If the option to show this page is not enabled, redirect
 */
if ( get_option('public_listing_page_enable') != 1 ) {
    ps_redirect(BASE_URI . "index.php");
}

/**
 * Check the option to show the page to logged in users only
 */
if ( get_option('public_listing_logged_only') == 1 ) {
    redirect_if_not_logged_in();
}

/**
 * Temp? Mode defines if current view is loose files or a group
 */
$mode = 'files';

/**
 * If viewing a particular group, make sure it's public
 */
if (!empty($_GET['token']) && !empty($_GET['id'])) {
    $can_view_group = false;

    $test_group = get_group_by_id($_GET['id']);
    if ( $test_group['public_token'] == $_GET['token'] ) {
        if ( $test_group['public'] == 1 ) {
            $can_view_group = true;
        }
    }

    if ( !$can_view_group ) {
        ps_redirect(BASE_URI . "index.php");
    }

    $mode = 'group';
}

$page_title = __('Public groups and files','cftp_admin');

$dont_redirect_if_logged = 1;

include_once ADMIN_VIEWS_DIR . DS . 'header-unlogged.php';
// include_once TEMPLATE_PATH;

/**
 * General function that defines the formating of files lines
 */
function list_file($data, $origin) {
    $show = false;
    if ( $origin == 'group' && get_option('public_listing_show_all_files') == 1 ) {
    }
    else {
        if ( $data['public'] != 1 ) {
            return;
        }
    }

    $output = '<li class="file"><i class="fa fa-file-o" aria-hidden="true"></i> ';
    if ( get_option('public_listing_use_download_link') == 1 && $data['expired'] != true && $data['public'] == 1 ) {
        $download_link = BASE_URI . 'download.php?id=' . $data['id'] . '&token=' . $data['token'];
        $output .= '<a href="' . $download_link . '">' . $data['filename'] . '</a>';
    }
    else {
        $output .= $data['filename'];
    }

    $output .= '</li>';

    return $output;
}

?>
<div class="row">
    <div class="col-xs-12 col-sm-12 col-lg-6 col-lg-offset-3">
        <div class="white-box">
            <div class="white-box-interior">
                <div class="text-center">
                    <?php
                        switch ( $mode ) {
                            case 'files':
                                    $title = __('Public groups and files','cftp_admin');
                                    $desc = null;
                                break;
                            case 'group':
                                    $title = $test_group['name'];
                                    $desc = htmlentities_allowed($test_group['description']);
                                break;
                        }
                    ?>
                    <h3><?php echo $title; ?></h3>
                    <div class="intro">
                        <?php echo $desc; ?>
                    </div>
                </div>

                <div class="treeview">
                    <div class="listing">
                        <ul class="list-unstyled">
                            <?php
                                /**
                                 * 1- Make a list of files IDs
                                 */
                                $all_files = array();
                                $public_files = array();
                                $expired_files = array();
                                $remove_files = array(); // used to remove file ids from the complete list after showing the groups so the files don't appear again on the list.
                                $files_sql = "SELECT * FROM " . TABLE_FILES;

                                /** All files or just the public ones? */
                                if ( get_option('public_listing_show_all_files') != 1 && $mode != 'group' ) {
                                    $files_sql .= " WHERE public_allow=1";
                                }

                                $sql = $dbh->prepare($files_sql);
                                $sql->execute();
                                $sql->setFetchMode(PDO::FETCH_ASSOC);
                                while ( $row = $sql->fetch() ) {

                                    /** Does it expire? */
                                    $add_file = true;
                                    $expired	= false;

                                    if ($row['expires'] == '1') {
                                        if (time() > strtotime($row['expiry_date'])) {
                                            if (get_option('expired_files_hide') == '1') {
                                                $add_file = false;
                                            }
                                            $expired = true;
                                        }
                                    }

                                    if ($add_file == true) {
                                        $filename_on_disk = (!empty( $row['original_url'] ) ) ? $row['original_url'] : $row['url'];

                                        $all_files[$row['id']] = array(
                                            'id' => encode_html($row['id']),
                                            'filename' => encode_html($filename_on_disk),
                                            'title' => encode_html($row['filename']),
                                            'public' => encode_html($row['public_allow']),
                                            'token' => encode_html($row['public_token']),
                                            'expired' => $expired,
                                            'expire_date' => encode_html($row['expiry_date']),
                                        );
                                        if ( $row['public_allow'] == 1 ) {
                                            $public_files[] = $row['id'];
                                        }
                                    }
                                    else {
                                        $expired_files[] = $row['id'];
                                    }
                                }

                                //print_array($all_files);

                                /**
                                 * 2- Get public groups
                                 */
                                $groups = array();
                                $found_groups = get_groups([
                                    'public' => true,
                                ]);
                                foreach ($found_groups as $group_id => $group_data) {
                                    $groups[$group_id] = array(
                                        'id' => $group_data['id'],
                                        'name'	=> $group_data['name'],
                                        'token'	=> $group_data['public_token'],
                                        'files'	=> array(),
                                    );
                                    /**
                                     * 3- Get list of files from this group
                                     */
                                    $group_files = array();
                                    $files_groups_sql = "SELECT id, file_id, client_id, group_id FROM " . TABLE_FILES_RELATIONS . " WHERE group_id=:group_id AND hidden = '0'";
                                    // Don't include private files
                                    if ( get_option('public_listing_show_all_files') != 1 ) {
                                        $files_groups_sql .= " AND FIND_IN_SET(file_id, :public_files)";
                                    }

                                    // Don't include expired files
                                    if (get_option('expired_files_hide') == '1') {
                                        $files_groups_sql .= " AND !FIND_IN_SET(file_id, :excluded_files)";
                                    }

                                    $sql = $dbh->prepare($files_groups_sql);
                                    $sql->bindParam(':group_id', $group_id, PDO::PARAM_INT);

                                    if ( get_option('public_listing_show_all_files') != 1 ) {
                                        $included_files = implode( ',', array_map( 'intval', array_unique( $public_files ) ) );
                                        $sql->bindParam(':public_files', $included_files);
                                    }
                                    if (get_option('expired_files_hide') == '1') {
                                        $excluded_files = implode( ',', array_map( 'intval', array_unique( $expired_files ) ) );
                                        $sql->bindParam(':excluded_files', $excluded_files);
                                    }

                                    $sql->execute();
                                    $sql->setFetchMode(PDO::FETCH_ASSOC);

                                    while ( $row = $sql->fetch() ) {
                                        $groups[$group_id]['files'][$row['file_id']] = $all_files[$row['file_id']];
                                        $remove_files[] = $row['file_id'];
                                    }
                                }

                                /**
                                 * Removes from the array of files those that are on, at least, one group
                                 * so in the list of groupless files they are not repeated.
                                 * Done here so if a file is on 2 groups, it won't get removed from any of them.
                                 */
                                foreach ( $remove_files as $file_id ) {
                                    unset($all_files[$file_id]);
                                }

                                //print_r($groups);
                                //print_r($all_files);

                                /**
                                 * Finally, generate the list
                                 * 1- Groups
                                 */

                                switch ( $mode ) {
                                    /**
                                    * 1- Loose files
                                    */
                                    case 'files':
                                        if ( empty( $all_files ) && empty( $groups ) ) {
                                            _e("There are no files available.",'cftp_admin');
                                        }
                                        else {
                                            foreach ( $groups as $group ) {
                                                $group_link = PUBLIC_GROUP_URL . '?group=' . $group['id'] . '&token=' . $group['token'];
                            ?>
                                                <li>
                                                    <a href="<?php echo $group_link; ?>">
                                                        <i class="fa fa-th-large fa-fw" aria-hidden="true"></i> <?php echo $group['name']; ?>
                                                    </a>
                                                </li>
                            <?php
                                            }
                                            foreach ( $all_files as $id => $file_info) {
                                                echo list_file($file_info, 'loose');
                                            }
                                        }
                                    break;

                                    /**
                                    * 2- Group files
                                    */
                                    case 'group':
                                        if ( !empty( $groups[$test_group['id']]['files'] ) ) {
                                            foreach ( $groups[$test_group['id']]['files'] as $id => $file_info) {
                                                echo list_file($file_info, 'group');
                                            }
                                        }
                                        else {
                                            _e("There are no files available.",'cftp_admin');
                                        }
                                    break;
                                }

                                //print_array($all_files);
                            ?>
                        </ul>
                    </div>

                </div>
            </div>
        </div>

        <div class="login_form_links">
            <?php
                if ( !user_is_logged_in() && get_option('clients_can_register') == '1') {
            ?>
                    <p id="register_link"><?php _e("Don't have an account yet?",'cftp_admin'); ?> <a href="<?php echo BASE_URI; ?>register.php"><?php _e('Register as a new client.','cftp_admin'); ?></a></p>
            <?php
                }
            ?>
            <p><a href="<?php echo BASE_URI; ?>" target="_self"><?php _e('Go back to the homepage.','cftp_admin'); ?></a></p>
        </div>
    </div>
</div>

<?php
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
