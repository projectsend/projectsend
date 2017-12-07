<?php

namespace SimpleSAML\Auth;

/**
 * A class that generates and verifies time-limited tokens.
 */
class TimeLimitedToken
{

    /**
     * @var string
     */
    protected $secretSalt;

    /**
     * @var int
     */
    protected $lifetime;

    /**
     * @var int
     */
    protected $skew;


    /**
     * @param int $lifetime Token lifetime in seconds. Defaults to 900 (15 min).
     * @param string $secretSalt A random and unique salt per installation. Defaults to the salt in the configuration.
     * @param int $skew The allowed time skew (in seconds) between what the server generates and the one that calculates
     * the token.
     */
    public function __construct($lifetime = 900, $secretSalt = null, $skew = 1)
    {
        if ($secretSalt === null) {
            $secretSalt = \SimpleSAML\Utils\Config::getSecretSalt();
        }

        $this->secretSalt = $secretSalt;
        $this->lifetime = $lifetime;
        $this->skew = $skew;
    }


    /**
     * Add some given data to the current token. This data will be needed later too for token validation.
     *
     * This mechanism can be used to provide context for a token, such as a user identifier of the only subject
     * authorised to use it. Note also that multiple data can be added to the token. This means that upon validation,
     * not only the same data must be added, but also in the same order.
     *
     * @param string $data The data to incorporate into the current token.
     */
    public function addVerificationData($data)
    {
        $this->secretSalt .= '|'.$data;
    }


    /**
     * Calculates a token value for a given offset.
     *
     * @param int $offset The offset to use.
     * @param int|null $time The time stamp to which the offset is relative to. Defaults to the current time.
     *
     * @return string The token for the given time and offset.
     */
    private function calculateTokenValue($offset, $time = null)
    {
        if ($time === null) {
            $time = time();
        }
        // a secret salt that should be randomly generated for each installation
        return sha1($offset.':'.floor(($time - $offset) / ($this->lifetime + $this->skew)).':'.$this->secretSalt);
    }


    /**
     * Generates a token that contains an offset and a token value, using the current offset.
     *
     * @return string A time-limited token with the offset respect to the beginning of its time slot prepended.
     */
    public function generate()
    {
        $time = time();
        $current_offset = ($time - $this->skew) % ($this->lifetime + $this->skew);
        return dechex($current_offset).'-'.$this->calculateTokenValue($current_offset, $time);
    }


    /**
     * @see generate
     * @deprecated This method will be removed in SSP 2.0. Use generate() instead.
     */
    public function generate_token()
    {
        return $this->generate();
    }


    /**
     * Validates a token by calculating the token value for the provided offset and comparing it.
     *
     * @param string $token The token to validate.
     *
     * @return boolean True if the given token is currently valid, false otherwise.
     */
    public function validate($token)
    {
        $splittoken = explode('-', $token);
        if (count($splittoken) !== 2) {
            return false;
        }
        $offset = hexdec($splittoken[0]);
        $value = $splittoken[1];
        return ($this->calculateTokenValue($offset) === $value);
    }


    /**
     * @see validate
     * @deprecated This method will be removed in SSP 2.0. Use validate() instead.
     */
    public function validate_token($token)
    {
        return $this->validate($token);
    }
}
