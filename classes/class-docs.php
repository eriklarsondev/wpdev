<?php
namespace wpdev;

class DocsConfig
{
    const PAGE = 'wpdev-api-docs';

    private $hook = '';
    private $namespaces = [];

    /**
     * constructor for the API documentation page. registers the admin page
     * and its Scalar API Reference assets; the OpenAPI spec is generated
     * server-side and embedded directly into the page.
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'registerPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    /**
     * registers the top-level "API Docs" admin page
     *
     * @return void
     */
    public function registerPage()
    {
        $this->hook = add_menu_page(
            'API Documentation',
            'API Docs',
            'manage_options',
            self::PAGE,
            [$this, 'renderPage'],
            'dashicons-rest-api',
            80
        );
    }

    /**
     * loads Scalar API Reference (from a CDN by default; override with the
     * wpdev_scalar_src filter to self-host) on the docs page only, and
     * initializes it against the generated spec. the REST nonce is injected
     * into every request so "Try it out" works for the logged-in admin.
     *
     * @param string $hook current admin page hook
     *
     * @return void
     */
    public function enqueue($hook)
    {
        if ($hook !== $this->hook) {
            return;
        }

        $src = apply_filters('wpdev_scalar_src', 'https://cdn.jsdelivr.net/npm/@scalar/api-reference');
        wp_enqueue_script('wpdev-scalar', $src, [], null, true);

        $config = [
            'spec' => $this->specArray(),
            'nonce' => wp_create_nonce('wp_rest')
        ];
        wp_add_inline_script('wpdev-scalar', 'window.wpdevDocs = ' . wp_json_encode($config) . ';', 'before');
        wp_add_inline_script('wpdev-scalar', $this->initScript());
    }

