<?php
namespace wpdev;

class ApiKeyConfig
{
    const POST_TYPE = 'wpdev_api_key';
    const HASH_META = '_wpdev_api_key_hash';
    const PREFIX_META = '_wpdev_api_key_prefix';
    const LAST_USED_META = '_wpdev_api_key_last_used';
    const LOG_META = '_wpdev_api_key_log';
    const ACTIVE_META = '_wpdev_api_key_active';
    const SHOW_TRANSIENT = 'wpdev_api_key_plain_';
    const LOG_MAX = 20;
    const LOG_THROTTLE = 60;

    /**
     * constructor for the API key store: registers the custom post type used
     * to manage keys and the admin glue for generating and displaying them.
     */
    public function __construct()
    {
        add_action('init', [$this, 'registerType']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'ensureKey'], 10, 2);
        add_action('admin_notices', [$this, 'showNewKeyNotice']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'renderColumn'], 10, 2);
        add_action('add_meta_boxes', [$this, 'registerMetaBox']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'saveMetaBox'], 10, 1);
        add_action('edit_form_after_title', [$this, 'renderKeyPreview']);

        DashboardConfig::add_widget('wpdev_api_keys', 'API Keys', [$this, 'renderWidget'], 'normal');
    }

    /**
     * whether a key is active. keys are active unless explicitly disabled, so
     * keys created before the toggle existed remain valid.
     *
     * @param integer $post_id
     *
     * @return boolean
     */
    private function isActive($post_id)
    {
        return get_post_meta($post_id, self::ACTIVE_META, true) !== '0';
    }

    /**
     * registers the status meta box on the api key edit screen
     *
     * @return void
     */
    public function registerMetaBox()
    {
        add_meta_box('wpdev_api_key_status', 'API Key', [$this, 'renderMetaBox'], self::POST_TYPE, 'side', 'high');

        add_meta_box(
            'wpdev_api_key_activity',
            'Activity Log',
            [$this, 'renderActivityMetaBox'],
            self::POST_TYPE,
            'normal',
            'default'
        );
    }

    /**
     * renders the status dropdown and key identity in the meta box
     *
     * @param \WP_Post $post
     *
     * @return void
     */
    public function renderMetaBox($post)
    {
        wp_nonce_field('wpdev_api_key_meta', 'wpdev_api_key_meta_nonce');

        $active = $this->isActive($post->ID);
        ?>
        <p>
            <label for="wpdev_api_key_active"><strong>Status</strong></label>
            <select name="wpdev_api_key_active" id="wpdev_api_key_active" style="width:100%;margin-top:4px;">
                <option value="1" <?php selected($active, true); ?>>Active</option>
                <option value="0" <?php selected($active, false); ?>>Disabled</option>
            </select>
        </p>
        <?php
    }

    /**
     * renders the key prefix under the title, mirroring how the permalink/slug
     * row normally appears on post edit screens
     *
     * @param \WP_Post $post
     *
     * @return void
     */
    public function renderKeyPreview($post)
    {
        if ($post->post_type !== self::POST_TYPE) {
            return;
        }

        $prefix = get_post_meta($post->ID, self::PREFIX_META, true);
        ?>
        <div class="wpdev-key-preview" style="margin:12px 0;">
            <?php if ($prefix) { ?>
                <span style="color:#646970;">API Key:</span>
                <code style="background:#f6f7f7;padding:4px 10px;border-radius:3px;font-size:13px;"><?php echo esc_html(
                    $prefix
                ); ?>&hellip;</code>
                <span style="color:#a7aaad;font-size:12px;">shown in full once, on creation</span>
            <?php } else { ?>
                <span style="color:#646970;">The key is generated when you publish.</span>
            <?php } ?>
        </div>
        <?php
    }

    /**
     * renders the last-activity meta box
     *
     * @param \WP_Post $post
     *
     * @return void
     */
    public function renderActivityMetaBox($post)
    {
        $log = get_post_meta($post->ID, self::LOG_META, true);

        if (!is_array($log) || !$log) {
            echo '<p style="margin:0;color:#646970;">This key has never been used.</p>';
            return;
        }
        ?>
        <table class="wpdev-key-log" style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr>
                    <th style="text-align:left;padding:6px 0;border-bottom:1px solid #f0f0f1;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#787c82;">When</th>
                    <th style="text-align:left;padding:6px 0;border-bottom:1px solid #f0f0f1;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#787c82;">Method</th>
                    <th style="text-align:left;padding:6px 0;border-bottom:1px solid #f0f0f1;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#787c82;">Route</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($log as $entry) {
                printf(
                    '<tr>
                        <td style="padding:6px 12px 6px 0;white-space:nowrap;" title="%s">%s ago</td>
                        <td style="padding:6px 12px 6px 0;"><code>%s</code></td>
                        <td style="padding:6px 0;color:#50575e;word-break:break-all;">%s</td>
                    </tr>',
                    esc_attr(wp_date('M j, Y \a\t g:i a', (int) $entry['time'])),
                    esc_html(human_time_diff((int) $entry['time'])),
                    esc_html($entry['method'] ?: '&mdash;'),
                    esc_html($entry['route'] ?: '&mdash;')
                );
            } ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * persists the status dropdown from the meta box
     *
     * @param integer $post_id
     *
     * @return void
     */
    public function saveMetaBox($post_id)
    {
        if (
            !isset($_POST['wpdev_api_key_meta_nonce']) ||
            !wp_verify_nonce(sanitize_key($_POST['wpdev_api_key_meta_nonce']), 'wpdev_api_key_meta')
        ) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['wpdev_api_key_active'])) {
            update_post_meta($post_id, self::ACTIVE_META, $_POST['wpdev_api_key_active'] === '0' ? '0' : '1');
        }
    }

    /**
     * registers the admin-only api key post type. keys are never public and
     * never exposed through the REST API.
     *
     * @return void
     */
    public function registerType()
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => 'API Keys',
                'singular_name' => 'API Key',
                'add_new' => 'Add Key',
                'add_new_item' => 'Add API Key',
                'edit_item' => 'API Key',
                'menu_name' => 'API Keys'
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'menu_icon' => 'dashicons-admin-network',
            'menu_position' => 81,
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true
        ]);
    }

    /**
     * generates and stores a key the first time a key post is published. only
     * the SHA-256 hash and a short identifying prefix are persisted; the
     * plaintext is stashed in a short-lived transient so it can be shown to the
     * author exactly once.
     *
     * @param integer $post_id
     * @param \WP_Post $post
     *
     * @return void
     */
    public function ensureKey($post_id, $post)
    {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if ($post->post_status !== 'publish') {
            return;
        }
        if (get_post_meta($post_id, self::HASH_META, true)) {
            return;
        }

        $plain = 'wpdev_' . bin2hex(random_bytes(24));

        update_post_meta($post_id, self::HASH_META, hash('sha256', $plain));
        update_post_meta($post_id, self::PREFIX_META, substr($plain, 0, 12));
        update_post_meta($post_id, self::ACTIVE_META, '1');

        set_transient(self::SHOW_TRANSIENT . get_current_user_id(), $plain, 120);
    }

    /**
     * shows the freshly generated key once, on the api key screens
     *
     * @return void
     */
    public function showNewKeyNotice()
    {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== self::POST_TYPE) {
            return;
        }

        $plain = get_transient(self::SHOW_TRANSIENT . get_current_user_id());
        if (!$plain) {
            return;
        }
        delete_transient(self::SHOW_TRANSIENT . get_current_user_id());

        printf(
            '<div class="notice notice-success is-dismissible">
                <p><strong>API key created.</strong> Copy it now &mdash; it is stored hashed and will not be shown again.</p>
                <p><code style="user-select:all;font-size:14px;padding:8px 12px;display:inline-block;background:#f6f7f7;border-radius:4px;">%s</code></p>
            </div>',
            esc_html($plain)
        );
    }

    /**
     * adds key prefix and last-used columns to the list table
     *
     * @param array $columns
     *
     * @return array
     */
    public function columns($columns)
    {
        $result = [];
        foreach ($columns as $key => $label) {
            $result[$key] = $label;
            if ($key === 'title') {
                $result['wpdev_prefix'] = 'Key';
                $result['wpdev_status'] = 'Status';
                $result['wpdev_last_used'] = 'Last used';
            }
        }
        return $result;
    }

    /**
     * renders the custom column values
     *
     * @param string $column
     * @param integer $post_id
     *
     * @return void
     */
    public function renderColumn($column, $post_id)
    {
        if ($column === 'wpdev_prefix') {
            $prefix = get_post_meta($post_id, self::PREFIX_META, true);
            echo $prefix ? '<code>' . esc_html($prefix) . '&hellip;</code>' : '&mdash;';
        } elseif ($column === 'wpdev_status') {
            $active = $this->isActive($post_id);
            printf(
                '<span style="color:%s;font-weight:600;">%s</span>',
                $active ? '#007017' : '#8c8f94',
                $active ? 'Active' : 'Disabled'
            );
        } elseif ($column === 'wpdev_last_used') {
            $last = (int) get_post_meta($post_id, self::LAST_USED_META, true);
            echo $last ? esc_html(human_time_diff($last) . ' ago') : '<span style="color:#a7aaad;">never</span>';
        }
    }

    /**
     * renders the dashboard card: each key with its active state and last
     * activity
     *
     * @return void
     */
    public function renderWidget()
    {
        $keys = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'numberposts' => 25,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        if (!$keys) {
            printf(
                '<p style="margin:0;color:#646970;">No API keys yet. <a href="%s">Create one</a> to let external apps read the API.</p>',
                esc_url(admin_url('post-new.php?post_type=' . self::POST_TYPE))
            );
            return;
        }
        ?>
        <style>
            .wpdev-keys { width:100%; border-collapse:collapse; font-size:13px; }
            .wpdev-keys th, .wpdev-keys td { text-align:left; padding:8px 0; border-top:1px solid #f0f0f1; }
            .wpdev-keys thead th { border-top:0; font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#787c82; }
            .wpdev-keys .right { text-align:right; }
            .wpdev-keys code { background:#f6f7f7; padding:1px 6px; border-radius:3px; font-size:12px; }
            .wpdev-key-status { display:inline-flex; align-items:center; gap:6px; font-weight:600; }
            .wpdev-key-status .dot { width:8px; height:8px; border-radius:50%; }
            .wpdev-key-status.active { color:#007017; }
            .wpdev-key-status.active .dot { background:#00a32a; }
            .wpdev-key-status.inactive { color:#8c8f94; }
            .wpdev-key-status.inactive .dot { background:#c3c4c7; }
        </style>
        <table class="wpdev-keys">
            <thead>
                <tr><th>Name</th><th>Key</th><th>Status</th><th class="right">Last activity</th></tr>
            </thead>
            <tbody>
        <?php foreach ($keys as $key) {
            $active = $this->isActive($key->ID);
            $prefix = get_post_meta($key->ID, self::PREFIX_META, true);
            $last = (int) get_post_meta($key->ID, self::LAST_USED_META, true);
            printf(
                '<tr>
                    <td><a href="%s">%s</a></td>
                    <td>%s</td>
                    <td><span class="wpdev-key-status %s"><span class="dot"></span>%s</span></td>
                    <td class="right">%s</td>
                </tr>',
                esc_url(get_edit_post_link($key->ID)),
                esc_html(get_the_title($key) ?: '(untitled)'),
                $prefix ? '<code>' . esc_html($prefix) . '&hellip;</code>' : '&mdash;',
                $active ? 'active' : 'inactive',
                $active ? 'Active' : 'Inactive',
                $last ? esc_html(human_time_diff($last) . ' ago') : '<span style="color:#a7aaad;">never</span>'
            );
        } ?>
            </tbody>
        </table>
        <?php printf(
            '<p style="margin:10px 0 0;text-align:right;"><a href="%s">Manage Keys &rarr;</a></p>',
            esc_url(admin_url('edit.php?post_type=' . self::POST_TYPE))
        );
    }

    /**
     * validates a presented plaintext key against the stored hashes. on a
     * match the key's last-used timestamp is refreshed.
     *
     * @param string $plain
     *
     * @return boolean
     */
    public static function validate($plain)
    {
        if (!$plain) {
            return false;
        }

        $query = new \WP_Query([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                [
                    'key' => self::HASH_META,
                    'value' => hash('sha256', $plain)
                ]
            ]
        ]);

        if (empty($query->posts)) {
            return false;
        }

        $id = $query->posts[0];
        if (get_post_meta($id, self::ACTIVE_META, true) === '0') {
            return false;
        }

        self::recordUse($id);
        return true;
    }

    /**
     * appends the current request to the key's rolling activity log (trimmed
     * to LOG_MAX entries) and updates the most-recent-use pointer used by the
     * dashboard card and list table.
     *
     * @param integer $id
     *
     * @return void
     */
    private static function recordUse($id)
    {
        $log = get_post_meta($id, self::LOG_META, true);
        if (!is_array($log)) {
            $log = [];
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
        $route = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(strtok(wp_unslash($_SERVER['REQUEST_URI']), '?'))
            : '';

        // collapse repeat hits to the same endpoint within the throttle window
        // so a polling / high-traffic key does not flood the log or the db
        $window = (int) apply_filters('wpdev_api_key_log_throttle', self::LOG_THROTTLE);
        if (
            $window > 0 &&
            !empty($log) &&
            ($log[0]['method'] ?? '') === $method &&
            ($log[0]['route'] ?? '') === $route &&
            time() - (int) ($log[0]['time'] ?? 0) < $window
        ) {
            return;
        }

        array_unshift($log, [
            'time' => time(),
            'method' => $method,
            'route' => $route
        ]);

        update_post_meta($id, self::LOG_META, array_slice($log, 0, self::LOG_MAX));
        update_post_meta($id, self::LAST_USED_META, time());
    }
}

new ApiKeyConfig();
