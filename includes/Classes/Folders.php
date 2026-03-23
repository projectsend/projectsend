<?php
namespace ProjectSend\Classes;

class Folders
{
    protected $folders;
    protected $arranged_folders;
    protected $dbh;
    protected $logger;

    public function __construct()
    {
        global $dbh;
        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;
    }

    function makeFolderBreadcrumbs($from_folder_id, $url = BASE_URI) {
        $base_url = strtok($url, '?');
        $parsed = parse_url($url);
        if (!empty($parsed['query'])) {
            $query = $parsed['query'];
            parse_str($query, $params);
            $params_remove = ['folder_id', 'search', 'assigned', 'uploader'];
            foreach ($params_remove as $param) {
                unset($params[$param]);
            }
        } else {
            $params = [];
        }
    
        $elements = [
            [
                'url' => $base_url,
                'name' => 'Files root',
            ],
        ];
    
        if (!empty($from_folder_id)) {
            $folder = new \ProjectSend\Classes\Folder($from_folder_id);
            $nested = $folder->getHierarchy();
            if (!empty($nested)) {
                $nested = array_reverse($nested);
    
                foreach ($nested as $folder) {
                    $params['folder_id'] = $folder['id'];
                    $url = ($folder['id'] != $from_folder_id) ? $base_url.'?'.http_build_query($params) : null;
                    $elements[] = [
                        'url' => $url,
                        'name' => $folder['name'],
                    ];
                }
            }
        }
    
        return $elements;
    }

    function getFolders($arguments = [])
    {
        // // Existing public flag fix
        // $queryx = "UPDATE `tbl_folders` set public = 1 ";
        // $statement = $this->dbh->prepare($queryx);
        // $statement->execute();

        // Initialize $folders as an empty array
        $folders = [];
        
        // Get client access level if not provided
        if (!isset($arguments['role']) && isset($arguments['user_id'])) {
            $arguments['role'] = $this->getUserRole($arguments['user_id']);
            if ($arguments['role'] === 'Client' && !isset($arguments['client_id'])) {
                $arguments['client_id'] = $arguments['user_id'];
            }
        }
    
        $query = "SELECT DISTINCT f.* FROM " . TABLE_FOLDERS . " f";
        $params = [];
        if (isset($arguments['role']) && $arguments['role'] === 'Client' && isset($arguments['client_id'])) {
            $query .= " WHERE (
            -- Folders created by the client
            f.user_id = :client_created
            OR
            -- Get folders that contain files created by current user
            EXISTS (
                SELECT 1
                FROM " . TABLE_FILES . " tf
                WHERE tf.folder_id = f.id
                AND tf.user_id = :current_user_id
            )
            OR
            -- Get folders through direct file assignments or group memberships
            EXISTS (
                SELECT 1
                FROM " . TABLE_FILES_RELATIONS . " fr
                JOIN " . TABLE_FILES . " tf ON fr.file_id = tf.id
                WHERE tf.folder_id = f.id
                AND fr.hidden = 0
                AND (
                    -- Direct client assignment
                    fr.client_id = :client_id
                    OR
                    -- Group assignment
                    fr.group_id IN (
                        SELECT group_id
                        FROM " . TABLE_MEMBERS . "
                        WHERE client_id = :client_id_groups
                    )
                )
            )
        )";
        $params[':client_created'] = $arguments['client_id'];
        $params[':current_user_id'] = $arguments['client_id'];
        $params[':client_id'] = $arguments['client_id'];
        $params[':client_id_groups'] = $arguments['client_id'];

            // For clients, we need to also include parent folders of accessible folders
            // to display a complete folder tree. This is done in PHP after the query
            // to maintain compatibility with MySQL 5.7 (which lacks WITH RECURSIVE).
            $needs_parent_resolution = true;

            // Parent folder filter for clients
            if (array_key_exists('parent', $arguments)) {
                if (is_null($arguments['parent'])) {
                    $query .= " AND f.parent IS NULL";
                } else {
                    $query .= " AND f.parent = :parent";
                    $params[':parent'] = (int)$arguments['parent'];
                }
            }
        } else {
            // Admin access remains unchanged...
            $where_conditions = [];
            if (array_key_exists('parent', $arguments)) {
                if (is_null($arguments['parent'])) {
                    $where_conditions[] = "f.parent IS NULL";
                } else {
                    $where_conditions[] = "f.parent = :parent";
                    $params[':parent'] = (int)$arguments['parent'];
                }
            }
    
            if (isset($arguments['search'])) {
                $where_conditions[] = "(f.name LIKE :name OR f.slug LIKE :slug)";
                $search_terms = '%' . $arguments['search'] . '%';
                $params[':name'] = $search_terms;
                $params[':slug'] = $search_terms;
            }
    
            if (isset($arguments['include_public']) && $arguments['include_public'] == true) {
                $where_conditions[] = "f.public = :public";
                $params[':public'] = '1';    
            }
    
            if (isset($arguments['user_id'])) {
                $where_conditions[] = "f.user_id = :user_id";
                $params[':user_id'] = $arguments['user_id'];
            }
            
            if (isset($arguments['public_or_client']) && $arguments['public_or_client'] == true) {
                $where_conditions[] = "(f.public = :public_client OR f.user_id = :client_id)";
                $params[':public_client'] = '1';
                $params[':client_id'] = $arguments['client_id'];
            }
    
            if (!empty($where_conditions)) {
                $query .= " WHERE " . implode(" AND ", $where_conditions);
            }
        }
    
        $query .= " ORDER BY f.name ASC";
    
        $statement = $this->dbh->prepare($query);
        $statement->execute($params);
        
        // Initialize $folders before using it
        $folders = [];
        
