<?php

if (!defined('ABSPATH')) {
    exit;
}

class OFQB_Roles
{
    const CAPABILITY = 'ofqb_create_quotes';
    const ROLE = 'ofqb_salesperson';

    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'simplify_salesperson_admin'), 999);
        add_action('admin_menu', array(__CLASS__, 'add_quote_builder_admin_link'));
        add_action('admin_init', array(__CLASS__, 'redirect_salesperson_dashboard'));
    }

    public static function activate()
    {
        add_role(
            self::ROLE,
            'Quote Salesperson',
            array(
                'read' => true,
                'read_private_pages' => true,
                self::CAPABILITY => true,
            )
        );

        self::add_cap_to_role('administrator');
        self::add_cap_to_role(self::ROLE);
    }

    public static function current_user_can_create_quotes()
    {
        return current_user_can(self::CAPABILITY);
    }

    public static function simplify_salesperson_admin()
    {
        if (!self::is_current_user_salesperson_only()) {
            return;
        }

        global $menu;
        $allowed = array('profile.php', 'ofqb-quote-builder-link');

        foreach ($menu as $item) {
            if (empty($item[2]) || in_array($item[2], $allowed, true)) {
                continue;
            }

            remove_menu_page($item[2]);
        }
    }

    public static function add_quote_builder_admin_link()
    {
        if (!self::is_current_user_salesperson_only()) {
            return;
        }

        add_menu_page(
            'Quote Builder',
            'Quote Builder',
            self::CAPABILITY,
            'ofqb-quote-builder-link',
            array(__CLASS__, 'render_quote_builder_admin_link'),
            'dashicons-media-document',
            2
        );
    }

    public static function render_quote_builder_admin_link()
    {
        $quote_url = self::get_quote_builder_url();
        ?>
        <div class="wrap">
            <h1>Quote Builder</h1>
            <p>Use the button below to open the Odor-Free Restoration quote builder.</p>
            <p><a class="button button-primary" href="<?php echo esc_url($quote_url); ?>">Open Quote Builder</a></p>
            <p><a href="<?php echo esc_url(admin_url('profile.php')); ?>">Manage your profile</a></p>
        </div>
        <?php
    }

    public static function redirect_salesperson_dashboard()
    {
        if (!self::is_current_user_salesperson_only()) {
            return;
        }

        $current_page = isset($GLOBALS['pagenow']) ? $GLOBALS['pagenow'] : '';

        if ('index.php' === $current_page) {
            wp_safe_redirect(admin_url('profile.php'));
            exit;
        }
    }

    private static function add_cap_to_role($role_name)
    {
        $role = get_role($role_name);

        if ($role) {
            $role->add_cap(self::CAPABILITY);
        }
    }

    private static function is_current_user_salesperson_only()
    {
        $user = wp_get_current_user();

        if (!$user || empty($user->roles)) {
            return false;
        }

        return in_array(self::ROLE, (array) $user->roles, true) && !current_user_can('manage_options');
    }

    private static function get_quote_builder_url()
    {
        $pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => array('publish', 'private'),
            'posts_per_page' => 10,
            's' => 'odorfree_quote_builder',
        ));

        foreach ($pages as $page) {
            if (has_shortcode($page->post_content, 'odorfree_quote_builder')) {
                return get_permalink($page);
            }
        }

        return home_url('/');
    }
}
