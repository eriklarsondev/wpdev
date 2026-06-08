<?php
namespace wpdev;

class MenuLocationConfig extends Base
{
    /**
     * constructor for menu location configuration
     *
     * @param boolean $static
     */
    public function __construct($static = false)
    {
        if (!$static) {
            add_action('init', [$this, 'initMenuLocations']);
            add_action('rest_api_init', [$this, 'registerRoutes']);
        }
    }

    /**
     * registers the public menu rest route
     *
     * @return void
     */
    public function registerRoutes()
    {
        register_rest_route('wpdev/v1', '/menus/(?P<location>[a-zA-Z0-9_-]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getMenuByLocation'],
                'permission_callback' => '__return_true',
                'args' => [
                    'location' => [
                        'sanitize_callback' => 'sanitize_key'
                    ]
                ]
            ],
            'schema' => [$this, 'itemSchema']
        ]);
    }

    /**
     * response schema for the menu endpoint (a nested tree of items)
     *
     * @return array
     */
    public function itemSchema()
    {
        return [
            'title' => 'menu',
            'type' => 'array',
            'items' => self::menuItemSchema()
        ];
    }

    /**
     * shared schema for a single menu item, reused by the site endpoint
     *
     * @return array
     */
    public static function menuItemSchema()
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'url' => ['type' => 'string'],
                'target' => ['type' => 'string'],
                'classes' => ['type' => 'array', 'items' => ['type' => 'string']],
                'children' => ['type' => 'array', 'items' => ['type' => 'object']]
            ]
        ];
    }

    /**
     * returns the menu assigned to a location as a nested tree
     *
     * @param \WP_REST_Request $request
     *
     * @return array|\WP_Error
     */
    public function getMenuByLocation($request)
    {
        $location = $request['location'];
        $locations = get_nav_menu_locations();

        if (!isset($locations[$location])) {
            return new \WP_Error('menu_location_not_found', 'Menu location does not exist.', ['status' => 404]);
        }

        $menu = wp_get_nav_menu_object($locations[$location]);
        if (!$menu) {
            return new \WP_Error('menu_not_assigned', 'No menu is assigned to this location.', ['status' => 404]);
        }

        $items = wp_get_nav_menu_items($menu->term_id);
        if (!$items) {
            return [];
        }

        return $this->buildMenuTree($items);
    }

    /**
     * static helper returning the menu assigned to a location as a nested
     * tree, or an empty array when nothing is assigned. shared with the site
     * bootstrap endpoint.
     *
     * @param string $location
     *
     * @return array
     */
    public static function tree($location)
    {
        $locations = get_nav_menu_locations();
        if (empty($locations[$location])) {
            return [];
        }

        $menu = wp_get_nav_menu_object($locations[$location]);
        if (!$menu) {
            return [];
        }

        $items = wp_get_nav_menu_items($menu->term_id);
        if (!$items) {
            return [];
        }

        $instance = new self(true);
        return $instance->buildMenuTree($items);
    }

    /**
     * recursively shapes a flat list of menu items into a nested tree
     *
     * @param array $items
     * @param integer $parent_id
     *
     * @return array
     */
    private function buildMenuTree($items, $parent_id = 0)
    {
        $branch = [];
        foreach ($items as $item) {
            if ((int) $item->menu_item_parent !== $parent_id) {
                continue;
            }
            $branch[] = [
                'id' => (int) $item->ID,
                'title' => $item->title,
                'url' => $item->url,
                'target' => $item->target,
                'classes' => array_values(array_filter($item->classes)),
                'children' => $this->buildMenuTree($items, (int) $item->ID)
            ];
        }
        return $branch;
    }

    /**
     * initializes default menu locations
     *
     * @return void
     */
    public function initMenuLocations()
    {
        $this->registerMenuLocation('header menu');
        $this->registerMenuLocation('footer menu');
        $this->registerMenuLocation('social media bar');
    }

    /**
     * adds new menu location
     *
     * @param string $menu_name
     *
     * @return void
     */
    private function registerMenuLocation($menu_name)
    {
        $key = parent::formatLabel($menu_name);
        $label = ucwords($menu_name);

        register_nav_menu($key, __($label, $key));
    }

    /**
     * removes existing menu location
     *
     * @param string $menu_name
     *
     * @return void
     */
    private function unregisterMenuLocation($menu_name)
    {
        $key = parent::formatLabel($menu_name);
        unregister_nav_menu($key);
    }

    /**
     * static wrapper for adding new menu location
     *
     * @param string $menu_name
     *
     * @return void
     */
    static function add_menu_location($menu_name)
    {
        $instance = new self(true);
        add_action('init', function () use ($menu_name) {
            $instance->registerMenuLocation($menu_name);
        });
    }

    /**
     * static wrapper for removing existing menu location
     *
     * @param string $menu_name
     *
     * @return void
     */
    static function remove_menu_location($menu_name)
    {
        $instance = new self(true);
        add_action('init', function () use ($menu_name) {
            $instance->unregisterMenuLocation($menu_name);
        });
    }
}

new MenuLocationConfig();
