<?php
/**
 * Class that handles all the actions and functions that can be applied to
 * the already uploaded files.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */
namespace ProjectSend\Classes;

use \ProjectSend\Classes\Validation;
use \PDO;

class Files
{
    public $id;
    public $user_id;
    public $title;
    public $description;
    public $uploaded_by;
    public $filename_on_disk; // the safe name given to the file to ensure uniqueness when moving it to the uploads directory
    public $filename_original; // the original filename as the user uploads it
    public $filename_unfiltered; // save as
    public $download_link;
    public $expires;
    public $expired;
    public $expiry_date;
    public $assignments_clients;
    public $assignments_groups;
    public $categories;
    public $uploaded_date;
    public $extension;
    public $size;
    public $size_formatted;
    public $public;
    public $public_token;
    public $public_url;
    public $location;
    public $full_path;

    private $is_filetype_allowed;

    public function __construct(PDO $dbh = null)
    {
        if (empty($dbh)) {
            global $dbh;
        }

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;

        $this->location = UPLOADED_FILES_DIR;

        $this->is_filetype_allowed = false;

        $this->assignments_clients = [];
        $this->assignments_groups = [];
        $this->categories = [];
    }

    public function __get($name)
    {
        return html_output($this->$name);
    }

    /**
     * Set the ID
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Return the ID
     * @return int
     */
    public function getId()
    {
        if (!empty($this->id)) {
            return $this->id;
        }

        return false;
    }

    public function currentUserCanEdit()
    {
        return userCanEditFile(CURRENT_USER_ID, $this->id);
    }

    /**
     * Set the properties when saving to the database (data comnes from the form)
     */
    public function set($arguments = [])
    {
		$this->title = (!empty($arguments['title'])) ? encode_html($arguments['title']) : null;
        $this->description = (!empty($arguments['description'])) ? encode_html($arguments['description']) : null;
        $this->uploaded_by = (!empty($arguments['uploaded_by'])) ? encode_html($arguments['uploaded_by']) : null;
        $this->filename_on_disk = (!empty($arguments['filename'])) ? $arguments['filename'] : null;
        $this->filename_original = (!empty($arguments['filename_original'])) ? (int)$arguments['filename_original'] : 0;
        $this->expires = (!empty($arguments['expires'])) ? (int)$arguments['expires'] : 0;
        $this->expiry_date = (!empty($arguments['expiry_date'])) ? $arguments['expiry_date'] : null;
        $this->uploaded_date = (!empty($arguments['uploaded_date'])) ? $arguments['uploaded_date'] : null;
        $this->public = (!empty($arguments['public'])) ? (int)$arguments['public'] : 0;
		$this->public_token = (!empty($arguments['public_token'])) ? encode_html($arguments['public_token']) : null;

        // Assignations
		$this->assignations_groups = !empty( $arguments['assignations_groups'] ) ? to_array_if_not($arguments['assignations_groups']) : null;
		$this->assignations_clients = !empty( $arguments['assignations_clients'] ) ? to_array_if_not($arguments['assignations_clients']) : null;

        $this->categories = !empty( $arguments['categories'] ) ? to_array_if_not($arguments['categories']) : null;

        $this->setFullPath();
        $this->setExtension();
        $this->isFiletypeAllowed();
        $this->isExpired();
    }

    /**
     * Get existing user data from the database
     * @return bool
     */
    public function get($id)
    {
        $this->id = $id;

        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_FILES . " WHERE id=:id");
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        if ($statement->rowCount() == 0) {
            return false;
        }
    
        while ($row = $statement->fetch() ) {
            $this->id = html_output($row['id']);
            $this->user_id = html_output($row['user_id']);
            $this->title = html_output($row['filename']);
            $this->description = htmlentities_allowed($row['description']);
            $this->uploaded_by = html_output($row['uploader']);
            $this->filename_on_disk = html_output($row['url']);
            $this->filename_original = (!empty( $row['original_url'] ) ) ? html_output($row['original_url']) : html_output($row['url']);
            $this->filename_unfiltered = $row['original_url'];
            $this->download_link = make_download_link(array('id' => $this->id));
            $this->expires = html_output($row['expires']);
            $this->expiry_date = html_output($row['expiry_date']);
            $this->uploaded_date = html_output($row['timestamp']);
            $this->public = html_output($row['public_allow']);
            $this->public_token = html_output($row['public_token']);
            $this->public_url = BASE_URI . 'download.php?id=' . $this->id . '&token=' . $this->public_token;
        }

