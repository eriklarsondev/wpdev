<?php
namespace wpdev;

class HardeningConfig
{
    /**
     * constructor for hardening configuration
     */
    public function __construct()
    {
        // block author enumeration via ?author=N
        add_action('init', [$this, 'blockAuthorEnumeration'], 1);

        // remove wp version fingerprint
        remove_action('wp_head', 'wp_generator');
        add_filter('the_generator', '__return_empty_string');
        add_filter('style_loader_src', [$this, 'stripCoreVersionQuery']);
        add_filter('script_loader_src', [$this, 'stripCoreVersionQuery']);

        // remove rss feed discovery link tags
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);

        // disable oembed for both producing and consuming
        remove_action('rest_api_init', 'wp_oembed_register_route');
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        add_filter('embed_oembed_discover', '__return_false');

        // remove rest api discovery link from head and headers
        remove_action('wp_head', 'rest_output_link_wp_head');
        remove_action('template_redirect', 'rest_output_link_header', 11);
    }

    /**
     * redirects ?author=N enumeration attempts to homepage before
     * the wordpress canonical redirect resolves them to /author/<slug>/
     *
     * @return void
     */
    public function blockAuthorEnumeration()
    {
        if (is_admin()) {
            return;
        }
        if (isset($_GET['author'])) {
            wp_redirect(home_url('/'), 301);
            exit();
        }
    }

    /**
     * strips the ?ver= query arg from asset urls when it matches the
     * wordpress core version, leaving plugin/theme versions intact
     *
     * @param string $src
     *
     * @return string
     */
    public function stripCoreVersionQuery($src)
    {
        global $wp_version;
        if (strpos($src, 'ver=' . $wp_version) !== false) {
            $src = remove_query_arg('ver', $src);
        }
        return $src;
    }
}

new HardeningConfig();
