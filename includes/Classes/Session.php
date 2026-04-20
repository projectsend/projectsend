<?php
namespace ProjectSend\Classes;

class Session
{
    /**
     * Create a session
     *
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public static function add($name, $value)
    {
        if ($name != '' && !empty($name) && $value != '' && !empty($value)) {
            $_SESSION[$name] = $value;
            return $value;
        }

        throw new \Exception('Name and value are required');
    }

    /**
     * Get value from session
     *
     * @param string $name
     * @return mixed
     */
    public static function get($name)
    {
        return $_SESSION[$name] ?? null;
    }

    /**
     * Check if session exists
     *
     * @param string $name
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
     * @param string $name
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
     * @param string $name
     * @param mixed $value
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