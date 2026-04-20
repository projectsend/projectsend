<?php
/**
 * Functions related to the files list templates
 */

/**
 * Get the template and author information from template.php
 * Based on the WordPress (LOVE IT!) function get_file_data()
 *
 * @param [type] $template_file
 * @return array
 */
function extract_template_info($template_directory)
{
    if (empty($template_directory)) {
        return false;
    }

    $folder = str_replace(TEMPLATES_DIR . DS, '', $template_directory);

    $read_file = $template_directory . DS . 'template.php';
    $fp = fopen( $read_file, 'r' );
    $file_info = fread( $fp, 8192 );
    fclose( $fp );

    $file_info = str_replace( "\r", "\n", $file_info );

    $template_info	= array(
        'name' => 'Template name',
        'themeuri' => 'URI',
        'author' => 'Author',
        'authoruri' => 'Author URI',
        'authoremail' => 'Author e-mail',
        'domain' => 'Domain',
        'description' => 'Description',
    );

    foreach ( $template_info as $data => $regex ) {
        if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_info, $match ) && $match[1] )
            $template_info[ $data ] = html_output($match[1]);
        else
            $template_info[ $data ] = '';
    }

    if ( empty( $template_info['name'] ) ) {
        $template_info['name'] = $template_directory;
    }

    // Location is the value saved on the DB.
    $template_info['location'] = $folder;
    
    // Currently active template
    if ( $folder == get_option('selected_clients_template') ) {
        $template_info['active'] = 1;
    }
    
    // Look for the screenshot
    $screenshot_file = $template_directory . DS . 'screenshot.png';
    $cover_file = $template_directory . DS . 'cover.png';
    $screenshot_url = TEMPLATES_URL . DS . $folder . DS . 'screenshot.png';
    $cover_url = TEMPLATES_URL . DS . $folder . DS . 'cover.png';

    $template_info['screenshot'] = ( file_exists( $screenshot_file ) ) ? $screenshot_url : ASSETS_IMG_URL . 'template-screenshot.png';
    if ( file_exists( $cover_file ) ) {
        $template_info['cover']	= $cover_url;
    }

    // Check for template features
    if ($folder === 'default') {
        $template_info['features'] = [
            'client_files' => true,
            'public_files' => true,
            'download_page' => true,
            'has_settings' => false,
        ];
    } else {
        $template_info['features'] = [
            'client_files' => file_exists($template_directory . DS . 'template.php'),
            'public_files' => file_exists($template_directory . DS . 'public.php'),
            'download_page' => file_exists($template_directory . DS . 'public-download.php'),
            'has_settings' => file_exists($template_directory . DS . 'settings.php'),
        ];
    }

    return $template_info;
}

/**
  * Generates an array of valid templates to use on the options page.
  * 
  * The template name must be defined on line 4 of template.php
  *
  * @return array
  */
function look_for_templates()
{
    // Get all folders under the templates directory
    $templates = [];
    $templates_error = [];

    $ignore = array('.', '..');
    $base_directory = TEMPLATES_DIR . DS;
    $directories = glob($base_directory . "*");
    foreach ($directories as $directory) {
        if (is_dir($directory) && !in_array($directory,$ignore)) {
            if (check_template_integrity($directory)) {
                
                $template_info = extract_template_info($directory);
                
                // Generate the valid templates array
                $templates[] = $template_info;
            }
            else {
                // Generate another array with the templates that are not complete
                $templates_error[] = [
                    'templates_error' => $directory
                ];
            }
        }
    }

    // Put active template as first element of the array
    foreach ($templates as $index => $template) {
        if (array_key_exists('active', $template) ) {
            unset($templates[$index]);
            array_unshift($templates, $template);
        }
    }

    //print_array($templates);
    return $templates;
}

/**
 * Define the basic files that each template must have to be considered valid
 * 
 * Each template must have at least two files:
 * template.php and main.css
 *
 * @param [type] $folder
 * @return bool
 */
function check_template_integrity($folder)
{
    $required_files = [
        'template.php',
    ];
    $miss = 0;
    $found = glob($folder . "/*");
    foreach ($required_files as $required) {
        $this_file = $folder . '/' . $required;
        if (!in_array($this_file, $found)) {
            $miss++;
        }
    }

    if ($miss == 0) {
        return true;
    }

    unset($miss);
    
    return false;
}

/**
 * Get appropriate FontAwesome icon class for file type
 *
 * @param string $extension
 * @return string
 */
