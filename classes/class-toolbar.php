<?php
namespace wpdev;

class ToolbarConfig extends Base
{
    public function __construct()
    {
        // disable admin toolbar
        add_filter('show_admin_bar', '__return_false');
    }
}

new ToolbarConfig();
