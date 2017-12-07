<?php
/**
 * Filter to modify attributes using regular expressions
 *
 * This filter can modify or replace attributes given a regular expression.
 *
 * @author Jacob Christiansen, WAYF
 * @package SimpleSAMLphp
 */
class sspmod_core_Auth_Process_AttributeAlter extends SimpleSAML_Auth_ProcessingFilter {

    /**
     * Should the pattern found be replaced?
     */
    private $replace = FALSE;

    /**
     * Should the value found be removed?
     */
    private $remove = FALSE;

    /**
     * Pattern to search for.
     */
    private $pattern = '';

    /**
     * String to replace the pattern found with.
     */
    private $replacement = FALSE;

    /**
     * Attribute to search in
     */
    private $subject = '';

    /**
     * Attribute to place the result in.
     */
    private $target = '';

    /**
     * Initialize this filter.
     *
     * @param array $config  Configuration information about this filter.
     * @param mixed $reserved  For future use.
     * @throws SimpleSAML_Error_Exception In case of invalid configuration.
     */
    public function __construct($config, $reserved) {
        parent::__construct($config, $reserved);

        assert('is_array($config)');

        // parse filter configuration
        foreach ($config as $name => $value) {
            if (is_int($name)) {
                // check if this is an option
                if($value === '%replace') {
                    $this->replace = TRUE;
                } elseif ($value === '%remove') {
                    $this->remove = TRUE;
                } else {
                    throw new SimpleSAML_Error_Exception('Unknown flag : ' . var_export($value, TRUE));
                }
                continue;
            }

            // Set pattern
            if ($name === 'pattern') {
                $this->pattern = $value;
            }

            // Set replacement
            if ($name === 'replacement') {
                $this->replacement = $value;
            }

            // Set subject
            if ($name === 'subject') {
                $this->subject = $value;
            }

            // Set target
            if ($name === 'target') {
                $this->target = $value;
            }
        }
    }

    /**
     * Apply the filter to modify attributes.
     *
     * Modify existing attributes with the configured values.
     *
     * @param array &$request The current request.
     * @throws SimpleSAML_Error_Exception In case of invalid configuration.
     */
    public function process(&$request) {
        assert('is_array($request)');
        assert('array_key_exists("Attributes", $request)');

        // get attributes from request
        $attributes =& $request['Attributes'];

        // check that all required params are set in config
        if (empty($this->pattern) || empty($this->subject)) {
            throw new SimpleSAML_Error_Exception("Not all params set in config.");
        }

        if (!$this->replace && !$this->remove && $this->replacement === false) {
            throw new SimpleSAML_Error_Exception("'replacement' must be set if neither '%replace' nor ".
                "'%remove' are set.");
        }

        if (!$this->replace && $this->replacement === null) {
            throw new SimpleSAML_Error_Exception("'%replace' must be set if 'replacement' is null.");
        }

        if ($this->replace && $this->remove) {
            throw new SimpleSAML_Error_Exception("'%replace' and '%remove' cannot be used together.");
        }

        if (empty($this->target)) {
            // use subject as target if target is not set
            $this->target = $this->subject;
        }

        if ($this->subject !== $this->target && $this->remove) {
            throw new SimpleSAML_Error_Exception("Cannot use '%remove' when 'target' is different than 'subject'.");
        }

        if (!array_key_exists($this->subject, $attributes)) {
            // if no such subject, stop gracefully
            return;
        }

        if ($this->replace) { // replace the whole value
            foreach ($attributes[$this->subject] as &$value) {
                $matches = array();
                if (preg_match($this->pattern, $value, $matches) > 0) {
                    $new_value = $matches[0];

                    if ($this->replacement !== FALSE) {
                        $new_value = $this->replacement;
                    }

                    if ($this->subject === $this->target) {
                        $value = $new_value;
                    } else {
                        $attributes[$this->target] = array($new_value);
                    }
                }
            }
        } elseif ($this->remove) { // remove the whole value
            $removedAttrs = array();
            foreach ($attributes[$this->subject] as $value) {
                $matches = array();
                if (preg_match($this->pattern, $value, $matches) > 0) {
                    $removedAttrs[] = $value;
                }
            }
            $attributes[$this->target] = array_diff($attributes[$this->subject], $removedAttrs);

            if (empty($attributes[$this->target])) {
                unset($attributes[$this->target]);
            }
        } else { // replace only the part that matches
            if ($this->subject === $this->target) {
                $attributes[$this->target] = preg_replace($this->pattern, $this->replacement,
                                                          $attributes[$this->subject]);
            } else {
                $attributes[$this->target] = array_diff(preg_replace($this->pattern, $this->replacement,
                                                                     $attributes[$this->subject]),
                                                        $attributes[$this->subject]);
            }
        }
    }
}
