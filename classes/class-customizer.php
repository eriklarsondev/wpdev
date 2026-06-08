<?php
namespace wpdev;

class CustomizerConfig extends Base
{
    /**
     * constructor for customizer configuration
     *
     * @param boolean $static
     */
    public function __construct($static = false)
    {
        if (!$static) {
            add_action('customize_register', [$this, 'initCustomizer']);
        }
    }

    /**
     * initializes default customizer panels, sections, and options
     *
     * @param \WP_Customize_Manager $wp_customize
     *
     * @return void
     */
    public function initCustomizer($wp_customize)
    {
        $this->registerSection($wp_customize, 'wpdev', '', 'Configuration for the decoupled frontend.', 'wpdev');
        $this->registerOption($wp_customize, 'frontend URL', 'wpdev', [
            'type' => 'url',
            'description' =>
                'The URL of the decoupled frontend application. Used as the allowed CORS origin for REST API requests in production.',
            'sanitize_callback' => 'esc_url_raw'
        ]);
    }

    /**
     * adds a new customizer panel
     *
     * @param \WP_Customize_Manager $wp_customize
     * @param string $panel_name
     * @param string $description
     *
     * @return void
     */
    private function registerPanel($wp_customize, $panel_name, $description = '')
    {
        $key = parent::formatLabel($panel_name);
        $label = ucwords($panel_name);

        $wp_customize->add_panel($key, [
            'title' => $label,
            'description' => $description
        ]);
    }

    /**
     * adds a new customizer section, optionally nested inside a panel
     *
     * @param \WP_Customize_Manager $wp_customize
     * @param string $section_name
     * @param string $panel_name
     * @param string $description
     * @param string|null $label
     *
     * @return void
     */
    private function registerSection($wp_customize, $section_name, $panel_name = '', $description = '', $label = null)
    {
        $key = parent::formatLabel($section_name);
        $label = $label !== null ? $label : ucwords($section_name);

        $args = [
            'title' => $label,
            'description' => $description
        ];

        if ($panel_name) {
            $args['panel'] = parent::formatLabel($panel_name);
        }

        $wp_customize->add_section($key, $args);
    }

    /**
     * adds a new customizer setting and its bound control
     *
     * @param \WP_Customize_Manager $wp_customize
     * @param string $option_name
     * @param string $section_name
     * @param array $args
     *
     * @return void
     */
    private function registerOption($wp_customize, $option_name, $section_name, $args = [])
    {
        $key = parent::formatLabel($option_name);
        $label = ucwords($option_name);
        $section = parent::formatLabel($section_name);

        $wp_customize->add_setting($key, [
            'default' => $args['default'] ?? '',
            'sanitize_callback' => $args['sanitize_callback'] ?? 'sanitize_text_field',
            'transport' => $args['transport'] ?? 'refresh'
        ]);

        $wp_customize->add_control($key, [
            'label' => $label,
            'section' => $section,
            'type' => $args['type'] ?? 'text',
            'description' => $args['description'] ?? ''
        ]);
    }

    /**
     * static wrapper for adding a new customizer panel
     *
     * @param string $panel_name
     * @param string $description
     *
     * @return void
     */
    static function add_panel($panel_name, $description = '')
    {
        $instance = new self(true);
        add_action('customize_register', function ($wp_customize) use ($instance, $panel_name, $description) {
            $instance->registerPanel($wp_customize, $panel_name, $description);
        });
    }

    /**
     * static wrapper for adding a new customizer section
     *
     * @param string $section_name
     * @param string $panel_name
     * @param string $description
     * @param string|null $label
     *
     * @return void
     */
    static function add_section($section_name, $panel_name = '', $description = '', $label = null)
    {
        $instance = new self(true);
        add_action('customize_register', function ($wp_customize) use (
            $instance,
            $section_name,
            $panel_name,
            $description,
            $label
        ) {
            $instance->registerSection($wp_customize, $section_name, $panel_name, $description, $label);
        });
    }

    /**
     * static wrapper for adding a new customizer option
     *
     * @param string $option_name
     * @param string $section_name
     * @param array $args
     *
     * @return void
     */
    static function add_option($option_name, $section_name, $args = [])
    {
        $instance = new self(true);
        add_action('customize_register', function ($wp_customize) use ($instance, $option_name, $section_name, $args) {
            $instance->registerOption($wp_customize, $option_name, $section_name, $args);
        });
    }
}

new CustomizerConfig();
