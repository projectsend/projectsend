<div class="row mt-4 form_actions_count">
    <div class="col-12 col-md-6">
        <p><?php echo sprintf(__('Found %d elements', 'cftp_admin'), (int)$elements_found_count); ?></p>
    </div>
    <div class="col-12 col-md-6">
        <?php if ($elements_found_count > 0 && !empty($bulk_actions_items)) { ?>
            <div class="row row-cols-lg-auto g-3 justify-content-end align-content-end">
                <?php
                    show_actions_form($bulk_actions_items);
                ?>
            </div>
        <?php } ?>
    </div>
</div>
