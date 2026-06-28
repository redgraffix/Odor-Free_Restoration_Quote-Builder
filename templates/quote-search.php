<?php

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="odorfree-quote-builder" data-ofqb>
    <section class="ofqb-quote-list">
        <div class="ofqb-list-header">
            <div>
                <h2><?php echo 'deleted' === $quote_list_context ? 'Deleted Quotes' : 'Search Quotes'; ?></h2>
                <p><?php echo 'deleted' === $quote_list_context ? 'Review deleted quotes and restore them from the quote detail page.' : 'Search active quotes by quote number, client, company, service, material, email, total, or salesperson.'; ?></p>
            </div>
        </div>

        <?php include OFQB_PLUGIN_DIR . 'templates/quote-table.php'; ?>

        <div class="ofqb-list-actions">
            <a class="ofqb-button" href="<?php echo esc_url($base_url); ?>">Home</a>
            <?php if ('deleted' === $quote_list_context) : ?>
                <a class="ofqb-button ofqb-button--secondary" href="<?php echo esc_url(add_query_arg('ofqb_view', 'search', $base_url)); ?>">Active Quotes</a>
            <?php else : ?>
                <a class="ofqb-button ofqb-button--secondary" href="<?php echo esc_url(add_query_arg('ofqb_view', 'deleted', $base_url)); ?>">View Deleted Quotes</a>
            <?php endif; ?>
        </div>
    </section>
</div>
