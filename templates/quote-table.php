<?php

if (!defined('ABSPATH')) {
    exit;
}

$quote_list_context = isset($quote_list_context) ? $quote_list_context : 'search';
$quote_table_show_filters = isset($quote_table_show_filters) ? (bool) $quote_table_show_filters : in_array($quote_list_context, array('search', 'mine', 'deleted'), true);
$quote_list_result = isset($quote_list_result) && is_array($quote_list_result) ? $quote_list_result : array(
    'items' => isset($quotes_for_table) && is_array($quotes_for_table) ? $quotes_for_table : array(),
    'total_items' => isset($quotes_for_table) && is_array($quotes_for_table) ? count($quotes_for_table) : 0,
    'total_pages' => 1,
    'page' => 1,
    'per_page' => 20,
    'sort' => 'date',
    'order' => 'desc',
    'search' => '',
);
$quotes_for_table = isset($quote_list_result['items']) && is_array($quote_list_result['items']) ? $quote_list_result['items'] : array();
$current_sort = !empty($_GET['ofqb_sort']) ? sanitize_key(wp_unslash($_GET['ofqb_sort'])) : 'date';
$current_order = !empty($_GET['ofqb_order']) && 'asc' === sanitize_key(wp_unslash($_GET['ofqb_order'])) ? 'asc' : 'desc';
$current_search = !empty($_GET['ofqb_search']) ? sanitize_text_field(wp_unslash($_GET['ofqb_search'])) : '';
$current_created_by = !empty($_GET['ofqb_created_by']) ? absint(wp_unslash($_GET['ofqb_created_by'])) : 0;
$list_base_args = array(
    'ofqb_view' => $quote_list_context,
    'ofqb_search' => $current_search,
    'ofqb_sort' => $current_sort,
    'ofqb_order' => $current_order,
);

if (in_array($quote_list_context, array('search', 'deleted'), true) && $current_created_by) {
    $list_base_args['ofqb_created_by'] = $current_created_by;
}

$list_base_url = add_query_arg($list_base_args, $base_url);
$reset_url = add_query_arg('ofqb_view', $quote_list_context, $base_url);
$result_start = (int) $quote_list_result['total_items'] > 0 ? (((int) $quote_list_result['page'] - 1) * (int) $quote_list_result['per_page']) + 1 : 0;
$result_end = min((int) $quote_list_result['total_items'], ((int) $quote_list_result['page'] - 1) * (int) $quote_list_result['per_page'] + count($quotes_for_table));
?>

