<?php
namespace ProjectSend\Classes;

use \PDO;

/**
 * Class for managing user roles and their permissions
 */
class Roles
{
    private $dbh;
    private $logger;

    public $id;
    public $name;
    public $description;
    public $is_system_role;
    public $permissions_editable;
    public $active;
    public $created_date;
    public $permissions = [];

    public function __construct($role_id = null)
    {
        global $dbh;
        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;

        if (!empty($role_id)) {
            $this->loadRole($role_id);
        }
    }

    /**
     * Load role data from database
     * @param int $role_id
     * @return bool
     */
    public function loadRole($role_id)
    {
        $sql = "SELECT * FROM " . TABLE_ROLES . " WHERE id = :id";
        $statement = $this->dbh->prepare($sql);
        $statement->execute(['id' => $role_id]);

        if ($role = $statement->fetch(PDO::FETCH_ASSOC)) {
            $this->id = $role['id'];
            $this->name = $role['name'];
            $this->description = $role['description'];
            $this->is_system_role = (bool)$role['is_system_role'];
            $this->permissions_editable = (bool)$role['permissions_editable'];
            $this->active = (bool)$role['active'];
            $this->created_date = $role['created_date'];

            $this->loadPermissions();
            return true;
        }

        return false;
    }

    /**
     * Load permissions for this role
     */
    private function loadPermissions()
    {
        if (empty($this->id)) {
            return;
        }

        $sql = "SELECT permission, granted FROM " . TABLE_ROLE_PERMISSIONS . "
                WHERE role_id = :role_id AND granted = 1";
        $statement = $this->dbh->prepare($sql);
        $statement->execute(['role_id' => $this->id]);

        $this->permissions = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $this->permissions[] = $row['permission'];
        }
    }

    /**
     * Get all roles from database
     * @param bool $active_only
     * @return array
     */
    public static function getAllRoles($active_only = true)
    {
        global $dbh;

        $sql = "SELECT * FROM " . TABLE_ROLES;
        if ($active_only) {
            $sql .= " WHERE active = 1";
        }
        $sql .= " ORDER BY id ASC";

        $statement = $dbh->prepare($sql);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get role by ID
     * @param int $role_id
     * @return array|null
     */
    public static function getRoleById($role_id)
    {
        global $dbh;

        $sql = "SELECT * FROM " . TABLE_ROLES . " WHERE id = :id";
        $statement = $dbh->prepare($sql);
        $statement->execute(['id' => $role_id]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get role permissions
     * @param int $role_id
     * @return array
     */
    public static function getRolePermissions($role_id)
    {
        global $dbh;

        $sql = "SELECT permission FROM " . TABLE_ROLE_PERMISSIONS . "
                WHERE role_id = :role_id AND granted = 1";
        $statement = $dbh->prepare($sql);
        $statement->execute(['role_id' => $role_id]);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Create a new role
     * @param array $data
     * @return bool|int
     */
    public function create($data)
    {
        // Validate required fields
        if (empty($data['name'])) {
            return false;
        }

        // Check if role name already exists
        if (self::getRoleByName($data['name'])) {
            return false;
        }

        $sql = "INSERT INTO " . TABLE_ROLES . "
                (name, description, is_system_role, active)
                VALUES (:name, :description, :is_system_role, :active)";

        $statement = $this->dbh->prepare($sql);
        $result = $statement->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'is_system_role' => $data['is_system_role'] ?? 0,
            'active' => $data['active'] ?? 1
        ]);

        if ($result) {
            $this->id = $this->dbh->lastInsertId();
            $this->loadRole($this->id);

            // Log the action
            $this->logger->addEntry([
                'action' => 50, // Custom action for role creation
                'owner_id' => defined('CURRENT_USER_ID') ? CURRENT_USER_ID : null,
                'affected_account_name' => $data['name'],
                'details' => "Role {$data['name']} created"
            ]);

            return $this->id;
        }

        return false;
    }

    /**
     * Update role data
     * @param array $data
     * @return bool
     */
    public function update($data)
    {
        if (empty($this->id)) {
            return false;
        }

        // Prevent updating system roles' system status
        $allowed_fields = ['name', 'description', 'active'];
        if ($this->is_system_role && isset($data['is_system_role'])) {
            unset($data['is_system_role']); // Cannot change system status of system roles
        }

        $update_fields = [];
        $params = [];

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($update_fields)) {
            return false;
        }

        $params['id'] = $this->id;

        $sql = "UPDATE " . TABLE_ROLES . " SET " . implode(', ', $update_fields) . "
                WHERE id = :id";

        $statement = $this->dbh->prepare($sql);
        $result = $statement->execute($params);

        if ($result) {
            // Reload role data
            $this->loadRole($this->id);

            // Log the action
            $this->logger->addEntry([
                'action' => 51, // Custom action for role update
                'owner_id' => defined('CURRENT_USER_ID') ? CURRENT_USER_ID : null,
                'affected_account_name' => $this->name,
                'details' => "Role {$this->name} updated"
            ]);

            return true;
        }

        return false;
    }

    /**
     * Delete role (only non-system roles)
     * @return bool
     */
    public function delete()
    {
        if (empty($this->id) || $this->is_system_role) {
            return false;
        }

        // Check if any users have this role
        $sql = "SELECT COUNT(*) FROM " . TABLE_USERS . " WHERE role_id = :role_id";
        $statement = $this->dbh->prepare($sql);
        $statement->execute(['role_id' => $this->id]);

        if ($statement->fetchColumn() > 0) {
            // Cannot delete role with users assigned
            return false;
        }

        // Delete role permissions
        $sql = "DELETE FROM " . TABLE_ROLE_PERMISSIONS . " WHERE role_id = :role_id";
        $statement = $this->dbh->prepare($sql);
        $statement->execute(['role_id' => $this->id]);

        // Delete role
        $sql = "DELETE FROM " . TABLE_ROLES . " WHERE id = :id";
        $statement = $this->dbh->prepare($sql);
        $result = $statement->execute(['id' => $this->id]);

        if ($result) {
            // Log the action
            $this->logger->addEntry([
                'action' => 52, // Custom action for role deletion
                'owner_id' => defined('CURRENT_USER_ID') ? CURRENT_USER_ID : null,
                'affected_account_name' => $this->name,
                'details' => "Role {$this->name} deleted"
            ]);

            return true;
        }

        return false;
    }

    /**
     * Set permissions for this role
     * @param array $permissions
     * @return bool
     */
    public function setPermissions($permissions)
    {
        if (empty($this->id)) {
            return false;
        }

        try {
            $this->dbh->beginTransaction();

            // Delete existing permissions
            $sql = "DELETE FROM " . TABLE_ROLE_PERMISSIONS . " WHERE role_id = :role_id";
            $statement = $this->dbh->prepare($sql);
            $statement->execute(['role_id' => $this->id]);

            // Insert new permissions
            if (!empty($permissions)) {
                $sql = "INSERT IGNORE INTO " . TABLE_ROLE_PERMISSIONS . " (role_id, permission, granted)
                        VALUES (:role_id, :permission, 1)";
                $statement = $this->dbh->prepare($sql);

                foreach ($permissions as $permission) {
                    $statement->execute([
                        'role_id' => $this->id,
                        'permission' => $permission
                    ]);
                }
            }

            $this->dbh->commit();
            $this->loadPermissions();

            // Log the action
            $this->logger->addEntry([
                'action' => 60, // Custom action for permission update
                'owner_id' => defined('CURRENT_USER_ID') ? CURRENT_USER_ID : 1, // Default to user 1 if no current user
                'affected_account_name' => $this->name,
                'details' => "Permissions updated for role {$this->name} (" . count($permissions) . " permissions)"
            ]);

            return true;
        } catch (Exception $e) {
            $this->dbh->rollback();
            return false;
        }
    }

    /**
     * Add permission to role
     * @param string $permission
     * @return bool
     */
    public function addPermission($permission)
    {
        if (empty($this->id) || in_array($permission, $this->permissions)) {
            return false;
        }

        $sql = "INSERT IGNORE INTO " . TABLE_ROLE_PERMISSIONS . " (role_id, permission, granted)
                VALUES (:role_id, :permission, 1)";
        $statement = $this->dbh->prepare($sql);
        $result = $statement->execute([
            'role_id' => $this->id,
            'permission' => $permission
        ]);

        if ($result) {
            $this->permissions[] = $permission;
            return true;
        }

        return false;
    }

    /**
     * Remove permission from role
     * @param string $permission
     * @return bool
     */
    public function removePermission($permission)
    {
        if (empty($this->id)) {
            return false;
        }

        $sql = "DELETE FROM " . TABLE_ROLE_PERMISSIONS . "
                WHERE role_id = :role_id AND permission = :permission";
        $statement = $this->dbh->prepare($sql);
        $result = $statement->execute([
            'role_id' => $this->id,
            'permission' => $permission
        ]);

        if ($result) {
            $this->permissions = array_filter($this->permissions, function($p) use ($permission) {
                return $p !== $permission;
            });
            return true;
        }

        return false;
    }

    /**
     * Check if role has permission
     * @param string $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        return in_array($permission, $this->permissions);
    }

    /**
     * Get role by name
     * @param string $name
     * @return array|null
     */
    public static function getRoleByName($name)
    {
        global $dbh;

        $sql = "SELECT * FROM " . TABLE_ROLES . " WHERE name = :name";
        $statement = $dbh->prepare($sql);
        $statement->execute(['name' => $name]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get client role ID
     * @return int
     */
    public static function getClientRoleId()
    {
        $client_role = self::getRoleByName('Client');
        return $client_role ? $client_role['id'] : 1; // Default to ID 1 if not found
    }

    /**
     * Get role hierarchy (ordered by ID)
     * @return array
     */
    public static function getRoleHierarchy()
    {
        global $dbh;

        $sql = "SELECT id, name FROM " . TABLE_ROLES . "
                WHERE active = 1 ORDER BY id ASC";
        $statement = $dbh->prepare($sql);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get users count for this role
     * @return int
     */
    public function getUserCount()
    {
        if (empty($this->id)) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM " . TABLE_USERS . " WHERE role_id = :role_id";
        $statement = $this->dbh->prepare($sql);
        $statement->execute(['role_id' => $this->id]);

        return (int)$statement->fetchColumn();
    }

    /**
     * Check if current role exists
     * @return bool
     */
    public function exists()
    {
        return !empty($this->id);
    }

    /**
     * Check if this role is a client role
     * @return bool
     */
    public function isClientRole()
    {
        return $this->name === 'Client';
    }

    /**
     * Get available role levels for role creation forms (backwards compatibility)
     * Note: This method is deprecated. New forms should use role IDs instead.
     * @return array
     */
    public static function getAvailableRoleLevels()
    {
        // Since role_level column is being phased out, return empty array
        // Forms should be updated to use role IDs instead of levels
        return [];
    }

    /**
     * Get role by level (backward compatibility method)
     * @param int $level
     * @return array|null
     */
    public static function getRoleByLevel($level)
    {
        // Map known levels to role names
        $level_to_name_map = [
            9 => 'System Administrator',
            8 => 'Account Manager',
            7 => 'Uploader',
            0 => 'Client'
        ];

        if (isset($level_to_name_map[$level])) {
            return self::getRoleByName($level_to_name_map[$level]);
        }

        return null;
    }

    /**
     * Get users assigned to this role
     * @return array
     */
    public function getUsers()
    {
        if (empty($this->id)) {
            return [];
        }

        $sql = "SELECT id, name, user, email FROM " . TABLE_USERS . " WHERE role_id = :role_id AND active = 1 ORDER BY name";
        $statement = $this->dbh->prepare($sql);
        $statement->execute(['role_id' => $this->id]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reassign all users from this role to another role
     * @param int $new_role_id
     * @return array
     */
    public function reassignUsersToRole($new_role_id)
    {
        if (empty($this->id)) {
            return ['status' => 'error', 'message' => 'Role not loaded'];
        }

        try {
            $sql = "UPDATE " . TABLE_USERS . " SET role_id = :new_role_id WHERE role_id = :old_role_id";
            $statement = $this->dbh->prepare($sql);
            $result = $statement->execute([
                'new_role_id' => $new_role_id,
                'old_role_id' => $this->id
            ]);

            if ($result) {
                $affected_rows = $statement->rowCount();
                return [
                    'status' => 'success',
                    'message' => sprintf('Successfully reassigned %d users', $affected_rows)
                ];
            } else {
                return ['status' => 'error', 'message' => 'Failed to update users'];
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}