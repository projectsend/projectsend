<?php
namespace ProjectSend\Classes;

use \ProjectSend\Classes\Validation;
use \Cocur\Slugify\Slugify;
use \PDO;

class Folder
{
    protected $id;
    protected $uuid;
    protected $name;
    protected $slug;
    protected $parent;

    public function __construct($id = null)
    {
        global $dbh;

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;

        if (!empty($id)) {
            $this->get($id);
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
     * Set the properties when editing
     */
    public function set($arguments = [])
    {
		$this->name = (!empty($arguments['name'])) ? encode_html($arguments['name']) : null;
        $this->parent = (!empty($arguments['parent'])) ? encode_html($arguments['parent']) : null;
    }

    /**
     * Get existing user data from the database
     * @return bool
     */
    public function get($id)
    {
        $this->id = $id;

        $this->statement = $this->dbh->prepare("SELECT * FROM " . TABLE_FOLDERS . " WHERE id=:id");
        $this->statement->bindParam(':id', $this->id, PDO::PARAM_INT);
        $this->statement->execute();
        $this->statement->setFetchMode(PDO::FETCH_ASSOC);

        if ($this->statement->rowCount() == 0) {
            return false;
        }
    
        while ($this->row = $this->statement->fetch() ) {
            $this->uuid = html_output($this->row['uuid']);
            $this->name = html_output($this->row['name']);
            $this->slug = html_output($this->row['slug']);
            $this->parent = html_output($this->row['parent']);
        }
    }

    public function create()
    {
        if (empty($this->name)) {
            return false;
        }

        try {
            $slugify = new Slugify();
    
            $this->uuid = uniqid();
            $this->parent = (!empty($this->parent)) ? $this->parent : null;
            $this->slug = $slugify->slugify($this->name);
    
            $statement = $this->dbh->prepare("INSERT INTO " . TABLE_FOLDERS . " (uuid, name, slug, parent) VALUES (:uuid, :name, :slug, :parent)");
            $statement->bindParam(':uuid', $this->uuid);
            $statement->bindParam(':name', $this->name);
            $statement->bindParam(':slug', $this->slug);
            $statement->bindParam(':parent', $this->parent);
            $statement->execute();

            $this->id = $this->dbh->lastInsertId();

            return true;
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }

    public function getData()
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent' => $this->parent,
        ];
    }
}
