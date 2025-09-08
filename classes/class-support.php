<?php
namespace wpdev;

class ThemeSupportConfig extends Base
{
    /**
     * constructor for theme support configuration
     *
     * @param boolean $static
     */
    public function __construct($static = false)
    {
        if (!$static) {
            add_action('after_setup_theme', [$this, 'initThemeSupport']);
        }
    }

    /**
     * initializes default theme support
     *
     * @return void
     */
    public function initThemeSupport()
    {
        $this->registerThemeSupport('post thumbnails');
        $this->registerThemeSupport('title tag');

        $this->unregisterThemeSupport('widgets block editor');
    }

    /**
     * adds new theme support for a feature
     *
     * @param string $feature
     *
     * @return void
     */
    private function registerThemeSupport($feature)
    {
        $key = parent::formatLabel($feature, '-', false);
        add_theme_support($key);
    }

    /**
     * removes existing theme support for a feature
     *
     * @param string $feature
     *
     * @return void
     */
    private function unregisterThemeSupport($feature)
    {
        $key = parent::formatLabel($feature, '-', false);
        remove_theme_support($key);
    }

    /**
     * static wrapper for adding new theme support
     *
     * @param string $feature
     *
     * @return void
     */
    static function add_theme_support($feature)
    {
        $instance = new self(true);
        add_action('after_setup_theme', function () use ($feature) {
            $instance->registerThemeSupport($feature);
        });
    }

    /**
     * static wrapper for removing existing theme support
     *
     * @param string $feature
     *
     * @return void
     */
    static function remove_theme_support($feature)
    {
        $instance = new self(true);
        add_action('after_setup_theme', function () use ($feature) {
            $instance->unregisterThemeSupport($feature);
        });
    }
}

new ThemeSupportConfig();
