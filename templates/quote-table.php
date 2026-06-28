<?php

if (!defined('ABSPATH')) {
    exit;
}

$quotes_for_table = isset($quotes_for_table) && is_array($quotes_for_table) ? $quotes_for_table : array();
?>

<?php if (!empty($quotes_for_table)) : ?>
    <div class="ofqb-table-wrap">
        <table class="ofqb-quote-table">
            <thead>
                <tr>
                    <th>Quote #</th>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Company</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quotes_for_table as $table_quote) : ?>
                    <tr>
                        <td data-label="Quote #">
                            <a href="<?php echo esc_url(add_query_arg('ofqb_quote_id', (int) $table_quote->id, $base_url)); ?>">
                                <?php echo esc_html($table_quote->quote_number); ?>
                            </a>
                        </td>
                        <td data-label="Date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($table_quote->updated_at))); ?></td>
                        <td data-label="Client"><?php echo esc_html($table_quote->customer_name); ?></td>
                        <td data-label="Company"><?php echo esc_html($table_quote->customer_company); ?></td>
                        <td data-label="Total"><?php echo esc_html(OFQB_Quotes::money($table_quote->total)); ?></td>
                        <td data-label="Status"><span class="ofqb-status-pill ofqb-status-pill--<?php echo esc_attr(sanitize_html_class($table_quote->status)); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $table_quote->status))); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else : ?>
    <div class="ofqb-empty-state">
        <strong>No quotes found</strong>
        <p>Create a quote and it will appear here.</p>
    </div>
<?php endif; ?>
