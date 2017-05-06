<?php
/**
 * File to check if the 'shred' command is installed.
 * This command is installed by default on ubuntu servers.
 * This file is called in options.php, when the user tries to enable
 * the option to securely delete files.
 *
 * @package ProjectSend
 */

if(empty(shell_exec("shred --version"))){
  echo '{"installed":false}';
} else {
  echo '{"installed":true}';
}

?>
