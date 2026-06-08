<?php
namespace wpdev;

class EnvironmentConfig
{
    /**
     * registers the environment and server-audit dashboard cards. placement
     * is governed by DashboardConfig's locked-column order.
     */
    public function __construct()
    {
        DashboardConfig::add_widget('wpdev_environment', 'Environment', [$this, 'renderEnvironmentWidget'], 'side');
        DashboardConfig::add_widget('wpdev_server_audit', 'Server Audit', [$this, 'renderServerAuditWidget'], 'side');
    }

    /**
     * styles for the environment and server-audit cards. printed once per
     * request via a static guard.
     *
     * @return void
     */
    private function printStyles()
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;?>
        <style>
            .wpdev-env {
                margin:0;
                display:flex;
                align-items:center;
                gap:12px;
            }
            .wpdev-env-badge {
                display:inline-flex;
                align-items:center;
                gap:7px;
                flex-shrink:0;
                font-size:12px;
                font-weight:700;
                letter-spacing:.03em;
                text-transform:uppercase;
                padding:6px 13px;
                border-radius:999px;
            }
            .wpdev-env-badge .dot {
                width:8px;
                height:8px;
                border-radius:50%;
                background:currentColor;
            }
            .wpdev-env-badge.local,
            .wpdev-env-badge.development { background:#e7eef7; color:#2271b1; }
            .wpdev-env-badge.staging { background:#fcf6e1; color:#8a6d00; }
            .wpdev-env-badge.production { background:#edfaef; color:#007017; }
            .wpdev-env-meta { flex:1; min-width:0; }
            .wpdev-env-desc { margin:0; font-size:12px; color:#646970; }
            .wpdev-env-source { margin:2px 0 0; font-size:11px; color:#8c8f94; }
            .wpdev-env-source code {
                background:#f6f7f7;
                padding:1px 5px;
                border-radius:3px;
                font-size:11px;
            }
            .wpdev-env-webhook {
                display:flex;
                align-items:center;
                gap:8px;
                margin-top:12px;
                padding-top:12px;
                border-top:1px solid #f0f0f1;
                font-size:12px;
            }
            .wpdev-env-wh-label { font-weight:600; color:#1d2327; }
            .wpdev-env-wh-pill {
                font-size:10px;
                font-weight:700;
                letter-spacing:.03em;
                text-transform:uppercase;
                padding:2px 8px;
                border-radius:999px;
            }
            .wpdev-env-wh-pill.ok { background:#edfaef; color:#007017; }
            .wpdev-env-wh-pill.warn { background:#fcf0e0; color:#8a5700; }
            .wpdev-env-wh-target {
                margin-left:auto;
                max-width:62%;
                overflow:hidden;
                text-overflow:ellipsis;
                white-space:nowrap;
                font-size:11px;
                color:#8c8f94;
            }

            .wpdev-card { margin:0; }
            .wpdev-card dl {
                margin:0;
                display:grid;
                grid-template-columns:max-content 1fr;
            }
            .wpdev-card dt,
            .wpdev-card dd {
                margin:0;
                padding:8px 0;
                font-size:13px;
                border-top:1px solid #f0f0f1;
            }
            .wpdev-card dl > dt:first-of-type,
            .wpdev-card dl > dt:first-of-type + dd { border-top:0; }
            .wpdev-card dt { font-weight:600; color:#1d2327; padding-right:16px; }
            .wpdev-card dd { color:#50575e; text-align:right; }
            .wpdev-card code {
                background:#f6f7f7;
                padding:2px 6px;
                border-radius:3px;
                font-size:12px;
            }
            .wpdev-card .wpdev-warn { color:#d63638; font-weight:600; }
        </style>
        <?php
    }

    /**
     * renders the current environment type as a single prominent badge with
     * a short description of what that environment implies
     *
     * @return void
     */
    public function renderEnvironmentWidget()
    {
        $this->printStyles();

        $env = wp_get_environment_type();
        $descriptions = [
            'local' => 'Running on a local development machine.',
            'development' => 'Shared development environment.',
            'staging' => 'Pre-production staging environment.',
            'production' => 'Live production environment — changes affect real users.'
        ];
        $desc = $descriptions[$env] ?? 'Unknown environment type.';

        printf(
            '<div class="wpdev-env">
                <span class="wpdev-env-meta">
                    <p class="wpdev-env-desc">%2$s</p>
                    <p class="wpdev-env-source">Set via %3$s</p>
                </span>
                <span class="wpdev-env-badge %1$s"><span class="dot"></span>%1$s</span>
            </div>',
            esc_attr($env),
            esc_html($desc),
            $this->environmentSource()
        );

        $this->renderWebhookRow();
    }

    /**
     * renders the webhook status row: active only when both a target frontend
     * url and a signing secret are configured, otherwise flags what is missing.
     *
     * @return void
     */
    private function renderWebhookRow()
    {
        $frontend = get_theme_mod('wpdev_frontend_url');
        $secret = defined('WPDEV_WEBHOOK_SECRET') && WPDEV_WEBHOOK_SECRET !== '';
        $path = apply_filters('wpdev_webhook_path', '/api/revalidate');

        if ($frontend && $secret) {
            $pill = '<span class="wpdev-env-wh-pill ok">Active</span>';
            $detail = rtrim(preg_replace('#^https?://#', '', $frontend), '/') . $path;
        } else {
            $pill = '<span class="wpdev-env-wh-pill warn">Inactive</span>';
            $detail = !$frontend ? 'Frontend URL not set' : 'Webhook secret not defined';
        }

        printf(
            '<div class="wpdev-env-webhook">
                <span class="wpdev-env-wh-label">Webhook</span>%s
                <span class="wpdev-env-wh-target">%s</span>
            </div>',
            $pill,
            esc_html($detail)
        );
    }

    /**
     * mirrors wp_get_environment_type()'s resolution order to report where
     * the active environment value is coming from: the WP_ENVIRONMENT_TYPE
     * constant, a server environment variable, or WordPress' production
     * default when neither is set.
     *
     * @return string html
     */
    private function environmentSource()
    {
        if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE) {
            return '<code>wp-config.php</code> constant';
        }
        if (getenv('WP_ENVIRONMENT_TYPE')) {
            return 'server environment variable';
        }
        return 'WordPress default';
    }

    /**
     * renders the server-audit card
     *
     * @return void
     */
    public function renderServerAuditWidget()
    {
        $this->printStyles();

        echo '<div class="wpdev-card"><dl>';
        foreach ($this->serverAudit() as $label => $value) {
            printf('<dt>%s</dt><dd>%s</dd>', esc_html($label), $value);
        }
        echo '</dl></div>';
    }

    /**
     * gathers a short server/database audit: WordPress, PHP and database
     * versions plus the PHP limits most likely to bite a headless install.
     * flags the PHP version when it falls below the version WordPress
     * currently recommends.
     *
     * @return array label => html value
     */
    private function serverAudit()
    {
        global $wpdb;

        $recommended_php = '7.4';
        $php_ok = version_compare(PHP_VERSION, $recommended_php, '>=');
        $php = $php_ok
            ? '<code>' . esc_html(PHP_VERSION) . '</code>'
            : sprintf(
                '<code>%s</code> <span class="wpdev-warn">&#9888; below %s</span>',
                esc_html(PHP_VERSION),
                esc_html($recommended_php)
            );

        $db_version = $wpdb->get_var('SELECT VERSION()');
        $db_server = is_string($db_version) && stripos($db_version, 'MariaDB') !== false ? 'MariaDB' : 'MySQL';

        return [
            'WordPress' => '<code>' . esc_html(get_bloginfo('version')) . '</code>',
            'PHP' => $php,
            $db_server => '<code>' . esc_html($db_version ?: 'unknown') . '</code>',
            'Server' => '<code>' . esc_html($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . '</code>',
            'Memory limit' => '<code>' . esc_html(WP_MEMORY_LIMIT) . '</code>',
            'PHP memory_limit' => '<code>' . esc_html(ini_get('memory_limit')) . '</code>',
            'Max execution' => '<code>' . esc_html(ini_get('max_execution_time')) . 's</code>',
            'Upload max' => '<code>' . esc_html(ini_get('upload_max_filesize')) . '</code>',
            'Post max' => '<code>' . esc_html(ini_get('post_max_size')) . '</code>'
        ];
    }
}

new EnvironmentConfig();
