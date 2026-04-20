<?php
namespace ProjectSend\Classes\Abstracts;

abstract class CaptchaAbstract {
    protected $method_name;
    protected $site_key;
    protected $secret_key;

    public function __construct($name)
    {
        $this->method_name = $name;
        $this->loadKeys();
    }

    public function getMethodName()
    {
        return $this->method_name;
    }

    /**
     * Load site and secret keys from database
     */
    abstract protected function loadKeys();

    /**
     * Check if this captcha method is enabled and properly configured
     */
    abstract public function isEnabled();

    /**
     * Render the captcha widget HTML
     */
    abstract public function renderWidget();

    /**
     * Get the captcha response from request
     */
    abstract public function getRequest();

    /**
     * Validate the captcha response
     */
    abstract public function check($response);

    /**
     * Get the script URL to load for this captcha method
     */
    abstract public function getScriptUrl();

    /**
     * Get the response field name for this captcha method
     */
    abstract public function getResponseFieldName();
}