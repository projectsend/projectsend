<?php
function upgrade_2025092107()
{
    global $dbh;

    // Add size column to files table
    $add_size_column_sql = "ALTER TABLE " . TABLE_FILES . "
                           ADD COLUMN size BIGINT UNSIGNED DEFAULT 0
                           AFTER original_url";

    try {
        // Check if column already exists
        $check_column_sql = "SHOW COLUMNS FROM " . TABLE_FILES . " LIKE 'size'";
        $check_statement = $dbh->prepare($check_column_sql);
        $check_statement->execute();

        if ($check_statement->rowCount() == 0) {
            // Column doesn't exist, add it
            $statement = $dbh->prepare($add_size_column_sql);
            $statement->execute();

            // Add index for better performance on size-based queries
            $add_index_sql = "ALTER TABLE " . TABLE_FILES . " ADD INDEX idx_size (size)";
            $index_statement = $dbh->prepare($add_index_sql);
            $index_statement->execute();
        }
    } catch (PDOException $e) {
        error_log("ProjectSend: Could not add size column to files table: " . $e->getMessage());
    }

    // Now populate file sizes from the file system
    // Get all files that need size calculation
    $files_sql = "SELECT id, filename FROM " . TABLE_FILES . " WHERE size = 0 OR size IS NULL";
    $files_statement = $dbh->prepare($files_sql);
    $files_statement->execute();
    $files = $files_statement->fetchAll(PDO::FETCH_ASSOC);

    $updated_count = 0;
    $total_files = count($files);

    if ($total_files > 0) {
        // Process files in batches to avoid memory issues
        foreach ($files as $file) {
            try {
                // Create a Files object to get the size using existing method
                $file_obj = new \ProjectSend\Classes\Files($file['id']);

                if ($file_obj->recordExists()) {
                    // Get the file size using the existing getSize method
                    $file_obj->getSize();
                    $file_size = $file_obj->size;

                    // Update the database with the calculated size
                    if (is_numeric($file_size) && $file_size >= 0) {
                        $update_sql = "UPDATE " . TABLE_FILES . " SET size = :size WHERE id = :id";
                        $update_statement = $dbh->prepare($update_sql);
                        $update_statement->execute([
                            'size' => $file_size,
                            'id' => $file['id']
                        ]);
                        $updated_count++;
                    }
                }
            } catch (Exception $e) {
                error_log("ProjectSend: Could not calculate size for file ID {$file['id']}: " . $e->getMessage());
                // Continue with other files even if one fails
            }
        }
    }

    // Log the results
    error_log("ProjectSend: File size migration completed. Updated {$updated_count} of {$total_files} files.");
}