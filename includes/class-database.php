<?php

if (!defined('ABSPATH')) {
    exit;
}

class OFQB_Database
{
    const SCHEMA_VERSION = '0.2.0';

    public static function activate()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $quotes_table = $wpdb->prefix . 'ofqb_quotes';
        $services_table = $wpdb->prefix . 'ofqb_quote_services';
        $materials_table = $wpdb->prefix . 'ofqb_quote_materials';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $quotes_sql = "CREATE TABLE {$quotes_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quote_number varchar(50) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            created_by_user_id bigint(20) unsigned NOT NULL,
            updated_by_user_id bigint(20) unsigned NULL,
            salesperson_name varchar(190) DEFAULT '' NOT NULL,
            salesperson_email varchar(190) DEFAULT '' NOT NULL,
            salesperson_cell varchar(50) DEFAULT '' NOT NULL,
            customer_name varchar(190) NOT NULL,
            customer_company varchar(190) DEFAULT '' NOT NULL,
            customer_phone varchar(50) DEFAULT '' NOT NULL,
            customer_email varchar(190) NOT NULL,
            accounts_payable_name varchar(190) DEFAULT '' NOT NULL,
            accounts_payable_phone varchar(50) DEFAULT '' NOT NULL,
            accounts_payable_email varchar(190) DEFAULT '' NOT NULL,
            customer_address text NOT NULL,
            customer_address_2 varchar(255) DEFAULT '' NOT NULL,
            customer_city varchar(190) DEFAULT '' NOT NULL,
            customer_state varchar(80) NOT NULL,
            customer_zip varchar(30) NOT NULL,
            customer_tax_id varchar(100) DEFAULT '' NOT NULL,
            terms text NULL,
            notes text NULL,
            approved_by varchar(190) DEFAULT '' NOT NULL,
            approval_signature varchar(190) DEFAULT '' NOT NULL,
            approval_date varchar(50) DEFAULT '' NOT NULL,
            subtotal decimal(12,2) NOT NULL DEFAULT 0.00,
            tax_rate decimal(8,4) NOT NULL DEFAULT 0.0000,
            tax_amount decimal(12,2) NOT NULL DEFAULT 0.00,
            total decimal(12,2) NOT NULL DEFAULT 0.00,
            status varchar(30) NOT NULL DEFAULT 'draft',
            deleted_at datetime NULL,
            deleted_by_user_id bigint(20) unsigned NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY quote_number (quote_number),
            KEY created_by_user_id (created_by_user_id),
            KEY updated_by_user_id (updated_by_user_id),
            KEY status (status)
        ) {$charset_collate};";

        $services_sql = "CREATE TABLE {$services_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) unsigned NOT NULL,
            service_description text NOT NULL,
            hours decimal(10,2) NOT NULL DEFAULT 0.00,
            rate decimal(12,2) NOT NULL DEFAULT 0.00,
            line_total decimal(12,2) NOT NULL DEFAULT 0.00,
            sort_order int(11) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY quote_id (quote_id)
        ) {$charset_collate};";

        $materials_sql = "CREATE TABLE {$materials_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) unsigned NOT NULL,
            material_description text NOT NULL,
            unit_cost decimal(12,2) NOT NULL DEFAULT 0.00,
            quantity decimal(10,2) NOT NULL DEFAULT 0.00,
            line_total decimal(12,2) NOT NULL DEFAULT 0.00,
            sort_order int(11) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY quote_id (quote_id)
        ) {$charset_collate};";

        dbDelta($quotes_sql);
        dbDelta($services_sql);
        dbDelta($materials_sql);

        update_option('ofqb_schema_version', self::SCHEMA_VERSION);
    }

    public static function maybe_upgrade()
    {
        if (get_option('ofqb_schema_version') !== self::SCHEMA_VERSION) {
            self::activate();
        }
    }
}
