<nav class="breadcrumbs">
    <div class="breadcrumb_item" id="breadcrumbs_label">
        <span><?php _e('Navigation','cftp_admin'); ?></span>
    </div>
    <?php
        $folders_obj = new \ProjectSend\Classes\Folders;
        $breadcrumbs = $folders_obj->makeFolderBreadcrumbs($current_folder, $current_url);
        foreach ($breadcrumbs as $nav_item) {
    ?>
            <div class="breadcrumb_item">
                <?php if (!empty($nav_item['url'])) { ?>
                    <a href="<?php echo $nav_item['url']; ?>">
                        <?php echo $nav_item['name']; ?>
                    </a>
                <?php } else { ?>
                    <span>
                        <?php echo $nav_item['name']; ?>
                    </span>
                <?php } ?>
            </div>
    <?php
        }
    ?>
</nav>
