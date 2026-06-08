<?php
namespace wpdev;

class RestConfig
{
    /**
     * constructor for rest api configuration
     */
    public function __construct()
    {
        // disable xml-rpc
        add_filter('xmlrpc_enabled', '__return_false');

        // restrict users endpoint for unauthenticated requests
        add_filter('rest_endpoints', [$this, 'restrictUsersEndpoint']);

        // seed the cors allowlist from the customizer-stored urls
        add_filter('wpdev_rest_allowed_origins', [$this, 'addConfiguredOrigins']);

        // replace default cors handling with allowlist
        add_action('rest_api_init', [$this, 'configureCors'], 15);

        // strip all write methods so the rest api is read-only
        add_filter('rest_endpoints', [$this, 'makeReadonly']);

        // remove editor / front-end rendering routes a headless install never serves
        add_filter('rest_endpoints', [$this, 'removeFrontendRoutes'], 20);

        // restrict the public read surface to content endpoints
        add_filter('rest_pre_dispatch', [$this, 'lockdownRest'], 10, 3);

        // require a valid api key for unauthenticated rest requests
        add_filter('rest_authentication_errors', [$this, 'requireApiKey']);
    }

    /**
     * requires a valid API key on REST requests that are not already
     * authenticated. logged-in users (cookie / application password) bypass so
     * wp-admin and the block editor keep working, and CORS preflight (OPTIONS)
     * is always allowed so browsers can negotiate the custom header.
     *
     * @param mixed $errors current authentication result (null, true or WP_Error)
     *
     * @return mixed
     */
    public function requireApiKey($errors)
    {
        // respect a result another handler already produced
        if (!empty($errors)) {
            return $errors;
        }

        if (!apply_filters('wpdev_rest_require_api_key', true)) {
            return $errors;
        }

        if (is_user_logged_in()) {
            return $errors;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            return $errors;
        }

        $key = $this->providedApiKey();
        if ($key && ApiKeyConfig::validate($key)) {
            return $errors;
        }

        return new \WP_Error(
            'wpdev_rest_api_key_required',
            'A valid API key is required.',
            ['status' => 401]
        );
    }

    /**
     * reads the presented API key from the X-API-Key header or a
     * "Authorization: Bearer <key>" header.
     *
     * @return string
     */
    private function providedApiKey()
    {
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_X_API_KEY']));
        }

        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (stripos($auth, 'Bearer ') === 0) {
            return sanitize_text_field(trim(substr($auth, 7)));
        }