    /**
     * the Scalar API Reference bootstrap script
     *
     * @return string
     */
    private function initScript()
    {
        return <<<JS
        (function () {
            function init() {
                if (!window.Scalar || !window.Scalar.createApiReference) {
                    return;
                }
                window.Scalar.createApiReference('#wpdev-scalar', {
                    content: window.wpdevDocs.spec,
                    layout: 'modern',
                    darkMode: false,
                    hideSearch: true,
                    proxyUrl: '',
                    onBeforeRequest: function (ctx) {
                        try {
                            ctx.requestBuilder.headers.set('X-WP-Nonce', window.wpdevDocs.nonce);
                        } catch (e) {}
                    }
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        JS;
    }

    /**
     * renders the docs page shell that Scalar mounts into
     *
     * @return void
     */
    public function renderPage()
    {
        ?>
        <style>
            html.wp-toolbar { overflow: hidden; }
            #wpcontent { padding-left: 0; }
            #wpbody-content { padding: 0; float: none; }
            #wpfooter { display: none; }
            #wpdev-scalar {
                overflow: auto;
                background: #fff;
                height: calc(100vh - 32px);
            }
        </style>
        <div id="wpdev-scalar"></div>
        <script>
            (function () {
                function fit() {
                    var el = document.getElementById('wpdev-scalar');
                    if (!el) {
                        return;
                    }
                    var top = el.getBoundingClientRect().top;
                    el.style.height = (window.innerHeight - top) + 'px';
                }
                window.addEventListener('resize', fit);
                window.addEventListener('load', fit);
                if (document.readyState !== 'loading') {
                    fit();
                }
                document.addEventListener('DOMContentLoaded', fit);
            })();
        </script>
        <?php
    }

    private function loadMarkdown($file, $vars = [])
    {
        $path = dirname(__DIR__) . '/docs/' . $file;
        if (!file_exists($path)) {
            return '';
        }
        $content = file_get_contents($path);
        foreach ($vars as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        return trim($content);
    }

    /**
     * builds an OpenAPI 3.0 document from rest_get_server()->get_routes().
     * the spec is generated server-side and embedded directly into the admin
     * page, so no (auth-gated) HTTP round-trip is needed to load it.
     *
     * @return array
     */
    private function specArray()
    {
        $server = rest_get_server();
        $this->namespaces = $server->get_namespaces();
        $routes = $server->get_routes();

        $allowed = apply_filters('wpdev_openapi_namespaces', []);
        $excluded = apply_filters('wpdev_openapi_exclude_namespaces', [
            'wp-block-editor/v1',
            'wp-abilities/v1'
        ]);

        $paths = [];
        $tag_set = [];

        foreach ($routes as $route => $endpoints) {
            $namespace = $this->routeNamespace($route);
            if (!$namespace) {
                continue;
            }
            if ($allowed && !in_array($namespace, $allowed, true)) {
                continue;
            }
            if (in_array($namespace, $excluded, true)) {
                continue;
            }
            if ($route === '/' . $namespace) {
                continue;
            }

            [$path, $path_params] = $this->convertPath($route);
            $item_schema = $this->itemSchemaForPath($path);

            foreach ($endpoints as $endpoint) {
                if (!is_array($endpoint) || empty($endpoint['methods'])) {
                    continue;
                }

                $methods = array_keys(array_filter((array) $endpoint['methods']));
                $args = isset($endpoint['args']) && is_array($endpoint['args']) ? $endpoint['args'] : [];

                foreach ($methods as $method) {
                    $method = strtoupper($method);
                    if (in_array($method, ['OPTIONS', 'HEAD'], true)) {
                        continue;
                    }

                    $paths[$path][strtolower($method)] = $this->buildOperation(
                        $method,
                        $path,
                        $namespace,
                        $args,
                        $path_params,
                        $item_schema
                    );
                }
            }

            $tag_set[$namespace] = true;
        }

        ksort($paths);

        $tag_descriptions = apply_filters('wpdev_openapi_tag_descriptions', [
            'wpdev/v1' => $this->loadMarkdown('tag-wpdev-v1.md'),
            'wp/v2'    => $this->loadMarkdown('tag-wp-v2.md'),
        ]);

        $tags = [];
        foreach (array_keys($tag_set) as $ns) {
            $tag = ['name' => $ns];
            if (!empty($tag_descriptions[$ns])) {
                $tag['description'] = $tag_descriptions[$ns];
            }
            $tags[] = $tag;
        }

        $site      = get_bloginfo('name');
        $rest_base = untrailingslashit(rest_url('/'));
        $version   = (string) (wp_get_theme()->get('Version') ?: '0.0.0');

        $description = $this->loadMarkdown('api-intro.md', [
            'site'      => $site,
            'rest_base' => $rest_base,
            'version'   => $version,
            'admin_url' => admin_url('admin.php?page=wpdev-api-keys'),
        ]);

        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title'       => $site . ' — REST API',
                'version'     => $version,
                'description' => $description,
            ],
            'servers' => [['url' => untrailingslashit(rest_url('/'))]],
            'tags' => $tags,
            'security' => [
                ['apiKeyAuth' => []],
                ['cookieAuth' => []]
            ],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'apiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-Key',
                        'description' => 'API key issued under "API Keys" in wp-admin. Required on every external request; send it in the X-API-Key header.'
                    ],
                    'cookieAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-WP-Nonce',
                        'description' => 'WordPress REST cookie nonce, sent automatically when authenticated in wp-admin.'
                    ]
                ],
                'schemas' => [
                    'Error' => [
                        'type' => 'object',
                        'description' => 'WordPress REST API error envelope.',
                        'properties' => [
                            'code' => ['type' => 'string', 'example' => 'rest_forbidden'],
                            'message' => ['type' => 'string', 'example' => 'Sorry, you are not allowed to do that.'],
                            'data' => [
                                'type' => 'object',
                                'properties' => [
                                    'status' => ['type' => 'integer', 'example' => 401]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return $spec;
    }

    /**
     * builds a single OpenAPI operation object for one method on one route
     *
     * @param string $method
     * @param string $path
     * @param string $namespace
     * @param array $args
     * @param array $path_params
     * @param array|null $item_schema OpenAPI schema for a single resource
     *
     * @return array
     */
    private function buildOperation($method, $path, $namespace, $args, $path_params, $item_schema = null)
    {
        $ok = ['description' => 'Successful response'];

        if ($item_schema) {
            // collection endpoints accept per_page and return an array of the
            // item schema; everything else returns the schema as-is
            $schema = isset($args['per_page'])
                ? ['type' => 'array', 'items' => $item_schema]
                : $item_schema;
            $ok['content'] = ['application/json' => ['schema' => $schema]];
        }

        $operation = [
            'tags' => [$namespace],
            'summary' => $method . ' ' . $path,
            'parameters' => [],
            'responses' => ['200' => $ok]
        ];

        foreach ($path_params as $name) {
            $operation['parameters'][] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => preg_match('/id$/i', $name) ? ['type' => 'integer'] : ['type' => 'string']
            ];
        }

        $body_args = [];

        foreach ($args as $name => $arg) {
            if (in_array($name, $path_params, true) || !is_array($arg)) {
                continue;
            }

            if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                $body_args[$name] = $arg;
                continue;
            }

            $param = [
                'name' => $name,
                'in' => 'query',
                'required' => !empty($arg['required']),
                'schema' => $this->argToSchema($arg)
            ];
            if (!empty($arg['description'])) {
                $param['description'] = $arg['description'];
            }
            $operation['parameters'][] = $param;
        }

        if ($body_args) {
            $properties = [];
            $required = [];

            foreach ($body_args as $name => $arg) {
                $properties[$name] = $this->argToSchema($arg);
                if (!empty($arg['description'])) {
                    $properties[$name]['description'] = $arg['description'];
                }
                if (!empty($arg['required'])) {
                    $required[] = $name;
                }
            }

            $schema = ['type' => 'object', 'properties' => $properties];
            if ($required) {
                $schema['required'] = $required;
            }

            $operation['requestBody'] = [
                'content' => ['application/json' => ['schema' => $schema]]
            ];
        }

        // success response varies by method
        if ($method === 'POST') {
            $created = ['description' => 'Resource created'];
            if ($item_schema) {
                $created['content'] = ['application/json' => ['schema' => $item_schema]];
            }
            $operation['responses']['201'] = $created;
        } elseif ($method === 'DELETE') {
            $operation['responses']['204'] = ['description' => 'Resource deleted'];
        }

        // error responses
        $has_query = false;
        foreach ($operation['parameters'] as $parameter) {
            if (($parameter['in'] ?? '') === 'query') {
                $has_query = true;
                break;
            }
        }

        if ($has_query || $body_args) {
            $operation['responses']['400'] = $this->errorResponse(400, 'Bad request — invalid or missing parameters');
        }

        $operation['responses']['401'] = $this->errorResponse(401, 'Unauthorized — authentication required');
        $operation['responses']['403'] = $this->errorResponse(403, 'Forbidden — insufficient permissions');

        if (substr($path, -1) === '}') {
            $operation['responses']['404'] = $this->errorResponse(404, 'Not found — resource does not exist');
        }

        $operation['responses']['500'] = $this->errorResponse(500, 'Internal server error');

        ksort($operation['responses']);

        return $operation;
    }

    private function errorResponse($status, $description)
    {
        $examples = [
            400 => ['code' => 'rest_invalid_param',   'message' => 'Invalid parameter(s): context',             'data' => ['status' => 400]],
            401 => ['code' => 'rest_not_logged_in',   'message' => 'You are not currently logged in.',          'data' => ['status' => 401]],
            403 => ['code' => 'rest_forbidden',        'message' => 'Sorry, you are not allowed to do that.',   'data' => ['status' => 403]],
            404 => ['code' => 'rest_post_invalid_id',  'message' => 'Invalid post ID.',                         'data' => ['status' => 404]],
            500 => ['code' => 'rest_unknown_error',    'message' => 'An unknown error occurred.',               'data' => ['status' => 500]],
        ];

        return [
            'description' => $description,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'code'    => ['type' => 'string'],
                            'message' => ['type' => 'string'],
                            'data'    => ['type' => 'object', 'properties' => ['status' => ['type' => 'integer']]]
                        ],
                        'example' => $examples[$status] ?? ['code' => 'rest_error', 'message' => 'An error occurred.', 'data' => ['status' => $status]]
                    ]
                ]
            ]
        ];
    }

