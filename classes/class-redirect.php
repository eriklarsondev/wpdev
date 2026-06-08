<?php
namespace wpdev;

class RedirectConfig
{
    /**
     * constructor for redirect configuration
     */
    public function __construct()
    {
        add_action('template_redirect', [$this, 'initRedirect']);
    }

    /**
     * redirects to homepage if not already on homepage or admin page
     *
     * @return void
     */
    public function initRedirect()
    {
        if (!is_front_page() && !is_admin()) {
            $redirect_url = home_url('/');

            wp_redirect($redirect_url, 301);
            exit();
        }
    }
}

new RedirectConfig();
