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
        $this->css_assets = [
            'head' => [
                //'font_opensans' => ['url' => 'https://fonts.googleapis.com/css?family=Open+Sans:400,700,300'],
                'css_assets' =>['url' => ASSETS_CSS_URL . '/assets.css'],
                'css_main' =>['url' => ASSETS_CSS_URL . '/main.css'],
            ],
            'footer' => [
            ]
        ];
        $this->js_assets = [
            'head' => [
                'jquery' => ['url' => ASSETS_LIB_URL.'/jquery/jquery.min.js'],
                'jquery-migrate' =>['url' => ASSETS_LIB_URL.'/jquery-migrate/jquery-migrate.min.js'],
                'ckeditor5' =>['url' => BASE_URI.'/node_modules/@ckeditor/ckeditor5-build-classic/build/ckeditor.js'],
                'html5shiv' =>['url' => ASSETS_LIB_URL.'/html5shiv.min.js', 'args' => ['condition' => 'lt IE 9']],
                'respondjs' =>['url' => ASSETS_LIB_URL.'/respond.min.js', 'args' => ['condition' => 'lt IE 9']],
            ],
            'footer' => [
                'recaptcha2' =>['url' => 'https://www.google.com/recaptcha/api.js'],
                'js_assets' =>['url' => ASSETS_JS_URL . '/assets.js'],
                'js_app' =>['url' => ASSETS_JS_URL . '/app.js'],
            ] 
        ];
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

    public function addAsset($type, $name, $url, $position, $arguments = [])
    {
        if (!in_array($position, ['head', 'footer'])) {
            $position = 'footer';
        }

        $asset = [
            'url' =>  $url,
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
                switch ($type) {
                    case 'js':
                        echo '<script name="'.$name.'" src="'.$data['url'].'" type="text/javascript"></script>'."\n";
                    break;
                    case 'css':
                        echo '<link name="'.$name.'" href="'.$data['url'].'" rel="stylesheet" media="all" type="text/css">'."\n";
                    break;
                }
                if (!empty($data['args']['condition'])) { echo '<![endif]-->'; }
            }
        }
    }
}
