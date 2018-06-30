<?php
/**
 * Common information used on all clients templates.
 * Avoids the need to define all of this when creating a new template.
 *
 * @package		ProjectSend
 * @subpackage	Templates
 */

/**
 * Since the header.php file is shared between the back-end and the
 * templates, it's necessary to define the allowed levels, or else
 * the files list will not be available.
 */
$allowed_levels = array(9,8,7,0);

/**
 * Define a variable that will tell header.php if session_start()
 * needs to be called or not (since it is also called from
 * session_check.php
 */
$is_template = true;

/**
 * Loads a language file from the current template folder based on
 * the system options.
 */
$lang = SITE_LANG;
if(!isset($ld)) { $ld = 'cftp_admin'; }
ProjectSend\I18n::LoadDomain(ROOT_DIR."/templates/".SELECTED_CLIENTS_TEMPLATE."/lang/{$lang}.mo", $ld);

$this_template = TEMPLATES_URI.'/'.SELECTED_CLIENTS_TEMPLATE.'/';

include_once(TEMPLATES_DIR . DS . 'session_check.php');

/**
 * URI to the default template CSS file.
 */
$this_template_css = $this_template.'/main.css';

global $dbh;

/**
 * Get all the client's information
 */
$client_info = get_client_by_username($this_user);

/**
 * Get the list of different groups the client belongs to.
 */
$get_groups		= new ProjectSend\MembersActions();
$get_arguments	= array(
						'client_id'	=> $client_info['id'],
						'return'	=> 'list',
					);
$found_groups	= $get_groups->client_get_groups($get_arguments);

/**
 * Define the arrays so they can't be empty
 */
$found_all_files_array	= array();
$found_own_files_temp	= array();
$found_group_files_temp	= array();

/** Category filter */
if ( !empty( $_GET['category'] ) ) {
	$category_filter = $_GET['category'];
}

/**
 * Get the client's own files
 * Construct the query first.
 */
$files_query = "SELECT id, file_id, client_id, group_id FROM " . TABLE_FILES_RELATIONS . " WHERE (client_id = :id";
if (!empty($found_groups)) {
	$files_query .= " OR FIND_IN_SET(group_id, :groups)";
}
$files_query .= ") AND hidden = '0'";

$files_sql = $dbh->prepare($files_query);

$files_sql->bindParam(':id', $client_info['id'], PDO::PARAM_INT);
if (!empty($found_groups)) {
	$files_sql->bindParam(':groups', $found_groups);
}

$files_sql->execute();
$files_sql->setFetchMode(PDO::FETCH_ASSOC);

while ( $row_files = $files_sql->fetch() ) {
	if (!is_null($row_files['client_id'])) {
		$found_all_files_array[]	= $row_files['file_id'];
		$found_own_files_temp[]		= $row_files['file_id'];
	}
	if (!is_null($row_files['group_id'])) {
		$found_all_files_array[]	= $row_files['file_id'];
		$found_group_files_temp[]	= $row_files['file_id'];
	}
}

$found_own_files_ids	= (!empty($found_own_files_temp)) ? implode(',', array_unique($found_own_files_temp)) : '';
$found_group_files_ids	= (!empty($found_group_files_temp)) ? implode(',', array_unique($found_group_files_temp)) : '';

$found_unique_files_ids = array_unique($found_all_files_array);

/**
 * Make an array of the categories containing the
 * files found for this account.
 */
$cat_ids	= array();
$file_ids	= array();
$files_keep	= array();

$files_ids_to_search = implode(',', $found_unique_files_ids);
$sql_sentence = "SELECT file_id, cat_id FROM " . TABLE_CATEGORIES_RELATIONS . " WHERE FIND_IN_SET(file_id, :files)";
$sql_client_categories = $dbh->prepare( $sql_sentence );
$sql_client_categories->bindParam(':files', $files_ids_to_search);
$sql_client_categories->execute();
$sql_client_categories->setFetchMode(PDO::FETCH_ASSOC);

while ( $row = $sql_client_categories->fetch() ) {
	$cat_ids[$row['cat_id']]		= $row['cat_id'];
	$files_keep[$row['file_id']][]	= $row['cat_id'];
}

