<?php
$page_title = __('File information', 'cftp_admin');
$page_title = $file->title;

include_once ADMIN_VIEWS_DIR . DS . 'header-unlogged.php';
?>

<div class="row">
    <div class="col-12 col-sm-12 col-lg-8 offset-lg-2">

        <div class="white-box">
            <div class="white-box-interior">
                <?php
                if ($can_view) {
                ?>
                    <div class="text-center p-5">
                        <h2 class="file_title">
                            <?php echo $file->filename_original; ?>

                            <?php if ($file->filename_original != $file->title) { ?>
                                <h3><?php echo $file->title; ?></h3>
                            <?php } ?>
                        </h2>
                        
                        <?php if (get_option('public_listing_enable_preview') == 1) { ?>
                            <div class="preview">
                                <?php
                                    // Preview
                                    if (file_is_image($file->full_path)) {
                                        $thumbnail = make_thumbnail($file->full_path, null, 250, 250);
                                        if (!empty($thumbnail['thumbnail']['url'])) {
                                ?>
                                            <a href="<?php echo $file->public_url . '&download'; ?>" class="get-preview">
                                                <img src="<?php echo $thumbnail['thumbnail']['url']; ?>" class="thumbnail" />
                                            </a>
                                <?php
                                        }
                                    } else {
                                        if ($file->embeddable) {
                                ?>
                                            <button class="btn btn-warning btn-sm btn-wide get-preview" data-url="<?php echo BASE_URI; ?>process.php?do=get_preview&file_id=<?php echo $file->id; ?>"><?php _e('Preview', 'cftp_admin'); ?></button>
                                <?php
                                        }
                                        
                                    }
                                ?>
                            </div>
                        <?php } ?>

                        <div class="description">
                            <?php echo $file->description; ?>
                        </div>

                        <div class="size">
                            <?php echo $file->size_formatted; ?>
                        </div>

                        <?php if ($can_download == true) { ?>
                            <div class="actions">
                                <a href="<?php echo $file->public_url . '&download'; ?>" class="btn btn-primary">
                                    <?php _e('Download file', 'cftp_admin'); ?>
                                </a>
                            </div>
                        <?php } ?>
                    </div>
                <?php
                }
                ?>
            </div>
        </div>

        <div class="login_form_links">
            <p><a href="<?php echo BASE_URI; ?>" target="_self"><?php _e('Go back to the homepage.', 'cftp_admin'); ?></a></p>
        </div>
    </div>
</div>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
