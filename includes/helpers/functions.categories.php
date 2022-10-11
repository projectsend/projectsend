<?php
/**
 * Contains all the functions related to categories
 */

function get_category($id)
{
    $dbh = get_dbh();

    $return = '';

    $file_count = 0;
    $statement = $dbh->prepare("SELECT COUNT(file_id) as count FROM " . TABLE_CATEGORIES_RELATIONS . " WHERE cat_id = :cat_id GROUP BY cat_id");
    $statement->bindParam(':cat_id', $id, PDO::PARAM_INT);
    $statement->execute();
    $statement->setFetchMode(PDO::FETCH_ASSOC);
    $file_count = $statement->rowCount();

    $statement = $dbh->prepare("SELECT * FROM " . TABLE_CATEGORIES . " WHERE id = :id");
    $statement->bindParam(':id', $id, PDO::PARAM_INT);
    $statement->execute();
    if ($statement->rowCount() > 0) {
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        while ($row = $statement->fetch()) {
            $return = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'parent' => (empty($row['parent'])) ? 0 : $row['parent'],
                'description' => $row['description'],
                'created_by' => $row['created_by'],
                'timestamp' => $row['timestamp'],
                'depth' => 0,
                'file_count' => $file_count,
                'children' => null,
            );
        }
    }

    return $return;
}

function get_categories($params = [])
{
    $dbh = get_dbh();
    $sql_params = [];

    // Set some defaults
    $orderby = (!empty($params['orderby'])) ? $params['orderby'] : 'name';
    $order = (!empty($params['order'])) ? $params['order'] : 'ASC';
    $parent = (!empty($params['parent'])) ? $params['parent'] : false;
    $is_tree = (!empty($params['is_tree'])) ? $params['is_tree'] : false;
    $id = (!empty($params['id'])) ? $params['id'] : array();
    // pagination
    $page = (!empty($params['page'])) ? $params['page'] : '';

    /**
     * By default, count files assigned to each category.
     * Avoids doing this individually later if needed.
     */
    $files_count = [];
    $statement = $dbh->prepare("SELECT cat_id, COUNT(file_id) as count FROM " . TABLE_CATEGORIES_RELATIONS . " GROUP BY cat_id");
    $statement->execute();
    $statement->setFetchMode(PDO::FETCH_ASSOC);
    while ($row = $statement->fetch()) {
        $files_count[$row["cat_id"]] = $row["count"];
    }

    /**
     * Parameter ID can be an array or a single category ID
     */
    /*
	if ( $id ) {
		if ( is_array( $id ) ) {
			$set_id = implode( ',', array_map( 'intval', array_unique( $id ) ) );
			$conditions[]			= 'FIND_IN_SET(id, :categories)';
			$sql_params[':categories']	= $set_id;
		}
		else {
			$conditions[]		= 'ID = :id';
			$sql_params[':id']	= $id;
		}
	}
*/

    $return = array(
        'count' => 0,
        'no_results_type' => '',
        'categories' => [],
    );

    /** Begin construction of the SQL sentence */
    $sql = "SELECT * FROM " . TABLE_CATEGORIES;

    /** Add the search terms */
    if (isset($params['search']) && !empty($params['search'])) {
        $conditions[] = "(name LIKE :name OR description LIKE :description)";
        $return['no_results_type'] = 'search';
        $search_terms = '%' . $params['search'] . '%';
        $sql_params[':name'] = $search_terms;
        $sql_params[':description'] = $search_terms;
    }

    /*
		Clients can only manage their own categories
		TODO: Implement this
	*/
    /*
	if (CURRENT_USER_LEVEL == '0') {
		$conditions[] = "created_by = :username";
		$sql_params[':username'] = CURRENT_USER_USERNAME;
	}
	*/

    /**
     * Apply the conditions to the SQL sentence
     */
    if (!empty($conditions)) {
        foreach ($conditions as $index => $condition) {
            $sql .= ($index == 0) ? ' WHERE ' : ' AND ';
            $sql .= $condition;
        }
    }

    $sql .= " ORDER BY $orderby $order";

    $statement = $dbh->prepare($sql);
    $statement->execute($sql_params);

    /** Count results and add the value to the response array */
    $count = $statement->rowCount();
    $return['count'] = $count;

    /**
     * Repeat the query but this time, limited by pagination
     */

    if ($count > 0) {
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        /**
         * Fetch all initially to only do it once.
         */
        $rows = $statement->fetchAll();
        $found_categories = [];
        foreach ($rows as $row) {
            $file_count = (!empty($files_count) && array_key_exists($row['id'], $files_count)) ? $files_count[$row['id']] : 0;

            $continue = false;
            if (empty($id)) {
                $continue = true;
            } else {
                if (in_array($row['id'], $id)) {
                    $continue = true;
                }
            }

            if ($continue === true) {
                $found_categories[$row['id']] = array(
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'parent' => (empty($row['parent'])) ? 0 : $row['parent'],
                    'description' => $row['description'],
                    'created_by' => $row['created_by'],
                    'timestamp' => $row['timestamp'],
                    'depth' => 0,
                    'file_count' => $file_count,
                    'children' => null,
                );
            }
        }


        $statement->execute($sql_params);
        $count = $statement->rowCount();

        if ($is_tree == true) {
            $found_categories = add_missing_to_tree($found_categories, $files_count);
        }

        $return['arranged'] = arrange_categories($found_categories);

        $return['categories'] = $found_categories;
    }

    return $return;
}

