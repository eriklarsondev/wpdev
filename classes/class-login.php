<?php
namespace wpdev;

class LoginConfig
{
    /**
     * constructor for login configuration
     */
    public function __construct()
    {
        // point the login logo at the site root instead of wordpress.org
        add_filter('login_headerurl', [$this, 'loginUrl']);
        add_filter('login_headertext', [$this, 'loginText']);

        // enqueue main bundle on the login screen for branded styling
        AssetConfig::enqueue('wpdev-login', 'assets/js/main.js', 'login_enqueue_scripts');
    }

    /**
     * url the login header logo links to
     *
     * @return string
     */
    public function loginUrl()
    {
        return home_url('/');
    }

    /**
     * alt text and tooltip for the login header logo
     *
     * @return string
     */
    public function loginText()
    {
        return get_bloginfo('name');
    }
}

new LoginConfig();
