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

class OrphanFiles
{
    private $allowed_files;
    private $not_allowed_files;


    private $dbh;
    private $logger;

    public function __construct()
    {
        global $dbh;

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;

        $this->allowed_files = [];
        $this->not_allowed_files = [];
    }

    public function getFiles($settings = [])
    {
        if (empty($this->allowed_files) && empty($this->not_allowed_files)) {
            $this->findOrphanFiles($settings);
        }

        return [
            'allowed' => $this->allowed_files,
            'not_allowed' => $this->not_allowed_files,
        ];
    }

    private function findOrphanFiles($settings = [])
    {
        $db_files = [];

        // Make a list of existing files on the database
        $sql = $this->dbh->query("SELECT original_url, url, id, public_allow FROM " . TABLE_FILES );
        $sql->setFetchMode(PDO::FETCH_ASSOC);
        while ($row = $sql->fetch()) {
            $db_files[$row["url"]] = $row["url"];
            $db_files[$row["original_url"]] = $row["original_url"];
        }

        // Read the temp folder and list every allowed file
        $ignore = [
            ".",
            "..",
            ".htaccess",
            "index.php",
            "web.config",
        ];

        if ($handle = opendir(UPLOADED_FILES_DIR)) {
            while (false !== ($filename = readdir($handle))) {
                $filename_path = UPLOADED_FILES_DIR.DS.$filename;

                if (!in_array($filename, $ignore)) {
                    // Check against search terms
                    if (!empty($settings['search'])) {
                        $search = htmlspecialchars($settings['search']);
                        if (stripos($filename, $search) === false) {
                            continue;
                        }
                    }
            
                    // Check file names that are not on the database
                    if (in_array($filename, $db_files)) {
                        unset($db_files[$filename]);
                        continue;
                    }

                    if (!is_dir($filename_path)) {
                        $file_info = [
                            'name' => $filename,
                            'path' => UPLOADED_FILES_DIR.DS.$filename,
                            'reason' => 'not_on_db',
                        ];
                        if (file_is_allowed($filename)) {
                            $this->allowed_files[] = $file_info;
                        } else {
                            $this->not_allowed_files[] = $file_info;
                        }
                    }
                }
            }

            closedir($handle);
        }
    }

    function deleteFiles($files = [])
	{
    }
}
