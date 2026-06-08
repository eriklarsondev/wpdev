<?php
namespace wpdev;

class CommentConfig
{
    /**
     * constructor for comment configuration
     */
    public function __construct()
    {
        // close comments and pings on all post types
        add_filter('comments_open', '__return_false');
        add_filter('pings_open', '__return_false');

        // hide any existing comments from the front
        add_filter('comments_array', '__return_empty_array');

        // remove comment endpoints from the rest api
        add_filter('rest_endpoints', [$this, 'removeRestEndpoints']);

        // remove comments admin menu item
        add_action('admin_menu', [$this, 'removeAdminMenu']);

        // redirect comment and discussion admin screens away
        add_action('admin_init', [$this, 'redirectAdminScreens']);
    }

    /**
     * removes the public comment endpoints from the rest api
     *
     * @param array $endpoints
     *
     * @return array
     */
    public function removeRestEndpoints($endpoints)
    {
        if (isset($endpoints['/wp/v2/comments'])) {
            unset($endpoints['/wp/v2/comments']);
        }
        if (isset($endpoints['/wp/v2/comments/(?P<id>[\d]+)'])) {
            unset($endpoints['/wp/v2/comments/(?P<id>[\d]+)']);
        }
        return $endpoints;
    }

    /**
     * removes the comments menu item from the admin sidebar
     *
     * @return void
     */
    public function removeAdminMenu()
    {
        remove_menu_page('edit-comments.php');
        remove_submenu_page('options-general.php', 'options-discussion.php');
    }

    /**
     * redirects users away from comment and discussion admin screens
     *
     * @return void
     */
    public function redirectAdminScreens()
    {
        global $pagenow;
        if (in_array($pagenow, ['edit-comments.php', 'options-discussion.php'], true)) {
            wp_redirect(admin_url());
            exit();
        }
    }
}

new CommentConfig();
