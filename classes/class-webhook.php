<?php
namespace wpdev;

class WebhookConfig
{
    /**
     * constructor for webhook configuration
     */
    public function __construct()
    {
        add_action('transition_post_status', [$this, 'onPostTransition'], 10, 3);
    }

    /**
     * fires the revalidation webhook when a post enters or leaves the
     * published state, ignoring drafts saving as drafts
     *
     * @param string $new_status
     * @param string $old_status
     * @param \WP_Post $post
     *
     * @return void
     */
    public function onPostTransition($new_status, $old_status, $post)
    {
        if (in_array($post->post_type, ['revision', 'auto-draft', 'nav_menu_item'], true)) {
            return;
        }

        // only notify for public, front-end-facing content (skips internal
        // post types such as the api key store)
        $type = get_post_type_object($post->post_type);
        if (!$type || !$type->public) {
            return;
        }

        if ($new_status !== 'publish' && $old_status !== 'publish') {
            return;
        }

        $this->dispatch($post);
    }

    /**
     * sends a non-blocking post request to the configured frontend url
     *
     * @param \WP_Post $post
     *
     * @return void
     */
    private function dispatch($post)
    {
        $url = get_theme_mod('wpdev_frontend_url');
        if (!$url) {
            return;
        }

        $path = apply_filters('wpdev_webhook_path', '/api/revalidate');

        $secret = defined('WPDEV_WEBHOOK_SECRET') ? WPDEV_WEBHOOK_SECRET : '';
        $secret = apply_filters('wpdev_webhook_secret', $secret);

        $payload = apply_filters(
            'wpdev_webhook_payload',
            [
                'id' => (int) $post->ID,
                'slug' => $post->post_name,
                'type' => $post->post_type,
                'status' => $post->post_status
            ],
            $post
        );

        wp_remote_post(rtrim($url, '/') . $path, [
            'timeout' => 5,
            'blocking' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Webhook-Secret' => $secret
            ],
            'body' => wp_json_encode($payload)
        ]);
    }
}

new WebhookConfig();
