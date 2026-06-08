<?php
namespace wpdev;

class SiteConfig
{
    /**
     * constructor for the site bootstrap endpoint: a single read-only call a
     * headless frontend can use to fetch site basics and all menus at once.
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoute']);
        add_filter('wpdev_rest_public_routes', [$this, 'allowRoute']);
    }

    /**
     * allows anonymous (api-key) reads of the site endpoint through the rest
     * lockdown allowlist
     *
     * @param array $routes
     *
     * @return array
     */
    public function allowRoute($routes)
    {
        $routes[] = '#^/wpdev/v1/site/?$#';
        return $routes;
    }

    /**
     * registers the GET /wpdev/v1/site route
     *
     * @return void
     */
    public function registerRoute()
    {
        register_rest_route('wpdev/v1', '/site', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getSite'],
                'permission_callback' => '__return_true'
            ],
            'schema' => [$this, 'itemSchema']
        ]);
    }

    /**
     * response schema for the site endpoint
     *
     * @return array
     */
    public function itemSchema()
    {
        return [
            'title' => 'site',
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'url' => ['type' => 'string', 'format' => 'uri'],
                'language' => ['type' => 'string'],
                'menus' => [
                    'type' => 'object',
                    'description' => 'Menu locations keyed by slug, each a nested tree.',
                    'additionalProperties' => [
                        'type' => 'array',
                        'items' => MenuLocationConfig::menuItemSchema()
                    ]
                ]
            ]
        ];
    }

    /**
     * returns site basics plus every registered menu location as a nested tree
     *
     * @return array
     */
    public function getSite()
    {
        $menus = [];
        foreach (array_keys(get_registered_nav_menus()) as $location) {
            $menus[$location] = MenuLocationConfig::tree($location);
        }

        return [
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url' => home_url('/'),
            'language' => get_bloginfo('language'),
            'menus' => $menus
        ];
    }
}

new SiteConfig();
