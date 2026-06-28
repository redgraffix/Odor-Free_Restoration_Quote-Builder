<?php

if (!defined('ABSPATH')) {
    exit;
}

class OFQB_Shortcode
{
    public static function init()
    {
        add_shortcode('odorfree_quote_builder', array(__CLASS__, 'render'));
        add_filter('wp_robots', array(__CLASS__, 'add_noindex_for_quote_page'));
    }

    public static function add_noindex_for_quote_page($robots)
    {
        if (!is_singular()) {
            return $robots;
        }

        $post = get_post();

        if (!$post || !has_shortcode($post->post_content, 'odorfree_quote_builder')) {
            return $robots;
        }

        $robots['noindex'] = true;
        $robots['nofollow'] = true;
        $robots['noarchive'] = true;

        return $robots;
    }

    public static function render()
    {
        if (!is_user_logged_in()) {
            return '<div class="odorfree-quote-builder odorfree-quote-builder--notice">Please <a href="' . esc_url(wp_login_url(get_permalink())) . '">log in</a> to create a quote.</div>';
        }

        if (!OFQB_Roles::current_user_can_create_quotes()) {
            return '<div class="odorfree-quote-builder odorfree-quote-builder--notice">You do not have permission to access the quote builder.</div>';
        }

        self::enqueue_assets();

        $current_user = wp_get_current_user();
        $salesperson_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;
        $base_url = remove_query_arg(array('ofqb_view', 'ofqb_quote_id', 'ofqb_mode', 'ofqb_page', 'ofqb_search', 'ofqb_sort', 'ofqb_order', 'ofqb_created_by'));
        $quote_view = !empty($_GET['ofqb_view']) ? sanitize_text_field(wp_unslash($_GET['ofqb_view'])) : 'home';

        if ('create' === $quote_view) {
            ob_start();
            include OFQB_PLUGIN_DIR . 'templates/quote-form.php';
            return ob_get_clean();
        }

        if ('search' === $quote_view) {
            ob_start();
            include OFQB_PLUGIN_DIR . 'templates/quote-search-placeholder.php';
            return ob_get_clean();
        }

        if ('mine' === $quote_view) {
            $recent_quotes = OFQB_Quotes::get_recent_quotes(20);
            ob_start();
            include OFQB_PLUGIN_DIR . 'templates/my-quotes-placeholder.php';
            return ob_get_clean();
        }

        if ('admin' === $quote_view) {
            if (!current_user_can('manage_options')) {
                return '<div class="odorfree-quote-builder odorfree-quote-builder--notice">You do not have permission to access admin tools.</div>';
            }

            $quote_admin_stats = OFQB_Quotes::get_admin_stats();
            ob_start();
            include OFQB_PLUGIN_DIR . 'templates/admin-tools-placeholder.php';
            return ob_get_clean();
        }

        $recent_quotes = OFQB_Quotes::get_recent_quotes(5);

        ob_start();
        include OFQB_PLUGIN_DIR . 'templates/quote-home.php';
        return ob_get_clean();
    }

    private static function enqueue_assets()
    {
        $css_path = OFQB_PLUGIN_DIR . 'assets/quote-builder.css';
        $js_path = OFQB_PLUGIN_DIR . 'assets/quote-builder.js';

        wp_enqueue_style(
            'ofqb-quote-builder',
            OFQB_PLUGIN_URL . 'assets/quote-builder.css',
            array(),
            file_exists($css_path) ? filemtime($css_path) : OFQB_VERSION
        );

        wp_enqueue_script(
            'ofqb-quote-builder',
            OFQB_PLUGIN_URL . 'assets/quote-builder.js',
            array(),
            file_exists($js_path) ? filemtime($js_path) : OFQB_VERSION,
            true
        );
    }
}
