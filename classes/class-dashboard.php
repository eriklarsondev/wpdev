<?php
namespace wpdev;

class DashboardConfig
{
    private static $lockedColumns = [
        'normal' => ['dashboard_right_now', 'dashboard_activity'],
        'side' => ['wpdev_site_health', 'wpdev_environment', 'wpdev_server_audit'],
        'column3' => ['wpdev_frontend', 'wpdev_api_log']
    ];

    /**
     * constructor for dashboard configuration
     *
     * @param boolean $static
     */
    public function __construct($static = false)
    {
        if (!$static) {
            add_action('wp_dashboard_setup', [$this, 'removeDefaultWidgets'], 999);
        }

        add_filter('get_user_option_meta-box-order_dashboard', [$this, 'setDefaultWidgetOrder']);
        add_filter('get_user_option_screen_layout_dashboard', [$this, 'lockColumnCount']);
    }

    /**
     * removes the noisy default dashboard widgets, keeping the useful ones
     * (at a glance, activity, site health)
     *
     * @return void
     */
    public function removeDefaultWidgets()
    {
        $widgets = [
            ['dashboard_quick_press', 'side'],
            ['dashboard_primary', 'side'],
            ['dashboard_recent_drafts', 'side'],
            ['dashboard_recent_comments', 'normal']
        ];

        foreach ($widgets as [$id, $context]) {
            remove_meta_box($id, 'dashboard', $context);
        }

        remove_action('welcome_panel', 'wp_welcome_panel');
    }

    /**
     * forces the dashboard to render with three columns so the configured
     * layout (left / middle / right) is always honored
     *
     * @param mixed $columns
     *
     * @return integer
     */
    public function lockColumnCount($columns)
    {
        return 3;
    }

    /**
     * sets the default widget column order on first load only. once the user
     * has saved a layout via drag-and-drop their preference takes over.
     *
     * @param mixed $order
     *
     * @return array
     */
    public function setDefaultWidgetOrder($order)
    {
        if ($order) {
            return $order;
        }

        $result = [];
        foreach (self::$lockedColumns as $col => $ids) {
            $result[$col] = implode(',', $ids);
        }

        return $result;
    }

    /**
     * removes a dashboard widget by id and context
     *
     * @param string $widget_id
     * @param string $context
     *
     * @return void
     */
    private function unregisterWidget($widget_id, $context)
    {
        remove_meta_box($widget_id, 'dashboard', $context);
    }

    /**
     * registers a custom dashboard widget
     *
     * @param string $widget_id
     * @param string $widget_name
     * @param callable $callback
     *
     * @return void
     */
    private function registerWidget($widget_id, $widget_name, $callback)
    {
        wp_add_dashboard_widget($widget_id, $widget_name, $callback);
    }

    /**
     * static wrapper for adding a new dashboard widget. optionally pins the
     * widget to a column so it stays put across page loads.
     *
     * @param string $widget_id
     * @param string $widget_name
     * @param callable $callback
     * @param string|null $column normal | side | column3 | column4
     *
     * @return void
     */
    static function add_widget($widget_id, $widget_name, $callback, $column = null)
    {
        $instance = new self(true);

        if ($column) {
            self::$lockedColumns[$column] = self::$lockedColumns[$column] ?? [];
            if (!in_array($widget_id, self::$lockedColumns[$column], true)) {
                self::$lockedColumns[$column][] = $widget_id;
            }
        }

        add_action('wp_dashboard_setup', function () use ($instance, $widget_id, $widget_name, $callback) {
            $instance->registerWidget($widget_id, $widget_name, $callback);
        });
    }

    /**
     * static wrapper for removing an existing dashboard widget
     *
     * @param string $widget_id
     * @param string $context
     *
     * @return void
     */
    static function remove_widget($widget_id, $context = 'normal')
    {
        $instance = new self(true);
        add_action(
            'wp_dashboard_setup',
            function () use ($instance, $widget_id, $context) {
                $instance->unregisterWidget($widget_id, $context);
            },
            999
        );
    }
}

new DashboardConfig();
