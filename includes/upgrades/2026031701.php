<?php
function upgrade_2026031701()
{
    global $dbh;

    set_time_limit(0);

    // Get files with missing sizes using lightweight query (no Files object)
    $files_sql = "SELECT id, url, disk_folder_year, disk_folder_month
                  FROM " . TABLE_FILES . "
                  WHERE size = 0 OR size IS NULL";
    $files_statement = $dbh->prepare($files_sql);
    $files_statement->execute();
    $files = $files_statement->fetchAll(PDO::FETCH_ASSOC);

    $total_files = count($files);
    if ($total_files == 0) {
        error_log("ProjectSend: File size migration - no files need updating.");
        return;
    }

    $update_sql = "UPDATE " . TABLE_FILES . " SET size = :size WHERE id = :id";
    $update_statement = $dbh->prepare($update_sql);

    $updated_count = 0;

    foreach ($files as $index => $file) {
        $filename = $file['url'];
        if (empty($filename)) {
            continue;
        }

        // Construct file path directly (same logic as Files::getFilePath())
        $path = UPLOADED_FILES_DIR . DS;
        if (!empty($file['disk_folder_year'])) {
            $path .= $file['disk_folder_year'] . DS;
        }
        if (!empty($file['disk_folder_month'])) {
            $path .= $file['disk_folder_month'] . DS;
        }
        $path .= $filename;

        // Fallback to flat path if date-folder path doesn't exist
        if (!file_exists($path) && (!empty($file['disk_folder_year']) || !empty($file['disk_folder_month']))) {
            $path = UPLOADED_FILES_DIR . DS . $filename;
        }

        if (file_exists($path)) {
            try {
                $file_size = get_real_size($path);
                if (is_numeric($file_size) && $file_size > 0) {
                    $update_statement->execute([
                        'size' => $file_size,
                        'id' => $file['id']
                    ]);
                    $updated_count++;
                }
            } catch (Exception $e) {
                error_log("ProjectSend: Could not get size for file ID {$file['id']}: " . $e->getMessage());
            }
        }

        // Log progress every 1000 files
        if (($index + 1) % 1000 == 0) {
            error_log("ProjectSend: File size migration progress - processed " . ($index + 1) . " of {$total_files} files.");
        }
    }

    error_log("ProjectSend: File size migration completed. Updated {$updated_count} of {$total_files} files.");
}
