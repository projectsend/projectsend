<?php
namespace ProjectSend\Classes\Captcha;

use ProjectSend\Classes\Abstracts\CaptchaAbstract;

final class CloudflareTurnstile extends CaptchaAbstract
{
    public function __construct()
    {
        parent::__construct('Cloudflare Turnstile');
    }

    protected function loadKeys()
    {
        $this->site_key = get_option('cloudflare_turnstile_site_key');
        $this->secret_key = get_option('cloudflare_turnstile_secret_key');
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
                <div class="cf-turnstile" data-sitekey="' . html_output($this->site_key) . '"></div>
            </div>
        </div>';
    }

    public function getRequest()
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $turnstile_response = $_POST[$this->getResponseFieldName()] ?? '';
        $turnstile_user_ip = $_SERVER["REMOTE_ADDR"];
        
        $data = [
            'secret' => $this->secret_key,
            'response' => $turnstile_response,
            'remoteip' => $turnstile_user_ip
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $turnstile_request = file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);

        return $turnstile_request;
    }

    public function check($response)
    {
        $result = json_decode($response, true);
        return isset($result['success']) && $result['success'] === true;
    }

    public function getScriptUrl()
    {
        return 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    }

    public function getResponseFieldName()
    {
        return 'cf-turnstile-response';
    }
}
