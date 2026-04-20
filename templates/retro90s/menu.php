<?php
/**
 * Unified Menu for Retro 90s Template
 * Handles both client (logged-in) and public navigation
 */

// Determine if we're in public mode
$is_public_mode = (basename($_SERVER['PHP_SELF']) === 'public.php');
$is_logged_in = user_is_logged_in();
?>

<?php if ($is_public_mode): ?>
    <!-- Public Navigation Bar -->
    <table width="100%" cellpadding="4" cellspacing="2" border="0" bgcolor="#008080">
        <tr>
            <td bgcolor="#c0c0c0">
                <table width="100%" cellpadding="2" cellspacing="1" border="0">
                    <tr bgcolor="#800080">
                        <td>
                            <font face="Arial, sans-serif" color="#ffffff" size="2">
                                <b>
                                    <?php if (isset($mode) && $mode == 'group'): ?>
                                        <a href="<?php echo BASE_URI; ?>public.php" style="color: #00ffff; text-decoration: none;">
                                            [← <?php _e('Back to Public Files', 'retro90s_template'); ?>]
                                        </a>
                                        &nbsp;|&nbsp;
                                        <?php echo html_output($group_props['name']); ?>
                                    <?php else: ?>
                                        <?php _e('Browse public files', 'retro90s_template'); ?>
                                    <?php endif; ?>
                                </b>
                            </font>
                        </td>
                        <td align="right">
                            <?php if ($is_logged_in): ?>
                                <font face="Arial, sans-serif" color="#00ffff" size="2">
                                    <a href="<?php echo BASE_URI; ?>my_files/" style="color: #00ffff; text-decoration: none;">[My Files]</a>
                                    <a href="<?php echo BASE_URI; ?>process.php?do=logout" style="color: #00ffff; text-decoration: none;">[Logout]</a>
                                </font>
                            <?php else: ?>
                                <font face="Arial, sans-serif" color="#00ffff" size="2">
                                    <a href="<?php echo BASE_URI; ?>index.php" style="color: #00ffff; text-decoration: none;">[Login]</a>
                                </font>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

