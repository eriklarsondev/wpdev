<?php
namespace wpdev;

class ImageConfig
{
    /**
     * constructor for image configuration
     */
    public function __construct()
    {
        add_filter('intermediate_image_sizes_advanced', [$this, 'removeDefaultSizes']);
    }

    /**
     * removes default intermediate image sizes that headless frontends
     * typically regenerate on their own (next/image, cdn, etc.)
     *
     * thumbnail is preserved for the wp media library admin previews
     *
     * @param array $sizes
     *
     * @return array
     */
    public function removeDefaultSizes($sizes)
    {
        $remove = ['medium', 'medium_large', 'large', '1536x1536', '2048x2048'];
        foreach ($remove as $size) {
            unset($sizes[$size]);
        }
        return $sizes;
    }
}

new ImageConfig();
