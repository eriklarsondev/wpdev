<?php
namespace wpdev;

class ScreensConfig
{
    /**
     * constructor for admin screens configuration
     *
     * @param boolean $static
     */
    public function __construct($static = false)
    {
        if (!$static) {
            add_action('admin_menu', [$this, 'initHiddenScreens'], 999);
        }
    }

    /**
     * hides admin screens that the theme considers default-noise
     *
     * @return void
     */
    public function initHiddenScreens()
    {
        // intentionally empty; child themes add hides via the static wrappers
    }

    /**
     * removes a top-level admin menu screen
     *
     * @param string $slug
     *
     * @return void
     */
    private function hideScreen($slug)
    {
        remove_menu_page($slug);
    }

    /**
     * removes a submenu entry under a parent screen
     *
     * @param string $parent_slug
     * @param string $submenu_slug
     *
     * @return void
     */
    private function hideSubmenu($parent_slug, $submenu_slug)
    {
        remove_submenu_page($parent_slug, $submenu_slug);
    }

    /**
     * static wrapper for hiding a top-level admin screen
     *
     * @param string $slug
     *
     * @return void
     */
    static function hide_screen($slug)
    {
        $instance = new self(true);
        add_action('admin_menu', function () use ($instance, $slug) {
            $instance->hideScreen($slug);
        }, 999);
    }

    /**
     * static wrapper for hiding an admin submenu entry
     *
     * @param string $parent_slug
     * @param string $submenu_slug
     *
     * @return void
     */
    static function hide_submenu($parent_slug, $submenu_slug)
    {
        $instance = new self(true);
        add_action('admin_menu', function () use ($instance, $parent_slug, $submenu_slug) {
            $instance->hideSubmenu($parent_slug, $submenu_slug);
        }, 999);
    }
}

new ScreensConfig();
