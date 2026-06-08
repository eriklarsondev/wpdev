<?php
namespace wpdev;

class StatusConfig
{
    const CACHE_KEY = 'wpdev_api_status';
    const CACHE_TTL = 60;

    /**
     * constructor for the API status bar. renders a full-width banner at the
     * top of the dashboard (dashboard widgets cannot natively span columns)
     * and registers the manual re-check handler.
     */
    public function __construct()
    {
        add_action('admin_notices', [$this, 'render']);
        add_action('admin_post_wpdev_recheck_api_status', [$this, 'handleRecheck']);
    }

    /**
     * busts the cached probe in response to the Re-check link so the next
     * dashboard load runs a fresh request.
     *
     * @return void
     */
    public function handleRecheck()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', 403);
        }
        check_admin_referer('wpdev_recheck_api_status');
        delete_transient(self::CACHE_KEY);
        wp_safe_redirect(admin_url());
        exit();
    }

    /**
     * performs a loopback request to the REST API root and classifies the
     * result as operational, degraded or unreachable. the outcome is cached
     * briefly so the dashboard does not issue a request on every load.
     *
     * @return array { state, code, ms, detail, checked }
     */
    private function probe()
    {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $start = microtime(true);
        $response = wp_remote_get(add_query_arg('per_page', 1, rest_url('wp/v2/posts')), [
            'timeout' => 4,
            'sslverify' => false,
            'headers' => ['Accept' => 'application/json']
        ]);
        $ms = (int) round((microtime(true) - $start) * 1000);

        if (is_wp_error($response)) {
            $status = [
                'state' => 'unreachable',
                'code' => 0,
                'ms' => $ms,
                'detail' => $response->get_error_message(),
                'checked' => time()
            ];
        } else {
            $code = (int) wp_remote_retrieve_response_code($response);

            if ($code === 0 || $code >= 500) {
                $state = 'unreachable';
            } elseif (in_array($code, [401, 403], true)) {
                // the API responded and is enforcing authentication / api keys
                $state = 'operational';
            } elseif ($code < 200 || $code >= 300) {
                $state = 'degraded';
            } elseif ($ms > 800) {
                $state = 'degraded';
            } else {
                $state = 'operational';
            }

            $status = [
                'state' => $state,
                'code' => $code,
                'ms' => $ms,
                'detail' => '',
                'checked' => time()
            ];
        }

        set_transient(self::CACHE_KEY, $status, self::CACHE_TTL);
        return $status;
    }

    /**
     * renders the full-width API status bar on the dashboard only
     *
     * @return void
     */
    public function render()
    {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'dashboard' || !current_user_can('manage_options')) {
            return;
        }

        $status = $this->probe();
        $meta = [
            'operational' => ['label' => 'Operational', 'desc' => 'The REST API is responding normally.'],
            'degraded' => ['label' => 'Degraded', 'desc' => 'The REST API is reachable but slow or returning errors.'],
            'unreachable' => ['label' => 'Unreachable', 'desc' => 'The REST API could not be reached.']
        ];
        $info = $meta[$status['state']] ?? $meta['unreachable'];

        $recheck = wp_nonce_url(
            admin_url('admin-post.php?action=wpdev_recheck_api_status'),
            'wpdev_recheck_api_status'
        );

        if ($status['detail']) {
            $detail = esc_html($status['detail']);
        } elseif ($status['state'] === 'operational') {
            $detail = (int) $status['ms'] . ' ms';
        } else {
            $detail = 'HTTP ' . (int) $status['code'] . ' &middot; ' . (int) $status['ms'] . ' ms';
        }
        ?>
        <style>
            .wpdev-status {
                display:flex;
                align-items:center;
                gap:14px;
                margin:16px 0;
                padding:13px 18px;
                background:#fff;
                border:1px solid #c3c4c7;
                border-left-width:4px;
                border-radius:6px;
                box-shadow:0 1px 1px rgba(0,0,0,.04);
            }
            .wpdev-status-light {
                width:12px;
                height:12px;
                border-radius:50%;
                flex-shrink:0;
                margin-right:4px;
            }
            .wpdev-status-pill {
                display:inline-flex;
                align-items:center;
                padding:2px 9px;
                border-radius:999px;
                font-size:11px;
                font-weight:700;
                letter-spacing:.03em;
                text-transform:uppercase;
            }
            .wpdev-status--operational { border-left-color:#00a32a; }
            .wpdev-status--operational .wpdev-status-light { background:#00a32a; box-shadow:0 0 0 4px rgba(0,163,42,.15); }
            .wpdev-status--operational .wpdev-status-pill { background:#edfaef; color:#007017; }
            .wpdev-status--degraded { border-left-color:#dba617; }
            .wpdev-status--degraded .wpdev-status-light { background:#dba617; box-shadow:0 0 0 4px rgba(219,166,23,.15); }
            .wpdev-status--degraded .wpdev-status-pill { background:#fcf6e1; color:#8a6d00; }
            .wpdev-status--unreachable { border-left-color:#d63638; }
            .wpdev-status--unreachable .wpdev-status-light { background:#d63638; box-shadow:0 0 0 4px rgba(214,54,56,.15); }
            .wpdev-status--unreachable .wpdev-status-pill { background:#fbeaea; color:#b32d2e; }
            .wpdev-status-main { flex:1; min-width:0; display:flex; flex-direction:column; gap:1px; }
            .wpdev-status-state { display:flex; align-items:center; gap:8px; font-size:14px; font-weight:600; color:#1d2327; }
            .wpdev-status-desc { padding-left:24px; }
            .wpdev-status-desc { font-size:12px; color:#646970; }
            .wpdev-status-metrics {
                display:flex;
                align-items:center;
                gap:14px;
                flex-shrink:0;
                font-size:12px;
                color:#646970;
            }
            .wpdev-status-metrics code { background:#f6f7f7; padding:2px 6px; border-radius:3px; }
        </style>
        <div id="wpdev-status" class="wpdev-status wpdev-status--<?php echo esc_attr($status['state']); ?>">
            <div class="wpdev-status-main">
                <span class="wpdev-status-state"><span class="wpdev-status-light"></span>REST API<span class="wpdev-status-pill"><?php echo esc_html($info['label']); ?></span></span>
                <span class="wpdev-status-desc"><?php echo esc_html($info['desc']); ?></span>
            </div>
            <div class="wpdev-status-metrics">
                <span><code><?php echo wp_kses($detail, ['span' => []]); ?></code></span>
                <span>checked <?php echo esc_html(human_time_diff($status['checked'], time())); ?> ago</span>
                <a href="<?php echo esc_url($recheck); ?>">Re-check</a>
            </div>
        </div>
        <script>
            jQuery(function ($) {
                var $bar = $('#wpdev-status');
                var $wrap = $('#dashboard-widgets-wrap');
                if ($bar.length && $wrap.length) {
                    $wrap.before($bar);
                }
            });
        </script>
        <?php
    }
}

new StatusConfig();
