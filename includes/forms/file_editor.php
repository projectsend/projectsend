<form action="files-edit.php?ids=<?php echo html_output($_GET['ids']); ?><?php if (isset($_GET['confirm'])) { echo "&confirmed=true"; } ?>" name="files" id="files" method="post" enctype="multipart/form-data">
    <?php addCsrf(); ?>

    <div class="files_editor_list">
        <?php
            $i = 1;

            $me = new \ProjectSend\Classes\Users(CURRENT_USER_ID);
            if ($me->shouldLimitUploadTo() && !empty($me->limit_upload_to)) {
                $clients = file_editor_get_clients_by_ids($me->limit_upload_to);
                $groups = file_editor_get_groups_by_members($me->limit_upload_to);
            } else {
                $clients = file_editor_get_all_clients();
                $groups = file_editor_get_all_groups();
            }

            foreach ($editable as $file_id) {
                clearstatcache();
                $file = new ProjectSend\Classes\Files($file_id);
                if ($file->recordExists()) {
                    if ($file->existsInStorage()) {
            ?>
                        <div class="file_editor_wrapper">
                            <div class="row file_editor">
                                <!-- Left Column: File Information -->
                                <div class="col-lg-3">
                                    <div class="ps-card file-info-sidebar">
                                        <div class="ps-card-body">
                                            <h3><?php echo html_output($file->filename_original); ?></h3>
                                            <?php /*<h3><?php _e('File Information', 'cftp_admin');?></h3> */ ?>

                                            <?php if (file_is_image($file->full_path)) {
                                                $thumbnail = make_thumbnail($file->full_path, 'proportional', 400, 400, 90);
                                                if (!empty($thumbnail['thumbnail']['url'])) {
                                            ?>
                                                <div class="file-preview mb-3">
                                                    <img src="<?php echo $thumbnail['thumbnail']['url']; ?>" alt="<?php echo html_output($file->filename_original); ?>" class="img-fluid rounded" />
                                                </div>
                                            <?php
                                                }
                                            } ?>

                                            <div class="file-info-item">
                                                <span class="info-label"><?php _e('Original Filename', 'cftp_admin');?>:</span>
                                                <span class="info-value"><?php echo html_output($file->filename_original); ?></span>
                                            </div>

                                            <div class="file-info-item">
                                                <span class="info-label"><?php _e('File Size', 'cftp_admin');?>:</span>
                                                <span class="info-value"><?php echo $file->size_formatted; ?></span>
                                            </div>

                                            <div class="file-info-item">
                                                <span class="info-label"><?php _e('Extension', 'cftp_admin');?>:</span>
                                                <span class="info-value"><?php echo strtoupper($file->extension); ?></span>
                                            </div>

                                            <div class="file-info-item">
                                                <span class="info-label"><?php _e('File Type', 'cftp_admin');?>:</span>
                                                <span class="info-value"><?php echo html_output($file->mime_type); ?></span>
                                            </div>

                                            <?php if (file_is_image($file->full_path)) {
                                                $dimensions = $file->getDimensions();
                                                if (!empty($dimensions)) {
                                            ?>
                                            <div class="file-info-item">
                                                <span class="info-label"><?php _e('Dimensions', 'cftp_admin');?>:</span>
                                                <span class="info-value"><?php echo $dimensions['width'].' × '.$dimensions['height'].' px'; ?></span>
                                            </div>
                                            <?php
                                                }
                                            } ?>

                                            <div class="file-info-item">
                                                <span class="info-label"><?php _e('Uploaded by', 'cftp_admin');?>:</span>
                                                <span class="info-value"><?php echo html_output($file->uploaded_by); ?></span>
                                            </div>

                                            <div class="file-info-item">
                                                <span class="info-label"><?php _e('Upload Date', 'cftp_admin');?>:</span>
                                                <span class="info-value"><?php echo format_date($file->uploaded_date); ?></span>
                                            </div>

                                            <?php if ($file->encrypted) { ?>
                                            <div class="file-info-item">
                                                <span class="info-label"><?php _e('Encryption', 'cftp_admin');?>:</span>
                                                <span class="info-value">
                                                    <span class="badge bg-success">
                                                        <i class="fa fa-lock"></i> <?php _e('Encrypted', 'cftp_admin');?>
                                                    </span>
                                                </span>
                                            </div>
                                            <?php } ?>

                                            <div class="file-info-item">
                                                <span class="info-label"><?php _e('File ID', 'cftp_admin');?>:</span>
                                                <span class="info-value"><?php echo $file->id; ?></span>
                                            </div>

                                            <?php if (current_user_can('upload_storage_select')): ?>
                                            <div class="file-info-item">
                                                <span class="info-label"><?php _e('Storage Location', 'cftp_admin');?>:</span>
                                                <span class="info-value">
                                                    <?php
                                                        if ($file->storage_type === 'local') {
                                                            echo '<i class="fa fa-server"></i> ' . __('Local Storage', 'cftp_admin');
                                                        } else {
                                                            // Get integration details for external storage
                                                            if (!empty($file->integration_id)) {
                                                                $integrations_handler = new \ProjectSend\Classes\Integrations();
                                                                $integration = $integrations_handler->getById($file->integration_id);
                                                                if ($integration) {
                                                                    $type_config = \ProjectSend\Classes\Integrations::getTypeConfig($integration['type']);
                                                                    $type_name = $type_config ? $type_config['name'] : ucfirst($integration['type']);
                                                                    echo '<i class="fa fa-cloud"></i> ' . html_output($integration['name']) . ' (' . $type_name . ')';
                                                                    if (!empty($file->external_path)) {
                                                                        echo '<br><small class="text-muted">' . __('Path:', 'cftp_admin') . ' ' . html_output($file->external_path) . '</small>';
                                                                    }
                                                                } else {
                                                                    echo '<i class="fa fa-exclamation-triangle text-warning"></i> ' . __('External Storage (Integration Not Found)', 'cftp_admin');
                                                                }
                                                            } else {
                                                                echo '<i class="fa fa-cloud"></i> ' . ucfirst(html_output($file->storage_type)) . ' ' . __('Storage', 'cftp_admin');
                                                            }
                                                        }
                                                    ?>
                                                </span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div><!-- /.col-lg-4 -->

                                <!-- Right Column: Editable Fields -->
                                <div class="col-lg-9">
                                    <!-- Hidden fields -->
                                    <input type="hidden" name="file[<?php echo $i; ?>][id]" value="<?php echo $file->id; ?>" />
                                    <input type="hidden" name="file[<?php echo $i; ?>][original]" value="<?php echo $file->filename_original; ?>" />
                                    <input type="hidden" name="file[<?php echo $i; ?>][file]" value="<?php echo $file->filename_on_disk; ?>" />

                                    <!-- Tabs Navigation -->
                                    <ul class="nav nav-pills file-editor-tabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="tab-basic-<?php echo $i; ?>" data-bs-toggle="tab" data-bs-target="#content-basic-<?php echo $i; ?>" type="button" role="tab">
                                                <i class="fa fa-info-circle"></i> <?php _e('Basic Info', 'cftp_admin');?>
                                            </button>
                                        </li>
                                        <?php if (current_user_can('set_file_expiration_date')) { ?>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="tab-expiration-<?php echo $i; ?>" data-bs-toggle="tab" data-bs-target="#content-expiration-<?php echo $i; ?>" type="button" role="tab">
                                                <i class="fa fa-clock-o"></i> <?php _e('Expiration', 'cftp_admin');?>
                                            </button>
                                        </li>
                                        <?php } ?>
                                        <?php if (current_user_can('limit_downloads')) { ?>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="tab-download-limit-<?php echo $i; ?>" data-bs-toggle="tab" data-bs-target="#content-download-limit-<?php echo $i; ?>" type="button" role="tab">
                                                <i class="fa fa-download"></i> <?php _e('Download Limit', 'cftp_admin');?>
                                            </button>
                                        </li>
                                        <?php } ?>
                                        <?php if (current_user_can('upload_public')) { ?>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="tab-visibility-<?php echo $i; ?>" data-bs-toggle="tab" data-bs-target="#content-visibility-<?php echo $i; ?>" type="button" role="tab">
                                                <i class="fa fa-eye"></i> <?php _e('Visibility', 'cftp_admin');?>
                                            </button>
                                        </li>
                                        <?php } ?>
                                        <?php if (!current_role_in(['Client'])) { ?>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="tab-assignment-<?php echo $i; ?>" data-bs-toggle="tab" data-bs-target="#content-assignment-<?php echo $i; ?>" type="button" role="tab">
                                                <i class="fa fa-users"></i> <?php _e('Assignment', 'cftp_admin');?>
                                            </button>
                                        </li>
                                        <?php } ?>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="tab-organization-<?php echo $i; ?>" data-bs-toggle="tab" data-bs-target="#content-organization-<?php echo $i; ?>" type="button" role="tab">
                                                <i class="fa fa-folder"></i> <?php _e('Organization', 'cftp_admin');?>
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="ps-card">
                                        <div class="ps-card-body">
                                            <!-- Tabs Content -->
                                            <div class="tab-content file-editor-tab-content">
                                                <!-- Tab 1: Basic Info -->
                                                <div class="tab-pane fade show active" id="content-basic-<?php echo $i; ?>" role="tabpanel">
                                                    <div class="file_data">

                                                        <div class="form-group">
                                                            <label><?php _e('Title', 'cftp_admin');?></label>
                                                            <input type="text" name="file[<?php echo $i; ?>][name]" value="<?php echo $file->title; ?>" class="form-control" placeholder="<?php _e('Enter here the required file title.', 'cftp_admin');?>" />
                                                        </div>

                                                        <div class="form-group">
                                                            <label><?php _e('Description', 'cftp_admin');?></label>
                                                            <textarea id="description_<?php echo $file->id; ?>" name="file[<?php echo $i; ?>][description]" class="<?php if ( get_option('files_descriptions_use_ckeditor') == 1 ) { echo 'ckeditor'; } ?> form-control textarea_description" placeholder="<?php _e('Optionally, enter here a description for the file.', 'cftp_admin');?>"><?php if (!empty($file->description)) { echo (get_option('files_descriptions_use_ckeditor') == 1) ? htmlentities_allowed($file->description) : html_output($file->description); } ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>

                                                <?php if (current_user_can('set_file_expiration_date')) { ?>
                                                <!-- Tab 2: Expiration -->
                                                <div class="tab-pane fade" id="content-expiration-<?php echo $i; ?>" role="tabpanel">
                                                    <div class="file_data">
                                                        <div class="form-group">
                                                            <label for="file[<?php echo $i; ?>][expires_date]"><?php _e('Expiration date', 'cftp_admin');?></label>
                                                            <div class="input-group date-container">
                                                                <input type="text" class="date-field form-control datapick-field readonly-not-grayed" readonly id="file_expiry_date_<?php echo $i; ?>" name="file[<?php echo $i; ?>][expiry_date]" value="<?php echo (!empty($file->expiry_date)) ? date('d-m-Y', strtotime($file->expiry_date)) : date('d-m-Y'); ?>" />
                                                                <div class="input-group-text">
                                                                    <i class="fa fa-clock-o"></i>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="checkbox">
                                                            <label for="exp_checkbox_<?php echo $i; ?>">
                                                                <input type="checkbox" class="checkbox_setting_expires" name="file[<?php echo $i; ?>][expires]" id="exp_checkbox_<?php echo $i; ?>" value="1" <?php if ($file->expires) { ?>checked="checked"<?php } ?> /> <?php _e('File expires', 'cftp_admin');?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php } ?>

                                                <?php if (current_user_can('limit_downloads')) { ?>
                                                <!-- Tab 3: Download Limit -->
                                                <div class="tab-pane fade" id="content-download-limit-<?php echo $i; ?>" role="tabpanel">
                                                    <div class="file_data">
                                                        <div class="checkbox">
                                                            <label for="dl_limit_checkbox_<?php echo $i; ?>">
                                                                <input type="checkbox" class="checkbox_download_limit_enabled" id="dl_limit_checkbox_<?php echo $i; ?>" name="file[<?php echo $i; ?>][download_limit_enabled]" value="1" <?php if ($file->download_limit_enabled) { ?>checked="checked"<?php } ?> /> <?php _e('Enable download limit', 'cftp_admin');?>
                                                            </label>
                                                        </div>

                                                        <div class="download_limit_settings mt-3" <?php if (!$file->download_limit_enabled) { ?>style="display:none;"<?php } ?>>
                                                            <div class="form-group">
                                                                <label class="mb-2"><?php _e('Limit type', 'cftp_admin');?></label>
                                                                <div class="form-check mb-2">
                                                                    <input class="form-check-input" type="radio" name="file[<?php echo $i; ?>][download_limit_type]" id="dl_type_per_user_<?php echo $i; ?>" value="per_user" <?php if ($file->download_limit_type == 'per_user') { ?>checked="checked"<?php } ?>>
                                                                    <label class="form-check-label" for="dl_type_per_user_<?php echo $i; ?>">
                                                                        <?php _e('Per user', 'cftp_admin');?>
                                                                    </label>
                                                                    <small class="form-text text-muted d-block"><?php _e('Each user can download this file a limited number of times', 'cftp_admin');?></small>
                                                                </div>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="radio" name="file[<?php echo $i; ?>][download_limit_type]" id="dl_type_total_<?php echo $i; ?>" value="total" <?php if ($file->download_limit_type == 'total' || empty($file->download_limit_type)) { ?>checked="checked"<?php } ?>>
                                                                    <label class="form-check-label" for="dl_type_total_<?php echo $i; ?>">
                                                                        <?php _e('Total downloads', 'cftp_admin');?>
                                                                    </label>
                                                                    <small class="form-text text-muted d-block"><?php _e('File can only be downloaded a limited number of times in total', 'cftp_admin');?></small>
                                                                </div>
                                                            </div>

                                                            <div class="form-group">
                                                                <label for="download_limit_count_<?php echo $i; ?>"><?php _e('Maximum downloads', 'cftp_admin');?></label>
                                                                <input type="number" class="form-control" id="download_limit_count_<?php echo $i; ?>" name="file[<?php echo $i; ?>][download_limit_count]" value="<?php echo (!empty($file->download_limit_count)) ? $file->download_limit_count : 0; ?>" min="0" />
                                                                <small class="form-text text-muted"><?php _e('Your own downloads do not count toward the limit', 'cftp_admin');?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php } ?>

                                                <?php if (current_user_can('upload_public')) { ?>
                                                <!-- Tab 3: Visibility -->
                                                <div class="tab-pane fade" id="content-visibility-<?php echo $i; ?>" role="tabpanel">
                                                    <div class="file_data">

                                                                <div class="checkbox">
                                                                    <label for="pub_checkbox_<?php echo $i; ?>">
                                                                        <input type="checkbox" class="checkbox_setting_public" id="pub_checkbox_<?php echo $i; ?>" name="file[<?php echo $i; ?>][public]" value="1" <?php if ($file->public) { ?>checked="checked"<?php } ?>/> <?php _e('Allow public downloading of this file.', 'cftp_admin');?>
                                                                    </label>
                                                                </div>

                                                                <div class="form-group">
                                                                    <div class="divider"></div>
                                                                    <h3><?php _e('Custom download aliases', 'cftp_admin');?></h3>
                                                                    <?php foreach ($file->getCustomDownloads() as $j => $custom_download) {
                                                                        $trans = __('Enter a custom download link.', 'cftp_admin');
                                                                        $custom_download_uri = get_option('custom_download_uri');
                                                                        if (!$custom_download_uri) $custom_download_uri = BASE_URI . 'custom-download.php?link=';
                                                                        echo <<<EOL
                                                                            <div class="input-group">
                                                                                <input type="hidden" value="{$custom_download['link']}" name="file[$i][custom_downloads][$j][id]" />
                                                                                <input type="text" name="file[$i][custom_downloads][$j][link]"
                                                                                    id="custom_download_input_$j"
                                                                                    value="{$custom_download['link']}"
                                                                                    class="form-control"
                                                                                    placeholder="$trans" />
                                                                                <a href="#" class="input-group-text" onclick="copyTextToClipboard('$custom_download_uri' + document.getElementById('custom_download_input_$j').value);">
                                                                                    <i class="fa fa-copy" style="cursor: pointer"></i>
                                                                                </a>
                                                                            </div>
        EOL;
                                                                    }
                                                                    ?>
                                                                    <p class="field_note form-text">
                                                                        <?php echo sprintf(__('Optional: enter an alias to use on the custom download link. Ej: "my-first-file" will let you download this file from %s'), BASE_URI.'custom-download.php?link=my-first-file'); ?>
                                                                    </p>
                                                                </div>
                                                    </div>
                                                </div>
                                                <?php } ?>

                                                <?php if (!current_role_in(['Client'])) { ?>
                                                <!-- Tab 4: Assignment -->
                                                <div class="tab-pane fade" id="content-assignment-<?php echo $i; ?>" role="tabpanel">
                                                    <div class="file_data">
                                                                <label><?php _e('Clients', 'cftp_admin');?></label>
                                                                <select class="form-select select2 assignments_clients none" multiple="multiple" id="clients_<?php echo $file->id; ?>" name="file[<?php echo $i; ?>][assignments][clients][]" data-file-id="<?php echo $file->id; ?>" data-type="clients" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
                                                                    <?php
                                                                        foreach($clients as $id => $name) {
                                                                        ?>
                                                                            <option value="<?php echo html_output($id); ?>" <?php if (in_array($id, $file->assignments_clients)) { echo ' selected="selected"'; } ?>>
                                                                                <?php echo html_output($name); ?>
                                                                            </option>
                                                                        <?php
                                                                        }
                                                                    ?>
                                                                </select>
                                                                <div class="select_control_buttons">
                                                                    <button type="button" class="btn btn-sm btn-primary add-all" data-target="clients_<?php echo $file->id; ?>"><?php _e('Add all','cftp_admin'); ?></button>
                                                                    <button type="button" class="btn btn-sm btn-primary remove-all" data-target="clients_<?php echo $file->id; ?>"><?php _e('Remove all','cftp_admin'); ?></button>
                                                                </div>

                                                                <?php if (current_user_can('manage_groups')) { ?>
                                                                <div class="divider"></div>

                                                                <label><?php _e('Groups', 'cftp_admin');?></label>
                                                                <select class="form-select select2 assignments_groups none" multiple="multiple" id="groups_<?php echo $file->id; ?>" name="file[<?php echo $i; ?>][assignments][groups][]" data-file-id="<?php echo $file->id; ?>" data-type="groups" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
                                                                    <?php
                                                                        foreach($groups as $id => $name) {
                                                                        ?>
                                                                            <option value="<?php echo html_output($id); ?>" <?php if (in_array($id, $file->assignments_groups)) { echo ' selected="selected"'; } ?>>
                                                                                <?php echo html_output($name); ?>
                                                                            </option>
                                                                        <?php
                                                                        }
                                                                    ?>
                                                                </select>
                                                                <div class="select_control_buttons">
                                                                    <button type="button" class="btn btn-sm btn-primary add-all" data-target="groups_<?php echo $file->id; ?>"><?php _e('Add all','cftp_admin'); ?></button>
                                                                    <button type="button" class="btn btn-sm btn-primary remove-all" data-target="groups_<?php echo $file->id; ?>"><?php _e('Remove all','cftp_admin'); ?></button>
                                                                </div>
                                                                <?php } ?>

                                                                <div class="divider"></div>

                                                                <div class="checkbox">
                                                                    <label for="hid_checkbox_<?php echo $i; ?>">
                                                                        <input type="checkbox" class="checkbox_setting_hidden" id="hid_checkbox_<?php echo $i; ?>" name="file[<?php echo $i; ?>][hidden]" value="1" /> <?php _e('Hidden (will not send notifications or show into the files list)', 'cftp_admin');?>
                                                                    </label>
                                                                </div>
                                                    </div>
                                                </div>
                                                <?php } ?>

                                                <!-- Tab 5: Organization -->
                                                <div class="tab-pane fade" id="content-organization-<?php echo $i; ?>" role="tabpanel">
                                                    <div class="file_data">
                                                    <?php
                                                        // Categories assignment is available to users with set_file_categories permission
                                                        if (current_user_can('set_file_categories')) {
                                                            $ignore = []; // Initialize ignore array for categories
                                                            $generate_categories_options = generate_categories_options( $get_categories['arranged'], 0, $file->categories);
                                                    ?>
                                                            <div class="categories">
                                                                <h3><?php _e('Categories', 'cftp_admin');?></h3>
                                                                    <label><?php _e('Add to', 'cftp_admin');?>:</label>
                                                                    <select class="form-select select2 none" multiple="multiple" id="categories_<?php echo $file->id; ?>" name="file[<?php echo $i; ?>][categories][]" data-type="categories" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
                                                                        <?php echo render_categories_options($generate_categories_options, ['selected' => $file->categories, 'ignore' => $ignore]); ?>
                                                                    </select>
                                                                    <div class="select_control_buttons">
                                                                        <button type="button" class="btn btn-sm btn-primary add-all" data-target="categories_<?php echo $file->id; ?>"><?php _e('Add all','cftp_admin'); ?></button>
                                                                        <button type="button" class="btn btn-sm btn-primary remove-all" data-target="categories_<?php echo $file->id; ?>"><?php _e('Remove all','cftp_admin'); ?></button>
                                                                    </div>
                                                                </div>
                                                    <?php
                                                        }

                                                        if (current_user_can('set_file_categories')) {
                                                    ?>
                                                        <div class="divider"></div>
                                                    <?php } ?>

                                                    <div class="folders">
                                                        <h3><?php _e('Location', 'cftp_admin');?></h3>
                                                        <label><?php _e('Store in this folder', 'cftp_admin');?>:</label>
                                                        <?php
                                                            $ignore = [];
                                                            if (current_role_in(['Client'])) {
                                                                $see_public_folders = get_option('clients_files_list_include_public');
                                                                $statement = $dbh->prepare("SELECT * FROM " . TABLE_FOLDERS);
                                                                $statement->execute();
                                                                if ($statement->rowCount() > 0) {
                                                                    $statement->setFetchMode(PDO::FETCH_ASSOC);
                                                                    while ($folder_row = $statement->fetch()) {
                                                                        if ($folder_row['user_id'] == CURRENT_USER_ID) {
                                                                            continue;
                                                                        }
                                                                        if ($see_public_folders == '1' && $folder_row['public'] != 1) {
                                                                            $ignore[] = $folder_row['id'];
                                                                        }
                                                                    }
                                                                }
                                                            }

                                                            $folders = new \ProjectSend\Classes\Folders;
                                                            $folders_arranged = $folders->getAllArranged();

                                                            if (current_role_in(['Client']) && get_option('clients_files_list_include_public')) {
                                                                $folders_arguments['public_or_client'] = true;
                                                            }
                                                        ?>
                                                        <select class="form-select select2 none" id="folder_<?php echo $file->id; ?>" name="file[<?php echo $i; ?>][folder_id]" data-type="folder" data-placeholder="<?php _e('Optional. Type to search.', 'cftp_admin');?>">
                                                            <option value=""><?php _e('Root','cftp_admin'); ?></option>
                                                            <?php echo $folders->renderSelectOptions($folders_arranged, ['selected' => $file->folder_id, 'ignore' => $ignore]); ?>
                                                        </select>
                                                    </div>
                                                    </div>
                                                </div>
                                            </div><!-- /.tab-content -->

                                        </div>
                                    </div><!-- /.ps-card -->
                                </div><!-- /.col-lg-8 -->
                            </div><!-- /.row.file_editor -->
                        </div><!-- /.file_editor_wrapper -->
            <?php
                        $i++;
                    } else {
                        $msg = sprintf(__('File not found on disk: %s'), $file->filename_on_disk);
                        echo system_message('danger', $msg);
                    }
                }
            }
        ?>

        <?php if (count($editable) > 1) { ?>
        <!-- Sticky Bulk Actions Panel -->
        <div class="bulk-actions-panel collapsed" id="bulkActionsPanel">
            <div class="bulk-actions-header">
                <h3>
                    <i class="fa fa-bolt"></i>
                    <?php echo sprintf(__('Bulk Actions for %d files', 'cftp_admin'), count($editable)); ?>
                </h3>
                <button type="button" class="btn btn-sm btn-secondary" id="toggleBulkActions">
                    <i class="fa fa-chevron-down"></i>
                </button>
            </div>

            <div class="bulk-actions-content">
                <!-- Source File Selector -->
                <div class="bulk-action-row">
                    <label class="bulk-action-label">
                        <i class="fa fa-copy"></i> <?php _e('Copy all settings from:', 'cftp_admin'); ?>
                    </label>
                    <div class="bulk-action-controls">
                        <select class="form-select" id="bulkCopySourceFile">
                            <option value=""><?php _e('Select a file...', 'cftp_admin'); ?></option>
                            <?php
                            $file_index = 1;
                            foreach ($editable as $file_id) {
                                $file = new \ProjectSend\Classes\Files($file_id);
                                if ($file->recordExists()) {
                                    echo '<option value="' . $file_index . '">' . html_output($file->filename_original) . '</option>';
                                }
                                $file_index++;
                            }
                            ?>
                        </select>
                        <button type="button" class="btn btn-primary" id="bulkCopyAllSettings">
                            <?php _e('Copy All Settings', 'cftp_admin'); ?>
                        </button>
                    </div>
                </div>

                <div class="bulk-actions-divider"><?php _e('OR set individual settings', 'cftp_admin'); ?></div>

                <?php if (current_user_can('set_file_expiration_date')) { ?>
                <!-- Expiration -->
                <div class="bulk-action-row">
                    <label class="bulk-action-label">
                        <i class="fa fa-clock-o"></i> <?php _e('Expiration', 'cftp_admin'); ?>
                    </label>
                    <div class="bulk-action-controls">
                        <select class="form-select" id="bulkExpirationSource">
                            <option value=""><?php _e('Copy from file...', 'cftp_admin'); ?></option>
                            <?php
                            $file_index = 1;
                            foreach ($editable as $file_id) {
                                $file = new \ProjectSend\Classes\Files($file_id);
                                if ($file->recordExists()) {
                                    echo '<option value="' . $file_index . '">' . html_output($file->filename_original) . '</option>';
                                }
                                $file_index++;
                            }
                            ?>
                        </select>
                        <button type="button" class="btn btn-primary btn-sm bulk-copy-expiration">
                            <?php _e('Apply', 'cftp_admin'); ?>
                        </button>
                    </div>
                </div>
                <?php } ?>

                <?php if (current_user_can('limit_downloads')) { ?>
                <!-- Download Limits -->
                <div class="bulk-action-row">
                    <label class="bulk-action-label">
                        <i class="fa fa-download"></i> <?php _e('Download Limits', 'cftp_admin'); ?>
                    </label>
                    <div class="bulk-action-controls">
                        <select class="form-select" id="bulkDownloadLimitSource">
                            <option value=""><?php _e('Copy from file...', 'cftp_admin'); ?></option>
                            <?php
                            $file_index = 1;
                            foreach ($editable as $file_id) {
                                $file = new \ProjectSend\Classes\Files($file_id);
                                if ($file->recordExists()) {
                                    echo '<option value="' . $file_index . '">' . html_output($file->filename_original) . '</option>';
                                }
                                $file_index++;
                            }
                            ?>
                        </select>
                        <button type="button" class="btn btn-primary btn-sm bulk-copy-download-limits">
                            <?php _e('Apply', 'cftp_admin'); ?>
                        </button>
                    </div>
                </div>
                <?php } ?>

                <?php if (current_user_can('upload_public')) { ?>
                <!-- Visibility -->
                <div class="bulk-action-row">
                    <label class="bulk-action-label">
                        <i class="fa fa-eye"></i> <?php _e('Visibility', 'cftp_admin'); ?>
                    </label>
                    <div class="bulk-action-controls">
                        <select class="form-select" id="bulkVisibilitySource">
                            <option value=""><?php _e('Copy from file...', 'cftp_admin'); ?></option>
                            <?php
                            $file_index = 1;
                            foreach ($editable as $file_id) {
                                $file = new \ProjectSend\Classes\Files($file_id);
                                if ($file->recordExists()) {
                                    echo '<option value="' . $file_index . '">' . html_output($file->filename_original) . '</option>';
                                }
                                $file_index++;
                            }
                            ?>
                        </select>
                        <button type="button" class="btn btn-primary btn-sm bulk-copy-public">
                            <?php _e('Apply', 'cftp_admin'); ?>
                        </button>
                    </div>
                </div>
                <?php } ?>

                <?php if (!current_role_in(['Client'])) { ?>
                <!-- Assignment -->
                <div class="bulk-action-row">
                    <label class="bulk-action-label">
                        <i class="fa fa-users"></i> <?php _e('Assignment', 'cftp_admin'); ?>
                    </label>
                    <div class="bulk-action-controls">
                        <select class="form-select" id="bulkAssignmentSource">
                            <option value=""><?php _e('Copy from file...', 'cftp_admin'); ?></option>
                            <?php
                            $file_index = 1;
                            foreach ($editable as $file_id) {
                                $file = new \ProjectSend\Classes\Files($file_id);
                                if ($file->recordExists()) {
                                    echo '<option value="' . $file_index . '">' . html_output($file->filename_original) . '</option>';
                                }
                                $file_index++;
                            }
                            ?>
                        </select>
                        <button type="button" class="btn btn-primary btn-sm bulk-copy-clients">
                            <?php _e('Apply Clients', 'cftp_admin'); ?>
                        </button>
                        <?php if (current_user_can('manage_groups')) { ?>
                        <button type="button" class="btn btn-primary btn-sm bulk-copy-groups">
                            <?php _e('Apply Groups', 'cftp_admin'); ?>
                        </button>
                        <?php } ?>
                        <button type="button" class="btn btn-primary btn-sm bulk-copy-hidden">
                            <?php _e('Apply Hidden', 'cftp_admin'); ?>
                        </button>
                    </div>
                </div>
                <?php } ?>

                <!-- Organization -->
                <div class="bulk-action-row">
                    <label class="bulk-action-label">
                        <i class="fa fa-folder"></i> <?php _e('Organization', 'cftp_admin'); ?>
                    </label>
                    <div class="bulk-action-controls">
                        <select class="form-select" id="bulkOrganizationSource">
                            <option value=""><?php _e('Copy from file...', 'cftp_admin'); ?></option>
                            <?php
                            $file_index = 1;
                            foreach ($editable as $file_id) {
                                $file = new \ProjectSend\Classes\Files($file_id);
                                if ($file->recordExists()) {
                                    echo '<option value="' . $file_index . '">' . html_output($file->filename_original) . '</option>';
                                }
                                $file_index++;
                            }
                            ?>
                        </select>
                        <?php if (current_user_can('set_file_categories')) { ?>
                        <button type="button" class="btn btn-primary btn-sm bulk-copy-categories">
                            <?php _e('Apply Categories', 'cftp_admin'); ?>
                        </button>
                        <?php } ?>
                        <button type="button" class="btn btn-primary btn-sm bulk-copy-folder">
                            <?php _e('Apply Folder', 'cftp_admin'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>
    </div> <!-- container -->

    <div class="after_form_buttons">
        <button type="submit" name="save" class="btn btn-wide btn-primary" id="upload-continue"><?php _e('Save','cftp_admin'); ?></button>
    </div>
</form>