<?php else: ?>
    <!-- Client Navigation Bar -->
    <table width="100%" cellpadding="4" cellspacing="2" border="0" bgcolor="#008080">
        <tr>
            <td bgcolor="#c0c0c0">
                <table width="100%" cellpadding="2" cellspacing="1" border="0">
                    <tr bgcolor="#808080">
                        <td>
                            <font face="Arial, sans-serif" color="#ffffff" size="2">
                                <b>► NAVIGATION</b>
                            </font>
                        </td>
                    </tr>
                    <tr bgcolor="#c0c0c0">
                        <td>
                            <font face="Arial, sans-serif" size="2">
                                <a href="<?php echo CLIENT_VIEW_FILE_LIST_URL; ?>">🏠 My Files</a> |
                                <?php if (current_user_can_upload()) { ?>
                                    <a href="<?php echo BASE_URI; ?>upload.php">📤 Upload</a> |
                                <?php } ?>
                                <a href="<?php echo BASE_URI; ?>manage-files.php">⚙️ Manage</a> |
                                <a href="<?php echo BASE_URI; ?>process.php?do=logout">🚪 Logout</a>
                            </font>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Search & Filters Table (Client only) -->
    <table width="100%" cellpadding="4" cellspacing="2" border="0" bgcolor="#008080">
        <tr>
            <td bgcolor="#c0c0c0">
                <table width="100%" cellpadding="2" cellspacing="1" border="0">
                    <tr bgcolor="#808080">
                        <td colspan="2">
                            <font face="Arial, sans-serif" color="#ffffff" size="2">
                                <b>🔍 SEARCH & FILTER</b>
                            </font>
                        </td>
                    </tr>
                    <tr bgcolor="#c0c0c0">
                        <td width="50%">
                            <?php if (!empty($search_form_action)) { ?>
                                <form action="<?php echo $search_form_action; ?>" name="form_search" method="get">
                                    <?php echo form_add_existing_parameters( array('search', 'action') ); ?>
                                    <font face="Arial, sans-serif" size="2">
                                        <b>Search:</b><br>
                                        <input type="text" name="search" value="<?php if(isset($_GET['search']) && !empty($_GET['search'])) { echo html_output($_GET['search']); } ?>" size="20" />
                                        <input type="submit" value="GO!" />
                                    </font>
                                </form>
                            <?php } ?>
                        </td>
                        <td width="50%">
                            <?php if (!empty($filters_form['items'])) { ?>
                                <form action="<?php echo $filters_form['action']; ?>" name="actions_filters" method="get">
                                    <?php echo form_add_existing_parameters(array_keys($filters_form['items'])); ?>
                                    <font face="Arial, sans-serif" size="2">
                                        <b>Category:</b><br>
                                        <?php foreach ($filters_form['items'] as $name => $data) { ?>
                                            <select name="<?php echo $name; ?>" onchange="this.form.submit()">
                                                <?php if (!empty($data['placeholder'])) { ?>
                                                    <option value="<?php echo $data['placeholder']['value']; ?>"><?php echo $data['placeholder']['label']; ?></option>
                                                <?php } ?>
                                                <?php foreach ($data['options'] as $value => $option) { ?>
                                                    <option value="<?php echo $value; ?>" <?php if (isset($data['current']) && $data['current'] == $value) { echo 'selected="selected"'; } ?>>
                                                        <?php echo is_array($option) ? $option['name'] : $option; ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        <?php } ?>
                                    </font>
                                </form>
                            <?php } ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Folders Navigation Table (Client only) -->
    <?php if (!empty($_GET['folder_id']) || !empty($folders)) { ?>
        <table width="100%" cellpadding="4" cellspacing="2" border="0" bgcolor="#008080">
            <tr>
                <td bgcolor="#c0c0c0">
                    <table width="100%" cellpadding="2" cellspacing="1" border="0">
                        <?php if (!empty($_GET['folder_id'])) { ?>
                            <tr bgcolor="#808080">
                                <td>
                                    <font face="Arial, sans-serif" color="#ffffff" size="2">
                                        <b>📁 FOLDER NAVIGATION</b>
                                    </font>
                                </td>
                            </tr>
                            <tr bgcolor="#c0c0c0">
                                <td>
                                    <?php
                                        $root_link = modify_url_with_parameters($current_url, [], ['folder_id']);
                                    ?>
                                    <font face="Arial, sans-serif" size="2">
                                        <a href="<?php echo $root_link; ?>" class="retro-button">
                                            🏠 ROOT
                                        </a>

                                        <?php
                                            $get_parent = new \ProjectSend\Classes\Folder($_GET['folder_id']);
                                            $parent_data = $get_parent->getData();
                                            if (!empty($parent_data['parent'])) {
                                                $up_link = modify_url_with_parameters($current_url, ['folder_id' => $parent_data['parent']], ['folder_id']);
                                        ?>
                                                <a href="<?php echo $up_link; ?>" class="retro-button">
                                                    ⬆️ UP
                                                </a>
                                        <?php } ?>
                                    </font>
                                </td>
                            </tr>
                        <?php } ?>

                        <!-- Show Folders if any exist -->
                        <?php if (!empty($folders)) { ?>
                            <tr bgcolor="#808080">
                                <td>
                                    <font face="Arial, sans-serif" color="#ffffff" size="2">
                                        <b>📂 FOLDERS</b>
                                    </font>
                                </td>
                            </tr>
                            <tr bgcolor="#c0c0c0">
                                <td>
                                    <table width="100%" cellpadding="2" cellspacing="1" border="0">
                                        <?php
                                        // Responsive folders per row - more on desktop, fewer on mobile
                                        $folders_per_row = 5; // Desktop: 5 folders per row
                                        $folder_count = count($folders);
                                        $folder_index = 0;

                                        foreach ($folders as $folder_id => $folder) {
                                            if ($folder_index % $folders_per_row == 0) {
                                                echo '<tr bgcolor="#f0f0f0">';
                                            }

                                            $folder_link = modify_url_with_parameters($current_url, ['folder_id' => $folder_id], ['folder_id']);
                                        ?>
                                            <td align="center" valign="top" style="padding: 4px;">
                                                <a href="<?php echo $folder_link; ?>" class="retro-button" style="display: block; text-decoration: none;">
                                                    <font face="Arial, sans-serif" size="1" color="#000080">
                                                        📁<br>
                                                        <b><?php echo html_output($folder['name']); ?></b>
                                                        <?php if (isset($folder['file_count']) && $folder['file_count'] > 0) { ?>
                                                            <br><font size="1">(<?php echo $folder['file_count']; ?> files)</font>
                                                        <?php } ?>
                                                    </font>
                                                </a>
                                            </td>
                                        <?php
                                            $folder_index++;

                                            if ($folder_index % $folders_per_row == 0 || $folder_index == $folder_count) {
                                                // Fill remaining cells if this is the last row and it's not complete
                                                if ($folder_index == $folder_count && $folder_count % $folders_per_row != 0) {
                                                    $remaining_cells = $folders_per_row - ($folder_count % $folders_per_row);
                                                    for ($i = 0; $i < $remaining_cells; $i++) {
                                                        echo '<td></td>';
                                                    }
                                                }
                                                echo '</tr>';
                                            }
                                        }
                                        ?>
                                    </table>
                                </td>
                            </tr>
                        <?php } ?>
                    </table>
                </td>
            </tr>
        </table>
    <?php } ?>

<?php endif; ?>