function get_file_type_icon($extension)
{
    $iconMap = [
        // Documents
        'pdf' => 'fas fa-file-pdf',
        'doc' => 'fas fa-file-word',
        'docx' => 'fas fa-file-word',
        'xls' => 'fas fa-file-excel',
        'xlsx' => 'fas fa-file-excel',
        'ppt' => 'fas fa-file-powerpoint',
        'pptx' => 'fas fa-file-powerpoint',
        'txt' => 'fas fa-file-text',
        'rtf' => 'fas fa-file-text',
        
        // Images
        'jpg' => 'fas fa-file-image',
        'jpeg' => 'fas fa-file-image',
        'png' => 'fas fa-file-image',
        'gif' => 'fas fa-file-image',
        'bmp' => 'fas fa-file-image',
        'svg' => 'fas fa-file-image',
        'webp' => 'fas fa-file-image',
        
        // Audio
        'mp3' => 'fas fa-file-audio',
        'wav' => 'fas fa-file-audio',
        'flac' => 'fas fa-file-audio',
        'aac' => 'fas fa-file-audio',
        'ogg' => 'fas fa-file-audio',
        
        // Video
        'mp4' => 'fas fa-file-video',
        'avi' => 'fas fa-file-video',
        'mov' => 'fas fa-file-video',
        'wmv' => 'fas fa-file-video',
        'flv' => 'fas fa-file-video',
        'webm' => 'fas fa-file-video',
        
        // Archives
        'zip' => 'fas fa-file-archive',
        'rar' => 'fas fa-file-archive',
        '7z' => 'fas fa-file-archive',
        'tar' => 'fas fa-file-archive',
        'gz' => 'fas fa-file-archive',
        
        // Code
        'html' => 'fas fa-file-code',
        'css' => 'fas fa-file-code',
        'js' => 'fas fa-file-code',
        'php' => 'fas fa-file-code',
        'py' => 'fas fa-file-code',
        'java' => 'fas fa-file-code',
        'cpp' => 'fas fa-file-code',
        'c' => 'fas fa-file-code',
        'sql' => 'fas fa-file-code'
    ];
    
    return $iconMap[strtolower($extension)] ?? 'fas fa-file';
}

function template_load_translation($template)
{
    $lang = (isset($_SESSION['lang'])) ? $_SESSION['lang'] : SITE_LANG;
    if(!isset($ld)) { $ld = 'cftp_admin'; }
    $mo_file = ROOT_DIR.DS."templates".DS.$template.DS."lang".DS."{$lang}.mo";

    ProjectSend\Classes\I18n::LoadDomain($mo_file, $ld);
}

function get_template_file_location($file)
{
    $location = get_selected_template_path() . $file;
    if (file_exists($location)) {
        return $location;
    }

    $default_location = get_default_template_path() . $file;
    if (file_exists($default_location)) {
        return $default_location;
    }
}

function get_selected_template_path()
{
    $path = ROOT_DIR.DS.'templates'.DS.get_option('selected_clients_template').DS;
    return $path;
}

function get_default_template_path()
{
    $path = ROOT_DIR.DS.'templates'.DS.'default'.DS;
    return $path;
}

function get_public_template_file_location()
{
    // Check for complete template override first (public.php)
    $template_public_file = get_selected_template_path() . 'public.php';
    if (file_exists($template_public_file)) {
        return $template_public_file;
    }
    
    // Fallback to content-only template (public-list.php)
    return get_template_file_location('public-list.php');
}

/**
 * Get Material Design icon for file type (Google Drive template)
 */
function get_material_file_icon($extension)
{
    $iconMap = [
        // Documents
        'pdf' => 'picture_as_pdf',
        'doc' => 'description',
        'docx' => 'description',
        'xls' => 'grid_on',
        'xlsx' => 'grid_on',
        'ppt' => 'slideshow',
        'pptx' => 'slideshow',
        'txt' => 'description',
        'rtf' => 'description',
        
        // Images
        'jpg' => 'image',
        'jpeg' => 'image',
        'png' => 'image',
        'gif' => 'image',
        'bmp' => 'image',
        'svg' => 'image',
        'webp' => 'image',
        
        // Audio
        'mp3' => 'audiotrack',
        'wav' => 'audiotrack',
        'flac' => 'audiotrack',
        'aac' => 'audiotrack',
        'ogg' => 'audiotrack',
        
        // Video
        'mp4' => 'movie',
        'avi' => 'movie',
        'mov' => 'movie',
        'wmv' => 'movie',
        'flv' => 'movie',
        'webm' => 'movie',
        
        // Archives
        'zip' => 'archive',
        'rar' => 'archive',
        '7z' => 'archive',
        'tar' => 'archive',
        'gz' => 'archive',
        
        // Code
        'html' => 'code',
        'css' => 'code',
        'js' => 'code',
        'php' => 'code',
        'py' => 'code',
        'java' => 'code',
        'cpp' => 'code',
        'c' => 'code',
        'sql' => 'code'
    ];
    
    return $iconMap[strtolower($extension)] ?? 'insert_drive_file';
}