        return '';
    }

    /**
     * unregisters the REST routes that exist only to power the block editor and
     * the WordPress front-end rendering layer (blocks, patterns, templates,
     * global styles, themes, widgets, fonts, oEmbed and the internal
     * wp-block-editor / wp-abilities namespaces). a headless install serves
     * content via posts/pages/CPTs, whose content.rendered already contains the
     * final block HTML, so none of these are needed. tune with the
     * wpdev_rest_blocked_routes filter.
     *
     * @param array $endpoints
     *
     * @return array
     */
    public function removeFrontendRoutes($endpoints)
    {
        $blocked = apply_filters('wpdev_rest_blocked_routes', [
            '/wp/v2/block',            // blocks, block-types, block-renderer, block-directory, block-patterns
            '/wp/v2/templates',
            '/wp/v2/template-parts',
            '/wp/v2/global-styles',
            '/wp/v2/styles',
            '/wp/v2/themes',
            '/wp/v2/sidebars',
            '/wp/v2/widgets',
            '/wp/v2/widget-types',
            '/wp/v2/font-collections',
            '/wp/v2/font-families',
            '/wp/v2/pattern-directory',
            '/wp/v2/wp_pattern_category',
            '/wp/v2/icons',
            '/wp/v2/plugins',
            '/wp/v2/menu',             // menus, menu-items, menu-locations (use wpdev/v1/menus)
            '/wp/v2/navigation',
            '/wp-block-editor',
            '/wp-abilities',
            '/wp-site-health',
            '/oembed'
        ]);

        // routes containing any of these fragments are editor/admin internals
        $blocked_fragments = apply_filters('wpdev_rest_blocked_fragments', [
            'autosaves',
            'revisions',
            'application-passwords'
        ]);

        foreach (array_keys($endpoints) as $route) {
            foreach ($blocked as $prefix) {
                if (strpos($route, $prefix) === 0) {
                    unset($endpoints[$route]);
                    continue 2;
                }
            }

            foreach ($blocked_fragments as $fragment) {
                if (strpos($route, $fragment) !== false) {
                    unset($endpoints[$route]);
                    break;
                }
            }
        }

        return $endpoints;
    }

    /**
     * removes every write method (POST, PUT, PATCH, DELETE) from the REST API
     * so it is read-only. routes left with no methods are dropped entirely, so
     * they also disappear from discovery and the generated API docs. toggle
     * with the wpdev_rest_readonly filter.
     *
     * note: this also stops the block editor from saving over REST. manage
     * content with the classic editor, or return false from wpdev_rest_readonly
     * if you need authenticated writes.
     *
     * @param array $endpoints
     *
     * @return array
     */
    public function makeReadonly($endpoints)
    {
        if (!apply_filters('wpdev_rest_readonly', true)) {
            return $endpoints;
        }

        $write = ['POST', 'PUT', 'PATCH', 'DELETE'];

        foreach ($endpoints as $route => $handlers) {
            foreach ($handlers as $index => $handler) {
                if (!is_array($handler) || !isset($handler['methods'])) {
                    continue;
                }

                // registered methods may be a comma string, a list of names,
                // or an assoc keyed by name; flatten to uppercase name strings
                $names = [];
                foreach ((array) $handler['methods'] as $key => $value) {
                    if (is_string($key)) {
                        $names[] = strtoupper($key);
                    } else {
                        foreach (explode(',', (string) $value) as $verb) {
                            $names[] = strtoupper(trim($verb));
                        }
                    }
                }

                $names = array_values(array_diff(array_unique($names), $write));

                // WordPress reads the array's values as the method names, so
                // this must stay a flat list, not a name => bool map
                if (empty($names)) {
                    unset($endpoints[$route][$index]);
                } else {
                    $endpoints[$route][$index]['methods'] = $names;
                }
            }

            if (empty($endpoints[$route])) {
                unset($endpoints[$route]);
            }
        }

        return $endpoints;
    }

    /**
     * locks the public REST surface down to read-only access for the content
     * a headless frontend needs: posts, pages, public custom post types and
     * the custom menus endpoint. authenticated requests are left untouched so
     * WordPress' own capability checks govern wp-admin and the block editor.
     *
     * @param mixed $result
     * @param \WP_REST_Server $server
     * @param \WP_REST_Request $request
     *
     * @return mixed
     */
    public function lockdownRest($result, $server, $request)
    {
        if (is_wp_error($result) || is_user_logged_in()) {
            return $result;
        }

        if (!apply_filters('wpdev_rest_lockdown', true)) {
            return $result;
        }

        $method = $request->get_method();
        if (in_array($method, ['GET', 'HEAD'], true) && $this->isPublicRoute($request->get_route())) {
            return $result;
        }

        return new \WP_Error(
            'rest_forbidden',
            'The REST API is restricted on this site.',
            ['status' => rest_authorization_required_code()]
        );
    }

    /**
     * builds the anonymous read allowlist: the menus endpoint plus every
     * public, REST-enabled post type (posts, pages, custom post types),
     * matching both the collection and single-item routes. media is excluded
     * by default. extend with the wpdev_rest_public_routes filter.
     *
     * @param string $route
     *
     * @return boolean
     */
    private function isPublicRoute($route)
    {
        $patterns = ['#^/wpdev/v1/menus/[^/]+/?$#'];

        $types = get_post_types(['public' => true, 'show_in_rest' => true], 'objects');
        foreach ($types as $type) {
            if ($type->name === 'attachment') {
                continue;
            }
            $namespace = $type->rest_namespace ?: 'wp/v2';
            $base = $type->rest_base ?: $type->name;
            $patterns[] = '#^/' . preg_quote($namespace, '#') . '/' . preg_quote($base, '#') . '(/\d+)?/?$#';
        }

        $patterns = apply_filters('wpdev_rest_public_routes', $patterns);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $route)) {
                return true;
            }
        }
        return false;
    }

    /**
     * injects the customizer frontend url into the cors allowlist
     *
     * @param array $origins
     *
     * @return array
     */
    public function addConfiguredOrigins($origins)
    {
        $frontend = $this->urlToOrigin(get_theme_mod('wpdev_frontend_url'));
        if ($frontend) {
            $origins[] = $frontend;
        }
        return $origins;
    }

    /**
     * normalizes a stored url to a cors-comparable origin
     * (scheme + host + optional port, no path or trailing slash)
     *
     * @param string $url
     *
     * @return string|null
     */
    private function urlToOrigin($url)
    {
        if (!$url) {
            return null;
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }
        return $origin;
    }

    /**
     * removes users endpoints from the rest api for unauthenticated requests
     *
     * @param array $endpoints
     *
     * @return array
     */
    public function restrictUsersEndpoint($endpoints)
    {
        if (is_user_logged_in()) {
            return $endpoints;
        }

        if (isset($endpoints['/wp/v2/users'])) {
            unset($endpoints['/wp/v2/users']);
        }
        if (isset($endpoints['/wp/v2/users/(?P<id>[\d]+)'])) {
            unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
        }
        return $endpoints;
    }

    /**
     * sends cors headers only for origins on the allowlist
     *
     * developers add origins via the wpdev_rest_allowed_origins filter
     *
     * @return void
     */
    public function configureCors()
    {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');

        add_filter('rest_pre_serve_request', [$this, 'sendCorsHeaders']);
    }

    /**
     * emits cors headers if the request origin is allowlisted
     *
     * @param boolean $value
     *
     * @return boolean
     */
    public function sendCorsHeaders($value)
    {
        $origin = get_http_origin();
        if (!$origin) {
            return $value;
        }

        $is_dev = in_array(wp_get_environment_type(), ['local', 'development'], true);
        $allowed = apply_filters('wpdev_rest_allowed_origins', []);

        if ($is_dev || in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, X-API-Key, Content-Type');
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }
        return $value;
    }
}

new RestConfig();
