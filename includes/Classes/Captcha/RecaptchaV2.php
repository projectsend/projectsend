<?php
namespace ProjectSend\Classes\Captcha;

use ProjectSend\Classes\Abstracts\CaptchaAbstract;

final class RecaptchaV2 extends CaptchaAbstract
{
    public function __construct()
    {
        parent::__construct('Recaptcha V2');
    }

    protected function loadKeys()
    {
        $this->site_key = get_option('recaptcha_site_key');
        $this->secret_key = get_option('recaptcha_secret_key');
    }

    public function isEnabled()
    {
        return !empty($this->site_key) && !empty($this->secret_key);
    }

    public function renderWidget()
    {
        if (!$this->isEnabled()) {
            return '';
        }

        return '<div class="form-group row">
            <div class="col-sm-8 offset-sm-4">
                <div class="g-recaptcha" data-sitekey="' . html_output($this->site_key) . '"></div>
            </div>
        </div>';
    }

    public function getRequest()
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $recaptcha_user_ip = $_SERVER["REMOTE_ADDR"];
        $recaptcha_response = $_POST[$this->getResponseFieldName()] ?? '';
        
        $recaptcha_link = 'https://www.google.com/recaptcha/api/siteverify?secret=' . $this->secret_key . '&response=' . $recaptcha_response . '&remoteip=' . $recaptcha_user_ip;
        $recaptcha_request = file_get_contents($recaptcha_link);

        return $recaptcha_request;
    }

    public function check($response)
    {
        return (strstr($response, '"success": true') !== false);
    }

    public function getScriptUrl()
    {
        return 'https://www.google.com/recaptcha/api.js';
    }

    public function getResponseFieldName()
    {
        return 'g-recaptcha-response';
    }
}