function add_missing_to_tree($categories, $files_count)
{
    $dbh = get_dbh();
    $return = $categories;

    foreach ($categories as $category) {
        if (!empty($category['parent']) && !array_key_exists($category['parent'], $categories)) {
            $query = "SELECT * FROM " . TABLE_CATEGORIES . " WHERE id=:id";
            $statement = $dbh->prepare($query);
            $statement->bindParam(':id', $category['parent'], PDO::PARAM_INT);
            $statement->execute();
            while ($row = $statement->fetch()) {
                $file_count = (!empty($files_count) && array_key_exists($row['id'], $files_count)) ? $files_count[$row['id']] : 0;

                $return[$row['id']] = array(
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'parent' => (empty($row['parent'])) ? 0 : $row['parent'],
                    'description' => $row['description'],
                    'created_by' => $row['created_by'],
                    'timestamp' => $row['timestamp'],
                    'depth' => 0,
                    'file_count' => $file_count,
                    'children' => null,
                );

                if (!empty($row['parent']) && !array_key_exists($row['parent'], $return)) {
                    $return = add_missing_to_tree($return, $files_count);
                }
            }
        }
    }

    return $return;
}

/**
 * Arrange is an external recursive function
 * Returns an array of categories nested by parent
 */
function arrange_categories(array &$elements, $parent = 0, $depth = 0)
{
    $branch = [];

    foreach ($elements as $element) {
        if ($element['parent'] == $parent) {
            $element['depth'] = $depth++;
            $children = arrange_categories($elements, $element['id'], $depth);

            if ($children) {
                $element['children'] = $children;
            }

            $branch[$element['id']] = $element;
            $element['depth'] = $depth--;
        }
    }

    return $branch;
}

function render_categories_options(&$categories = [], $arguments = [])
{
    $return = '';
    if (empty($categories)) {
        return $return;
    }

    $arguments['selected'] = to_array_if_not($arguments['selected']);
    foreach ($categories as $id => $category) {
        $depth = ($category['depth'] > 0) ? str_repeat('&mdash;', $category['depth']) . ' ' : false;
        $selected = (!empty($arguments['selected']) && in_array($id, $arguments['selected'])) ? 'selected="selected"' : '';
        $return .= '<option '.$selected.' value="'.$id.'">'.$depth . $category['name'].'</option>';
        if (!empty($category['children'])) {
            $return .= render_categories_options($category['children'], $arguments);
        }
    }

    return $return;
}

function generate_categories_options(&$categories, $parent = 0, $selected = [], $filter_type = '', $filter_values = [0])
{
    $return = [];

    if (!empty($categories)) {
        foreach ($categories as $category) {
            $is_selected = (in_array($category['id'], $selected)) ? true : false;

            $add_children = true;
            $add_to_results = true;
            if (!empty($filter_type)) {
                switch ($filter_type) {
                    case 'include':
                        if (!in_array($category['id'], $filter_values)) {
                            $add_to_results = false;
                        }
                        break;
                    case 'exclude':
                        if (in_array($category['id'], $filter_values)) {
                            $add_to_results = false;
                        }
                    case 'exclude_and_children':
                        if (in_array($category['id'], $filter_values)) {
                            $add_to_results = false;
                            $add_children = false;
                        }
                        break;
                }
            }

            if ($add_to_results === true) {
                $return[$category['id']] = [
                    'value' => $category['id'],
                    'selected' => $is_selected,
                    'depth' => $category['depth'],
                    'children' => [],
                    'name' => html_output($category['name']),
                ];
            }

            if ($add_children === true) {
                $children = $category['children'];
                if (!empty($children)) {
                    $return[$category['id']]['children'] = generate_categories_options($children,
                        $category['parent'],
                        $selected,
                        $filter_type,
                        $filter_values
                    );
                }
            }
        }
    }

    return $return;
}
