<?php
namespace wpdev;

class AdminConfig
{
    /**
     * constructor for admin configuration class
     */
    public function __construct()
    {
        // disable theme/plugin file editor
        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }

        // disable automatic updates for themes and plugins
        add_filter('auto_update_theme', '__return_false');
        add_filter('auto_update_plugin', '__return_false');
    }
}

new AdminConfig();
