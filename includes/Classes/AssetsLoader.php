<?php
namespace ProjectSend\Classes;

Class AssetsLoader
{
    private $css_assets;
    private $js_assets;

    public function __construct()
    {
        $this->loadDefaultAssets();
        $this->addCustomFilesAssets();
    }

    private function loadDefaultAssets()
    {
        $db_version = get_option('database_version');

        $this->css_assets = [
            'head' => [
                //'font_opensans' => ['url' => 'https://fonts.googleapis.com/css?family=Open+Sans:400,700,300'],
                'css_assets' =>['url' => ASSETS_CSS_URL . '/assets.css', 'version' => $db_version],
                'css_main' =>['url' => ASSETS_CSS_URL . '/main.css', 'version' => $db_version],
            ],
            'footer' => [
            ]
        ];
        $this->js_assets = [
            'head' => [
                'jquery' => ['url' => ASSETS_LIB_URL.'/jquery/jquery.min.js', 'version' => '3.6.1'],
                'jquery-migrate' =>['url' => ASSETS_LIB_URL.'/jquery-migrate/jquery-migrate.min.js', 'version' => '3.0.1'],
                'ckeditor5' =>['url' => BASE_URI.'/node_modules/@ckeditor/ckeditor5-build-classic/build/ckeditor.js', 'version' => '23.1.0'],
                'html5shiv' =>['url' => ASSETS_LIB_URL.'/html5shiv.min.js', 'version' => '3.7.3', 'args' => ['condition' => 'lt IE 9']],
                'respondjs' =>['url' => ASSETS_LIB_URL.'/respond.min.js', 'version' => '1.4.2', 'args' => ['condition' => 'lt IE 9']],
            ],
            'footer' => [
                'js_assets' =>['url' => ASSETS_JS_URL . '/assets.js', 'version' => $db_version],
                'js_app' =>['url' => ASSETS_JS_URL . '/app.js', 'version' => $db_version],
                ] 
            ];

            if (recaptcha2_is_enabled()) {
                $this->js_assets['footer']['recaptcha2'] = ['url' => 'https://www.google.com/recaptcha/api.js'];
            }
    }

    private function addCustomFilesAssets()
    {
        $custom_css_locations = [ 'css/custom.css', 'assets/custom/custom.css' ];
        foreach ( $custom_css_locations as $css_file ) {
            if ( file_exists ( ROOT_DIR . DS . $css_file ) ) {
                $name = str_replace('.', '_', basename($css_file));
                $this->css_assets['head'][$name]	= ['url' => BASE_URI . $css_file];
            }
        }

        $custom_js_locations = [ 'includes/js/custom.js', 'assets/custom/custom.js' ];
        foreach ( $custom_js_locations as $js_file ) {
            if ( file_exists ( ROOT_DIR . DS . $js_file ) ) {
                $name = str_replace('.', '_', basename($js_file));
                $this->js_assets['head'][$name]	= ['url' => BASE_URI . $js_file];
            }
        }
    }

    public function addAsset($type, $name, $url, $position, $version = null, $arguments = [])
    {
        if (!in_array($position, ['head', 'footer'])) {
            $position = 'footer';
        }

        $asset = [
            'url' =>  $url,
            'version' => $version,
            'args' => $arguments,
        ];
        switch ($type) {
            case 'js':
                $this->js_assets[$position][$name] = $asset;
            break;
            case 'css':
                $this->css_assets[$position][$name] = $asset;
            break;
        }
    }

    public function renderAssets($type, $location)
    {
        switch ($type) {
            case 'js':
                $assets = $this->js_assets;
            break;
            case 'css':
                $assets = $this->css_assets;
            break;
        }

        if (!empty($assets[$location])) {
            foreach ($assets[$location] as $name => $data) {
                if (!empty($data['args']['condition'])) { echo '<!--[if '.$data['args']['condition'].']>'; }
                $version = (!empty($data['version'])) ? $data['version'] : '';
                switch ($type) {
                    case 'js':
                        echo '<script name="'.$name.'" src="'.$data['url'].'?v='.$version.'" type="text/javascript"></script>'."\n";
                    break;
                    case 'css':
                        echo '<link name="'.$name.'" href="'.$data['url'].'?v='.$version.'" rel="stylesheet" media="all" type="text/css">'."\n";
                    break;
                }
                if (!empty($data['args']['condition'])) { echo '<![endif]-->'; }
            }
        }
    }
}
