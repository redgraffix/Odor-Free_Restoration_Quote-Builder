<?php

if (!defined('ABSPATH')) {
    exit;
}

$quotes_by_user = !empty($quote_admin_stats['quotes_by_user']) ? $quote_admin_stats['quotes_by_user'] : array();
$quotes_by_month = !empty($quote_admin_stats['quotes_by_month']) ? $quote_admin_stats['quotes_by_month'] : array();
$max_user_total = 1;
$max_month_total = 1;

foreach ($quotes_by_user as $user_stat) {
    $max_user_total = max($max_user_total, (int) $user_stat->total);
}

foreach ($quotes_by_month as $month_stat) {
    $max_month_total = max($max_month_total, (int) $month_stat['total']);
}

$line_points = array();
$chart_width = 420;
$chart_height = 126;
$point_count = max(1, count($quotes_by_month) - 1);

foreach ($quotes_by_month as $index => $month_stat) {
    $x = 18 + (($chart_width - 36) * ($index / $point_count));
    $y = 108 - (((int) $month_stat['total'] / $max_month_total) * 88);
    $line_points[] = round($x, 2) . ',' . round($y, 2);
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
            <div>
                <dt>Deleted</dt>
                <dd><?php echo esc_html($quote_admin_stats['deleted_quotes']); ?></dd>
            </div>
            <div>
                <dt>Active Quote Value</dt>
                <dd><?php echo esc_html(OFQB_Quotes::money($quote_admin_stats['total_value'])); ?></dd>
            </div>
        </dl>

        <section class="ofqb-admin-chart-panel" aria-label="Quote activity snapshots">
            <div class="ofqb-admin-chart-heading">
                <h3>Stats</h3>
                <p>Quick quote activity snapshots. Deleted quotes are not included.</p>
            </div>

            <div class="ofqb-admin-charts">
                <div class="ofqb-admin-chart-card">
                    <label>
                        <span>Bar Graph</span>
                        <select aria-label="Bar graph metric">
                            <option>Total quotes by user</option>
                        </select>
                    </label>

                    <div class="ofqb-bar-chart">
                        <?php if (!empty($quotes_by_user)) : ?>
                            <?php foreach ($quotes_by_user as $user_stat) : ?>
                                <?php $bar_width = max(6, ((int) $user_stat->total / $max_user_total) * 100); ?>
                                <div class="ofqb-bar-row">
                                    <span><?php echo esc_html($user_stat->label); ?></span>
                                    <i style="width: <?php echo esc_attr(number_format($bar_width, 2)); ?>%;"></i>
                                    <strong><?php echo esc_html((int) $user_stat->total); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="ofqb-chart-empty">No active quote activity yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="ofqb-admin-chart-card">
                    <label>
                        <span>Line Graph</span>
                        <select aria-label="Line graph metric">
                            <option>Quotes created per month</option>
                        </select>
                    </label>

                    <p class="ofqb-chart-high">High: <?php echo esc_html($max_month_total); ?></p>
                    <svg class="ofqb-line-chart" viewBox="0 0 <?php echo esc_attr($chart_width); ?> <?php echo esc_attr($chart_height); ?>" role="img" aria-label="Quotes created per month">
                        <line x1="18" y1="108" x2="402" y2="108"></line>
                        <?php if (!empty($line_points)) : ?>
                            <polyline points="<?php echo esc_attr(implode(' ', $line_points)); ?>"></polyline>
                            <?php foreach ($line_points as $line_point) : ?>
                                <?php list($point_x, $point_y) = array_map('floatval', explode(',', $line_point)); ?>
                                <circle cx="<?php echo esc_attr($point_x); ?>" cy="<?php echo esc_attr($point_y); ?>" r="2.5"></circle>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </svg>
                    <?php if (!empty($quotes_by_month)) : ?>
                        <div class="ofqb-line-chart-labels">
                            <span><?php echo esc_html($quotes_by_month[0]['label']); ?></span>
                            <span><?php echo esc_html($quotes_by_month[count($quotes_by_month) - 1]['label']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <p><a class="ofqb-button ofqb-button--secondary" href="<?php echo esc_url($base_url); ?>">Back to Quote Home</a></p>
    </section>
</div>
