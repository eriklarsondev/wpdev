<?php
namespace wpdev;

class AppConfig
{
    /**
     * registers the external-app dashboard card. placement is governed by
     * DashboardConfig's locked-column order.
     */
    public function __construct()
    {
        DashboardConfig::add_widget('wpdev_frontend', 'External App', [$this, 'renderWidget'], 'column3');
    }

    /**
     * styles for the external-app card. printed once per request via a
     * static guard.
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
            .wpdev-frontend {
                margin:0;
                display:flex;
                align-items:stretch;
                gap:10px;
            }
            .wpdev-frontend-box {
                flex:1;
                min-width:0;
                display:flex;
                align-items:center;
                gap:8px;
                padding:6px 10px;
                background:#f6f7f7;
                border-radius:4px;
            }
            .wpdev-frontend-box .dashicons {
                color:#787c82;
                font-size:16px;
                width:16px;
                height:16px;
                flex-shrink:0;
            }
            .wpdev-frontend-meta { min-width:0; }
            .wpdev-frontend-eyebrow {
                display:block;
                font-size:9px;
                font-weight:600;
                letter-spacing:.06em;
                text-transform:uppercase;
                color:#787c82;
            }
            .wpdev-frontend-link {
                display:block;
                font-size:12px;
                font-weight:600;
                color:#8c8f94;
                text-decoration:none;
                white-space:nowrap;
                overflow:hidden;
                text-overflow:ellipsis;
            }
            .wpdev-frontend-link:hover { color:#646970; }
            .wpdev-frontend-empty { font-size:12px; color:#8c8f94; }
            .wpdev-frontend-btn {
                display:inline-flex;
                align-items:center;
                justify-content:center;
                gap:6px;
                flex-shrink:0;
                white-space:nowrap;
                box-sizing:border-box;
                padding:0 16px;
                font-size:13px;
                font-weight:600;
                line-height:1;
                text-decoration:none;
                color:#fff;
                background:#2271b1;
                border-radius:4px;
                transition:background .15s ease;
            }
            .wpdev-frontend-btn:hover,
            .wpdev-frontend-btn:focus { color:#fff; background:#135e96; }
            .wpdev-frontend-btn .dashicons {
                font-size:16px;
                width:16px;
                height:16px;
            }
        </style>
        <?php
    }

    /**
     * renders the external-app card: the configured headless app URL with a
     * button that opens it in a new tab, or a prompt to configure it when no
     * URL has been set yet
     *
     * @return void
     */
    public function renderWidget()
    {
        $this->printStyles();

        $frontend = get_theme_mod('wpdev_frontend_url');

        echo '<div class="wpdev-frontend">';
        if ($frontend) {
            printf(
                '<div class="wpdev-frontend-box">
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                    <span class="wpdev-frontend-meta">
                        <span class="wpdev-frontend-eyebrow">External App</span>
                        <a class="wpdev-frontend-link" href="%1$s" target="_blank" rel="noopener">%2$s</a>
                    </span>
                </div>
                <a href="%1$s" target="_blank" rel="noopener" class="wpdev-frontend-btn">Open <span class="dashicons dashicons-external"></span></a>',
                esc_url($frontend),
                esc_html(preg_replace('#^https?://#', '', untrailingslashit($frontend)))
            );
        } else {
            printf(
                '<div class="wpdev-frontend-box">
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                    <span class="wpdev-frontend-meta">
                        <span class="wpdev-frontend-eyebrow">External App</span>
                        <span class="wpdev-frontend-empty">Not Configured</span>
                    </span>
                </div>
                <a href="%s" class="wpdev-frontend-btn">Configure URL</a>',
                esc_url(admin_url('customize.php?autofocus[control]=wpdev_frontend_url'))
            );
        }
        echo '</div>';
    }
}

new AppConfig();
