<?php
/**
 * Class that handles all the actions and functions that can be applied to
 * the already uploaded files.
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
    public $download_link_xaccel;
    public $expires;
    public $expired;
    public $expiry_date;
    public $assignments_clients;
    public $assignments_groups;
    public $categories;
    public $folder_id;
    public $disk_folder_year;
    public $disk_folder_month;
    public $uploaded_date;
    public $extension;
    public $size;
    public $size_formatted;
    public $public;
    public $public_token;
    public $public_url;
    public $location;
    public $full_path;
    public $record_exists;
    public $mime_type;
    public $embeddable;
    public $embeddable_type;
    public $custom_downloads = [];
    public $storage_type; // 'local', 's3', 'gcs', 'azure'
    public $external_path; // path/key in external storage
    public $bucket_name; // bucket/container name
    public $integration_id; // foreign key to tbl_integrations
    public ?int $encrypted = null; // 1 if file is encrypted, 0 otherwise
    public ?string $encryption_key_encrypted = null; // encrypted file key (base64)
    public ?string $encryption_iv = null; // IV for key encryption (base64)
    public ?string $encryption_algorithm = null; // encryption algorithm used
    public ?string $encryption_file_iv = null; // IV for file encryption (base64)
    public ?int $download_limit_enabled = null; // 1 if download limits are enabled, 0 otherwise
    public ?string $download_limit_type = null; // 'per_user' or 'total'
    public ?int $download_limit_count = null; // maximum number of downloads allowed
    private $dbh;
    private $logger;
    private $external_storage;
    private $date_folder_year;
    private $date_folder_month;

    private $use_date_folder;
    private $is_filetype_allowed;

    public function __construct($file_id = null)
    {
        global $dbh;

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;

        $this->location = UPLOADED_FILES_DIR;

        $this->is_filetype_allowed = false;
        $this->record_exists = false;

        $this->assignments_clients = [];
        $this->assignments_groups = [];
        $this->categories = [];

        $this->embeddable = false;

        // Initialize external storage properties
        $this->storage_type = 'local';
        $this->external_path = null;
        $this->bucket_name = null;
        $this->integration_id = null;
        $this->external_storage = null;

        // Initialize encryption properties
        $this->encrypted = 0;
        $this->encryption_key_encrypted = null;
        $this->encryption_iv = null;
        $this->encryption_algorithm = 'aes-256-gcm';
        $this->encryption_file_iv = null;

        // Initialize download limit properties
        $this->download_limit_enabled = 0;
        $this->download_limit_type = 'total';
        $this->download_limit_count = 0;

        if (!empty($file_id)) {
            $this->get($file_id);
        }
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

    /**
     * Check if the file exists (local or external storage)
     */
    public function exists()
    {
        // For external files, check if they exist in external storage
        if ($this->storage_type !== 'local' && !empty($this->integration_id)) {
            $integrations_handler = new \ProjectSend\Classes\Integrations();
            $integration = $integrations_handler->getById($this->integration_id);

            if ($integration) {
                $storage = $integrations_handler->createStorageInstance($integration);
                if ($storage && !empty($this->external_path)) {
                    return $storage->fileExists($this->external_path);
                }
            }
            return false;
        }

        // For local files, check the filesystem
        return !empty($this->full_path) && file_exists($this->full_path);
    }

    public function currentUserCanEdit()
    {
        return user_can_edit_file(CURRENT_USER_ID, $this->id);
    }

    /**
     * Check if a user can edit this file
     * @param int $user_id User ID to check (defaults to current user)
     * @return bool
     */
    public function canUserEdit($user_id = null)
    {
        if ($user_id === null) {
            if (defined('CURRENT_USER_ID')) {
                $user_id = \CURRENT_USER_ID;
            } else {
                return false; // No user logged in
            }
        }

        // User can edit if they have the edit_files permission (can edit all files)
        if (\current_user_can('edit_files')) {
            return true;
        }

        // User can edit their own files if they have upload permission
        if (\current_user_can('upload') && $this->user_id == $user_id) {
            return true;
        }

        // Use the existing permission check function
        return user_can_edit_file($user_id, $this->id);
    }

    /**
     * Set the properties when saving to the database (data comnes from the form)
     */
    public function set($arguments = [])
    {
		$this->title = (!empty($arguments['title'])) ? encode_html($arguments['title']) : null;
        $this->description = (!empty($arguments['description'])) ? sanitize_description($arguments['description']) : null;
        $this->uploaded_by = (!empty($arguments['uploaded_by'])) ? encode_html($arguments['uploaded_by']) : null;
        $this->filename_on_disk = (!empty($arguments['filename'])) ? $arguments['filename'] : null;
        $this->filename_original = (!empty($arguments['filename_original'])) ? (int)$arguments['filename_original'] : 0;
        $this->expires = (!empty($arguments['expires'])) ? (int)$arguments['expires'] : 0;
        $this->expiry_date = (!empty($arguments['expiry_date'])) ? $arguments['expiry_date'] : null;
        $this->uploaded_date = (!empty($arguments['uploaded_date'])) ? $arguments['uploaded_date'] : null;
        $this->public = (!empty($arguments['public'])) ? (int)$arguments['public'] : 0;
		$this->public_token = (!empty($arguments['public_token'])) ? encode_html($arguments['public_token']) : null;
        $this->folder_id = (!empty($arguments['folder_id'])) ? encode_html($arguments['folder_id']) : null;
        $this->disk_folder_year = (isset($this->date_folder_year)) ? (int)$this->date_folder_year : null;
        $this->disk_folder_month = (isset($this->date_folder_month)) ? (int)$this->date_folder_month : null;

        // Assignations
		$this->assignments_groups = !empty( $arguments['assignations_groups'] ) ? to_array_if_not($arguments['assignations_groups']) : null;
		$this->assignments_clients = !empty( $arguments['assignations_clients'] ) ? to_array_if_not($arguments['assignations_clients']) : null;

        $this->categories = !empty( $arguments['categories'] ) ? to_array_if_not($arguments['categories']) : null;

        // Download limits
        $this->download_limit_enabled = (!empty($arguments['download_limit_enabled'])) ? (int)$arguments['download_limit_enabled'] : 0;
        $this->download_limit_type = (!empty($arguments['download_limit_type'])) ? $arguments['download_limit_type'] : 'total';
        $this->download_limit_count = (!empty($arguments['download_limit_count'])) ? (int)$arguments['download_limit_count'] : 0;

        $this->setFullPath();
        $this->setExtension();
        $this->isFiletypeAllowed();
        $this->isExpired();

        $this->mime_type = get_file_type_by_mime($this->full_path);
        $this->setEmbeddableType();
    }

    /**
     * Get existing file data from the database
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

        $this->record_exists = true;
    
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
            $this->download_link_xaccel = XACCEL_FILES_URL.'/files/'.$this->filename_on_disk;
            $this->expires = html_output($row['expires']);
            $this->expiry_date = html_output($row['expiry_date']);
            $this->uploaded_date = html_output($row['timestamp']);
            $this->public = html_output($row['public_allow']);
            $this->public_token = html_output($row['public_token']);
            $this->public_url = BASE_URI . 'download.php?id=' . $this->id . '&token=' . $this->public_token;
            $this->folder_id = html_output($row['folder_id']);
            $this->disk_folder_year = html_output($row['disk_folder_year']);
            $this->disk_folder_month = html_output($row['disk_folder_month']);
            if (is_numeric($this->disk_folder_month) && $this->disk_folder_month < 10) $this->disk_folder_month = '0' . $this->disk_folder_month;

            // Load size from database if available
            if (isset($row['size']) && is_numeric($row['size']) && $row['size'] > 0) {
                $this->size = $row['size'];
                $this->size_formatted = format_file_size($this->size);
            }

            // Load external storage properties
            $this->storage_type = html_output($row['storage_type'] ?? 'local');
            $this->external_path = html_output($row['external_path'] ?? null);
            $this->bucket_name = html_output($row['bucket_name'] ?? null);
            $this->integration_id = html_output($row['integration_id'] ?? null);

            // Load encryption properties
            $this->encrypted = isset($row['encrypted']) ? (int)$row['encrypted'] : 0;
            $this->encryption_key_encrypted = $row['encryption_key_encrypted'] ?? null;
            $this->encryption_iv = $row['encryption_iv'] ?? null;
            $this->encryption_algorithm = $row['encryption_algorithm'] ?? 'aes-256-gcm';
            $this->encryption_file_iv = $row['encryption_file_iv'] ?? null;

            // Load download limit properties
            $this->download_limit_enabled = isset($row['download_limit_enabled']) ? (int)$row['download_limit_enabled'] : 0;
            $this->download_limit_type = $row['download_limit_type'] ?? 'total';
            $this->download_limit_count = isset($row['download_limit_count']) ? (int)$row['download_limit_count'] : 0;
        }

        $this->full_path = $this->getFilePath();
        $this->isExpired();
        $this->setExtension();
        $this->getSize();

        $this->mime_type = get_file_type_by_mime($this->full_path);
        $this->setEmbeddableType();

        $this->getCurrentAssignments();
        $this->getCurrentCategories();

        return true;
    }

    /**
     * Get file by filename (URL column)
     * Searches for a file by its filename_on_disk (url column) and populates the object
     *
     * @param string $filename The filename to search for
     * @return bool True if file found and loaded, false otherwise
     */
    public function getByFilename($filename)
    {
        $statement = $this->dbh->prepare("SELECT id FROM " . TABLE_FILES . " WHERE url = :filename");
        $statement->execute([':filename' => $filename]);

        if ($statement->rowCount() > 0) {
            $row = $statement->fetch();
            $file_id = $row['id'];

            if (!empty($file_id)) {
                return $this->get($file_id);
            }
        }

        return false;
    }

    public function getCustomDownloads()
    {
        if (!empty($this->custom_downloads))
            return $this->custom_downloads;

        $statement = $this->dbh->prepare("SELECT * FROM " . TABLE_CUSTOM_DOWNLOADS . " WHERE file_id=:file_id");
        $statement->bindParam(':file_id', $this->id);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        while ($row = $statement->fetch()) {
            $this->custom_downloads[] = $row;
        }

        $this->custom_downloads[] = [
            'link' => null,
            'client_id' => null,
            'file_id' => $this->id,
            'timestamp' => (new \DateTime())->getTimestamp(),
            'expiry_date' => null,
            'visit_count' => 0,
        ];

        return $this->custom_downloads;
    }

    public function recordExists()
    {
        return $this->record_exists;
    }

    public function getCurrentAssignments()
    {
        $this->assignments_clients = [];
        $this->assignments_groups = [];

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

        return $this->expired;
    }

    public function isPublic()
    {
        if ($this->public == '1') {
            return true;
        }

        return false;
    }

    public function isImage()
    {
        if (file_is_image($this->full_path)) {
            return true;
        }

        return false;
    }

    public function setEmbeddableType()
    {
        if (empty($this->mime_type)) {
            return null;
        }

        if ($this->isImage()) {
            $this->embeddable = true;
            $this->embeddable_type = 'image';
        }

        // Video
        $embeddable = ['mp4', 'ogg', 'webm'];
        if (file_is_video($this->full_path) && in_array($this->extension, $embeddable)) {
            $this->embeddable = true;
            $this->embeddable_type = 'video';
        }

        // Audio
        $embeddable = ['mp3', 'wav'];
        if (file_is_audio($this->full_path) || in_array($this->extension, $embeddable)) {
            $this->embeddable = true;
            $this->embeddable_type = 'audio';
        }

        // PDF
        if ($this->mime_type == 'application/pdf') {
            $this->embeddable = true;
            $this->embeddable_type = 'pdf';
        }
    }

    public function getEmbedData()
    {
        if ($this->embeddable) {
            // Use authenticated endpoint for non-image files to enforce permissions on every request
            // This prevents direct URL sharing that bypasses permission checks
            if ($this->isImage()) {
                $file_url = make_thumbnail($this->full_path, 'proportional', 500)['thumbnail']['url'];
            } else {
                // PDFs, videos, audio - use serve_file endpoint
                $file_url = BASE_URI . 'process.php?do=serve_file&id=' . $this->id;
            }

            $return = [
                'name' => $this->filename_original,
                'file_url' => $file_url,
                'type' => $this->embeddable_type,
                'mime_type' => $this->mime_type,
            ];

            // Note: Action logging is now done in the serve_file endpoint for non-images
            // to log each actual access, not just when embed data is requested
            if ($this->isImage()) {
                $this->logger->addEntry([
                    'action' => 41,
                    'owner_id' => defined('CURRENT_USER_ID') ? CURRENT_USER_ID : 0,
                    'affected_file' => $this->id,
                    'affected_file_name' => $this->filename_on_disk,
                    'affected_account' => defined('CURRENT_USER_ID') ? CURRENT_USER_ID : 0,
                    'file_title_column' => true
                ]);
            }

            return json_encode($return);
        }

        return null;
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
            'folder_id' => $this->folder_id,
        ];

        return $data;
    }

    /**
     * Get public file data suitable for API responses
     * 
     * @return array
     */
    public function getPublicData()
    {
        // Base file data
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'filename_original' => $this->filename_original,
            'extension' => $this->extension,
            'size' => $this->size,
            'size_formatted' => $this->size_formatted,
            'uploaded_date' => date(get_option('timeformat'), strtotime($this->uploaded_date)),
            'expires' => $this->expires,
            'expired' => (bool)$this->expired,
            'download_link' => $this->download_link,
            'is_image' => $this->isImage(),
            'mime_type' => $this->mime_type
        ];

        // Add expiry date if file expires
        if ($this->expires && $this->expiry_date) {
            $data['expiry_date'] = date(get_option('timeformat'), strtotime($this->expiry_date));
        }

        // Add thumbnail for images
        if ($this->isImage() && !$this->expired && !empty($this->full_path)) {
            $thumbnail = make_thumbnail($this->full_path, null, 200, 200);
            if ($thumbnail && isset($thumbnail['thumbnail']['url'])) {
                $data['thumbnail'] = $thumbnail['thumbnail']['url'];
            }
        }

        // Add image metadata if it's an image
        if ($this->isImage() && !empty($this->full_path) && file_exists($this->full_path)) {
            $image_info = getimagesize($this->full_path);
            if ($image_info) {
                $data['image_info'] = [
                    'width' => $image_info[0],
                    'height' => $image_info[1],
                    'dimensions_formatted' => $image_info[0] . ' × ' . $image_info[1],
                    'type' => image_type_to_mime_type($image_info[2]),
                    'bits' => isset($image_info['bits']) ? $image_info['bits'] : null,
                    'channels' => isset($image_info['channels']) ? $image_info['channels'] : null,
                ];
            }
        }

        // Add material icon for file type
        $data['icon'] = get_material_file_icon($this->extension);

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

        if (get_option('uploads_organize_folders_by_date') == '1') {
            $use_date_folder = false;
            $y =  date('Y');
            $m =  date('m');
            $year_folder = $this->location . DS .$y;
            $month_folder = $year_folder.DS.$m;
            if (!is_dir($year_folder)) {
                @mkdir($year_folder, 0775, false);
            }

            if (!is_dir($month_folder)) {
                @mkdir($month_folder, 0775, false);
            }

            if (is_dir($month_folder)) {
                $use_date_folder = true;
                $this->date_folder_year = $y;
                $this->date_folder_month = $m;
            }

            if ($use_date_folder) {
                $this->full_path = $month_folder . DS . $this->filename_on_disk;
            }
        }

        return $this->full_path;
    }

    private function getFilePath()
    {
        $path = UPLOADED_FILES_DIR.DS;

        if (!empty($this->disk_folder_year)) {
            $path .= $this->disk_folder_year.DS;
        }
        if (!empty($this->disk_folder_month)) {
            $path .= $this->disk_folder_month.DS;
        }

        $path .= $this->filename_on_disk;

        return $path;
    }

    /**
     * Sets the size in bytes and in a more human readable format
     *
     * @return void
     */
    public function getSize()
    {
        // Only calculate size from file system if not already loaded from database
        if (empty($this->size) && $this->filename_on_disk)
        {
            if ( file_exists( $this->full_path ) ) {
                $this->size = get_real_size($this->full_path);
                $this->size_formatted = format_file_size($this->size);
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

    /**
     * Check if file exists in storage (local or external)
     * For external storage files, they don't exist on local disk but are editable
     */
    public function existsInStorage()
    {
        // For local storage, check if file exists on disk
        if ($this->storage_type === 'local' || empty($this->storage_type)) {
            return $this->existsOnDisk();
        }

        // For external storage files, they should be editable even if not on local disk
        // The file exists if it has valid external storage properties
        if (!empty($this->storage_type) && $this->storage_type !== 'local') {
            // File exists in external storage if it has an external path or integration
            return (!empty($this->external_path) || !empty($this->integration_id));
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
        $this->is_filetype_allowed = file_is_allowed($this->filename_on_disk);

        return $this->is_filetype_allowed;
	}

	/**
	 * Convert a string into a url safe address.
	 * Original name: formatURL
	 * John Magnolia / svick on StackOverflow
	 *
	 * @param string $original_filename
	 * @return string
	 * @link http://stackoverflow.com/questions/2668854/sanitizing-strings-to-make-them-url-and-filename-safe
	 */
    public function generateSafeFilename($original_filename)
    {
        if (empty($original_filename)) {
            return false;
        }
        
		$original_filename = basename(trim($original_filename));
        $filename = generate_safe_filename($original_filename);
        
        // Set the properties
        $this->filename_original = $original_filename;
        $this->filename_on_disk = $filename;

        return $this->filename_on_disk;
	}
	
    /**
	 * Used to copy a file from the temporary folder (the default location where it's put
	 * after uploading it) to the final folder.
	 * If successful, the original file is then deleted.
	 */
	public function moveToUploadDirectory($temp_name)
	{
        $safe_filename = $this->generateSafeFilename($temp_name);

		$this->uid = CURRENT_USER_ID;
		$this->username = CURRENT_USER_USERNAME;

		$this->filename_on_disk = time() . '-' . bin2hex(random_bytes(8)) . '-' . $safe_filename;
        $this->setFullPath();

        if (file_exists($this->full_path)) {
            $ext_pos = strrpos($this->full_path, '.');
            $path_name = substr($this->full_path, 0, $ext_pos);
            $path_ext = substr($this->full_path, $ext_pos);

            // Disk name
            $disk_ext_pos = strrpos($this->filename_on_disk, '.');
            $disk_name = substr($this->filename_on_disk, 0, $disk_ext_pos);
            $disk_ext = substr($this->filename_on_disk, $disk_ext_pos);

            // Original name
            $original_ext_pos = strrpos($this->filename_original, '.');
            $original_name = substr($this->filename_original, 0, $original_ext_pos);
            $original_ext = substr($this->filename_original, $original_ext_pos);
            
            $count = 1;
            while (file_exists($path_name . '_' . $count . $path_ext))
                $count++;
            
            $this->filename_on_disk = $disk_name . '_' . $count . $disk_ext;
            $this->filename_original = $original_name . '_' . $count . $original_ext;
            $this->path = $path_name . '_' . $count . $path_ext;
        }

		
		if (rename($temp_name, $this->full_path)) {

            @chmod($this->full_path, 0644);

            // Get file size after moving
            if (file_exists($this->full_path)) {
                $this->size = get_real_size($this->full_path);
            }

            $return = array(
                'filename_original' => $this->filename_original,
                'filename_disk' => $this->filename_on_disk,
            );

            return $return;
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
     * @param int $status Hide or show status (0 or 1)
     * @param string $to_type Group or client, changes the column on the query
     * @param int $to_id ID of the group or client
     * @return void
     */
	private function changeHiddenStatus($status, $to_type, $to_id)
	{
        $this->check_level = ['System Administrator', 'Account Manager', 'Uploader'];
        
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
        }

        switch ($to_type) {
            case 'client':
                $column = 'client_id';
                $client = get_client_by_id($to_id);
                $log_to = $client['username'];
                break;
            case 'group':
                $column = 'group_id';
                $group = get_group_by_id($to_id);
                $log_to = $group['name'];
                break;
            default:
                throw new \Exception('Invalid modify type');
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
            $this->logger->addEntry([
                'action' => $log_action_number,
                'owner_id' => CURRENT_USER_ID,
                'affected_file' => $this->id,
                'affected_file_name' => $this->title,
                'affected_account_name' => $log_to,
            ]);

            return true;
        }

        return false;
	}

	public function hideFromEveryone()
	{
        $this->check_level = ['System Administrator', 'Account Manager', 'Uploader'];

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
            $this->logger->addEntry([
                'action' => 40,
                'owner_id' => CURRENT_USER_ID,
                'affected_file' => $this->id,
                'affected_file_name' => $this->title
            ]);

            return true;
        }

        return false;
	}

	public function showToEveryone()
	{
        $this->check_level = ['System Administrator', 'Account Manager', 'Uploader'];

        if (empty($this->id)) {
            return false;
        }

        /** Do a permissions check */
        if (isset($this->check_level) && current_role_in($this->check_level)) {
            $statement = $this->dbh->prepare("UPDATE " . TABLE_FILES_RELATIONS . " SET hidden='0' WHERE file_id = :file_id");
            $statement->bindParam(':file_id', $this->id, PDO::PARAM_INT);
            $statement->execute();

            unset($this->check_level);

            /** Record the action log */
            $this->logger->addEntry([
                'action' => 46,
                'owner_id' => CURRENT_USER_ID,
                'affected_file' => $this->id,
                'affected_file_name' => $this->title
            ]);

            return true;
        }

        return false;
	}

    private function currentUserCanDeleteFile()
    {
        if (defined('CRON_TASKS_AUTHORIZED') && CRON_TASKS_AUTHORIZED == true) {
            return true;
        }

        // Clients with delete_files permission can delete their own files
        if (current_role_in(['Client'])) {
            if (current_user_can('delete_files')) {
                if ($this->uploaded_by == CURRENT_USER_USERNAME) {
                    return true;
                }
                if ($this->user_id == CURRENT_USER_ID) {
                    return true;
                }
            }
        }
        
        // Users with delete_files permission can delete their own files
        if ( current_user_can('delete_files') ) {
            if ( $this->uploaded_by == CURRENT_USER_USERNAME ) {
                return true;
            }
        }

        // Users with delete_others_files permission can delete any files
        if (current_user_can('delete_others_files')) {
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
        if (!$this->currentUserCanDeleteFile()) {
            return [
                'status' => 'error',
                'message' => __('You do not have permission to delete this file.', 'cftp_admin')
            ];
        }

        /*
        * Thumbnails should be deleted too.
        * Start by making a pattern with the file name, a shorter version of what's
        * used on make_thumbnail.
        */
        $this->thumbnails_pattern = 'thumb_' . md5($this->filename_on_disk);
        $this->find_thumbnails = glob( THUMBNAILS_FILES_DIR . DS . $this->thumbnails_pattern . '*.*' );

        try {
            // Use the id and uri information to delete the file.
            $delete = delete_file_from_disk($this->getFilePath());

            // Delete the reference to the file on the database only if file is deleted from disk
            if ($delete) {
                $sql = $this->dbh->prepare("DELETE FROM " . TABLE_FILES . " WHERE id = :file_id");
                $sql->bindParam(':file_id', $this->id, PDO::PARAM_INT);
                $sql->execute();

                // Delete the thumbnails
                foreach ( $this->find_thumbnails as $this->thumbnail ) {
                    $delete = delete_file_from_disk($this->thumbnail);
                }

                /** Record the action log */
                if (defined('CURRENT_USER_ID')) {
                    $this->logger->addEntry([
                        'action' => 12,
                        'owner_id' => CURRENT_USER_ID,
                        'affected_file' => $this->id,
                        'affected_file_name' => $this->title
                    ]);
                }    
            }

            return [
                'status' => 'success',
                'message' => __('File deleted successfully.', 'cftp_admin')
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => __('Failed to delete file.', 'cftp_admin')
            ];
        }
    }
    
    public function setDefaults()
    {
        $expire = get_option('files_default_expire');
        $expire_days_option = get_option('files_default_expire_days_after');
        $expire_days = (!empty($expire_days_option) && is_numeric($expire_days_option)) ? $expire_days_option : 30;
        $this->title = $this->filename_original;
        $this->description = null;
        $this->expires = (!empty($expire)) ? $expire : 0;
        $public = get_option('files_default_public');
        $this->public = (!empty($public)) ? $public : 0;
        $this->expiry_date = date('Y-m-d', strtotime("+$expire_days days"));
    }

    /**
     * Set up external file properties for import from external storage
     * This is used instead of moveToUploadDirectory for files already in external storage
     */
    public function setExternalFileProperties($file_key, $metadata, $integration_id, $storage_type)
    {
        $this->uid = CURRENT_USER_ID;
        $this->username = CURRENT_USER_USERNAME;

        // Extract filename from key
        $this->filename_original = basename($file_key);

        // For external files, we use the external key as the "disk filename"
        $this->filename_on_disk = $file_key;

        // Set external storage properties
        $this->storage_type = $storage_type;
        $this->external_path = $file_key;
        $this->integration_id = $integration_id;

        // Set file properties from metadata
        $this->size = $metadata['size'] ?? 0;

        // Set mime type if available
        if (isset($metadata['content_type'])) {
            $this->mime_type = $metadata['content_type'];
        }

        // Don't set full_path for external files as they don't exist locally
        $this->full_path = null;

        return [
            'filename_original' => $this->filename_original,
            'filename_disk' => $this->filename_on_disk,
        ];
    }

    /**
	 * Called after correctly moving the file to the final location.
	 */
	public function addToDatabase()
	{
        // Check permissions
        if (!\current_user_can('upload')) {
            return [
                'status' => 'error',
                'message' => __('You do not have permission to upload files.', 'cftp_admin')
            ];
        }

        // Check disk quota
        $quota_check = user_can_upload_file(CURRENT_USER_ID, $this->size);
        if (!$quota_check['allowed']) {
            // Delete the file from disk since quota exceeded
            if (file_exists($this->full_path)) {
                unlink($this->full_path);
            }

            return [
                'status' => 'error',
                'message' => $quota_check['message']
            ];
        }

		$this->uploader = CURRENT_USER_USERNAME;
		$this->uploader_id = CURRENT_USER_ID;
		$this->uploader_type = CURRENT_USER_TYPE;
		$this->hidden = 0;
        $this->public_token = generate_random_string(32);
        $this->disk_folder_year = (isset($this->date_folder_year)) ? (int)$this->date_folder_year : null;
        $this->disk_folder_month = (isset($this->date_folder_month)) ? (int)$this->date_folder_month : null;
		
        $statement = $this->dbh->prepare("INSERT INTO " . TABLE_FILES . " (user_id, url, original_url, size, filename, description, uploader, expires, expiry_date, public_allow, public_token, folder_id, disk_folder_year, disk_folder_month, storage_type, external_path, bucket_name, integration_id, encrypted, encryption_key_encrypted, encryption_iv, encryption_algorithm, encryption_file_iv, download_limit_enabled, download_limit_type, download_limit_count)"
                                        ."VALUES (:user_id, :url, :original_url, :size, :filename, :description, :uploader, :expires, :expiry_date, :public, :public_token, :folder_id, :disk_folder_year, :disk_folder_month, :storage_type, :external_path, :bucket_name, :integration_id, :encrypted, :encryption_key_encrypted, :encryption_iv, :encryption_algorithm, :encryption_file_iv, :download_limit_enabled, :download_limit_type, :download_limit_count)");
        $statement->bindParam(':user_id', $this->uploader_id, PDO::PARAM_INT);
        $statement->bindParam(':url', $this->filename_on_disk);
        $statement->bindParam(':original_url', $this->filename_original);
        $statement->bindParam(':size', $this->size, PDO::PARAM_INT);
        $statement->bindParam(':filename', $this->filename_original);
        $statement->bindParam(':description', $this->description);
        $statement->bindParam(':uploader', $this->uploader);
        $statement->bindParam(':expires', $this->expires, PDO::PARAM_INT);
        $statement->bindParam(':expiry_date', $this->expiry_date);
        $statement->bindParam(':public', $this->public, PDO::PARAM_INT);
        $statement->bindParam(':public_token', $this->public_token);
        $statement->bindParam(':folder_id', $this->folder_id, PDO::PARAM_INT);
        $statement->bindParam(':disk_folder_year', $this->disk_folder_year, PDO::PARAM_INT);
        $statement->bindParam(':disk_folder_month', $this->disk_folder_month, PDO::PARAM_INT);
        $statement->bindParam(':storage_type', $this->storage_type);
        $statement->bindParam(':external_path', $this->external_path);
        $statement->bindParam(':bucket_name', $this->bucket_name);
        $statement->bindParam(':integration_id', $this->integration_id, PDO::PARAM_INT);
        $statement->bindParam(':encrypted', $this->encrypted, PDO::PARAM_INT);
        $statement->bindParam(':encryption_key_encrypted', $this->encryption_key_encrypted);
        $statement->bindParam(':encryption_iv', $this->encryption_iv);
        $statement->bindParam(':encryption_algorithm', $this->encryption_algorithm);
        $statement->bindParam(':encryption_file_iv', $this->encryption_file_iv);
        $statement->bindParam(':download_limit_enabled', $this->download_limit_enabled, PDO::PARAM_INT);
        $statement->bindParam(':download_limit_type', $this->download_limit_type);
        $statement->bindParam(':download_limit_count', $this->download_limit_count, PDO::PARAM_INT);
        $statement->execute();

        $this->file_id = $this->dbh->lastInsertId();
        $this->id = $this->file_id;
        $this->record_exists = true;

		if (!empty($this->file_id)) {
            /** Record the action log */
            if ($this->uploader_type == 'user') {
                $this->action_type = 5;
            }
            elseif ($this->uploader_type == 'client') {
                $this->action_type = 6;
            }
            $this->logger->addEntry([
                'action' => $this->action_type,
                'owner_id' => $this->uploader_id,
                'affected_file' => $this->file_id,
                'affected_file_name' => $this->filename_original,
                'affected_account_name' => $this->uploader
            ]);

            return [
                'status' => 'success',
                'id' => $this->file_id,
                'public_token' => $this->public_token,
            ];
		}
		
		return [
            'status' => 'error',
            'message' => null,
        ];
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
        $this->name = encode_html($data["name"]);
        $this->description = sanitize_description($data["description"]);
        $this->expires = (isset($data["expires"])) ? $data["expires"] : 0;
        $this->expiry_date = (isset($expiration_str)) ? $expiration_str : $current["expiry_date"];
        $this->is_public = (isset($data["public"])) ? $data["public"] : 0;
        $this->folder_id = (isset($data["folder_id"]) && !(empty($data["folder_id"]))) ? $data["folder_id"] : null;

        // Download limits
        $this->download_limit_enabled = (isset($data["download_limit_enabled"])) ? (int)$data["download_limit_enabled"] : 0;
        $this->download_limit_type = (isset($data["download_limit_type"])) ? $data["download_limit_type"] : 'total';
        $this->download_limit_count = (isset($data["download_limit_count"])) ? (int)$data["download_limit_count"] : 0;

        /**
         * Restrict file properties based on user permissions
         */
        // Check expiration permissions
        if (!current_user_can('set_file_expiration_date')) {
            $this->expires = (int)$current["expires"];
            $this->expiry_date = $current["expiry_date"];
        }

        // Check public download permissions
        if (!current_user_can('upload_public')) {
            $this->is_public = $current["public"];
        }

        // Check download limit permissions
        if (!current_user_can('limit_downloads')) {
            $this->download_limit_enabled = (int)$current["download_limit_enabled"];
            $this->download_limit_type = $current["download_limit_type"];
            $this->download_limit_count = (int)$current["download_limit_count"];
        }

        if (empty($this->name)) {
            $this->name = $this->filename_original;
        }

        $is_public = (is_null($this->is_public) ? 0 : $this->is_public);
        // Handle expires value (integer field)
        $expires = (!empty($this->expires)) ? $this->expires : 0;

        // Handle expiry_date
        if (empty($this->expiry_date)) {
            // If expiry_date is not set, use current date + 1 year
            $expiry_date = date('Y-m-d H:i:s', strtotime('+1 year'));
        } else {
            // Use the provided expiry_date
            $expiry_date = $this->expiry_date;
        }

        $statement = $this->dbh->prepare("UPDATE " . TABLE_FILES . " SET
            filename = :title,
            description = :description,
            expires = :expires,
            expiry_date = :expiry_date,
            public_allow = :public,
            folder_id = :folder_id,
            download_limit_enabled = :download_limit_enabled,
            download_limit_type = :download_limit_type,
            download_limit_count = :download_limit_count
            WHERE id = :id
        ");

        $statement->bindParam(':title', $this->name);
        $statement->bindParam(':description', $this->description);
        $statement->bindParam(':expires', $expires, PDO::PARAM_INT);  // Using our new $expires variable
        $statement->bindParam(':expiry_date', $expiry_date);
        $statement->bindParam(':public', $is_public, PDO::PARAM_INT);
        $statement->bindParam(':folder_id', $this->folder_id);
        $statement->bindParam(':download_limit_enabled', $this->download_limit_enabled, PDO::PARAM_INT);
        $statement->bindParam(':download_limit_type', $this->download_limit_type);
        $statement->bindParam(':download_limit_count', $this->download_limit_count, PDO::PARAM_INT);
        $statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $statement->execute();
        $hidden = (!empty($data['hidden']) && is_numeric($data['hidden'])) ? $data['hidden'] : 0;

		if (!empty($statement)) {
            // Update assignments
            $assignments = (!empty($data['assignments'])) ? $data['assignments'] : null;
            $assignments = $this->saveAssignments($assignments, $hidden);

            // Create notifications only for newly added assignments
            if (!empty($assignments['added']['clients'])) {
                if (current_role_in(['Client']) || $hidden == 0) {
                    $notification_type = current_role_in(['Client']) ? 0 : 1;
                    $users = current_role_in(['Client']) ? [CURRENT_USER_ID] : $assignments['added']['clients'];
                    $this->createNotifications($users, $notification_type);
                }
            }

            // Categories
            $categories = (!empty($data['categories'])) ? $data['categories'] : [];
            $this->saveCategories($categories);
            $this->refresh();

            /** Record the action log */
            if (CURRENT_USER_TYPE == 'user') {
                $action_type = 32;
            }
            elseif (CURRENT_USER_TYPE == 'client') {
                $action_type = 33;
            }
            $this->logger->addEntry([
                'action' => $action_type,
                'owner_id' => CURRENT_USER_ID,
                'affected_file' => $this->id,
                'affected_file_name' => $this->filename_original,
            ]);

            return true;
		}
		
		return false;
	}

    /**
     * Edit an existing file.
     * @return array Result with status and message
     */
    public function edit($data)
    {
        if (empty($this->id)) {
            return [
                'status' => 'error',
                'message' => __('File ID is required for editing.', 'cftp_admin')
            ];
        }

        // Check permissions
        $current_user_id = defined('CURRENT_USER_ID') ? \CURRENT_USER_ID : null;
        $can_edit = \current_user_can('edit_files') ||
                   (\current_user_can('upload') && $current_user_id && $this->user_id == $current_user_id) ||
                   user_can_edit_file($current_user_id, $this->id);

        if (!$can_edit) {
            return [
                'status' => 'error',
                'message' => __('You do not have permission to edit this file.', 'cftp_admin')
            ];
        }

        // Use the existing save method
        $result = $this->save($data);

        if ($result) {
            return [
                'status' => 'success',
                'message' => __('File updated successfully.', 'cftp_admin')
            ];
        }

        return [
            'status' => 'error',
            'message' => __('Failed to update file.', 'cftp_admin')
        ];
    }

    // Assign
    public function saveAssignments($new_values, $hidden = 0)
    {
        if (!current_user_can('edit_files')) {
            return false;
        }

        if (empty($this->id)) {
            return false;
        }

        $hidden = (int)$hidden;

        if (empty($new_values['clients'])) { $new_values['clients'] = []; }
        if (empty($new_values['groups'])) { $new_values['groups'] = []; }

        // If user doesn't have permission to manage groups, preserve existing group assignments
        if (!current_user_can('manage_groups')) {
            $new_values['groups'] = $this->assignments_groups;
        } 

        // Clean new ids based on user permissions for limited users
        $get_user = new \ProjectSend\Classes\Users(CURRENT_USER_ID);
        if (!empty($get_user->limit_upload_to)) {
                // If client ID is not allowed, remove from array
                foreach ($new_values['clients'] as $key => $client_id) {
                    if (!in_array($client_id, $get_user->limit_upload_to)) {
                        unset($new_values['clients'][$key]);
                    }
                }
                // Do the same for groups. First get allowed groups
                $allowed_groups = array_keys(file_editor_get_groups_by_members($get_user->limit_upload_to));
                foreach ($new_values['groups'] as $key => $group_id) {
                    if (!in_array($group_id, $allowed_groups)) {
                        unset($new_values['groups'][$key]);
                    }
                }
            }

        // Get current assignments from database to compare with new values
        $current = [
            'clients' => $this->assignments_clients,
            'groups' => $this->assignments_groups,
        ];

        $added_clients = [];
        $added_groups = [];
        $removed_clients = [];
        $removed_groups = [];

        // Remove each item that is current but not on the new values
        foreach ($current['clients'] as $client_id) {
            if (!in_array($client_id, $new_values['clients'])) {
                $this->removeAssignment('client', $client_id);
                $removed_clients[] = $client_id;
            }
        }
        foreach ($current['groups'] as $group_id) {
            if (!in_array($group_id, $new_values['groups'])) {
                $this->removeAssignment('group', $group_id);
                $removed_groups[] = $group_id;
            }
        }

        // Create new relations
        foreach ($new_values['clients'] as $client_id) {
            if (!in_array($client_id, $current['clients'])) {
                $this->addAssignment('client', $client_id, $hidden);
                $added_clients[] = $client_id;
            }
        }
        foreach ($new_values['groups'] as $group_id) {
            if (!in_array($group_id, $current['groups'])) {
                $this->addAssignment('group', $group_id, $hidden);
                $added_groups[] = $group_id;
            }
        }

        // Response
        foreach ($added_groups as $group_id) {
            $group = new \ProjectSend\Classes\Groups($group_id);
            if (!empty($group->members)) {
                foreach ($group->members as $user_id) {
                    if (!in_array($user_id, $added_clients)) {
                        $added_clients[] = $user_id;
                    }
                }
            }
        }

        $return = [
            'added' => [
                'clients' => $added_clients,
                'groups' => $added_groups,
            ],
            'removed' => [
                'clients' => $removed_clients,
                'groups' => $removed_groups,
            ]
        ];

        return $return;
    }

    private function createNotifications($user_ids = [], $notification_type = 0)
    {
        if (empty($user_ids)) {
            return false;
        }

        foreach ($user_ids as $user_id) {
            $max_tries = get_option('notifications_max_tries');
            // See if there's a pending notification already.
            $statement = $this->dbh->prepare("SELECT id FROM " . TABLE_NOTIFICATIONS . " WHERE file_id = :file_id AND client_id = :client_id AND upload_type = :type AND sent_status = '0' AND times_failed <= :times_failed");
            $statement->bindParam(':file_id', $this->id, PDO::PARAM_INT);
            $statement->bindParam(':type', $notification_type, PDO::PARAM_INT);
            $statement->bindParam(':client_id', $user_id, PDO::PARAM_INT);
            $statement->bindParam(':times_failed', $max_tries, PDO::PARAM_INT);
            $statement->execute();
            $found = $statement->rowCount();

            if ($found < 1) {
                $statement = $this->dbh->prepare("INSERT INTO " . TABLE_NOTIFICATIONS . " (file_id, client_id, upload_type, sent_status, times_failed)
                VALUES (:file_id, :client_id, :type, '0', '0')");
                $statement->bindParam(':file_id', $this->id, PDO::PARAM_INT);
                $statement->bindParam(':client_id', $user_id, PDO::PARAM_INT);
                $statement->bindParam(':type', $notification_type, PDO::PARAM_INT);
                $statement->execute();
            }
        }
    }

    public function addAssignment($type = null, $to_id = 0, $hidden = 0)
    {
        if (!current_user_can('edit_files')) {
            return false;
        }
        
        if (empty($this->id)) {
            return false;
        }

        if (empty($to_id)) {
            return false;
        }

        switch ($type) {
            case 'client':
                $column = 'client_id';
                $log_action_number = 25;
                $client = new \ProjectSend\Classes\Users($to_id);
                $log_name = $client->name;
                break;
            case 'group':
                $column = 'group_id';
                $log_action_number = 26;
                $group = new \ProjectSend\Classes\Groups($to_id);
                $log_name = $group->name;
                break;
            default:
                throw new \Exception('Invalid type');
        }

        $statement = $this->dbh->prepare("INSERT INTO " . TABLE_FILES_RELATIONS . " (file_id, $column, hidden)"
                                                ."VALUES (:file_id, :assignment, :hidden)");
        $statement->bindParam(':file_id', $this->id, PDO::PARAM_INT);
        $statement->bindParam(':assignment', $to_id);
        $statement->bindParam(':hidden', $hidden, PDO::PARAM_INT);
        if ($statement->execute()) {
            $this->logger->addEntry([
                /** Record the action log */
                'action' => $log_action_number,
                'owner_id' => CURRENT_USER_ID,
                'affected_file' => $this->id,
                'affected_file_name' => $this->name,
                'affected_account' => $to_id,
                'affected_account_name' => $log_name
            ]);
        }
    }

    public function removeAssignment($from_type, $from_id)
	{
        if (!current_user_can('edit_files')) {
            return false;
        }
        
        if (empty($this->id)) {
            return false;
        }

        switch ($from_type) {
            case 'client':
                $column = 'client_id';
                $log_action_number = 10;
                $client = new \ProjectSend\Classes\Users($from_id);
                $log_name = $client->name;
                break;
            case 'group':
                $column = 'group_id';
                $log_action_number = 11;
                $group = new \ProjectSend\Classes\Groups($from_id);
                $log_name = $group->name;
                break;
            default:
                throw new \Exception('Invalid modify type');
        }

        $sql = "DELETE FROM " . TABLE_FILES_RELATIONS . " WHERE file_id = :file_id AND " . $column . " = :from_id";
        $statement = $this->dbh->prepare($sql);
        $statement->bindParam(':file_id', $this->id, PDO::PARAM_INT);
        $statement->bindParam(':from_id', $from_id, PDO::PARAM_INT);
        $statement->execute();

        if (!empty($statement)) {
            $this->logger->addEntry([
                /** Record the action log */
                'action' => $log_action_number,
                'owner_id' => CURRENT_USER_ID,
                'affected_file' => $this->id,
                'affected_file_name' => $this->title,
                'affected_account' => $from_id,
                'affected_account_name' => $log_name
            ]);

            return true;
        }

        return false;
    }

    public function saveCategories($categories = [])
    {
        if (!current_user_can('set_file_categories')) {
            return false;
        }
        
        if (empty($this->id)) {
            return false;
        }

        $current = $this->categories;

        $remove = [];
        $create = [];

        // Remove each item that is current but not on the new values
        if (!empty($current)) {
            foreach ($current as $category_id) {
                if (!in_array($category_id, $categories)) {
                    $this->removeFromCategory($category_id);
                }
            }
        }

        // Create new relations
        if (!empty($categories)) {
            foreach ($categories as $category_id) {
                if (!in_array($category_id, $current)) {
                    $this->addToCategory($category_id);
                }
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

    public function getDimensions()
    {
        $image_data = getimagesize($this->full_path);
        if (empty($image_data)) {
            return null;
        }

        return [
            'width' => $image_data[0],
            'height' => $image_data[1],
        ];
    }

    public function displayExif()
    {
        if (!$this->isImage()) {
            return;
        }

        $exif = exif_read_data($this->full_path, null, true);
        $exif = $exif['IFD0'];
        if (!empty($exif)) {
            $exif_display = [
                [
                    'label' => 'Model',
                    'value' => 'Model',
                ],
                [
                    'label' => 'Exposure time',
                    'value' => 'ExposureTime',
                ],
                [
                    'label' => 'Focal length',
                    'value' => 'FocalLength',
                ],
                [
                    'label' => 'F number',
                    'value' => 'FNumber',
                ],
                [
                    'label' => 'ISO speed ratings',
                    'value' => 'ISOSpeedRatings',
                ],
            ];
            foreach ($exif_display as $item) {
                if (!empty($exif[$item['value']])) {
                    echo $item['label'].': ' . $item['value'];
                }
            }
        }
    }

    public function moveToFolder($folder_id)
    {
        if (!$this->id) {
            return false;
        }

        if (current_role_in(['Client'])) {
            if ($folder_id == null) {
                if (!$this->currentUserCanEdit()) {
                    return false;
                }
            }
            else {
                $folder = new \ProjectSend\Classes\Folder($folder_id);
                if (!$folder->currentUserCanAssignToFolder()) {
                    return false;
                }
            }
        }

        if (!empty($folder_id)) {
            $statement = $this->dbh->prepare("UPDATE " . TABLE_FILES . " SET folder_id=:folder_id WHERE id=:id");
            $statement->bindParam(':id', $this->id);
            $statement->bindParam(':folder_id', $folder_id);
            if ($statement->execute()) {
                $this->folder_id = $folder_id;
                return true;
            }
        } else {
            $statement = $this->dbh->prepare("UPDATE " . TABLE_FILES . " SET folder_id=NULL WHERE id=:id");
            $statement->bindParam(':id', $this->id);
            if ($statement->execute()) {
                $this->folder_id = null;
                return true;
            }
        }

        return false;
    }

    /**
     * External Storage Methods
     */

    /**
     * Check if file uses external storage
     *
     * @return bool
     */
    public function isExternal()
    {
        return $this->storage_type !== 'local';
    }

    /**
     * Get external storage instance
     *
     * @return ExternalStorage|null
     */
    public function getExternalStorage()
    {
        if ($this->external_storage !== null) {
            return $this->external_storage;
        }

        if (!$this->isExternal() || !$this->integration_id) {
            return null;
        }

        $integrations = new \ProjectSend\Classes\Integrations();
        $integration = $integrations->getById($this->integration_id);

        if (!$integration) {
            return null;
        }

        $this->external_storage = $integrations->createStorageInstance($integration);
        return $this->external_storage;
    }

    /**
     * Upload file to external storage
     *
     * @param string $local_file_path Local file path
     * @param int $integration_id Integration ID
     * @param string $remote_path Optional custom remote path
     * @return array Upload result
     */
    public function uploadToExternal($local_file_path, $integration_id, $remote_path = null)
    {
        $integrations = new \ProjectSend\Classes\Integrations();
        $integration = $integrations->getById($integration_id);

        if (!$integration) {
            return [
                'success' => false,
                'message' => __('Integration not found.', 'cftp_admin')
            ];
        }

        $storage = $integrations->createStorageInstance($integration);
        if (!$storage) {
            return [
                'success' => false,
                'message' => __('Failed to initialize storage.', 'cftp_admin')
            ];
        }

        // Generate remote path if not provided
        if (!$remote_path) {
            $remote_path = $storage->generateFileKey($this->filename_original ?: $this->filename_on_disk);
        }

        // Prepare metadata
        $metadata = [
            'original_filename' => $this->filename_original,
            'uploaded_by' => $this->uploaded_by,
            'file_id' => $this->id
        ];

        $result = $storage->uploadFile($local_file_path, $remote_path, $metadata);

        if ($result['success']) {
            // Update file record with external storage information
            $this->updateExternalStorageInfo($integration_id, $remote_path, $integration['type']);
        }

        return $result;
    }

    /**
     * Download file from external storage to local path
     *
     * @param string $local_path Local destination path
     * @return array Download result
     */
    public function downloadFromExternal($local_path)
    {
        if (!$this->isExternal()) {
            return [
                'success' => false,
                'message' => __('File is not stored externally.', 'cftp_admin')
            ];
        }

        $storage = $this->getExternalStorage();
        if (!$storage) {
            return [
                'success' => false,
                'message' => __('External storage not available.', 'cftp_admin')
            ];
        }

        return $storage->downloadFile($this->external_path, $local_path);
    }

    /**
     * Get presigned URL for direct external access
     *
     * @param int $expires_in Expiration in seconds
     * @return string|false Presigned URL or false on error
     */
    public function getExternalPresignedUrl($expires_in = 3600)
    {
        if (!$this->isExternal()) {
            return false;
        }

        $storage = $this->getExternalStorage();
        if (!$storage) {
            return false;
        }

        return $storage->getPresignedUrl($this->external_path, $expires_in);
    }

    /**
     * Delete file from external storage
     *
     * @return array Delete result
     */
    public function deleteFromExternal()
    {
        if (!$this->isExternal()) {
            return [
                'success' => false,
                'message' => __('File is not stored externally.', 'cftp_admin')
            ];
        }

        $storage = $this->getExternalStorage();
        if (!$storage) {
            return [
                'success' => false,
                'message' => __('External storage not available.', 'cftp_admin')
            ];
        }

        return $storage->deleteFile($this->external_path);
    }

    /**
     * Update external storage information in database
     *
     * @param int $integration_id Integration ID
     * @param string $external_path External file path
     * @param string $storage_type Storage type
     * @param string $bucket_name Optional bucket name
     * @return bool Success status
     */
    public function updateExternalStorageInfo($integration_id, $external_path, $storage_type, $bucket_name = null)
    {
        if (!$this->id) {
            return false;
        }

        try {
            $query = "UPDATE " . TABLE_FILES . "
                      SET storage_type = :storage_type,
                          external_path = :external_path,
                          bucket_name = :bucket_name,
                          integration_id = :integration_id
                      WHERE id = :id";

            $statement = $this->dbh->prepare($query);
            $statement->bindParam(':storage_type', $storage_type, \PDO::PARAM_STR);
            $statement->bindParam(':external_path', $external_path, \PDO::PARAM_STR);
            $statement->bindParam(':bucket_name', $bucket_name, \PDO::PARAM_STR);
            $statement->bindParam(':integration_id', $integration_id, \PDO::PARAM_INT);
            $statement->bindParam(':id', $this->id, \PDO::PARAM_INT);

            $result = $statement->execute();

            if ($result) {
                // Update object properties
                $this->storage_type = $storage_type;
                $this->external_path = $external_path;
                $this->bucket_name = $bucket_name;
                $this->integration_id = $integration_id;
            }

            return $result;

        } catch (\PDOException $e) {
            error_log('Failed to update external storage info: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Move file from external storage back to local storage
     *
     * @return array Migration result
     */
    public function moveToLocal()
    {
        if (!$this->isExternal()) {
            return [
                'success' => false,
                'message' => __('File is already stored locally.', 'cftp_admin')
            ];
        }

        // Download file to local storage
        $local_path = $this->getFilePath();
        $download_result = $this->downloadFromExternal($local_path);

        if (!$download_result['success']) {
            return $download_result;
        }

        // Update database to mark as local storage
        $update_result = $this->updateExternalStorageInfo(null, null, 'local', null);

        if ($update_result) {
            return [
                'success' => true,
                'message' => __('File moved to local storage successfully.', 'cftp_admin')
            ];
        } else {
            // Clean up downloaded file if database update failed
            if (file_exists($local_path)) {
                unlink($local_path);
            }
            return [
                'success' => false,
                'message' => __('Failed to update file record.', 'cftp_admin')
            ];
        }
    }

    /**
     * Override getFilePath to handle external storage
     *
     * @return string File path (local or external)
     */
    public function getExternalFilePath()
    {
        if ($this->isExternal()) {
            return $this->external_path;
        }

        return $this->getFilePath();
    }

    /**
     * Check if external file exists
     *
     * @return bool
     */
    public function externalFileExists()
    {
        if (!$this->isExternal()) {
            return false;
        }

        $storage = $this->getExternalStorage();
        if (!$storage) {
            return false;
        }

        return $storage->fileExists($this->external_path);
    }

    /**
     * Get external file metadata
     *
     * @return array|false File metadata or false on error
     */
    public function getExternalFileMetadata()
    {
        if (!$this->isExternal()) {
            return false;
        }

        $storage = $this->getExternalStorage();
        if (!$storage) {
            return false;
        }

        return $storage->getFileMetadata($this->external_path);
    }

    /**
     * Upload file directly to external storage (bypass local temp storage)
     *
     * @param string $temp_file_path Temporary file path from plupload
     * @param int $integration_id Integration ID for external storage
     * @param string $original_filename Original filename
     * @return array Result with success/error status
     */
    public function uploadToExternalStorage($temp_file_path, $integration_id, $original_filename)
    {
        // Get integration details
        $integrations_handler = new \ProjectSend\Classes\Integrations();
        $integration = $integrations_handler->getById($integration_id);

        if (!$integration) {
            return [
                'success' => false,
                'message' => __('Integration not found', 'cftp_admin')
            ];
        }

        // Create storage instance
        $storage = $integrations_handler->createStorageInstance($integration);
        if (!$storage) {
            return [
                'success' => false,
                'message' => __('Failed to initialize storage connection', 'cftp_admin')
            ];
        }

        // Generate safe filename and external path
        $safe_filename = $this->generateSafeFilename($temp_file_path);
        $this->uid = CURRENT_USER_ID;
        $this->username = CURRENT_USER_USERNAME;

        // Create unique external path
        $external_key = time() . '-' . bin2hex(random_bytes(8)) . '-' . $safe_filename;

        // Add folder structure if using date organization
        if (get_option('uploads_organize_folders_by_date') == 1) {
            $current_date = date('Y/m');
            $external_key = $current_date . '/' . $external_key;
        }

        // Get file metadata
        $file_size = file_exists($temp_file_path) ? filesize($temp_file_path) : 0;
        $metadata = [
            'uploaded_by' => $this->username,
            'upload_date' => date('Y-m-d H:i:s')
        ];

        // Upload to external storage
        $upload_result = $storage->uploadFile($temp_file_path, $external_key, $metadata);

        if (!$upload_result['success']) {
            return $upload_result;
        }

        // Set file properties for external storage
        $this->filename_original = $original_filename;
        $this->filename_on_disk = $external_key;
        $this->storage_type = $integration['type'];
        $this->external_path = $external_key;
        $this->integration_id = $integration_id;
        $this->bucket_name = $storage->getBucketName();
        $this->size = $file_size;

        // Clean up temp file
        if (file_exists($temp_file_path)) {
            unlink($temp_file_path);
        }

        return [
            'success' => true,
            'filename_original' => $this->filename_original,
            'filename_disk' => $this->filename_on_disk,
            'external_path' => $this->external_path
        ];
    }

    /**
     * Route file to appropriate storage based on selection
     *
     * @param string $temp_file_path Temporary file path
     * @param string $storage_selection Storage selection ('local' or integration ID)
     * @param string $original_filename Original filename
     * @return array Result with success/error status
     */
    public function routeToStorage($temp_file_path, $storage_selection, $original_filename)
    {
        $this->filename_original = $original_filename;

        // Route to local storage
        if ($storage_selection === 'local') {
            return $this->moveToUploadDirectory($temp_file_path);
        }

        // Route to external storage
        if (is_numeric($storage_selection)) {
            return $this->uploadToExternalStorage($temp_file_path, (int)$storage_selection, $original_filename);
        }

        return [
            'success' => false,
            'message' => __('Invalid storage selection', 'cftp_admin')
        ];
    }
}