<?php if ($quote_table_show_filters) : ?>
    <form class="ofqb-quote-filters" method="get" action="<?php echo esc_url($base_url); ?>">
        <input type="hidden" name="ofqb_view" value="<?php echo esc_attr($quote_list_context); ?>">

        <div class="ofqb-filter-search">
            <label>
                <span>Search</span>
                <input type="search" name="ofqb_search" value="<?php echo esc_attr($current_search); ?>" placeholder="Search quote #, client, company, service, material, email, total, or salesperson">
            </label>
            <button type="submit" class="ofqb-button ofqb-button--blue">Search</button>
            <a class="ofqb-button ofqb-button--secondary" href="<?php echo esc_url($reset_url); ?>">Clear Search</a>
        </div>

        <div class="ofqb-filter-controls">
            <label>
                <span>Sort By</span>
                <select name="ofqb_sort" onchange="this.form.submit()">
                    <option value="date" <?php selected($current_sort, 'date'); ?>>Last Updated</option>
                    <option value="created_at" <?php selected($current_sort, 'created_at'); ?>>Created Date</option>
                    <option value="company" <?php selected($current_sort, 'company'); ?>>Company</option>
                    <option value="customer" <?php selected($current_sort, 'customer'); ?>>Client</option>
                    <option value="total" <?php selected($current_sort, 'total'); ?>>Total</option>
                    <option value="status" <?php selected($current_sort, 'status'); ?>>Status</option>
                    <?php if ('search' === $quote_list_context) : ?>
                        <option value="created_by" <?php selected($current_sort, 'created_by'); ?>>Created By</option>
                    <?php endif; ?>
                </select>
            </label>

            <label>
                <span>Order</span>
                <select name="ofqb_order" onchange="this.form.submit()">
                    <option value="asc" <?php selected($current_order, 'asc'); ?>>Ascending</option>
                    <option value="desc" <?php selected($current_order, 'desc'); ?>>Descending</option>
                </select>
            </label>

            <?php if (in_array($quote_list_context, array('search', 'deleted'), true) && !empty($quote_creators)) : ?>
                <label>
                    <span>Created By</span>
                    <select name="ofqb_created_by" onchange="this.form.submit()">
                        <option value="0">All Salespeople</option>
                        <?php foreach ($quote_creators as $quote_creator_option) : ?>
                            <option value="<?php echo esc_attr((int) $quote_creator_option->ID); ?>" <?php selected($current_created_by, (int) $quote_creator_option->ID); ?>>
                                <?php echo esc_html($quote_creator_option->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
        </div>
    </form>
<?php endif; ?>

<?php if (!empty($quotes_for_table)) : ?>
    <?php if ($quote_table_show_filters) : ?>
        <p class="ofqb-list-count">
            Showing <?php echo esc_html(number_format_i18n($result_start)); ?>-<?php echo esc_html(number_format_i18n($result_end)); ?> of <?php echo esc_html(number_format_i18n((int) $quote_list_result['total_items'])); ?> quotes
        </p>
    <?php endif; ?>
    <div class="ofqb-table-wrap">
        <table class="ofqb-quote-table">
            <thead>
                <tr>
                    <th>Quote #</th>
                    <th>Date</th>
                    <th>Created By</th>
                    <th>Client</th>
                    <th>Company</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quotes_for_table as $table_quote) : ?>
                    <?php
                    $quote_creator = get_userdata((int) $table_quote->created_by_user_id);
                    $quote_creator_name = $quote_creator ? $quote_creator->display_name : '';
                    ?>
                    <tr>
                        <td data-label="Quote #">
                            <a href="<?php echo esc_url(add_query_arg('ofqb_quote_id', (int) $table_quote->id, $base_url)); ?>">
                                <?php echo esc_html($table_quote->quote_number); ?>
                            </a>
                        </td>
                        <td data-label="Date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($table_quote->updated_at))); ?></td>
                        <td data-label="Created By"><?php echo esc_html($quote_creator_name); ?></td>
                        <td data-label="Client"><?php echo esc_html($table_quote->customer_name); ?></td>
                        <td data-label="Company"><?php echo esc_html($table_quote->customer_company); ?></td>
                        <td data-label="Total"><?php echo esc_html(OFQB_Quotes::money($table_quote->total)); ?></td>
                        <td data-label="Status"><span class="ofqb-status-pill ofqb-status-pill--<?php echo esc_attr(sanitize_html_class($table_quote->status)); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $table_quote->status))); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($quote_table_show_filters && (int) $quote_list_result['total_pages'] > 1) : ?>
        <nav class="ofqb-pagination" aria-label="Quote pages">
            <?php if ((int) $quote_list_result['page'] > 1) : ?>
                <a href="<?php echo esc_url(add_query_arg('ofqb_page', (int) $quote_list_result['page'] - 1, $list_base_url)); ?>">Previous</a>
            <?php endif; ?>

            <?php for ($page_number = 1; $page_number <= (int) $quote_list_result['total_pages']; $page_number += 1) : ?>
                <?php if ($page_number === (int) $quote_list_result['page']) : ?>
                    <span aria-current="page"><?php echo esc_html($page_number); ?></span>
                <?php else : ?>
                    <a href="<?php echo esc_url(add_query_arg('ofqb_page', $page_number, $list_base_url)); ?>"><?php echo esc_html($page_number); ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ((int) $quote_list_result['page'] < (int) $quote_list_result['total_pages']) : ?>
                <a href="<?php echo esc_url(add_query_arg('ofqb_page', (int) $quote_list_result['page'] + 1, $list_base_url)); ?>">Next</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php else : ?>
    <div class="ofqb-empty-state">
        <strong>No quotes found</strong>
        <p>Create a quote and it will appear here.</p>
    </div>
<?php endif; ?>
