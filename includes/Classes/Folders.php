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
        }  else {
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
        $folders = [];
    
        /**
         * Get the actually requested items
         */
        $query = "SELECT * FROM " . TABLE_FOLDERS;
        $params = [];
    
        // Parent
        if (isset($arguments['parent'])) {
            $query .= " WHERE parent = :parent";
            $params[':parent'] = (int)$arguments['parent'];
        } else {
            $query .= " WHERE parent IS NULL";
        }
    
        // Search
        if (isset($arguments['search'])) {
            $query .= " AND (name LIKE :name OR slug LIKE :slug)";
        
            $search_terms = '%' . $arguments['search'] . '%';
            $params[':name'] = $search_terms;
            $params[':slug'] = $search_terms;
        }

        if (isset($arguments['include_public'])) {
            if ($arguments['include_public'] == true) {
                $query .= " AND public=:public";
                $params[':public'] = '1';    
            }
        }
    
        if (isset($arguments['user_id'])) {
            $query .= " AND user_id=:user_id";
            $params[':user_id'] = $arguments['user_id'];
        }
        
        if (isset($arguments['public_or_client']) && $arguments['public_or_client'] == true) {
            $query .= " AND (public=:public OR user_id=:client_id)";
            $params[':public'] = '1';
            $params[':client_id'] = $arguments['client_id'];
        }
    
        $statement = $this->dbh->prepare($query);
        $statement->execute($params);
        if ($statement->rowCount() > 0) {
            $statement->setFetchMode(\PDO::FETCH_ASSOC);
            while ($row = $statement->fetch()) {
                $obj = new \ProjectSend\Classes\Folder($row['id']);
                $folders[$row['id']] = $obj->getData();
            }
        }
    
        $this->folders = $folders;

        return $this->folders;
    }


    function getAllArranged($parent = null, $depth = 0, $include = [])
    {
        $data = [];
        $folders = $this->getfolders(['parent' => $parent]);
        if(!empty($folders)){
            foreach ($folders as $folder_id => $folder) {
                if (!empty($include) && !in_array($folder_id, $include)) {
                    continue;
                }

                $depth++;
                $folder['depth'] = $depth;
                if ($folder['parent'] == null) {
                    $depth = 0;
                }
                $folder['children'] = $this->getAllArranged($folder['id'], $depth, $include);
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
                // continue;
            } else {
                $return .= '<option '.$selected.' value="'.$folder['id'].'">'.$depth_indicator . $folder['name'].'</option>';
            }
            if (!empty($folder['children'])) {
                $return .= $this->renderSelectOptions($folder['children'], $arguments);
            }
        }

        return $return;
    }
}