    /**
     * resolves a resource's item schema by issuing an internal OPTIONS request
     * to its collection path (the schema lives in the route's OPTIONS data, not
     * in get_routes()). results are cached per base path. returns an
     * OpenAPI-compatible schema, or null when the route exposes none.
     *
     * @param string $path the converted OpenAPI path
     *
     * @return array|null
     */
    private function itemSchemaForPath($path)
    {
        static $cache = [];

        if (array_key_exists($path, $cache)) {
            return $cache[$path];
        }

        // build a concrete path that matches the route's pattern (ids -> 1,
        // other params -> a token) and ask for its schema via OPTIONS
        $probe = preg_replace_callback('#\{([^}]+)\}#', function ($matches) {
            return preg_match('/id$/i', $matches[1]) ? '1' : 'sample';
        }, $path);

        $response = rest_do_request(new \WP_REST_Request('OPTIONS', $probe));
        $schema = null;

        if (!$response->is_error()) {
            $data = $response->get_data();
            if (!empty($data['schema']) && is_array($data['schema'])) {
                $schema = $this->cleanSchema($data['schema']);
            }
        }

        return $cache[$path] = $schema;
    }

    /**
     * recursively converts a WordPress JSON schema into an OpenAPI 3.0 schema:
     * drops WP-only keys, and rewrites union "type" arrays (e.g. [string,null])
     * into a single type plus nullable.
     *
     * @param mixed $schema
     *
     * @return mixed
     */
    private function cleanSchema($schema)
    {
        if (!is_array($schema)) {
            return $schema;
        }

        foreach (['context', 'arg_options', 'sanitize_callback', 'validate_callback', '$schema', 'readonly', 'links'] as $key) {
            unset($schema[$key]);
        }

        if (isset($schema['type']) && is_array($schema['type'])) {
            $types = array_values(array_filter($schema['type'], function ($t) {
                return $t !== 'null';
            }));
            if (count($types) !== count($schema['type'])) {
                $schema['nullable'] = true;
            }
            $schema['type'] = $types[0] ?? 'string';
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $name => $prop) {
                $schema['properties'][$name] = $this->cleanSchema($prop);
            }
        }

