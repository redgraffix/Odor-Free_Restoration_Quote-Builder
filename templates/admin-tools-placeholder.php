<?php

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="odorfree-quote-builder" data-ofqb>
    <section class="ofqb-panel">
        <div class="ofqb-panel-heading">
            <h2>Admin Tools</h2>
            <p>Plugin registration and database setup are ready.</p>
        </div>

        <dl class="ofqb-admin-stats">
            <div>
                <dt>Total Quotes</dt>
                <dd><?php echo esc_html($quote_admin_stats['total_quotes']); ?></dd>
            </div>
            <div>
                <dt>Drafts</dt>
                <dd><?php echo esc_html($quote_admin_stats['draft_quotes']); ?></dd>
            </div>
            <div>
                <dt>Sent</dt>
                <dd><?php echo esc_html($quote_admin_stats['sent_quotes']); ?></dd>
            </div>
            <div>
                <dt>Closed</dt>
                <dd><?php echo esc_html($quote_admin_stats['closed_quotes']); ?></dd>
            </div>
        </dl>

        <p><a class="ofqb-button ofqb-button--secondary" href="<?php echo esc_url($base_url); ?>">Back to Quote Home</a></p>
    </section>
</div>
