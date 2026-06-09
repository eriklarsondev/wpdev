<?php
namespace wpdev;

class CustomPostTypeConfig extends Base
{
    /**
     * constructor for custom post type configuration
     *
     * @param boolean $static
     */
    public function __construct($static = false)
    {
        if (!$static) {
            add_action('init', [$this, 'initCustomPostTypes']);
        }
    }

    /**
     * initializes default custom post types
     *
     * @return void
     */
    public function initCustomPostTypes() {}

    /**
     * registers a custom post type from a config array
     *
     * @param array $config
     *
     * @return void
     */
    public function registerPostType($config)
    {
        $name = $config['name'] ?? '';
        if (empty($name)) {
            return;
        }

        $plural = $config['plural'] ?? $name . 's';
        $slug = $this->formatLabel($name);
        $rewrite_slug = strtolower(preg_replace('/\s+/', '-', trim($plural)));

        $labels = [
            'name' => $plural,
            'singular_name' => $name,
            'add_new' => 'Add New',
            'add_new_item' => 'Add New ' . $name,
            'edit_item' => 'Edit ' . $name,
            'new_item' => 'New ' . $name,
            'view_item' => 'View ' . $name,
            'view_items' => 'View ' . $plural,
            'search_items' => 'Search ' . $plural,
            'not_found' => 'No ' . strtolower($plural) . ' found.',
            'not_found_in_trash' => 'No ' . strtolower($plural) . ' found in Trash.',
            'all_items' => 'All ' . $plural,
            'menu_name' => $plural,
            'name_admin_bar' => $name
        ];

        $defaults = [
            'labels' => $labels,
            'public' => $config['public'] ?? true,
            'has_archive' => $config['has_archive'] ?? true ? $rewrite_slug : false,
            'show_in_rest' => $config['show_in_rest'] ?? true,
            'supports' => $config['supports'] ?? ['title', 'editor'],
            'taxonomies' => $config['taxonomies'] ?? [],
            'menu_icon' => $config['icon'] ?? 'dashicons-admin-post',
            'menu_position' => $config['menu_position'] ?? null,
            'rewrite' => ['slug' => $rewrite_slug, 'with_front' => false]
        ];

        register_post_type($slug, array_merge($defaults, $config['args'] ?? []));
    }

    /**
     * unregisters a custom post type by name
     *
     * @param string $name
     *
     * @return void
     */
    public function removePostType($name)
    {
        $slug = $this->formatLabel($name);
        unregister_post_type($slug);
    }

    /**
     * static wrapper for registering a custom post type outside of the class init flow
     *
     * @param array $config
     *
     * @return void
     */
    static function register_post_type($config)
    {
        $instance = new self(true);
        add_action('init', function () use ($instance, $config) {
            $instance->registerPostType($config);
        });
    }

    /**
     * static wrapper for unregistering a custom post type outside of the class init flow
     *
     * @param string $name
     *
     * @return void
     */
    static function remove_post_type($name)
    {
        $instance = new self(true);
        add_action(
            'init',
            function () use ($instance, $name) {
                $instance->removePostType($name);
            },
            20
        );
    }
}

new CustomPostTypeConfig();
