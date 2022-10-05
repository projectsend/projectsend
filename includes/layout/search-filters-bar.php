<?php
    if (!empty($search_form_action) || !empty($filters_form)) {
?>
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
<?php
    }

    if (!empty($filters_links)) {
?>
        <div class="row">
            <div class="col-12">
                <div class="form_results_filter">
                    <?php
                        foreach ($filters_links as $type => $filter) {
                            ?>
                                <a href="<?php echo $filter['link']; ?>" class="<?php echo $search_type == $type ? 'filter_current' : 'filter_option' ?>"><?php echo $filter['title']; ?> (<?php echo $filter['count']; ?>)</a>
                            <?php
                            }
                    ?>
                </div>
            </div>
        </div>
<?php
    }