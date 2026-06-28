<?php

if (!defined('ABSPATH')) {
    exit;
}

class OFQB_Quotes
{
    const DEFAULT_BCC_EMAIL = 'sales@odorfreerestoration.com';

    public static function get_recent_quotes($limit = 5)
    {
        global $wpdb;

        $quotes_table = $wpdb->prefix . 'ofqb_quotes';
        $limit = max(1, min(25, absint($limit)));

        if (!self::table_exists($quotes_table)) {
            return array();
        }

        if (!current_user_can('manage_options')) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$quotes_table} WHERE status <> %s AND created_by_user_id = %d ORDER BY updated_at DESC, created_at DESC, id DESC LIMIT %d",
                    'deleted',
                    get_current_user_id(),
                    $limit
                )
            );
        }

        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$quotes_table} WHERE status <> %s ORDER BY updated_at DESC, created_at DESC, id DESC LIMIT %d", 'deleted', $limit));
    }

    public static function get_admin_stats()
    {
        global $wpdb;

        $quotes_table = $wpdb->prefix . 'ofqb_quotes';

        if (!self::table_exists($quotes_table)) {
            return array(
                'total_quotes' => 0,
                'draft_quotes' => 0,
                'sent_quotes' => 0,
                'closed_quotes' => 0,
            );
        }

        return array(
            'total_quotes' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$quotes_table} WHERE status <> %s", 'deleted')),
            'draft_quotes' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$quotes_table} WHERE status = %s", 'draft')),
            'sent_quotes' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$quotes_table} WHERE status = %s", 'sent')),
            'closed_quotes' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$quotes_table} WHERE status = %s", 'closed')),
        );
    }

    public static function get_default_terms()
    {
        return "Prefilled standard terms can be edited for each client.\n\nAcceptance of this quote authorizes Odor-Free Restoration LLC to perform the listed services and supply the listed materials.";
    }

    public static function get_quote_email_bcc()
    {
        return apply_filters('ofqb_quote_email_bcc', self::DEFAULT_BCC_EMAIL);
    }

    private static function table_exists($table)
    {
        global $wpdb;

        return $table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    }
}
