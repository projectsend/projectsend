<?php
namespace ProjectSend\Classes;

use ProjectSend\Classes\Captcha\RecaptchaV2;
use ProjectSend\Classes\Captcha\RecaptchaV3;
use ProjectSend\Classes\Captcha\CloudflareTurnstile;

class CaptchaManager
{
    private static $instance = null;
    private $captcha = null;
    private $method = null;

    private function __construct()
    {
        $this->method = get_option('captcha_method');
        $this->initializeCaptcha();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the appropriate captcha class based on selected method
     */
    private function initializeCaptcha()
    {
        $methods = $this->getAvailableMethods();
        
        if (!empty($this->method) && isset($methods[$this->method])) {
            $captchaClass = $methods[$this->method];
            $this->captcha = new $captchaClass();
        }
    }

    /**
     * Get available captcha methods
     */
    public function getAvailableMethods()
    {
        return [
            'recaptchav2' => RecaptchaV2::class,
            'recaptchav3' => RecaptchaV3::class,
            'cloudflare_turnstile' => CloudflareTurnstile::class,
        ];
    }

    /**
     * Check if captcha is enabled and configured
     */
    public function isEnabled()
    {
        return $this->captcha !== null && $this->captcha->isEnabled();
    }

    /**
     * Render the captcha widget
     */
    public function renderWidget()
    {
        if (!$this->isEnabled()) {
            return '';
        }
        
        return $this->captcha->renderWidget();
    }

    /**
     * Get the captcha request/response
     */
    public function getRequest()
    {
        if (!$this->isEnabled()) {
            return null;
        }
        
        return $this->captcha->getRequest();
    }

    /**
     * Validate the captcha request
     */
    public function validateRequest($redirect = true)
    {
        $validation_passed = false;
        
        if ($this->isEnabled()) {
            $response = $this->getRequest();
            
            if ($response !== null) {
                $validation_passed = $this->captcha->check($response);
            }
            
            if (!$validation_passed && $redirect) {
                $error_msg = __('Security verification failed. Please try again.', 'cftp_admin');
                ps_redirect(BASE_URI . 'index.php?error=' . urlencode($error_msg));
            }
        } else {
            $validation_passed = true;
        }
        
        return $validation_passed;
    }

    /**
     * Get the script URL for the current captcha method
     */
    public function getScriptUrl()
    {
        if (!$this->isEnabled()) {
            return null;
        }
        
        return $this->captcha->getScriptUrl();
    }

    /**
     * Get the response field name for the current captcha method
     */
    public function getResponseFieldName()
    {
        if (!$this->isEnabled()) {
            return null;
        }
        
        return $this->captcha->getResponseFieldName();
    }

    /**
     * Get the current captcha instance
     */
    public function getCaptchaInstance()
    {
        return $this->captcha;
    }
}