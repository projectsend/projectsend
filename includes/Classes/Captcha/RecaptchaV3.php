<?php
namespace ProjectSend\Classes\Captcha;

use ProjectSend\Classes\Abstracts\CaptchaAbstract;

final class RecaptchaV3 extends CaptchaAbstract
{
    private $score_threshold = 0.5;
    
    public function __construct()
    {
        parent::__construct('Recaptcha V3');
    }

    protected function loadKeys()
    {
        $this->site_key = get_option('recaptcha_v3_site_key');
        $this->secret_key = get_option('recaptcha_v3_secret_key');
        
        $threshold = get_option('recaptcha_v3_score_threshold');
        if (!empty($threshold) && is_numeric($threshold)) {
            $this->score_threshold = (float)$threshold;
        }
    }

    public function isEnabled()
    {
        // Skip reCAPTCHA on .local domains for development
        if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], '.local') !== false) {
            return false;
        }
        
        return !empty($this->site_key) && !empty($this->secret_key);
    }

    public function renderWidget()
    {
        if (!$this->isEnabled()) {
            return '';
        }

        // reCAPTCHA v3 doesn't render a visible widget, but needs a hidden input and JS
        return '<input type="hidden" id="g-recaptcha-response" name="' . $this->getResponseFieldName() . '" />
        <script>
            // Prevent multiple initializations
            if (typeof window.recaptchaV3Initialized === "undefined") {
                window.recaptchaV3Initialized = false;
                
                function initializeRecaptchaV3() {
                    if (window.recaptchaV3Initialized) {
                        return;
                    }
                    
                    if (typeof grecaptcha !== "undefined" && grecaptcha.ready) {
                        window.recaptchaV3Initialized = true;
                        
                        grecaptcha.ready(function() {
                            // Execute immediately for initial token
                            grecaptcha.execute("' . html_output($this->site_key) . '", {action: "submit"}).then(function(token) {
                                document.getElementById("g-recaptcha-response").value = token;
                            }).catch(function(error) {
                                // Silent fail - form will still submit
                            });
                            
                            // Set up form submission handlers
                            var forms = document.querySelectorAll("form");
                            
                            forms.forEach(function(form, index) {
                                // Remove any existing listeners to prevent duplicates
                                form.removeEventListener("submit", window.recaptchaV3Handler);
                                
                                window.recaptchaV3Handler = function(e) {
                                    e.preventDefault();
                                    
                                    grecaptcha.execute("' . html_output($this->site_key) . '", {action: "submit"}).then(function(token) {
                                        document.getElementById("g-recaptcha-response").value = token;
                                        // Remove the handler to prevent infinite loop
                                        form.removeEventListener("submit", window.recaptchaV3Handler);
                                        form.submit();
                                    }).catch(function(error) {
                                        // Submit anyway to not block user
                                        form.removeEventListener("submit", window.recaptchaV3Handler);
                                        form.submit();
                                    });
                                };
                                
                                form.addEventListener("submit", window.recaptchaV3Handler);
                            });
                        });
                    } else {
                        setTimeout(initializeRecaptchaV3, 200);
                    }
                }
                
                // Initialize when ready
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", initializeRecaptchaV3);
                } else {
                    initializeRecaptchaV3();
                }
            }
        </script>';
    }

    public function getRequest()
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $recaptcha_user_ip = $_SERVER["REMOTE_ADDR"];
        $recaptcha_response = $_POST[$this->getResponseFieldName()] ?? '';
        
        $data = [
            'secret' => $this->secret_key,
            'response' => $recaptcha_response,
            'remoteip' => $recaptcha_user_ip
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $recaptcha_request = file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);

        return $recaptcha_request;
    }

    public function check($response)
    {
        $result = json_decode($response, true);
        
        // reCAPTCHA v3 returns a score between 0.0 and 1.0
        // Higher scores are more likely to be legitimate users
        if (isset($result['success']) && $result['success'] === true) {
            if (isset($result['score']) && $result['score'] >= $this->score_threshold) {
                return true;
            }
        }
        
        return false;
    }

    public function getScriptUrl()
    {
        if (!$this->isEnabled()) {
            return null;
        }
        return 'https://www.google.com/recaptcha/api.js?render=' . $this->site_key;
    }

    public function getResponseFieldName()
    {
        return 'g-recaptcha-response';
    }
}