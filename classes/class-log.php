<?php
namespace wpdev;

class LogConfig
{
    const OPTION_KEY = 'wpdev_api_log';
    const MAX_ENTRIES = 50;

    private $startTime = null;

    /**
     * constructor for rest api logging
     */
    public function __construct()
    {
        add_filter('rest_pre_dispatch', [$this, 'startTimer'], 10, 3);
        add_filter('rest_post_dispatch', [$this, 'logRequest'], 10, 3);
        add_action('admin_post_wpdev_clear_api_log', [$this, 'handleClear']);

        DashboardConfig::add_widget(
            'wpdev_api_log',
            'Recent API Activity',
            [$this, 'renderWidget'],
            'column3'
        );
    }

    /**
     * empties the ring buffer in response to the Clear button on the widget.
     *
     * @return void
     */
    public function handleClear()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', 403);
        }
        check_admin_referer('wpdev_clear_api_log');
        delete_option(self::OPTION_KEY);
        wp_safe_redirect(admin_url());
        exit;
    }

    /**
     * captures the start time of an incoming rest request
     *
     * @param mixed $result
     * @param \WP_REST_Server $server
     * @param \WP_REST_Request $request
     *
     * @return mixed
     */
    public function startTimer($result, $server, $request)
    {
        $this->startTime = microtime(true);
        return $result;
    }

    /**
     * appends a request entry to the ring buffer after dispatch
     *
     * @param \WP_REST_Response $result
     * @param \WP_REST_Server $server
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response
     */
    public function logRequest($result, $server, $request)
    {
        $origin = get_http_origin() ?: '';
        if (!$this->isExternalOrigin($origin)) {
            return $result;
        }

        $duration = $this->startTime
            ? round((microtime(true) - $this->startTime) * 1000, 2)
            : null;

        $status = $result instanceof \WP_HTTP_Response
            ? $result->get_status()
            : 200;

        $entry = [
            'time' => time(),
            'method' => $request->get_method(),
            'route' => $request->get_route(),
            'status' => $status,
            'duration_ms' => $duration,
            'origin' => $origin,
            'ip' => $this->getAnonymizedIp(),
            'user' => get_current_user_id()
        ];

        $this->append($entry);
        return $result;
    }

    /**
     * determines whether the request is from an external caller (a cross-origin
     * app or local dev frontend). requests originating from wp-admin, wp-login,
     * or the wp site's own origin are treated as internal and skipped, as are
     * server-side requests with no origin header.
     *
     * @param string $origin
     *
     * @return boolean
     */
    private function isExternalOrigin($origin)
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer && (
            strpos($referer, '/wp-admin/') !== false
            || strpos($referer, '/wp-login.php') !== false
        )) {
            return false;
        }

        if (!$origin) {
            return false;
        }

        $internal = array_unique(array_filter([
            $this->hostPort(home_url()),
            $this->hostPort(site_url()),
            $this->hostPort(admin_url()),
            isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : ''
        ]));

        $originHostPort = $this->hostPort($origin);
        if (!$originHostPort) {
            return false;
        }

        $originHost = strtok($originHostPort, ':');
        foreach ($internal as $entry) {
            $entryHost = strtok($entry, ':');
            if ($originHost === $entryHost) {
                return false;
            }
        }

        return (bool) apply_filters('wpdev_api_log_external_origin', true, $origin);
    }

    /**
     * reduces a url to host[:port] for scheme-agnostic origin comparison.
     * scheme is dropped so requests originating from https://site over to
     * http://site (or vice versa) still register as internal.
     *
     * @param string $url
     *
     * @return string
     */
    private function hostPort($url)
    {
        if (!$url) {
            return '';
        }
        $parts = wp_parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return '';
        }
        $hp = strtolower($parts['host']);
        if (!empty($parts['port'])) {
            $hp .= ':' . $parts['port'];
        }
        return $hp;
    }

    /**
     * appends an entry and trims the buffer to the configured max
     *
     * @param array $entry
     *
     * @return void
     */
    private function append($entry)
    {
        $entries = get_option(self::OPTION_KEY, []);
        if (!is_array($entries)) {
            $entries = [];
        }

        array_unshift($entries, $entry);

        $max = apply_filters('wpdev_api_log_max_entries', self::MAX_ENTRIES);
        $entries = array_slice($entries, 0, $max);

        update_option(self::OPTION_KEY, $entries, false);
    }

    /**
     * returns the remote ip truncated for privacy (ipv4 /24, ipv6 /48)
     *
     * @return string
     */
    private function getAnonymizedIp()
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!$ip) {
            return '';
        }

        if (strpos($ip, '.') !== false) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                $parts[3] = '0';
                return implode('.', $parts);
            }
        }

        if (strpos($ip, ':') !== false) {
            $parts = array_slice(explode(':', $ip), 0, 3);
            return implode(':', $parts) . '::';
        }

        return '';
    }

    /**
     * renders the ring buffer as a table on the dashboard
     *
     * @return void
     */
    public function renderWidget()
    {
        $entries = get_option(self::OPTION_KEY, []);
        if (!is_array($entries) || empty($entries)) {
            echo '<p><em>No API activity logged yet.</em></p>';
            return;
        }

        echo '<style>
            .wpdev-log-table { font-size: 12px; width: 100%; }
            .wpdev-log-table .route { max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .wpdev-log-table .status-2xx { color: #00a32a; }
            .wpdev-log-table .status-3xx { color: #72aee6; }
            .wpdev-log-table .status-4xx { color: #dba617; }
            .wpdev-log-table .status-5xx { color: #d63638; }
        </style>';

        echo '<table class="widefat striped wpdev-log-table">';
        echo '<thead><tr><th>When</th><th>Method</th><th>Route</th><th>Status</th><th>ms</th></tr></thead>';
        echo '<tbody>';

        foreach ($entries as $entry) {
            $when = human_time_diff($entry['time'], time()) . ' ago';
            $bucket = 'status-' . (intdiv((int) $entry['status'], 100)) . 'xx';
            $route = (string) $entry['route'];

            printf(
                '<tr>
                    <td>%s</td>
                    <td><code>%s</code></td>
                    <td class="route" title="%s"><code>%s</code></td>
                    <td class="%s"><strong>%d</strong></td>
                    <td>%s</td>
                </tr>',
                esc_html($when),
                esc_html($entry['method']),
                esc_attr($route),
                esc_html($route),
                esc_attr($bucket),
                (int) $entry['status'],
                $entry['duration_ms'] !== null ? esc_html($entry['duration_ms']) : '&mdash;'
            );
        }

        echo '</tbody></table>';

        $clear_url = wp_nonce_url(
            admin_url('admin-post.php?action=wpdev_clear_api_log'),
            'wpdev_clear_api_log'
        );
        printf(
            '<p style="margin-top:10px;text-align:right;"><a href="%s" class="button button-small">Clear log</a></p>',
            esc_url($clear_url)
        );
    }
}

new LogConfig();
