<?php
/**
 * Generates an array of valid templates to use on the options page.
 * Each template must have at least two files:
 *
 * template.php and main.css
 * 
 * The template name must be defined on line 4 of template.php
 *
 * @package		ProjectSend
 * @subpackage	Templates
 * 
 */

function look_for_templates() {
	/**
	 * Get all folders under the templates directory
	 */
	global $templates_ok;
	global $templates_error;
	$ignore = array('.', '..');
	$base_directory = './templates/';
	$directories = glob($base_directory . "*");
	foreach($directories as $directory) {
		if(is_dir($directory) && !in_array($directory,$ignore)) {
			if(check_template_integrity($directory)) {
				$folder = str_replace($base_directory,'',$directory);
				/**
				 * Get the template name from line nº4 of template.php
				 * If it's empty, the name is defined using the container folder name.
				 */
				$read_file = $directory.'/template.php';
				$file_info = file($read_file);
				$name = (string)$file_info[3];
				if (empty($name)) {
					$name = '$directory';
				}
				/**
				 * Generate the valid templates array
				 */
				$templates_ok[] = array(
									'folder' => $folder,
									'uri' => $directory,
									'name' => $name
								);
			}
			else {
				/**
				 * Generate another array with the templates that are not complete
				 */
				$templates_error[] = array(
									'uri' => $directory
								);
			}
		}
	}
	return $templates_ok;
}

function check_template_integrity($folder) {
	/**
	 * Define the basic files that each template must have to be considered
	 * valid.
	 */
	$required_files = array(
		'template.php',
		'main.css'
	);
	$miss = 0;
	$found = glob($folder . "/*");
	foreach ($required_files as $required) {
		$this_file = $folder.'/'.$required;
		if(!in_array($this_file,$found)) {
			$miss++;
		}
	}
	if($miss == 0) {
		return true;
	}
	unset($miss);
}