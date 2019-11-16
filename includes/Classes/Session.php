<?php
namespace ProjectSend\Classes;

class Session
{
    /**
     * Create a session
     *
     * @param [type] $name
     * @param [type] $value
     * @return void
     */
    public static function add($name, $value)
    {
        if ($name != '' && !empty($name) && $value != '' && !empty($value)) {
            return $_SESSION[$name] = $value;
        }

        throw new \Exception('Name and value are required');
    }

    /**
     * Get value from session
     *
     * @param [type] $name
     * @return void
     */
    public static function get($name)
    {
        return $_SESSION[$name];
    }

    /**
     * Check if session exists
     *
     * @param [type] $name
     * @return boolean
     */
    public static function has($name)
    {
        if ($name != '' && !empty($name)) {
            return (isset($_SESSION[$name])) ? true : false;
        }

        throw new \Exception('Name is required');
    }

    /**
     * Remove session
     *
     * @param [type] $name
     * @return void
     */
    public static function remove($name)
    {
        if (self::has($name))
        {
            unset($_SESSION[$name]);
        }
    }

    /**
     * Flash a message and unset old session value
     *
     * @param [type] $name
     * @param [type] $value
     * @return mixed|null
     */
    public static function flash($name, $value = null)
    {
        if (self::has($name)) {
            $old_value = self::get($name);
            self::remove($name);

            return $old_value;
        }
        else {
            self::add($name, $value);
        }

        return null;
    }
}