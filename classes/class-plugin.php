<?php
namespace wpdev;

class RequiredPluginConfig extends Base
{
    /**
     * constructor for required plugin configuration
     *
     * @param boolean $static
     */
    public function __construct($static = false)
    {
        if (!$static) {
            add_action('admin_init', [$this, 'initRequiredPlugins']);
        }
    }

    /**
     * initializes required plugins and ensure plugins are installed and activated
     *
     * @return void
     */
    public function initRequiredPlugins()
    {
        $this->isPluginActive('advanced custom fields', 'ACF');
    }

    /**
     * check if required plugin is installed and activated
     *
     * @param string $plugin_name
     * @param string $class_name
     *
     * @return boolean
     */
    private function isPluginActive($plugin_name, $class_name = '')
    {
        if (!class_exists($class_name)) {
            $this->renderAdminNotice($plugin_name);
            return false;
        }
        return true;
    }

    /**
     * render admin notice if required plugin is not installed or activated
     *
     * @param string $plugin_name
     *
     * @return void
     */
    private function renderAdminNotice($plugin_name)
    {
        add_action('admin_notices', function () use ($plugin_name) {
            $label = ucwords($plugin_name);

            $url = admin_url('plugin-install.php') . '?s=' . parent::formatLabel($plugin_name, '%20', false);
            $url .= '&tab=search&type=term';

            echo "
            <div class=\"notice notice-error\">
                <p>
                    <strong>$label</strong> was not found.
                    Click <a href=\"$url\">here</a> to install or activate this plugin.
                </p>
            </div>
            ";
        });
    }

    /**
     * static wrapper for checking if required plugin is installed and activated
     *
     * @param string $plugin_name
     * @param string $class_name
     *
     * @return void
     */
    static function is_plugin_active($plugin_name, $class_name = '')
    {
        $instance = new self(true);
        add_action('admin_init', function () use ($plugin_name, $class_name) {
            $instance->isPluginActive($plugin_name, $class_name);
        });
    }
}

new RequiredPluginConfig();
