<div class="row">
    <div class="col-12">
        <div class="row">
            <?php
                if (!empty($search_form_action)) {
            ?>
                    <div class="col-12 col-md-6">
                        <?php show_search_form($search_form_action); ?>
                    </div>
            <?php
                }
                
                if (!empty($filters_form)) {
            ?>
                    <div class="col-12 col-md-6">
                        <?php show_filters_form($filters_form); ?>
                    </div>
            <?php
                }
            ?>
        </div>
    </div>
</div>
