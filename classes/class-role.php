<?php
namespace wpdev;

class RoleConfig extends Base
{
    /**
     * constructor for role configuration
     *
     * @param boolean $static
     */
    public function __construct($static = false)
    {
        if (!$static) {
            add_action('after_switch_theme', [$this, 'initRoles']);
        }
    }

    /**
     * initializes default theme roles
     *
     * @return void
     */
    public function initRoles()
    {
        $this->registerRole('content editor', 'Content Editor', [
            'read' => true,
            'upload_files' => true,
            'edit_posts' => true,
            'edit_others_posts' => true,
            'edit_published_posts' => true,
            'publish_posts' => true,
            'delete_posts' => true,
            'delete_others_posts' => true,
            'delete_published_posts' => true,
            'edit_pages' => true,
            'edit_others_pages' => true,
            'edit_published_pages' => true,
            'publish_pages' => true,
            'delete_pages' => true,
            'delete_others_pages' => true,
            'delete_published_pages' => true,
            'manage_categories' => true
        ]);
    }

    /**
     * adds a new role with the provided capability map
     *
     * @param string $role_name
     * @param string $display_name
     * @param array $caps
     *
     * @return void
     */
    private function registerRole($role_name, $display_name, $caps)
    {
        $key = parent::formatLabel($role_name, '_', false);
        add_role($key, $display_name, $caps);
    }

    /**
     * removes an existing role
     *
     * @param string $role_name
     *
     * @return void
     */
    private function unregisterRole($role_name)
    {
        $key = parent::formatLabel($role_name, '_', false);
        remove_role($key);
    }

    /**
     * static wrapper for adding a new role
     *
     * @param string $role_name
     * @param string $display_name
     * @param array $caps
     *
     * @return void
     */
    static function add_role($role_name, $display_name, $caps)
    {
        $instance = new self(true);
        add_action('after_switch_theme', function () use ($instance, $role_name, $display_name, $caps) {
            $instance->registerRole($role_name, $display_name, $caps);
        });
    }

    /**
     * static wrapper for removing an existing role
     *
     * @param string $role_name
     *
     * @return void
     */
    static function remove_role($role_name)
    {
        $instance = new self(true);
        add_action('after_switch_theme', function () use ($instance, $role_name) {
            $instance->unregisterRole($role_name);
        });
    }
}

new RoleConfig();
