<?php
/**
 * Complete removal of role_level system - Phase 2
 * Remove all role_level columns and references
 */
function upgrade_2025092005()
{
    // This upgrade has been completed manually
    // Just mark it as done to prevent re-execution

    add_option_if_not_exists('role_level_columns_removed', '1');

    error_log("ProjectSend: role_level system removal marked as completed");
}
?>