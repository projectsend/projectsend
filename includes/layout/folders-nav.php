<div id="folders_nav">
    <?php
        $ondrop_url = AJAX_PROCESS_URL.'?do=folder_move';
        if (!empty($_GET['folder_id'])) {
            $root_link = modify_url_with_parameters($current_url, [], ['folder_id']);
    ?>
            <div class="folder folder_root folder_destination" data-folder-id="">
                <a href="<?php echo $root_link; ?>">
                    <i class="fa fa-hdd-o" aria-hidden="true"></i>
                    <span><?php _e('Root','cftp_admin'); ?></span>
                </a>
            </div>
    <?php
            $get_parent = new \ProjectSend\Classes\Folder($_GET['folder_id']);
            $parent_data = $get_parent->getData();
            $up_link = modify_url_with_parameters($current_url, ['folder_id' => $parent_data['parent']], ['folder_id']);
    ?>
            <div class="folder folder_up folder_destination" data-folder-id="<?php echo $parent_data['parent']; ?>">
                <a href="<?php echo $up_link; ?>">
                    <i class="fa fa-arrow-up" aria-hidden="true"></i>
                    <span><?php _e('Parent folder','cftp_admin'); ?></span>
                </a>
            </div>
    <?php
        }

        // Folders
        if (!empty($folders)) {
            $key = array_keys($folders)[0];

            foreach ($folders as $folder) {
                $folder = new \ProjectSend\Classes\Folder($folder['id']);
                $folder_data = $folder->getData();
                $link = modify_url_with_parameters($current_url, ['folder_id' => $folder_data['id']], ['folder_id']);
    ?>
                <div class="folder folder_draggable folder_destination"
                    data-folder-id="<?php echo $folder_data['id']; ?>"
                    data-name="<?php echo $folder_data['name']; ?>"
                    draggable="true"
                    data-draggable-type="folder"
                    data-can-edit="<?php echo var_export($folder->userCanEdit(CURRENT_USER_ID), true); ?>"
                    data-can-delete="<?php echo var_export($folder->userCanDelete(CURRENT_USER_ID), true); ?>"
                    data-can-assign-file="<?php echo var_export($folder->currentUserCanAssignToFolder(), true); ?>"
                >
                    <a href="<?php echo $link; ?>">
                        <i class="fa fa-folder-o" aria-hidden="true"></i>
                        <span><?php echo $folder->name; ?></span>
                    </a>
                </div>
    <?php
            }
        }
    ?>

    <template id="new_folder">
        <div class="folder folder_draggable folder_destination" data-folder-id="" draggable="true" data-draggable-type="folder">
            <a href="{url}">
                <i class="fa fa-folder-o" aria-hidden="true"></i>
                <span>{name}</span>
            </a>
        </div>
    </template>
</div>

<span id="folder_context_menu__links"
    data-url-share="<?php echo AJAX_PROCESS_URL.'?do=folder_share'; ?>"
    data-url-rename="<?php echo AJAX_PROCESS_URL.'?do=folder_rename'; ?>"
    data-url-delete="<?php echo AJAX_PROCESS_URL.'?do=folder_delete'; ?>"
    data-url-folder-ondrop="<?php echo AJAX_PROCESS_URL.'?do=folder_move'; ?>"
    data-url-file-ondrop="<?php echo AJAX_PROCESS_URL.'?do=file_move'; ?>"
></span>