        if ($statement->rowCount() > 0) {
            $statement->setFetchMode(\PDO::FETCH_ASSOC);
            while ($row = $statement->fetch()) {
                $obj = new \ProjectSend\Classes\Folder($row['id']);
                $folders[$row['id']] = $obj->getData();
            }
        }

        // For client access without a parent filter, resolve ancestor folders
        // so the full folder tree path is visible (e.g., if a client has access
        // to a file in "Level3/Level2/Level1", they need to see Level1 and Level2 too)
        if (!empty($needs_parent_resolution) && !array_key_exists('parent', $arguments) && !empty($folders)) {
            // Load all folders for parent lookups
            $all_folders_stmt = $this->dbh->prepare("SELECT id, parent FROM " . TABLE_FOLDERS);
            $all_folders_stmt->execute();
            $parent_map = [];
            while ($row = $all_folders_stmt->fetch(\PDO::FETCH_ASSOC)) {
                $parent_map[(int)$row['id']] = $row['parent'] !== null ? (int)$row['parent'] : null;
            }

            // Walk up parent chains for each accessible folder
            $ancestor_ids = [];
            foreach ($folders as $folder_id => $folder_data) {
                $current = isset($parent_map[(int)$folder_id]) ? $parent_map[(int)$folder_id] : null;
                while ($current !== null) {
                    if (isset($folders[$current]) || isset($ancestor_ids[$current])) {
                        break; // Already have this ancestor
                    }
                    $ancestor_ids[$current] = true;
                    $current = isset($parent_map[$current]) ? $parent_map[$current] : null;
                }
            }

            // Load and add ancestor folders
            foreach (array_keys($ancestor_ids) as $ancestor_id) {
                if (!isset($folders[$ancestor_id])) {
                    $obj = new \ProjectSend\Classes\Folder($ancestor_id);
                    if ($obj->getId()) {
                        $folders[$ancestor_id] = $obj->getData();
                    }
                }
            }
        }

        // For client access with a parent filter, get all accessible folder IDs
        // first, then filter to only those matching the requested parent
        if (!empty($needs_parent_resolution) && array_key_exists('parent', $arguments)) {
            // Get all accessible folder IDs (without parent filter)
            $args_no_parent = $arguments;
            unset($args_no_parent['parent']);
            $all_accessible = $this->getAccessibleFolderIds($args_no_parent);

            // Filter to only folders that match the parent AND are accessible
            $folders = [];
            foreach ($all_accessible as $folder_id) {
                $obj = new \ProjectSend\Classes\Folder($folder_id);
                if (!$obj->getId()) continue;
                $data = $obj->getData();
                $folder_parent = $data['parent'] !== null ? (int)$data['parent'] : null;
                $requested_parent = $arguments['parent'] !== null ? (int)$arguments['parent'] : null;
                if ($folder_parent === $requested_parent) {
                    $folders[$folder_id] = $data;
                }
            }
        }

        $this->folders = $folders;
        return $this->folders;
    }


    /**
     * Get all folder IDs accessible to a client, including ancestor folders.
     * Used internally to support parent-filtered queries without recursive CTEs.
     *
     * @param array<string, mixed> $arguments
     * @return array<int>
     */
    private function getAccessibleFolderIds(array $arguments): array
    {
        // Get directly accessible folders (without parent filter, without parent resolution)
        $saved_folders = $this->folders;
        $folders_obj = new self();

        // Temporarily call getFolders without parent filter to get all accessible folders
        // This will trigger parent resolution and return the full set
        $all_folders = $folders_obj->getFolders($arguments);

        $this->folders = $saved_folders;

        return array_map('intval', array_keys($all_folders));
    }

    function getUserRole($user_id)
    {
        $query = "SELECT r.name FROM " . TABLE_USERS . " u
                  JOIN " . TABLE_ROLES . " r ON u.role_id = r.id
                  WHERE u.id = :user_id";
        $statement = $this->dbh->prepare($query);
        $statement->execute([':user_id' => $user_id]);
        $result = $statement->fetch(\PDO::FETCH_ASSOC);
        return ($result) ? $result['name'] : null;
    }

    function getAllArranged($parent = null, $depth = 0, $include = [])
    {
        $data = [];
        $folders = $this->getFolders(['parent' => $parent]);
        if (!empty($folders)) {
            foreach ($folders as $folder_id => $folder) {
                if (!empty($include) && !in_array($folder_id, $include)) {
                    continue;
                }

                // Set depth based on parent
                if ($folder['parent'] == null) {
                    $folder['depth'] = 0;
                    $currentDepth = 0;
                } else {
                    $folder['depth'] = $depth + 1;
                    $currentDepth = $depth + 1;
                }
                
                // Get child elements with current depth
                $folder['children'] = $this->getAllArranged($folder['id'], $currentDepth, $include);
                $data[] = $folder;
            }
        }
    
        return $data;
    }

    function renderSelectOptions(&$folders = [], $arguments = [])
    {
        $return = '';
        if (empty($folders)) {
            return $return;
        }

        foreach ($folders as $folder) {
            $depth_indicator = ($folder['depth'] > 0) ? str_repeat('&mdash;', $folder['depth']) . ' ' : false;
            $selected = (!empty($arguments['selected']) && $arguments['selected'] == $folder['id']) ? 'selected="selected"' : '';
            if (!empty($arguments['ignore']) && in_array($folder['id'], $arguments['ignore'])) {
                continue;
            }
            $return .= '<option '.$selected.' value="'.$folder['id'].'">'.$depth_indicator . $folder['name'].'</option>';
            
            if (!empty($folder['children'])) {
                $return .= $this->renderSelectOptions($folder['children'], $arguments);
            }
        }

        return $return;
    }
}
