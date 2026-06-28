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
        $submission_result = OFQB_Quotes::maybe_handle_submission();

        if ($submission_result && !is_wp_error($submission_result)) {
            $saved_quote = $submission_result['quote'];
            $saved_services = $submission_result['services'];
            $saved_materials = $submission_result['materials'];
            $show_saved_notice = true;

            if ('draft' === $saved_quote->status) {
                $editing_quote = $saved_quote;
                $editing_services = $saved_services;
                $editing_materials = $saved_materials;
                $quote_action_notice = 'Draft ' . $saved_quote->quote_number . ' was saved.';
                $quote_error = '';

                ob_start();
                include OFQB_PLUGIN_DIR . 'templates/quote-form.php';
                return ob_get_clean();
            }

            ob_start();
            include OFQB_PLUGIN_DIR . 'templates/quote-preview.php';
            return ob_get_clean();
        }

        if (!empty($_GET['ofqb_quote_id'])) {
            $quote_id = absint(wp_unslash($_GET['ofqb_quote_id']));
            $quote_action_result = OFQB_Quotes::maybe_handle_quote_action($quote_id);
            $quote_action_error = is_wp_error($quote_action_result) ? $quote_action_result->get_error_message() : '';
            $quote_action_notice = '';
            $loaded_quote = OFQB_Quotes::get_quote_with_items($quote_id);

            if ($loaded_quote) {
                $saved_quote = $loaded_quote['quote'];
                $saved_services = $loaded_quote['services'];
                $saved_materials = $loaded_quote['materials'];

                if (!OFQB_Quotes::current_user_can_modify_quote($saved_quote)) {
                    return '<div class="odorfree-quote-builder odorfree-quote-builder--notice">You can only view quotes you created.</div>';
                }

                $creator = get_userdata((int) $saved_quote->created_by_user_id);
                $salesperson_name = $creator ? $creator->display_name : $salesperson_name;

                if (true === $quote_action_result) {
                    $completed_action = !empty($_POST['ofqb_action']) ? sanitize_key(wp_unslash($_POST['ofqb_action'])) : '';

                    if ('update_status' === $completed_action) {
                        $quote_action_notice = 'Quote ' . $saved_quote->quote_number . ' status was updated.';
                    } else {
                        $quote_action_notice = 'deleted' === $saved_quote->status ? 'Quote ' . $saved_quote->quote_number . ' was deleted.' : 'Quote ' . $saved_quote->quote_number . ' was restored.';
                    }
                }

                $should_edit_quote = (!empty($_GET['ofqb_mode']) && 'revise' === sanitize_text_field(wp_unslash($_GET['ofqb_mode']))) || 'draft' === $saved_quote->status;

                if ($should_edit_quote) {
                    $editing_quote = $saved_quote;
                    $editing_services = $saved_services;
                    $editing_materials = $saved_materials;
                    $quote_error = is_wp_error($submission_result) ? $submission_result->get_error_message() : '';

                    ob_start();
                    include OFQB_PLUGIN_DIR . 'templates/quote-form.php';
                    return ob_get_clean();
                }

                ob_start();
                include OFQB_PLUGIN_DIR . 'templates/quote-preview.php';
                return ob_get_clean();
            }
        }

        $quote_error = is_wp_error($submission_result) ? $submission_result->get_error_message() : '';
        $quote_view = !empty($_GET['ofqb_view']) ? sanitize_text_field(wp_unslash($_GET['ofqb_view'])) : 'home';

        if ('create' === $quote_view) {
            ob_start();
            include OFQB_PLUGIN_DIR . 'templates/quote-form.php';
            return ob_get_clean();
        }

        if ('search' === $quote_view) {
            $quote_list_context = 'search';
            $quote_list_result = OFQB_Quotes::get_quote_list(self::get_quote_list_args());
            $quote_creators = OFQB_Quotes::get_quote_creators();

            ob_start();
            include OFQB_PLUGIN_DIR . 'templates/quote-search.php';
            return ob_get_clean();
        }

        if ('deleted' === $quote_view) {
            $quote_list_context = 'deleted';
            $quote_list_result = OFQB_Quotes::get_quote_list(
                array_merge(
                    self::get_quote_list_args(),
                    array('status_filter' => 'deleted')
                )
            );
            $quote_creators = OFQB_Quotes::get_quote_creators('deleted');

            ob_start();
            include OFQB_PLUGIN_DIR . 'templates/quote-search.php';
            return ob_get_clean();
        }

        if ('mine' === $quote_view) {
            $quote_list_context = 'mine';
            $quote_list_result = OFQB_Quotes::get_quote_list(
                array_merge(
                    self::get_quote_list_args(),
                    array('created_by_user_id' => get_current_user_id())
                )
            );

            ob_start();
            include OFQB_PLUGIN_DIR . 'templates/my-quotes.php';
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

        wp_localize_script(
            'ofqb-quote-builder',
            'ofqbSettings',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
            )
        );
    }

    private static function get_quote_list_args()
    {
        $sort = !empty($_GET['ofqb_sort']) ? sanitize_key(wp_unslash($_GET['ofqb_sort'])) : 'date';
        $order = !empty($_GET['ofqb_order']) ? sanitize_key(wp_unslash($_GET['ofqb_order'])) : 'desc';

        return array(
            'search' => !empty($_GET['ofqb_search']) ? sanitize_text_field(wp_unslash($_GET['ofqb_search'])) : '',
            'sort' => $sort,
            'order' => 'asc' === $order ? 'asc' : 'desc',
            'created_by_filter' => !empty($_GET['ofqb_created_by']) ? absint(wp_unslash($_GET['ofqb_created_by'])) : 0,
            'page' => !empty($_GET['ofqb_page']) ? absint(wp_unslash($_GET['ofqb_page'])) : 1,
            'per_page' => 20,
        );
    }
}
