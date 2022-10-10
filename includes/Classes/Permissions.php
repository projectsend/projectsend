<?php
namespace ProjectSend\Classes;

class Permissions {
    private $permissions;
    private $user;

    public function __construct($user_id = null)
    {
        $this->setDefaultPermissions();

        if (!empty($user_id)) {
            $this->user = new \ProjectSend\Classes\Users($user_id);
            $this->setPermissionsForRole($this->user->role);
            $this->setPermissionsFromSettings();
        }
    }

    private function getAvailablePermissions()
    {
        return [
            'upload' => [9, 8, 7],
            'edit_files' => [9, 8, 7],
            'edit_others_files' => [9, 8],
            'delete_files' => [9, 8, 7],
            'delete_others_files' => [9, 8],
            'set_file_expiration_date' => [9, 8, 7],
            'upload_public' => [9, 8, 7],
            'import_orphans' => [9, 8, 7],
            'create_categories' => [9, 8, 7],
            'edit_categories' => [9, 8, 7],
            'delete_categories' => [9, 8, 7],
            'create_clients' => [9, 8],
            'edit_clients' => [9, 8],
            'create_users' => [9],
            'edit_users' => [9],
            'edit_self_account' => [9, 8, 7, 0],
            'approve_account_requests' => [9, 8],
            'approve_groups_memberships_requests' => [9, 8],
            'create_groups' => [9, 8],
            'edit_groups' => [9, 8],
            'edit_settings' => [9],
            'edit_email_templates' => [9],
            'change_template' => [9],
            'view_actions_log' => [9, 8, 7],
            'view_statistics' => [9, 8, 7],
            'view_news' => [9, 8, 7],
            'view_system_info' => [9],
            'view_dashboard_counters' => [9],
            'test_email' => [9],
            'unblock_ip' => [9, 8],
            'create_assets' => [9],
            'edit_assets' => [9],
            'delete_assets' => [9],
        ];
    }

    private function setDefaultPermissions()
    {
        $permissions = $this->getAvailablePermissions();
        foreach ($permissions as $permission => $roles_allowed) {
            $this->permissions[$permission] = false;
        }
    }

    private function setPermissionsFromSettings()
    {
        if (empty($this->user)) {
            return;
        }
        
        if ($this->user->isClient()) {
            if (get_option('clients_can_upload') == 1) {
                $this->permissions['upload'] = true;
                $this->permissions['edit_files'] = true;
            }

            if (get_option('clients_can_delete_own_files') == 1) {
                $this->permissions['delete_files'] = true;
            }

            if (get_option('clients_can_set_expiration_date') == 1) {
                $this->permissions['set_file_expiration_date'] = true;
            }

            $this->permissions['upload_public'] = client_can_upload_public($this->user->id);
        }
    }

    public function can($permission)
    {
        return $this->permissions[$permission];
    }

    public function set($permission, $value = false)
    {
        if (!array_key_exists($permission, $this->permissions)) {
            return;
        }

        $this->permissions[$permission] = (bool)$value;
    }

    public function setPermissionsForRole($role = 0)
    {
        $role = (int)$role;
        if (!$role || !in_array($role, [0, 7, 8, 9])) {
            return;
        }

        $permissions = $this->getAvailablePermissions();
        foreach ($permissions as $permission => $roles_allowed) {
            $allowed = in_array($role, $roles_allowed);
            $this->permissions[$permission] = (bool)$allowed;
        }
    }
}