        $this->setFullPath();
        $this->isExpired();
        $this->setExtension();
        $this->getSize();

        $this->getCurrentAssignments();
        $this->getCurrentCategories();

        return true;
    }

    public function getCurrentAssignments()
    {
        $statement = $this->dbh->prepare("SELECT file_id, client_id, group_id FROM " . TABLE_FILES_RELATIONS . " WHERE file_id = :id");
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $statement->execute();
        if ($statement->rowCount() > 0) {
            while ( $row = $statement->fetch() ) {
                if (!empty($row['client_id'])) {
                    $this->assignments_clients[] = $row['client_id'];
                }
                elseif (!empty($row['group_id'])) {
                    $this->assignments_groups[] = $row['group_id'];
                }
            }
        }
    }

    public function getCurrentCategories()
    {
        $statement = $this->dbh->prepare("SELECT cat_id FROM " . TABLE_CATEGORIES_RELATIONS . " WHERE file_id = :id");
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $statement->execute();
        if ($statement->rowCount() > 0) {
            while ( $row = $statement->fetch() ) {
                $this->categories[] = $row['cat_id'];
            }
        }
    }

    public function refresh()
    {
        if (!empty($this->id)) {
            $this->get($this->id);
        }
    }

    public function isExpired()
    {
        $this->expired = false;
        if ($this->expires == '1' && time() > strtotime($this->expiry_date)) {
            $this->expired = true;
        }
    }

    public function isPublic()
    {
        if ($this->public == '1') {
            return true;
        }

        return false;
    }

    public function getTypeByMime()
    {
        if (!$this->existsOnDisk()) {
            return null;
        }

        $mimeType = mime_content_type($this->full_path);
        return explode('/', $mimeType)[0];
    }

    public function isImage()
    {
        if ($this->getTypeByMime() == 'image') {
            return true;
        }

        return false;
    }

    public function getData()
    {
        $data = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'uploaded_by' => $this->uploaded_by,
            'filename_on_disk' => $this->filename_on_disk,
            'filename_original' => $this->filename_original,
            'extension' => $this->extension,
            'expires' => $this->expires,
            'expiry_date' => $this->expiry_date,
            'expired' => (bool)$this->expired,
            'uploaded_date' => $this->uploaded_date,
            'public' => $this->public,
            'public_token' => $this->public_token,
            'public_url' => $this->public_url,
            'assignments' => [
                'clients' => $this->assignments_clients,
                'groups' => $this->assignments_groups,
            ],
            'categories' => $this->categories,
        ];

        return $data;
    }

    public function getSafeFilename()
    {
        return $this->filename_on_disk;
    }

    /**
     * Construct the full path with the uploads directory location
     *
     * @return void
     */
    private function setFullPath()
    {
        $this->full_path = $this->location . DS . $this->filename_on_disk;
    }

    /**
     * Sets the size in bytes and in a more human readable format
     *
     * @return void
     */
    public function getSize()
    {
        if ($this->filename_on_disk)
        {
            if ( file_exists( $this->full_path ) ) {
                $this->size = get_real_size($this->full_path);
                $this->size_formatted = format_file_size($this->full_path);
            }
            else {
                $this->size = '0';
                $this->size_formatted = '-';
            }

            // $this->size = filesize($this->full_path);
            $this->size_formatted = format_file_size($this->size);
        }

        return false;
    }

    public function existsOnDisk()
    {
        if ( file_exists( $this->full_path ) ) {
            return true;
        }

        return false;
    }

    public function setExtension()
    {
        $this->extension = pathinfo($this->filename_on_disk, PATHINFO_EXTENSION);
    }

    public function getExtension()
    {
        if (empty($this->extension)) {
            $this->setExtension();
        }

        return $this->extension;
    }

    /**
	 * Check if the file extension is among the allowed ones, that are defined on
	 * the options page.
	 */
	public function isFiletypeAllowed()
	{
		if ( true === CAN_UPLOAD_ANY_FILE_TYPE ) {
            $this->is_filetype_allowed = true;
		}
		else {
            $this->getExtension();

            $allowed_extensions = explode(',', get_option('allowed_file_types') );
            if (in_array($this->extension, $allowed_extensions)) {
                $this->is_filetype_allowed = true;
            }
        }
        
        return $this->is_filetype_allowed;
	}

	/**
	 * Convert a string into a url safe address.
	 * Original name: formatURL
	 * John Magnolia / svick on StackOverflow
	 *
	 * @param string $unformatted
	 * @return string
	 * @link http://stackoverflow.com/questions/2668854/sanitizing-strings-to-make-them-url-and-filename-safe
	 */
    public function generateSafeFilename($original_filename)
    {
        if (empty($original_filename)) {
            return false;
        }
        
		$original_filename = basename(trim($original_filename));
        $filename = generateSafeFilename($original_filename);
        
        // Set the properties
        $this->filename_original = $original_filename;
        $this->filename_on_disk = $filename;

        return $this->filename_on_disk;
	}
	
    /**
	 * Used to copy a file from the temporary folder (the default location where it's put
	 * after uploading it) to the final folder.
	 * If succesful, the original file is then deleted.
	 */
	public function moveToUploadDirectory($temp_name)
	{
        $safe_filename = $this->generateSafeFilename($temp_name);

		$this->uid = CURRENT_USER_ID;
		$this->username = CURRENT_USER_USERNAME;
		$this->makehash = sha1($this->username);

		$this->filename_on_disk = time().'-'.$this->makehash.'-'.$safe_filename;
		$this->path = UPLOADED_FILES_DIR.DS.$this->filename_on_disk;
		if (rename($temp_name, $this->path)) {

            @chmod($this->path, 0644);

            $this->return = array(
                'filename_original' => $this->filename_original,
                'filename_disk' => $this->filename_on_disk,
            );

            return $this->return;
		}
		else {
			return false;
		}
	}

    /**
     * Makes the file as hidden to a client or group
     */
	public function hide($to_type, $to_id) {
        $this->changeHiddenStatus(1, $to_type, $to_id);
    }

    /**
     * Makes the file as visible to a client or group
     */
	public function show($to_type, $to_id) {
        $this->changeHiddenStatus(0, $to_type, $to_id);
    }

    /**
     * Makes the change on the database to hide or show a file
     *
     * @param [type] Group or client, changes the column on the query
     * @param [type] ID of the group or client
     * @return void
     */
	private function changeHiddenStatus($status, $to_type, $to_id)
	{
        $this->check_level = array(9,8,7);
        
        if (empty($this->id)) {
            return false;
        }

        switch ($status) {
            case 1:
                $log_action_number = 21;
                break;
            case 0:
                $log_action_number = 22;
                break;
            default:
                throw new \Exception('Invalid status code');
                return false;
        }

        switch ($to_type) {
            case 'client':
                $column = 'client_id';
                break;
            case 'group':
                $column = 'group_id';
                break;
            default:
                throw new \Exception('Invalid modify type');
                return false;
        }

        /** Do a permissions check */
        if (isset($this->check_level) && current_role_in($this->check_level)) {
            $sql = "UPDATE " . TABLE_FILES_RELATIONS . " SET hidden=:hidden WHERE file_id = :file_id AND " . $column . " = :entity_id";
            $statement = $this->dbh->prepare($sql);
            $statement->bindParam(':hidden', $status, PDO::PARAM_INT);
            $statement->bindParam(':file_id', $this->id, PDO::PARAM_INT);
            $statement->bindParam(':entity_id', $to_id, PDO::PARAM_INT);
            $statement->execute();

            unset($this->check_level);

            /** Record the action log */
            $record = $this->logger->addEntry([
                'action' => $log_action_number,
                'owner_id' => CURRENT_USER_ID,
                'affected_file' => $this->id,
                'affected_file_name' => $this->title
            ]);

            return true;
        }

        return false;
	}

	public function hideForEveryone()
	{
        $this->check_level = array(9,8,7);

        if (empty($this->id)) {
            return false;
        }

        /** Do a permissions check */
        if (isset($this->check_level) && current_role_in($this->check_level)) {
            $statement = $this->dbh->prepare("UPDATE " . TABLE_FILES_RELATIONS . " SET hidden='1' WHERE file_id = :file_id");
            $statement->bindParam(':file_id', $this->id, PDO::PARAM_INT);
            $statement->execute();

            unset($this->check_level);

            /** Record the action log */
            $record = $this->logger->addEntry([
                'action' => 40,
                'owner_id' => CURRENT_USER_ID,
                'affected_file' => $this->id,
                'affected_file_name' => $this->title
            ]);

            return true;
        }

        return false;
	}

    /**
     * Delete the file and its thumbnails
     *
     * @return bool
     */
    function deleteFiles()
	{
		$this->can_delete = false;
		$this->check_level = array(9,8,7,0);

        if (empty($this->id) || empty($this->uploaded_by)) {
            return false;
        }

        // Clients can only delete files if allowed
        if (CURRENT_USER_LEVEL == '0') {
            if (get_option('clients_can_delete_own_files') != '1') {
                return false;
            }

            if ($this->uploaded_by != CURRENT_USER_USERNAME) {
                return false;
            }
        }
        
        // Uploaders can only delete their own files
        if ( CURRENT_USER_LEVEL == '7' ) {
            if ( $this->uploaded_by != CURRENT_USER_USERNAME ) {
                return false;
            }
        }

        /** Do a permissions check */
        if (isset($this->check_level) && current_role_in($this->check_level)) {
            /*
             * Thumbnails should be deleted too.
             * Start by making a pattern with the file name, a shorter version of what's
             * used on make_thumbnail.
            */
            $this->thumbnails_pattern = 'thumb_' . md5($this->filename_on_disk);
            $this->find_thumbnails = glob( THUMBNAILS_FILES_DIR . DS . $this->thumbnails_pattern . '*.*' );
            //print_array($this->find_thumbnails);

            // Delete the reference to the file on the database
            if ( true === $this->can_delete ) {
                $sql = $this->dbh->prepare("DELETE FROM " . TABLE_FILES . " WHERE id = :file_id");
                $sql->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
                $sql->execute();
                // Use the id and uri information to delete the file.
                delete_file_from_disk(UPLOADED_FILES_DIR . DS . $this->filename_on_disk);
                
                // Delete the thumbnails
                foreach ( $this->find_thumbnails as $this->thumbnail ) {
                    delete_file_from_disk($this->thumbnail);
                }

                /** Record the action log */
                $record = $this->logger->addEntry([
                    'action' => 12,
                    'owner_id' => CURRENT_USER_ID,
                    'affected_file' => $this->id,
                    'affected_file_name' => $this->title
                ]);
    
                return true;
            }

            unset($this->check_level);
        }
        
        return false;
    }
    
    public function setDefaults()
    {
        $this->title = $this->filename_original;
        $this->description = null;
        $this->expires = 0;
        $this->public = 0;
        $this->expiry_date = date('Y-m-d', strtotime('+30 days'));
    }

    /**
	 * Called after correctly moving the file to the final location.
	 */
	public function addToDatabase()
	{
		$this->uploader = CURRENT_USER_USERNAME;
		$this->uploader_id = CURRENT_USER_ID;
		$this->uploader_type = CURRENT_USER_TYPE;
		$this->hidden = 0;
        $this->public_token = generateRandomString(32);
        $this->state = [];
		
        $statement = $this->dbh->prepare("INSERT INTO " . TABLE_FILES . " (user_id, url, original_url, filename, description, uploader, expires, expiry_date, public_allow, public_token)"
                                        ."VALUES (:user_id, :url, :original_url, :title, :description, :uploader, :expires, :expiry_date, :public, :public_token)");
        $statement->bindParam(':user_id', $this->uploader_id, PDO::PARAM_INT);
        $statement->bindParam(':url', $this->filename_on_disk);
        $statement->bindParam(':original_url', $this->filename_original);
        $statement->bindParam(':title', $this->title);
        $statement->bindParam(':description', $this->description);
        $statement->bindParam(':uploader', $this->uploader);
        $statement->bindParam(':expires', $this->expires, PDO::PARAM_INT);
        $statement->bindParam(':expiry_date', $this->expiry_date);
        $statement->bindParam(':public', $this->public, PDO::PARAM_INT);
        $statement->bindParam(':public_token', $this->public_token);
        $statement->execute();

        $this->file_id = $this->dbh->lastInsertId();
        $this->state['id'] = $this->file_id;
        $this->state['public_token'] = $this->public_token;
        $this->id = $this->file_id;

		if (!empty($this->file_id)) {
            /** Record the action log */
            if ($this->uploader_type == 'user') {
                $this->action_type = 5;
            }
            elseif ($this->uploader_type == 'client') {
                $this->action_type = 6;
            }
            $new_record_action = $this->logger->addEntry([
                'action' => $this->action_type,
                'owner_id' => $this->uploader_id,
                'affected_file' => $this->file_id,
                'affected_file_name' => $this->filename_original,
                'affected_account_name' => $this->uploader
            ]);

            return $this->state;
		}
		
		return false;
	}

    /**
	 * Update file information
	 */
	public function save($data)
	{
        if (empty($data)) {
            return false;
        }

        if (empty($this->id)) {
            return false;
        }

        if (!$this->currentUserCanEdit()) {
            return false;
        }

        $this->refresh();
        $current = $this->getData();

        if (isset($data["expiry_date"])) {
            $expiration = \DateTime::createFromFormat('d-m-Y', $data["expiry_date"]);
            $expiration_str = $expiration->format('Y-m-d');
        }

        // Set data
        $this->name = $data["name"];
        $this->description = $data["description"];
        $this->expires = (isset($data["expires"])) ? $data["expires"] : 0;
        $this->expiry_date = (isset($expiration_str)) ? $expiration_str : $current["expiry_date"];
        $this->is_public = (isset($data["public"])) ? $data["public"] : 0;
    
        /**
         * If a client is editing a file, only a few properties can be changed
         */
        if ( CURRENT_USER_LEVEL == 0 ) {
            $this->expires = $current["expires"];
            $this->expiry_date = $current["expiry_date"];
            $this->is_public = $current["public"];
        }

        if (empty($this->name)) {
            $this->name = $this->filename_original;
        }

        $statement = $this->dbh->prepare("UPDATE " . TABLE_FILES . " SET
            filename = :title,
            description = :description,
            expires = :expires,
            expiry_date = :expiry_date,
            public_allow = :public
            WHERE id = :id
        ");
        $statement->bindParam(':title', $this->name);
        $statement->bindParam(':description', $this->description);
        $statement->bindParam(':expires', $this->expires, PDO::PARAM_INT);
        $statement->bindParam(':expiry_date', $this->expiry_date);
        $statement->bindParam(':public', $this->is_public, PDO::PARAM_INT);
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $statement->execute();

        $hidden = (!empty($data['hidden']) && is_numeric($data['hidden'])) ? $data['hidden'] : 0;

		if (!empty($statement)) {
            // Update assignments
            $this->saveAssignments($data['assignments'], $hidden);
            $this->saveCategories($data['categories']);
            $this->refresh();

            /** Record the action log */
            if (CURRENT_USER_TYPE == 'user') {
                $this->action_type = 32;
            }
            elseif (CURRENT_USER_TYPE == 'client') {
                $this->action_type = 33;
            }
            $new_record_action = $this->logger->addEntry([
                'action' => $this->action_type,
                'owner_id' => CURRENT_USER_ID,
                'affected_file' => $this->id,
                'affected_file_name' => $this->name,
                'affected_account_name' => CURRENT_USER_NAME
            ]);

            return true;
		}
		
		return false;
	}

    // Assign
    public function saveAssignments($new_values, $hidden = 0)
    {
        $allowed = array(9,8,7);
        if (!current_role_in($allowed)) {
            return false;
        }

        if (empty($this->id)) {
            return false;
        }

        $hidden = (int)$hidden;

        // Get current assignments from database to compare with new values
        $current = [
            'clients' => $this->assignments_clients,
            'groups' => $this->assignments_groups,
        ];

        // Remove each item that is current but not on the new values
        foreach ($current['clients'] as $client_id) {
            if (!in_array($client_id, $new_values['clients'])) {
                $this->removeAssignment('client', $client_id);
            }
        }
        foreach ($current['groups'] as $group_id) {
            if (!in_array($group_id, $new_values['groups'])) {
                $this->removeAssignment('group', $group_id);
            }
        }

        // Create new relations
        foreach ($new_values['clients'] as $client_id) {
            if (!in_array($client_id, $current['clients'])) {
                $this->addAssignment('client', $client_id, $hidden);
            }
        }
        foreach ($new_values['groups'] as $group_id) {
            if (!in_array($group_id, $current['groups'])) {
                $this->addAssignment('group', $group_id, $hidden);
            }
        }
    }

    private function addAssignment($type = null, $to_id, $hidden = 0)
    {
        $allowed = array(9,8,7);
        if (!current_role_in($allowed)) {
            return false;
        }
        
        if (empty($this->id)) {
            return false;
        }

        switch ($type) {
            case 'client':
                $column = 'client_id';
                $log_action_number = 25;
                break;
            case 'group':
                $column = 'group_id';
                $log_action_number = 26;
                break;
            default:
                throw new \Exception('Invalid type');
                return false;
        }

        $statement = $this->dbh->prepare("INSERT INTO " . TABLE_FILES_RELATIONS . " (file_id, $column, hidden)"
                                                ."VALUES (:file_id, :assignment, :hidden)");
        $statement->bindParam(':file_id', $this->id, PDO::PARAM_INT);
        $statement->bindParam(':assignment', $to_id);
        $statement->bindParam(':hidden', $hidden, PDO::PARAM_INT);
        $statement->execute();
    }

    private function removeAssignment($from_type, $from_id)
	{
        $allowed = array(9,8,7);
        if (!current_role_in($allowed)) {
            return false;
        }
        
        if (empty($this->id)) {
            return false;
        }

        switch ($from_type) {
            case 'client':
                $column = 'client_id';
                $log_action_number = 10;
                break;
            case 'group':
                $column = 'group_id';
                $log_action_number = 11;
                break;
            default:
                throw new \Exception('Invalid modify type');
                return false;
        }

        $sql = "DELETE FROM " . TABLE_FILES_RELATIONS . " WHERE file_id = :file_id AND " . $column . " = :from_id";
        $statement = $this->dbh->prepare($sql);
        $statement->bindParam(':file_id', $this->id, PDO::PARAM_INT);
        $statement->bindParam(':from_id', $from_id, PDO::PARAM_INT);
        $statement->execute();

        if (!empty($statement)) {
            $record = $this->logger->addEntry([
                /** Record the action log */
                'action' => $log_action_number,
                'owner_id' => CURRENT_USER_ID,
                'affected_file' => $this->id,
                'affected_file_name' => $this->title
            ]);

            return true;
        }

        return false;
    }

    public function saveCategories($categories = null)
    {
        $allowed = array(9,8,7);
        if (!current_role_in($allowed)) {
            return false;
        }
        
        if (empty($this->id)) {
            return false;
        }

        $current = $this->categories;

        $remove = [];
        $create = [];

        // Remove each item that is current but not on the new values
        foreach ($current as $category_id) {
            if (!in_array($category_id, $categories)) {
                $this->removeFromCategory($category_id);
            }
        }

        // Create new relations
        foreach ($categories as $category_id) {
            if (!in_array($category_id, $current)) {
                $this->addToCategory($category_id);
            }
        }
    }

    private function removeFromCategory($category_id)
    {
        $sql = "DELETE FROM " . TABLE_CATEGORIES_RELATIONS . " WHERE file_id = :file_id AND cat_id = :category_id";
        $statement = $this->dbh->prepare($sql);
        $statement->bindParam(':file_id', $this->id, PDO::PARAM_INT);
        $statement->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        $statement->execute();
    }

    private function addToCategory($category_id)
    {
        $statement = $this->dbh->prepare("INSERT INTO " . TABLE_CATEGORIES_RELATIONS . " (file_id, cat_id)"
                                                ."VALUES (:file_id, :category_id)");
        $statement->bindParam(':file_id', $this->id, PDO::PARAM_INT);
        $statement->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        $statement->execute();
    }
}
