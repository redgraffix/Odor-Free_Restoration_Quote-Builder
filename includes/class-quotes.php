<?php

if (!defined('ABSPATH')) {
    exit;
}

class OFQB_Quotes
{
    const DEFAULT_BCC_EMAIL = 'sales@odorfreerestoration.com';

    public static function maybe_handle_submission()
    {
        if (!is_user_logged_in() || !OFQB_Roles::current_user_can_create_quotes() || 'POST' !== $_SERVER['REQUEST_METHOD']) {
            return null;
        }

        if (empty($_POST['ofqb_action']) || 'generate_quote' !== $_POST['ofqb_action']) {
            return null;
        }

        if (empty($_POST['ofqb_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ofqb_nonce'])), 'ofqb_generate_quote')) {
            return new WP_Error('ofqb_invalid_nonce', 'Quote could not be saved. Please refresh the page and try again.');
        }

        $intent = !empty($_POST['ofqb_submit_intent']) && 'draft' === sanitize_key(wp_unslash($_POST['ofqb_submit_intent'])) ? 'draft' : 'generate';
        $quote_data = self::sanitize_quote_data($_POST);
        $services = self::build_service_rows(isset($_POST['services']) ? wp_unslash($_POST['services']) : array());
        $materials = self::build_material_rows(isset($_POST['materials']) ? wp_unslash($_POST['materials']) : array());
        $line_count = count($services) + count($materials);

        $validation_error = self::validate_quote_data($quote_data, $line_count);

        if ('generate' === $intent && is_wp_error($validation_error)) {
            return $validation_error;
        }

        $quote_data['status'] = self::resolve_status($intent, !empty($_POST['ofqb_existing_quote_id']) ? absint(wp_unslash($_POST['ofqb_existing_quote_id'])) : 0);
        $totals = self::calculate_totals($services, $materials, $quote_data['tax_rate']);

        if (!empty($_POST['ofqb_existing_quote_id'])) {
            return self::update_quote(absint(wp_unslash($_POST['ofqb_existing_quote_id'])), $quote_data, $services, $materials, $totals);
        }

        return self::save_quote($quote_data, $services, $materials, $totals);
    }

    public static function maybe_handle_quote_action($quote_id)
    {
        if (!is_user_logged_in() || !OFQB_Roles::current_user_can_create_quotes() || 'POST' !== $_SERVER['REQUEST_METHOD']) {
            return null;
        }

        if (empty($_POST['ofqb_action']) || empty($_POST['ofqb_quote_action_nonce'])) {
            return null;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ofqb_quote_action_nonce'])), 'ofqb_quote_action_' . $quote_id)) {
            return new WP_Error('ofqb_quote_action_nonce', 'Quote action could not be verified. Please refresh the page and try again.');
        }

        $action = sanitize_key(wp_unslash($_POST['ofqb_action']));

        if ('update_status' === $action) {
            $status = !empty($_POST['quote_status']) ? sanitize_key(wp_unslash($_POST['quote_status'])) : '';
            return self::update_status($quote_id, $status);
        }

        if ('delete_quote' === $action) {
            return self::soft_delete_quote($quote_id);
        }

        if ('restore_quote' === $action) {
            return self::restore_quote($quote_id);
        }

        return null;
    }

    public static function get_quote_with_items($quote_id)
    {
        global $wpdb;

        $quotes_table = $wpdb->prefix . 'ofqb_quotes';
        $services_table = $wpdb->prefix . 'ofqb_quote_services';
        $materials_table = $wpdb->prefix . 'ofqb_quote_materials';

        if (!self::table_exists($quotes_table)) {
            return null;
        }

        $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$quotes_table} WHERE id = %d", $quote_id));

        if (!$quote) {
            return null;
        }

        return array(
            'quote' => $quote,
            'services' => $wpdb->get_results($wpdb->prepare("SELECT * FROM {$services_table} WHERE quote_id = %d ORDER BY sort_order ASC, id ASC", $quote_id)),
            'materials' => $wpdb->get_results($wpdb->prepare("SELECT * FROM {$materials_table} WHERE quote_id = %d ORDER BY sort_order ASC, id ASC", $quote_id)),
        );
    }

    public static function current_user_can_modify_quote($quote)
    {
        if (!$quote || !is_user_logged_in()) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        return (int) $quote->created_by_user_id === get_current_user_id();
    }

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
        return "Acceptance of this quote authorizes Odor-Free Restoration LLC to perform the listed services and supply the listed materials.";
    }

    public static function get_quote_email_bcc()
    {
        return apply_filters('ofqb_quote_email_bcc', self::DEFAULT_BCC_EMAIL);
    }

    public static function get_default_email_message($quote_number)
    {
        return "Dear Customer,\n\nPlease find your Odor-Free Restoration service quote, #{$quote_number}, attached for review.\n\nIf you have any questions, please contact us at 866-4-NO-ODOR (466-6367).\n\nThank you,\nOdor-Free Restoration LLC";
    }

    public static function mark_quote_sent($quote_id)
    {
        global $wpdb;

        $bundle = self::get_quote_with_items($quote_id);

        if (!$bundle || !self::current_user_can_modify_quote($bundle['quote'])) {
            return false;
        }

        return false !== $wpdb->update(
            $wpdb->prefix . 'ofqb_quotes',
            array(
                'status' => 'sent',
                'updated_at' => current_time('mysql'),
                'updated_by_user_id' => get_current_user_id(),
            ),
            array('id' => $quote_id),
            array('%s', '%s', '%d'),
            array('%d')
        );
    }

    public static function get_quote_list($args = array())
    {
        global $wpdb;

        $quotes_table = $wpdb->prefix . 'ofqb_quotes';
        $services_table = $wpdb->prefix . 'ofqb_quote_services';
        $materials_table = $wpdb->prefix . 'ofqb_quote_materials';
        $users_table = $wpdb->users;
        $defaults = array(
            'created_by_user_id' => 0,
            'created_by_filter' => 0,
            'search' => '',
            'sort' => 'date',
            'order' => 'desc',
            'page' => 1,
            'per_page' => 20,
            'status_filter' => 'active',
        );
        $args = wp_parse_args($args, $defaults);

        if (!self::table_exists($quotes_table)) {
            return array(
                'items' => array(),
                'total_items' => 0,
                'total_pages' => 1,
                'page' => 1,
                'per_page' => (int) $args['per_page'],
                'sort' => 'date',
                'order' => 'desc',
                'search' => '',
                'status_filter' => 'active',
            );
        }

        if (!current_user_can('manage_options')) {
            $args['created_by_user_id'] = get_current_user_id();
            $args['created_by_filter'] = 0;
        }

        $page = max(1, absint($args['page']));
        $per_page = max(1, min(100, absint($args['per_page'])));
        $offset = ($page - 1) * $per_page;
        $where = array();
        $values = array();

        if ('deleted' === $args['status_filter']) {
            $where[] = 'q.status = %s';
            $values[] = 'deleted';
        } else {
            $where[] = 'q.status <> %s';
            $values[] = 'deleted';
        }

        if (!empty($args['created_by_user_id'])) {
            $where[] = 'q.created_by_user_id = %d';
            $values[] = absint($args['created_by_user_id']);
        }

        if (!empty($args['created_by_filter'])) {
            $where[] = 'q.created_by_user_id = %d';
            $values[] = absint($args['created_by_filter']);
        }

        $search = trim((string) $args['search']);

        if ('' !== $search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(
                q.quote_number LIKE %s
                OR q.customer_name LIKE %s
                OR q.customer_company LIKE %s
                OR q.customer_email LIKE %s
                OR q.customer_phone LIKE %s
                OR q.accounts_payable_name LIKE %s
                OR q.accounts_payable_email LIKE %s
                OR q.customer_address LIKE %s
                OR q.customer_city LIKE %s
                OR q.customer_state LIKE %s
                OR q.customer_zip LIKE %s
                OR q.customer_tax_id LIKE %s
                OR q.notes LIKE %s
                OR q.status LIKE %s
                OR CAST(q.subtotal AS CHAR) LIKE %s
                OR CAST(q.tax_amount AS CHAR) LIKE %s
                OR CAST(q.total AS CHAR) LIKE %s
                OR u.display_name LIKE %s
                OR u.user_login LIKE %s
                OR u.user_email LIKE %s
                OR s.service_description LIKE %s
                OR m.material_description LIKE %s
            )';
            array_push($values, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like);
        }

        $sort_columns = array(
            'date' => 'q.updated_at',
            'updated_at' => 'q.updated_at',
            'created_at' => 'q.created_at',
            'company' => 'q.customer_company',
            'customer' => 'q.customer_name',
            'total' => 'q.total',
            'created_by' => 'u.display_name',
            'status' => 'q.status',
        );
        $sort = isset($sort_columns[$args['sort']]) ? $sort_columns[$args['sort']] : 'q.updated_at';
        $order = 'asc' === strtolower((string) $args['order']) ? 'ASC' : 'DESC';
        $where_sql = implode(' AND ', $where);
        $joins_sql = "LEFT JOIN {$users_table} u ON q.created_by_user_id = u.ID LEFT JOIN {$services_table} s ON q.id = s.quote_id LEFT JOIN {$materials_table} m ON q.id = m.quote_id";
        $total_items = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT q.id) FROM {$quotes_table} q {$joins_sql} WHERE {$where_sql}", $values));

        $values[] = $per_page;
        $values[] = $offset;
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT q.* FROM {$quotes_table} q {$joins_sql} WHERE {$where_sql} ORDER BY {$sort} {$order}, q.id DESC LIMIT %d OFFSET %d",
                $values
            )
        );

        return array(
            'items' => $items,
            'total_items' => $total_items,
            'total_pages' => max(1, (int) ceil($total_items / $per_page)),
            'page' => $page,
            'per_page' => $per_page,
            'sort' => array_search($sort, $sort_columns, true) ?: 'date',
            'order' => strtolower($order),
            'search' => $search,
            'status_filter' => 'deleted' === $args['status_filter'] ? 'deleted' : 'active',
        );
    }

    public static function get_quote_creators($status_filter = 'active')
    {
        global $wpdb;

        $quotes_table = $wpdb->prefix . 'ofqb_quotes';
        $users_table = $wpdb->users;
        $status_operator = 'deleted' === $status_filter ? '=' : '<>';

        if (!self::table_exists($quotes_table)) {
            return array();
        }

        if (!current_user_can('manage_options')) {
            $user = get_userdata(get_current_user_id());
            return $user ? array($user) : array();
        }

        $user_ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT q.created_by_user_id FROM {$quotes_table} q LEFT JOIN {$users_table} u ON q.created_by_user_id = u.ID WHERE q.status {$status_operator} %s AND q.created_by_user_id > 0 ORDER BY u.display_name ASC", 'deleted'));
        $users = array();

        foreach ($user_ids as $user_id) {
            $user = get_userdata((int) $user_id);

            if ($user) {
                $users[] = $user;
            }
        }

        return $users;
    }

    private static function sanitize_quote_data($source)
    {
        return array(
            'salesperson_name' => self::clean_text($source, 'salesperson_name'),
            'salesperson_email' => isset($source['salesperson_email']) ? sanitize_email(wp_unslash($source['salesperson_email'])) : '',
            'salesperson_cell' => self::clean_phone($source, 'salesperson_cell'),
            'customer_name' => self::clean_text($source, 'customer_name'),
            'customer_company' => self::clean_text($source, 'customer_company'),
            'customer_phone' => self::clean_phone($source, 'customer_phone'),
            'customer_email' => isset($source['customer_email']) ? sanitize_email(wp_unslash($source['customer_email'])) : '',
            'accounts_payable_name' => self::clean_text($source, 'accounts_payable_name'),
            'accounts_payable_phone' => self::clean_phone($source, 'accounts_payable_phone'),
            'accounts_payable_email' => isset($source['accounts_payable_email']) ? sanitize_email(wp_unslash($source['accounts_payable_email'])) : '',
            'customer_address' => self::clean_text($source, 'customer_address'),
            'customer_address_2' => self::clean_text($source, 'customer_address_2'),
            'customer_city' => self::clean_text($source, 'customer_city'),
            'customer_state' => self::clean_state($source),
            'customer_zip' => self::clean_text($source, 'customer_zip'),
            'customer_tax_id' => self::clean_text($source, 'customer_tax_id'),
            'terms' => self::clean_textarea($source, 'terms'),
            'notes' => self::clean_textarea($source, 'notes'),
            'tax_rate' => self::clean_decimal($source, 'tax_rate'),
        );
    }

    private static function build_service_rows($rows)
    {
        if (!is_array($rows)) {
            return array();
        }

        $items = array();

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $description = isset($row['description']) ? sanitize_textarea_field(wp_unslash($row['description'])) : '';

            if ('' === trim($description)) {
                continue;
            }

            $hours = isset($row['hours']) ? self::normalize_decimal($row['hours']) : 0;
            $rate = isset($row['rate']) ? self::normalize_decimal($row['rate']) : 0;

            $items[] = array(
                'service_description' => $description,
                'hours' => $hours,
                'rate' => $rate,
                'line_total' => round($hours * $rate, 2),
                'sort_order' => count($items),
            );
        }

        return $items;
    }

    private static function build_material_rows($rows)
    {
        if (!is_array($rows)) {
            return array();
        }

        $items = array();

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $description = isset($row['description']) ? sanitize_text_field(wp_unslash($row['description'])) : '';

            if ('' === trim($description)) {
                continue;
            }

            $unit_cost = isset($row['unit_cost']) ? self::normalize_decimal($row['unit_cost']) : 0;
            $quantity = isset($row['quantity']) ? self::normalize_decimal($row['quantity']) : 0;

            $items[] = array(
                'material_description' => $description,
                'unit_cost' => $unit_cost,
                'quantity' => $quantity,
                'line_total' => round($unit_cost * $quantity, 2),
                'sort_order' => count($items),
            );
        }

        return $items;
    }

    private static function save_quote($quote_data, $services, $materials, $totals)
    {
        global $wpdb;

        $quotes_table = $wpdb->prefix . 'ofqb_quotes';
        $now = current_time('mysql');
        $quote_number = self::generate_quote_number();

        $saved = $wpdb->insert(
            $quotes_table,
            self::get_quote_db_row($quote_number, $now, $now, get_current_user_id(), get_current_user_id(), $quote_data, $totals),
            self::get_quote_db_formats()
        );

        if (!$saved) {
            return new WP_Error('ofqb_quote_save_failed', 'Quote could not be saved. Please try again.');
        }

        $quote_id = (int) $wpdb->insert_id;
        self::replace_service_rows($quote_id, $services);
        self::replace_material_rows($quote_id, $materials);

        return self::get_quote_with_items($quote_id);
    }

    private static function update_quote($quote_id, $quote_data, $services, $materials, $totals)
    {
        global $wpdb;

        $existing = self::get_quote_with_items($quote_id);

        if (!$existing) {
            return new WP_Error('ofqb_quote_missing', 'Quote could not be found for update.');
        }

        if (!self::current_user_can_modify_quote($existing['quote'])) {
            return new WP_Error('ofqb_quote_update_denied', 'You can only update quotes you created.');
        }

        $now = current_time('mysql');
        $row = self::get_quote_update_db_row($now, get_current_user_id(), $quote_data, $totals);
        $updated = $wpdb->update($wpdb->prefix . 'ofqb_quotes', $row, array('id' => $quote_id), self::get_quote_update_db_formats(), array('%d'));

        if (false === $updated) {
            return new WP_Error('ofqb_quote_update_failed', 'Quote could not be updated. Please try again.');
        }

        self::replace_service_rows($quote_id, $services);
        self::replace_material_rows($quote_id, $materials);

        return self::get_quote_with_items($quote_id);
    }

    private static function get_quote_db_row($quote_number, $created_at, $updated_at, $created_by_user_id, $updated_by_user_id, $quote_data, $totals)
    {
        return array_merge(
            array(
                'quote_number' => $quote_number,
                'created_at' => $created_at,
                'updated_at' => $updated_at,
                'created_by_user_id' => $created_by_user_id,
                'updated_by_user_id' => $updated_by_user_id,
            ),
            self::get_quote_update_db_row($updated_at, $updated_by_user_id, $quote_data, $totals, false)
        );
    }

    private static function get_quote_update_db_row($updated_at, $updated_by_user_id, $quote_data, $totals, $include_updated = true)
    {
        $row = array(
            'salesperson_name' => $quote_data['salesperson_name'],
            'salesperson_email' => $quote_data['salesperson_email'],
            'salesperson_cell' => $quote_data['salesperson_cell'],
            'customer_name' => $quote_data['customer_name'],
            'customer_company' => $quote_data['customer_company'],
            'customer_phone' => $quote_data['customer_phone'],
            'customer_email' => $quote_data['customer_email'],
            'accounts_payable_name' => $quote_data['accounts_payable_name'],
            'accounts_payable_phone' => $quote_data['accounts_payable_phone'],
            'accounts_payable_email' => $quote_data['accounts_payable_email'],
            'customer_address' => $quote_data['customer_address'],
            'customer_address_2' => $quote_data['customer_address_2'],
            'customer_city' => $quote_data['customer_city'],
            'customer_state' => $quote_data['customer_state'],
            'customer_zip' => $quote_data['customer_zip'],
            'customer_tax_id' => $quote_data['customer_tax_id'],
            'terms' => $quote_data['terms'],
            'notes' => $quote_data['notes'],
            'subtotal' => $totals['subtotal'],
            'tax_rate' => $totals['tax_rate'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
            'status' => $quote_data['status'],
        );

        if ($include_updated) {
            $row = array_merge(
                array(
                    'updated_at' => $updated_at,
                    'updated_by_user_id' => $updated_by_user_id,
                ),
                $row
            );
        }

        return $row;
    }

    private static function get_quote_db_formats()
    {
        return array_merge(array('%s', '%s', '%s', '%d', '%d'), self::get_quote_update_db_formats(false));
    }

    private static function get_quote_update_db_formats($include_updated = true)
    {
        $formats = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s');

        if ($include_updated) {
            return array_merge(array('%s', '%d'), $formats);
        }

        return $formats;
    }

    private static function replace_service_rows($quote_id, $services)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ofqb_quote_services';
        $wpdb->delete($table, array('quote_id' => $quote_id), array('%d'));

        foreach ($services as $service) {
            $wpdb->insert(
                $table,
                array_merge(array('quote_id' => $quote_id), $service),
                array('%d', '%s', '%f', '%f', '%f', '%d')
            );
        }
    }

    private static function replace_material_rows($quote_id, $materials)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ofqb_quote_materials';
        $wpdb->delete($table, array('quote_id' => $quote_id), array('%d'));

        foreach ($materials as $material) {
            $wpdb->insert(
                $table,
                array_merge(array('quote_id' => $quote_id), $material),
                array('%d', '%s', '%f', '%f', '%f', '%d')
            );
        }
    }

    private static function calculate_totals($services, $materials, $tax_rate)
    {
        $subtotal = 0;

        foreach ($services as $service) {
            $subtotal += $service['line_total'];
        }

        foreach ($materials as $material) {
            $subtotal += $material['line_total'];
        }

        $tax_rate = max(0, (float) $tax_rate);
        $tax_amount = round($subtotal * ($tax_rate / 100), 2);

        return array(
            'subtotal' => round($subtotal, 2),
            'tax_rate' => $tax_rate,
            'tax_amount' => $tax_amount,
            'total' => round($subtotal + $tax_amount, 2),
        );
    }

    private static function generate_quote_number()
    {
        global $wpdb;

        $quotes_table = $wpdb->prefix . 'ofqb_quotes';
        $year = current_time('Y');
        $like = $wpdb->esc_like('OFQ-' . $year . '-') . '%';
        $latest = $wpdb->get_var($wpdb->prepare("SELECT quote_number FROM {$quotes_table} WHERE quote_number LIKE %s ORDER BY id DESC LIMIT 1", $like));
        $next = 1;

        if ($latest && preg_match('/-(\d+)$/', $latest, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('OFQ-%s-%06d', $year, $next);
    }

    private static function update_status($quote_id, $status)
    {
        global $wpdb;

        $allowed = array('generated', 'sent', 'revised', 'closed');

        if (!in_array($status, $allowed, true)) {
            return new WP_Error('ofqb_invalid_status', 'Please choose a valid quote status.');
        }

        $bundle = self::get_quote_with_items($quote_id);

        if (!$bundle || !self::current_user_can_modify_quote($bundle['quote'])) {
            return new WP_Error('ofqb_status_denied', 'You do not have permission to update this quote.');
        }

        return false !== $wpdb->update(
            $wpdb->prefix . 'ofqb_quotes',
            array(
                'status' => $status,
                'updated_at' => current_time('mysql'),
                'updated_by_user_id' => get_current_user_id(),
            ),
            array('id' => $quote_id),
            array('%s', '%s', '%d'),
            array('%d')
        );
    }

    private static function soft_delete_quote($quote_id)
    {
        global $wpdb;

        $bundle = self::get_quote_with_items($quote_id);

        if (!$bundle || !self::current_user_can_modify_quote($bundle['quote'])) {
            return new WP_Error('ofqb_delete_denied', 'You do not have permission to delete this quote.');
        }

        $now = current_time('mysql');

        return false !== $wpdb->update(
            $wpdb->prefix . 'ofqb_quotes',
            array(
                'status' => 'deleted',
                'deleted_at' => $now,
                'deleted_by_user_id' => get_current_user_id(),
                'updated_at' => $now,
                'updated_by_user_id' => get_current_user_id(),
            ),
            array('id' => $quote_id),
            array('%s', '%s', '%d', '%s', '%d'),
            array('%d')
        );
    }

    private static function restore_quote($quote_id)
    {
        global $wpdb;

        $bundle = self::get_quote_with_items($quote_id);

        if (!$bundle || !self::current_user_can_modify_quote($bundle['quote'])) {
            return new WP_Error('ofqb_restore_denied', 'You do not have permission to restore this quote.');
        }

        return false !== $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}ofqb_quotes SET status = %s, deleted_at = NULL, deleted_by_user_id = NULL, updated_at = %s, updated_by_user_id = %d WHERE id = %d",
                'generated',
                current_time('mysql'),
                get_current_user_id(),
                $quote_id
            )
        );
    }

    private static function validate_quote_data($quote_data, $line_count)
    {
        $required = array(
            'customer_name',
            'customer_company',
            'customer_phone',
            'customer_email',
            'customer_address',
            'customer_city',
            'customer_state',
            'customer_zip',
            'accounts_payable_name',
            'accounts_payable_phone',
            'accounts_payable_email',
        );

        foreach ($required as $field) {
            if ('' === trim($quote_data[$field])) {
                return new WP_Error('ofqb_missing_required', 'Please fill out all required client information before generating a quote.');
            }
        }

        if (!is_email($quote_data['customer_email']) || !is_email($quote_data['accounts_payable_email'])) {
            return new WP_Error('ofqb_invalid_email', 'Please enter valid client and accounts payable email addresses.');
        }

        if (!preg_match('/^\d{3}-\d{3}-\d{4}$/', $quote_data['customer_phone']) || !preg_match('/^\d{3}-\d{3}-\d{4}$/', $quote_data['accounts_payable_phone'])) {
            return new WP_Error('ofqb_invalid_phone', 'Please enter phone numbers in 000-000-0000 format.');
        }

        if ($line_count < 1) {
            return new WP_Error('ofqb_no_lines', 'Please add at least one service or material line before generating a quote.');
        }

        return true;
    }

    private static function resolve_status($intent, $existing_quote_id)
    {
        if ('draft' === $intent) {
            return 'draft';
        }

        if ($existing_quote_id) {
            return 'revised';
        }

        return 'generated';
    }

    private static function clean_text($source, $key)
    {
        return isset($source[$key]) ? sanitize_text_field(wp_unslash($source[$key])) : '';
    }

    private static function clean_textarea($source, $key)
    {
        return isset($source[$key]) ? sanitize_textarea_field(wp_unslash($source[$key])) : '';
    }

    private static function clean_decimal($source, $key)
    {
        return isset($source[$key]) ? self::normalize_decimal(wp_unslash($source[$key])) : 0;
    }

    private static function normalize_decimal($value)
    {
        return max(0, (float) preg_replace('/[^0-9.\-]/', '', (string) $value));
    }

    private static function clean_state($source)
    {
        $state = self::clean_text($source, 'customer_state');
        return preg_match('/^[A-Z]{2}$/', $state) ? $state : '';
    }

    private static function clean_phone($source, $key)
    {
        $phone = self::clean_text($source, $key);
        $digits = preg_replace('/\D+/', '', $phone);

        if (10 !== strlen($digits)) {
            return $phone;
        }

        return substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6, 4);
    }

    public static function money($value)
    {
        return '$' . number_format((float) $value, 2);
    }

    private static function table_exists($table)
    {
        global $wpdb;

        return $table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    }
}
