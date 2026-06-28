<?php

if (!defined('ABSPATH')) {
    exit;
}

$quote_creator = !empty($saved_quote->created_by_user_id) ? get_userdata((int) $saved_quote->created_by_user_id) : null;
$quote_creator_email = $quote_creator && $quote_creator->user_email ? $quote_creator->user_email : $saved_quote->salesperson_email;
$can_modify_quote = OFQB_Quotes::current_user_can_modify_quote($saved_quote);
$quote_header_path = OFQB_PLUGIN_DIR . 'assets/images/quote-header.jpg';
$quote_header_version = file_exists($quote_header_path) ? filemtime($quote_header_path) : OFQB_VERSION;
?>

<div class="odorfree-quote-builder" data-ofqb>
    <?php if (!empty($quote_action_error)) : ?>
        <div class="ofqb-message ofqb-message--error"><?php echo esc_html($quote_action_error); ?></div>
    <?php endif; ?>

    <?php if (!empty($show_saved_notice)) : ?>
        <div class="ofqb-message ofqb-message--success">
            Quote <?php echo esc_html($saved_quote->quote_number); ?> was saved.
        </div>
    <?php endif; ?>

    <?php if (!empty($quote_action_notice)) : ?>
        <div class="ofqb-message ofqb-message--success"><?php echo esc_html($quote_action_notice); ?></div>
    <?php endif; ?>

    <div class="ofqb-preview-wrap">
        <article class="ofqb-quote-preview">
            <header class="ofqb-preview-header">
                <img src="<?php echo esc_url(add_query_arg('v', $quote_header_version, OFQB_PLUGIN_URL . 'assets/images/quote-header.jpg')); ?>" alt="Odor-Free Restoration LLC - Service Quote">
            </header>

            <section class="ofqb-preview-meta">
                <div>
                    <p><strong>Quote:</strong> <?php echo esc_html($saved_quote->quote_number); ?></p>
                    <p><strong>Date:</strong> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($saved_quote->created_at))); ?></p>
                    <br>
                    <p><?php echo esc_html($saved_quote->salesperson_name ? $saved_quote->salesperson_name : $salesperson_name); ?></p>
                    <p><strong>Email:</strong> <?php echo esc_html($quote_creator_email); ?></p>
                    <p><strong>Phone:</strong> 866-4-NO-ODOR (466-6367)</p>
                    <?php if (!empty($saved_quote->salesperson_cell)) : ?>
                        <p><strong>Cell:</strong> <?php echo esc_html($saved_quote->salesperson_cell); ?></p>
                    <?php endif; ?>
                    <br>
                    <p><strong class="ofqb-green-text">Odor-Free Restoration LLC</strong></p>
                    <p>3065 Nationwide Parkway</p>
                    <p>Brunswick Ohio 44212</p>
                </div>

                <div>
                    <p><?php echo esc_html($saved_quote->customer_name); ?></p>
                    <p><?php echo esc_html($saved_quote->customer_company); ?></p>
                    <p><strong>Phone:</strong> <?php echo esc_html($saved_quote->customer_phone); ?></p>
                    <p><strong>Email:</strong> <?php echo esc_html($saved_quote->customer_email); ?></p>
                    <br>
                    <p><?php echo esc_html($saved_quote->customer_address); ?></p>
                    <?php if (!empty($saved_quote->customer_address_2)) : ?>
                        <p><?php echo esc_html($saved_quote->customer_address_2); ?></p>
                    <?php endif; ?>
                    <p><?php echo esc_html(trim($saved_quote->customer_city . ' ' . $saved_quote->customer_state . ' ' . $saved_quote->customer_zip)); ?></p>
                    <br>
                    <p><strong>Accounts Payable</strong></p>
                    <p><?php echo esc_html($saved_quote->accounts_payable_name); ?></p>
                    <?php if (!empty($saved_quote->customer_tax_id)) : ?>
                        <p><strong>Tax ID:</strong> <?php echo esc_html($saved_quote->customer_tax_id); ?></p>
                    <?php endif; ?>
                    <p><strong>Phone:</strong> <?php echo esc_html($saved_quote->accounts_payable_phone); ?></p>
                    <p><strong>Email:</strong> <?php echo esc_html($saved_quote->accounts_payable_email); ?></p>
                </div>
            </section>

            <section class="ofqb-preview-table-section">
                <table class="ofqb-preview-table">
                    <thead>
                        <tr>
                            <th>Service Description</th>
                            <th>Hrs</th>
                            <th>Rate</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($saved_services as $service) : ?>
                            <tr>
                                <td><?php echo nl2br(esc_html($service->service_description)); ?></td>
                                <td><?php echo esc_html(rtrim(rtrim(number_format((float) $service->hours, 2, '.', ''), '0'), '.')); ?></td>
                                <td><?php echo esc_html(OFQB_Quotes::money($service->rate)); ?></td>
                                <td><?php echo esc_html(OFQB_Quotes::money($service->line_total)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section class="ofqb-preview-table-section">
                <table class="ofqb-preview-table">
                    <thead>
                        <tr>
                            <th>Materials</th>
                            <th>Unit Cost</th>
                            <th>QTY</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($saved_materials as $material) : ?>
                            <tr>
                                <td><?php echo esc_html($material->material_description); ?></td>
                                <td><?php echo esc_html(OFQB_Quotes::money($material->unit_cost)); ?></td>
                                <td><?php echo esc_html(rtrim(rtrim(number_format((float) $material->quantity, 2, '.', ''), '0'), '.')); ?></td>
                                <td><?php echo esc_html(OFQB_Quotes::money($material->line_total)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section class="ofqb-preview-bottom">
                <div class="ofqb-preview-terms">
                    <p><strong>Terms and Conditions:</strong></p>
                    <p><?php echo nl2br(esc_html($saved_quote->terms)); ?></p>
                </div>
                <div class="ofqb-preview-totals">
                    <div><span>Subtotal</span><strong><?php echo esc_html(OFQB_Quotes::money($saved_quote->subtotal)); ?></strong></div>
                    <div><span>Tax Rate</span><strong><?php echo esc_html(rtrim(rtrim(number_format((float) $saved_quote->tax_rate, 2, '.', ''), '0'), '.')); ?>%</strong></div>
                    <div class="ofqb-preview-total-grand"><span>Total</span><strong><?php echo esc_html(OFQB_Quotes::money($saved_quote->total)); ?></strong></div>
                </div>
            </section>

            <section class="ofqb-preview-approval">
                <h3>Approval:</h3>
                <div><span>Approved By:</span><i></i></div>
                <div class="ofqb-preview-approval-split"><span>Signature:</span><i></i><span>Date:</span><i></i></div>
            </section>
        </article>
    </div>

    <div class="ofqb-preview-actions">
        <p><strong>Status:</strong> <?php echo esc_html(ucwords(str_replace('_', ' ', $saved_quote->status))); ?></p>
        <div class="ofqb-action-grid">
            <button type="button" class="ofqb-button" data-ofqb-print-quote>Print Quote</button>
            <button type="button" class="ofqb-button ofqb-button--secondary" disabled>Download PDF Soon</button>
            <button type="button" class="ofqb-button ofqb-button--secondary" disabled>Email PDF Soon</button>
            <?php if ($can_modify_quote && 'deleted' !== $saved_quote->status) : ?>
                <a class="ofqb-button ofqb-button--secondary" href="<?php echo esc_url(add_query_arg(array('ofqb_quote_id' => (int) $saved_quote->id, 'ofqb_mode' => 'revise'), $base_url)); ?>">Revise Quote</a>
                <form action="" method="post">
                    <?php wp_nonce_field('ofqb_quote_action_' . (int) $saved_quote->id, 'ofqb_quote_action_nonce'); ?>
                    <input type="hidden" name="ofqb_action" value="delete_quote">
                    <button type="submit" class="ofqb-button ofqb-button--danger">Move to Deleted</button>
                </form>
            <?php endif; ?>
            <a class="ofqb-button ofqb-button--secondary" href="<?php echo esc_url($base_url); ?>">Home</a>
        </div>
    </div>
</div>
