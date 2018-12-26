<?php
namespace ProjectSend\Classes;

class Redirect
{
    /**
     * Redirect to specific page
     *
     * @param [type] $page
     * @return void
     */
    public static function to($page)
    {
        header("Location: $page");
        exit;
    }

    /**
     * Redirect to same page
     *
     * @return void
     */
    public static function back()
    {
        $uri = $_SERVER['REQUEST_URI'];
        header("Location: $uri");
        exit;
    }
}