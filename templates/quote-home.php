<?php

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="odorfree-quote-builder" data-ofqb>
    <section class="ofqb-home">
        <p class="ofqb-home-user">Signed in as <?php echo esc_html($salesperson_name); ?></p>

        <div class="ofqb-home-actions <?php echo current_user_can('manage_options') ? '' : 'ofqb-home-actions--salesperson'; ?>">
            <a class="ofqb-home-button ofqb-home-button--blue" href="<?php echo esc_url(add_query_arg('ofqb_view', 'create', $base_url)); ?>">
                <?php echo OFQB_Icons::icon('plus-file'); ?>
                <strong>Create New Quote</strong>
                <span>Start a new service quote</span>
            </a>
            <?php if (current_user_can('manage_options')) : ?>
                <a class="ofqb-home-button ofqb-home-button--green" href="<?php echo esc_url(add_query_arg('ofqb_view', 'search', $base_url)); ?>">
                    <?php echo OFQB_Icons::icon('search'); ?>
                    <strong>Search Quotes</strong>
                    <span>Find quotes by client, company, status, or salesperson</span>
                </a>
            <?php endif; ?>
            <a class="ofqb-home-button ofqb-home-button--yellow" href="<?php echo esc_url(add_query_arg('ofqb_view', 'mine', $base_url)); ?>">
                <?php echo OFQB_Icons::icon('user-file'); ?>
                <strong>My Quotes</strong>
                <span>View quotes you created</span>
            </a>
        </div>

        <?php if (current_user_can('manage_options')) : ?>
            <div class="ofqb-home-admin-actions">
                <a class="ofqb-home-button ofqb-home-button--navy ofqb-home-button--wide" href="<?php echo esc_url(add_query_arg('ofqb_view', 'admin', $base_url)); ?>">
                    <?php echo OFQB_Icons::icon('settings'); ?>
                    <strong>Admin Tools</strong>
                    <span>Review quote activity and plugin readiness</span>
                </a>
            </div>
        <?php endif; ?>

        <section class="ofqb-panel" aria-labelledby="ofqb-recent-quotes-heading">
            <div class="ofqb-panel-heading">
                <h3 id="ofqb-recent-quotes-heading">Recent Quotes</h3>
                <p>Latest saved or edited quotes</p>
            </div>

            <?php if (empty($recent_quotes)) : ?>
                <div class="ofqb-empty-state">
                    <strong>No quotes yet</strong>
                    <p>Create a quote and it will appear here.</p>
                </div>
            <?php else : ?>
                <?php $quotes_for_table = $recent_quotes; ?>
                <?php include OFQB_PLUGIN_DIR . 'templates/quote-table.php'; ?>
            <?php endif; ?>
        </section>
    </section>
</div>
