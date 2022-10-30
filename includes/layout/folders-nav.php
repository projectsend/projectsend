<div id="folders_nav">
    <?php
        if (!empty($_GET['folder_id'])) {
            $get_parent = new \ProjectSend\Classes\Folder($_GET['folder_id']);
            $parent_data = $get_parent->getData();
            $up_link = modify_url_with_parameters($current_url, ['folder_id' => $parent_data['parent']], ['folder_id']);
    ?>
                <div class="folder folder_up">
                    <a href="<?php echo $up_link; ?>">
                        <i class="fa fa-folder-o" aria-hidden="true"></i>
                        <span>
                            ../
                        </span>
                    </a>
                </div>
    <?php
        }

            // Folders
        if (!empty($folders)) {
            $key = array_keys($folders)[0];

            foreach ($folders as $folder) {
                $link = modify_url_with_parameters($current_url, ['folder_id' => $folder['id']], ['folder_id']);
    ?>
                <div class="folder">
                    <a href="<?php echo $link; ?>">
                        <i class="fa fa-folder-o" aria-hidden="true"></i>
                        <span>
                            <?php echo $folder['name']; ?>
                        </span>
                    </a>
                </div>
    <?php
            }
        }
    ?>

    <template id="new_folder">
        <div class="folder">
            <a href="{url}">
                <i class="fa fa-folder-o" aria-hidden="true"></i>
                <span>
                    {name}
                </span>
            </a>
        </div>
    </template>
</div>