        foreach (['items', 'additionalProperties'] as $key) {
            if (isset($schema[$key]) && is_array($schema[$key])) {
                $schema[$key] = $this->cleanSchema($schema[$key]);
            }
        }

        foreach (['oneOf', 'anyOf', 'allOf'] as $combiner) {
            if (isset($schema[$combiner]) && is_array($schema[$combiner])) {
                $schema[$combiner] = array_map([$this, 'cleanSchema'], $schema[$combiner]);
            }
        }

        return $schema;
    }

    /**
     * maps a WordPress REST argument definition to an OpenAPI schema
     *
     * @param array $arg
     *
     * @return array
     */
    private function argToSchema($arg)
    {
        $type = $arg['type'] ?? 'string';
        if (is_array($type)) {
            $type = $this->firstScalarType($type);
        }

        $map = [
            'integer' => 'integer',
            'number' => 'number',
            'string' => 'string',
            'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'object',
            'null' => 'string'
        ];

        $schema = ['type' => $map[$type] ?? 'string'];

        if (isset($arg['enum']) && is_array($arg['enum'])) {
            $schema['enum'] = array_values($arg['enum']);
        }
        if (array_key_exists('default', $arg)) {
            $schema['default'] = $arg['default'];
        }
        if (!empty($arg['format'])) {
            $schema['format'] = $arg['format'];
        }
        if ($schema['type'] === 'array' && isset($arg['items']) && is_array($arg['items'])) {
            $schema['items'] = $this->argToSchema($arg['items']);
        }

        return $schema;
    }

    /**
     * returns the first non-null type from a WordPress union type list
     *
     * @param array $types
     *
     * @return string
     */
    private function firstScalarType($types)
    {
        foreach ($types as $type) {
            if ($type !== 'null') {
                return $type;
            }
        }
        return 'string';
    }

    /**
     * resolves which registered namespace a route belongs to
     *
     * @param string $route
     *
     * @return string
     */
    private function routeNamespace($route)
    {
        foreach ($this->namespaces as $namespace) {
            if (strpos($route, '/' . $namespace) === 0) {
                return $namespace;
            }
        }
        return '';
    }

    /**
     * converts a WordPress route regex into an OpenAPI path, swapping named
     * capture groups for {param} placeholders. parentheses are matched by
     * depth so nested patterns are handled, and any leftover non-capturing
     * groups / optional markers are stripped.
     *
     * @param string $route
     *
     * @return array [path, paramNames]
     */
    private function convertPath($route)
    {
        $path = '';
        $params = [];
        $len = strlen($route);
        $i = 0;

        while ($i < $len) {
            if (substr($route, $i, 4) === '(?P<') {
                $close = strpos($route, '>', $i);
                $name = substr($route, $i + 4, $close - ($i + 4));
                $params[] = $name;

                $depth = 0;
                for ($j = $i; $j < $len; $j++) {
                    if ($route[$j] === '(') {
                        $depth++;
                    } elseif ($route[$j] === ')') {
                        $depth--;
                        if ($depth === 0) {
                            break;
                        }
                    }
                }

                $path .= '{' . $name . '}';
                $i = $j + 1;
            } else {
                $path .= $route[$i];
                $i++;
            }
        }

        $path = preg_replace('#\(\?:[^)]*\)\??#', '', $path);
        $path = str_replace(['(', ')', '?'], '', $path);

        return [$path, $params];
    }
}

new DocsConfig();
