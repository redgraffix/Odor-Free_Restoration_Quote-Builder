<?php

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="odorfree-quote-builder" data-ofqb>
    <section class="ofqb-quote-list">
        <div class="ofqb-list-header">
            <div>
                <h2>My Quotes</h2>
                <p>View, search, and sort quotes you created.</p>
            </div>
        </div>

        <?php include OFQB_PLUGIN_DIR . 'templates/quote-table.php'; ?>

        <div class="ofqb-list-actions">
            <a class="ofqb-button" href="<?php echo esc_url($base_url); ?>">Home</a>
        </div>
    </section>
</div>