if ( !empty( $cat_ids ) ) {
	$get_categories	= get_categories(
									array(
										'id'	=> $cat_ids,
									)
								);
}
/**
 * With the categories generated, keep only the files
 * that are assigned to the selected one.
 */
if ( !empty( $category_filter ) && $category_filter != '0' ) {
	$filtered_file_ids = array();
	foreach ( $files_keep as $keep_file_id => $keep_cat_ids ) {
		if ( in_array( $category_filter, $keep_cat_ids ) ) {
			$filtered_file_ids[] = $keep_file_id;
		}
	}
	$ids_to_search = implode(',', $filtered_file_ids);
}
else {
	$ids_to_search = implode(',', $found_unique_files_ids);
}

/** Create the files list */
$my_files = array();


if (!empty($found_own_files_ids) || !empty($found_group_files_ids)) {
	$f = 0;
	$files_query = "SELECT * FROM " . TABLE_FILES . " WHERE FIND_IN_SET(id,:search_ids)";

	$params		= array(
						':search_ids' => $ids_to_search
					);

	/** Add the search terms */
	if ( isset($_GET['search']) && !empty($_GET['search']) ) {
		$files_query		.= " AND (filename LIKE :title OR description LIKE :description)";
		$no_results_error	= 'search';

		$params[':title']		= '%'.$_GET['search'].'%';
		$params[':description']	= '%'.$_GET['search'].'%';
	}


	/**
	 * Add the order.
	 * Defaults to order by: timestamp, order: DESC (shows last uploaded files first)
	 */
	$files_query .= sql_add_order( TABLE_FILES, 'timestamp', 'desc' );

	/**
	 * Pre-query to count the total results
	*/
	$count_sql = $dbh->prepare( $files_query );
	$count_sql->execute($params);
	$count_for_pagination = $count_sql->rowCount();

	/**
	 * Repeat the query but this time, limited by pagination
	 */
	$files_query .= " LIMIT :limit_start, :limit_number";
	$sql_files = $dbh->prepare( $files_query );

	$pagination_page			= ( isset( $_GET["page"] ) ) ? $_GET["page"] : 1;
	$pagination_start			= ( $pagination_page - 1 ) * TEMPLATE_RESULTS_PER_PAGE;
	$params[':limit_start']		= $pagination_start;
	$params[':limit_number']	= TEMPLATE_RESULTS_PER_PAGE;

	$sql_files->execute( $params );
	$count = $sql_files->rowCount();

	$sql_files->setFetchMode(PDO::FETCH_ASSOC);
	while ( $data = $sql_files->fetch() ) {
		$add_file	= true;
		$expired	= false;

		/** Does it expire? */
		if ($data['expires'] == '1') {
			if (time() > strtotime($data['expiry_date'])) {
				if (EXPIRED_FILES_HIDE == '1') {
					$add_file = false;
				}
				$expired = true;
			}
		}

		/** Make the list of files */
		if ($add_file == true) {
			/*
			if (in_array($data['id'], $found_own_files_temp)) {
				$origin = 'own';
			}
			if (in_array($data['id'], $found_group_files_temp)) {
				$origin = 'group';
			}
			*/
			$pathinfo = pathinfo($data['url']);

			$my_files[$f] = array(
								//'origin'		=> $origin,
								'id'			=> $data['id'],
								'url'			=> $data['url'],
								'dir'			=> UPLOADED_FILES_DIR . DS . $data['url'],
								'save_as'		=> (!empty( $data['original_url'] ) ) ? $data['original_url'] : $data['url'],
								'extension'		=> strtolower($pathinfo['extension']),
								'name'			=> $data['filename'],
								'description'	=> $data['description'],
								'timestamp'		=> $data['timestamp'],
								'expires'		=> $data['expires'],
								'expiry_date'	=> $data['expiry_date'],
								'expired'		=> $expired,
							);
			$f++;
		}
	}

}

// DEBUG
//echo '<pre>'; print_r($my_files); echo '</pre>';


/** Get the url for the logo from "Branding" */
$logo_file_info = generate_logo_url();
