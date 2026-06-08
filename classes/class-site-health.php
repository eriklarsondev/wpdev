<?php
namespace wpdev;

class SiteHealthConfig
{
    /**
     * constructor for site health dashboard widget. swaps the default
     * "Site Health Status" widget for one that actually runs the direct
     * health tests on page load.
     */
    public function __construct()
    {
        DashboardConfig::remove_widget('dashboard_site_health', 'normal');
        DashboardConfig::add_widget(
            'wpdev_site_health',
            'Site Health',
            [$this, 'renderWidget']
        );
    }

    /**
     * resolves the status counts shown on the widget. the Site Health report
     * stores its complete {good, recommended, critical} tally (direct + async
     * tests) in the health-check-site-status-result transient, so that is the
     * authoritative source and is used verbatim to keep the pills in sync with
     * the full report. when the report has never been run the transient is
     * absent, so we fall back to the synchronous direct tests only and flag the
     * result as incomplete.
     *
     * @return array [counts, issues, complete]
     */
    private function gatherResults()
    {
        [$direct_counts, $issues] = $this->runDirectTests();

        $cached = get_transient('health-check-site-status-result');
        $decoded = $cached ? json_decode($cached, true) : null;

        if (is_array($decoded)) {
            $counts = [
                'good' => (int) ($decoded['good'] ?? 0),
                'recommended' => (int) ($decoded['recommended'] ?? 0),
                'critical' => (int) ($decoded['critical'] ?? 0)
            ];

            return [$counts, $issues, true];
        }

        return [$direct_counts, $issues, false];
    }

    /**
     * runs every direct (synchronous) site-health test, returning the status
     * counts and the labels of any non-good results. used for the issue list
     * and as a fallback tally before the full report has run.
     *
     * @return array [counts, issues]
     */
    private function runDirectTests()
    {
        if (!class_exists('WP_Site_Health')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
        }

        $instance = \WP_Site_Health::get_instance();
        $tests = \WP_Site_Health::get_tests();

        $counts = ['good' => 0, 'recommended' => 0, 'critical' => 0];
        $issues = [];

        foreach ($tests['direct'] as $test) {
            if (empty($test['test'])) {
                continue;
            }

            $callback = is_string($test['test'])
                ? [$instance, 'get_test_' . $test['test']]
                : $test['test'];

            if (!is_callable($callback)) {
                continue;
            }

            $result = call_user_func($callback);
            if (!is_array($result) || empty($result['status'])) {
                continue;
            }

            $status = $result['status'];
            if (isset($counts[$status])) {
                $counts[$status]++;
            }

            if ($status !== 'good') {
                $issues[] = [
                    'label' => $result['label'] ?? '',
                    'status' => $status
                ];
            }
        }

        return [$counts, $issues];
    }

    /**
     * mirrors the percentage the Site Health report's progress ring uses, so
     * the widget score matches: critical issues are weighted 1.5x and
     * recommended 0.5x against the total.
     *
     * @param array $counts
     *
     * @return integer 0-100
     */
    private function gradePercent($counts)
    {
        $total = $counts['good'] + $counts['recommended'] + ($counts['critical'] * 1.5);
        if ($total <= 0) {
            return 100;
        }

        $failed = ($counts['recommended'] * 0.5) + ($counts['critical'] * 1.5);

        return max(0, 100 - (int) round(($failed / $total) * 100));
    }

    /**
     * renders the widget with three colored count badges, a short list of
     * outstanding issues, and a link to the full Site Health report
     *
     * @return void
     */
    public function renderWidget()
    {
        [$counts, $issues, $complete] = $this->gatherResults();
        $percent = $this->gradePercent($counts);

        echo '<style>
            .wpdev-health-meta { display:flex; gap:6px; flex-wrap:wrap; margin:0 0 10px; align-items:center; }
            .wpdev-health-score { font-size:18px; font-weight:600; margin-right:8px; }
            .wpdev-health-badge { font-size:11px; padding:3px 8px; border-radius:10px; font-weight:600; }
            .wpdev-health-badge.good { background:#edfaef; color:#00a32a; }
            .wpdev-health-badge.recommended { background:#fcf0e0; color:#bd8600; }
            .wpdev-health-badge.critical { background:#fbeaea; color:#d63638; }
            .wpdev-health-issues { margin:0 0 10px; padding:0; list-style:none; max-height:160px; overflow:auto; }
            .wpdev-health-issues li { padding:6px 0; border-top:1px solid #f0f0f1; font-size:12px; display:flex; align-items:flex-start; gap:8px; }
            .wpdev-health-issues li:first-child { border-top:0; }
            .wpdev-health-issues li .dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:5px; }
            .wpdev-health-issues li.critical .dot { background:#d63638; }
            .wpdev-health-issues li.recommended .dot { background:#bd8600; }
            .wpdev-health-empty { color:#646970; font-size:12px; margin:0 0 10px; }
            .wpdev-health-more { color:#646970; font-size:12px; margin:0 0 10px; }
            .wpdev-health-note { color:#8c8f94; font-size:11px; margin:0 0 10px; }
        </style>';

        printf(
            '<div class="wpdev-health-meta">
                <span class="wpdev-health-score">%d%%</span>
                <span class="wpdev-health-badge good">%d good</span>
                <span class="wpdev-health-badge recommended">%d to review</span>
                <span class="wpdev-health-badge critical">%d critical</span>
            </div>',
            (int) $percent,
            (int) $counts['good'],
            (int) $counts['recommended'],
            (int) $counts['critical']
        );

        $displayed = array_slice($issues, 0, 6);
        $non_good = $counts['recommended'] + $counts['critical'];
        $hidden = max(0, $non_good - count($displayed));

        if (!empty($displayed)) {
            echo '<ul class="wpdev-health-issues">';
            foreach ($displayed as $issue) {
                printf(
                    '<li class="%s"><span class="dot"></span>%s</li>',
                    esc_attr($issue['status']),
                    esc_html($issue['label'])
                );
            }
            echo '</ul>';
        }

        if ($hidden > 0) {
            printf('<p class="wpdev-health-more">+%d more in the full report</p>', (int) $hidden);
        } elseif (empty($displayed)) {
            echo '<p class="wpdev-health-empty">No issues detected.</p>';
        }

        if (!$complete) {
            echo '<p class="wpdev-health-note">Async checks haven\'t run yet — open the full report for complete results.</p>';
        }

        printf(
            '<p style="margin:0;text-align:right;"><a href="%s">View full report &rarr;</a></p>',
            esc_url(admin_url('site-health.php'))
        );
    }
}

new SiteHealthConfig();
