<?php
namespace wpdev;

class AssetConfig
{
    private $manifest = null;

    /**
     * constructor for asset configuration
     *
     * @param boolean $static
     */
    public function __construct($static = false) {}

    /**
     * loads and caches the vite manifest from the dist directory
     *
     * @return array
     */
    private function loadManifest()
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $path = dirname(__DIR__) . '/dist/.vite/manifest.json';
        if (!file_exists($path)) {
            $this->manifest = [];
            return $this->manifest;
        }

        $this->manifest = json_decode(file_get_contents($path), true) ?: [];
        return $this->manifest;
    }

    /**
     * resolves the hashed urls for a vite entry's js and css files
     *
     * @param string $entry
     *
     * @return array
     */
    private function resolveUrls($entry)
    {
        $manifest = $this->loadManifest();
        if (!isset($manifest[$entry])) {
            return ['js' => null, 'css' => []];
        }

        $base = get_template_directory_uri() . '/dist/';
        $js = $base . $manifest[$entry]['file'];

        $css = [];
        if (!empty($manifest[$entry]['css'])) {
            foreach ($manifest[$entry]['css'] as $css_file) {
                $css[] = $base . $css_file;
            }
        }

        return ['js' => $js, 'css' => $css];
    }

    /**
     * enqueues the js and css for a vite entry
     *
     * @param string $handle
     * @param string $entry
     *
     * @return void
     */
    private function enqueueAsset($handle, $entry)
    {
        $urls = $this->resolveUrls($entry);

        if ($urls['js']) {
            wp_enqueue_script($handle, $urls['js'], [], null, true);
        }

        foreach ($urls['css'] as $i => $css_url) {
            $css_handle = $i === 0 ? $handle : "{$handle}-{$i}";
            wp_enqueue_style($css_handle, $css_url, [], null);
        }
    }

    /**
     * static wrapper for enqueuing a vite entry on a given hook
     *
     * @param string $handle
     * @param string $entry
     * @param string $hook
     *
     * @return void
     */
    static function enqueue($handle, $entry, $hook = 'wp_enqueue_scripts')
    {
        $instance = new self(true);
        add_action($hook, function () use ($instance, $handle, $entry) {
            $instance->enqueueAsset($handle, $entry);
        });
    }
}

new AssetConfig();
