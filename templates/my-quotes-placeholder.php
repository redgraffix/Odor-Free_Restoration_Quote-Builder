<?php

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="odorfree-quote-builder" data-ofqb>
    <section class="ofqb-panel">
        <div class="ofqb-panel-heading">
            <h2>My Quotes</h2>
            <p>Quotes you created.</p>
        </div>
        <?php include OFQB_PLUGIN_DIR . 'templates/quote-table.php'; ?>
        <p><a class="ofqb-button ofqb-button--secondary" href="<?php echo esc_url($base_url); ?>">Back to Quote Home</a></p>
    </section>
</div>
