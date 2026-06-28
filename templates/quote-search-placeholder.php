<?php

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="odorfree-quote-builder" data-ofqb>
    <section class="ofqb-panel">
        <div class="ofqb-panel-heading">
            <h2>Search Quotes</h2>
            <p>Find quotes by quote number, client, company, email, phone, or status.</p>
        </div>
        <form class="ofqb-quote-filters" method="get" action="<?php echo esc_url($base_url); ?>">
            <input type="hidden" name="ofqb_view" value="search">
            <label>
                <span>Search</span>
                <input type="search" name="ofqb_search" value="<?php echo esc_attr(!empty($_GET['ofqb_search']) ? sanitize_text_field(wp_unslash($_GET['ofqb_search'])) : ''); ?>">
            </label>
            <button type="submit" class="ofqb-button">Search</button>
            <a class="ofqb-button ofqb-button--secondary" href="<?php echo esc_url(add_query_arg('ofqb_view', 'search', $base_url)); ?>">Clear</a>
        </form>
        <?php include OFQB_PLUGIN_DIR . 'templates/quote-table.php'; ?>
        <p><a class="ofqb-button ofqb-button--secondary" href="<?php echo esc_url($base_url); ?>">Back to Quote Home</a></p>
    </section>
</div>
