<?php

if (!defined('ABSPATH')) {
    exit;
}

$states = array('AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY');
$salesperson_email = isset($current_user->user_email) ? $current_user->user_email : '';
$is_editing_quote = !empty($editing_quote);
$form_title_quote_number = $is_editing_quote ? $editing_quote->quote_number : 'Pending';
$form_services = !empty($editing_services) ? $editing_services : array(null);
$form_materials = !empty($editing_materials) ? $editing_materials : array(null);
$field_value = function ($field, $default = '') use ($is_editing_quote, $editing_quote) {
    if (!$is_editing_quote || !isset($editing_quote->{$field})) {
        return $default;
    }

    return $editing_quote->{$field};
};
?>

<div class="odorfree-quote-builder" data-ofqb>
    <?php if (!empty($quote_action_notice)) : ?>
        <div class="ofqb-message ofqb-message--success"><?php echo esc_html($quote_action_notice); ?></div>
    <?php endif; ?>

    <?php if (!empty($quote_error)) : ?>
        <div class="ofqb-message ofqb-message--error"><?php echo esc_html($quote_error); ?></div>
    <?php endif; ?>

    <div class="ofqb-header">
        <div>
            <h2>Service Quote Form - Quote # <?php echo esc_html($form_title_quote_number); ?></h2>
            <p>Build a service quote with labor, materials, tax, and editable terms.</p>
        </div>
        <div class="ofqb-sales-meta">
            <p><strong>Sales Person:</strong> <?php echo esc_html($salesperson_name); ?></p>
            <p><strong>Email:</strong> <?php echo esc_html($salesperson_email); ?></p>
            <p><strong>Date:</strong> <?php echo esc_html(date_i18n(get_option('date_format'))); ?></p>
        </div>
    </div>

    <form class="ofqb-form" action="" method="post" data-ofqb-form novalidate>
        <?php wp_nonce_field('ofqb_generate_quote', 'ofqb_nonce'); ?>
        <input type="hidden" name="ofqb_action" value="generate_quote">
        <input type="hidden" name="salesperson_name" value="<?php echo esc_attr($salesperson_name); ?>">
        <input type="hidden" name="salesperson_email" value="<?php echo esc_attr($salesperson_email); ?>">
        <?php if ($is_editing_quote) : ?>
            <input type="hidden" name="ofqb_existing_quote_id" value="<?php echo esc_attr((int) $editing_quote->id); ?>">
        <?php endif; ?>
        <div class="ofqb-message ofqb-message--error" data-ofqb-form-alert hidden></div>

        <section class="ofqb-section" aria-labelledby="ofqb-client-heading">
            <h3 id="ofqb-client-heading">1. Client Information</h3>

            <div class="ofqb-grid">
                <label>
                    <span>Name*</span>
                    <input type="text" name="customer_name" value="<?php echo esc_attr($field_value('customer_name')); ?>" required data-ofqb-required>
                </label>

                <label>
                    <span>Company*</span>
                    <input type="text" name="customer_company" value="<?php echo esc_attr($field_value('customer_company')); ?>" required data-ofqb-required>
                </label>

                <label>
                    <span>Phone*</span>
                    <input type="tel" name="customer_phone" value="<?php echo esc_attr($field_value('customer_phone')); ?>" inputmode="numeric" autocomplete="tel" maxlength="12" placeholder="___-___-____" pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" required data-ofqb-phone data-ofqb-required>
                </label>

                <label>
                    <span>Email*</span>
                    <input type="email" name="customer_email" value="<?php echo esc_attr($field_value('customer_email')); ?>" required data-ofqb-email data-ofqb-required>
                </label>

                <label class="ofqb-grid-wide">
                    <span>Address*</span>
                    <input type="text" name="customer_address" value="<?php echo esc_attr($field_value('customer_address')); ?>" required data-ofqb-required>
                </label>

                <label class="ofqb-grid-wide">
                    <span>Address 2</span>
                    <input type="text" name="customer_address_2" value="<?php echo esc_attr($field_value('customer_address_2')); ?>" placeholder="Apartment, suite, unit, etc.">
                </label>

                <label>
                    <span>City*</span>
                    <input type="text" name="customer_city" value="<?php echo esc_attr($field_value('customer_city')); ?>" required data-ofqb-required>
                </label>

                <label>
                    <span>State*</span>
                    <select name="customer_state" required data-ofqb-required>
                        <option value="">Select state</option>
                        <?php foreach ($states as $state) : ?>
                            <option value="<?php echo esc_attr($state); ?>" <?php selected($field_value('customer_state', 'OH'), $state); ?>><?php echo esc_html($state); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Zip*</span>
                    <input type="text" name="customer_zip" value="<?php echo esc_attr($field_value('customer_zip')); ?>" required data-ofqb-required>
                </label>
            </div>

            <div class="ofqb-subsection">
                <h4>Accounts Payable</h4>
                <div class="ofqb-grid">
                    <label>
                        <span>Name*</span>
                        <input type="text" name="accounts_payable_name" value="<?php echo esc_attr($field_value('accounts_payable_name')); ?>" required data-ofqb-required>
                    </label>

                    <label>
                        <span>Phone*</span>
                        <input type="tel" name="accounts_payable_phone" value="<?php echo esc_attr($field_value('accounts_payable_phone')); ?>" inputmode="numeric" autocomplete="tel" maxlength="12" placeholder="___-___-____" pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" required data-ofqb-phone data-ofqb-required>
                    </label>

                    <label>
                        <span>Email*</span>
                        <input type="email" name="accounts_payable_email" value="<?php echo esc_attr($field_value('accounts_payable_email')); ?>" required data-ofqb-email data-ofqb-required>
                    </label>

                    <label>
                        <span>Tax ID</span>
                        <input type="text" name="customer_tax_id" value="<?php echo esc_attr($field_value('customer_tax_id')); ?>">
                    </label>
                </div>
            </div>
        </section>

        <section class="ofqb-section" aria-labelledby="ofqb-services-heading">
            <div class="ofqb-section-heading">
                <h3 id="ofqb-services-heading">2. Services</h3>
            </div>

            <div class="ofqb-line-table-wrap">
                <table class="ofqb-line-table">
                    <thead>
                        <tr>
                            <th>Service Description</th>
                            <th>Hrs</th>
                            <th>Rate</th>
                            <th>Total</th>
                            <th><span class="screen-reader-text">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody data-ofqb-services>
                        <?php foreach ($form_services as $index => $service) : ?>
                            <tr data-ofqb-service-row>
                                <td data-label="Service Description"><textarea name="services[<?php echo esc_attr($index); ?>][description]" rows="3" placeholder="Input service type or description"><?php echo esc_textarea($service ? $service->service_description : ''); ?></textarea></td>
                                <td data-label="Hrs"><input type="number" name="services[<?php echo esc_attr($index); ?>][hours]" min="0" step="0.25" value="<?php echo esc_attr($service ? $service->hours : '0'); ?>" data-ofqb-hours></td>
                                <td data-label="Rate"><input type="number" name="services[<?php echo esc_attr($index); ?>][rate]" min="0" step="0.01" value="<?php echo esc_attr($service ? number_format((float) $service->rate, 2, '.', '') : '0.00'); ?>" data-ofqb-rate></td>
                                <td data-label="Total"><strong data-ofqb-line-total>$0.00</strong></td>
                                <td data-label="Actions"><button type="button" class="ofqb-remove-button" data-ofqb-remove-row>Remove</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="ofqb-line-actions">
                <button type="button" class="ofqb-button ofqb-button--small" data-ofqb-add-service>Add Service</button>
            </div>
        </section>

        <section class="ofqb-section" aria-labelledby="ofqb-materials-heading">
            <div class="ofqb-section-heading">
                <h3 id="ofqb-materials-heading">3. Materials</h3>
            </div>

            <div class="ofqb-line-table-wrap">
                <table class="ofqb-line-table">
                    <thead>
                        <tr>
                            <th>Materials</th>
                            <th>Unit Cost</th>
                            <th>QTY</th>
                            <th>Total</th>
                            <th><span class="screen-reader-text">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody data-ofqb-materials>
                        <?php foreach ($form_materials as $index => $material) : ?>
                            <tr data-ofqb-material-row>
                                <td data-label="Materials"><input type="text" name="materials[<?php echo esc_attr($index); ?>][description]" value="<?php echo esc_attr($material ? $material->material_description : ''); ?>" placeholder="Input product or material item"></td>
                                <td data-label="Unit Cost"><input type="number" name="materials[<?php echo esc_attr($index); ?>][unit_cost]" min="0" step="0.01" value="<?php echo esc_attr($material ? number_format((float) $material->unit_cost, 2, '.', '') : '0.00'); ?>" data-ofqb-unit-cost></td>
                                <td data-label="QTY"><input type="number" name="materials[<?php echo esc_attr($index); ?>][quantity]" min="0" step="1" value="<?php echo esc_attr($material ? $material->quantity : '0'); ?>" data-ofqb-quantity></td>
                                <td data-label="Total"><strong data-ofqb-line-total>$0.00</strong></td>
                                <td data-label="Actions"><button type="button" class="ofqb-remove-button" data-ofqb-remove-row>Remove</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="ofqb-line-actions">
                <button type="button" class="ofqb-button ofqb-button--small" data-ofqb-add-material>Add Material</button>
            </div>
        </section>

        <section class="ofqb-section ofqb-summary-section" aria-labelledby="ofqb-summary-heading">
            <div>
                <h3 id="ofqb-summary-heading">4. Terms and Conditions</h3>
                <label class="ofqb-terms-field">
                    <span>Terms and Conditions</span>
                    <textarea name="terms" rows="7"><?php echo esc_textarea($field_value('terms', OFQB_Quotes::get_default_terms())); ?></textarea>
                </label>
            </div>

            <aside class="ofqb-totals" aria-label="Quote totals">
                <div class="ofqb-total-row">
                    <span>Subtotal</span>
                    <strong data-ofqb-subtotal>$0.00</strong>
                </div>
                <label class="ofqb-total-row">
                    <span>Tax Rate</span>
                    <input type="number" name="tax_rate" value="<?php echo esc_attr($field_value('tax_rate', '8')); ?>" min="0" max="100" step="0.01" data-ofqb-tax-rate>
                </label>
                <div class="ofqb-total-row">
                    <span>Tax</span>
                    <strong data-ofqb-tax-amount>$0.00</strong>
                </div>
                <div class="ofqb-total-row ofqb-total-row--grand">
                    <span>Total</span>
                    <strong data-ofqb-total>$0.00</strong>
                </div>
            </aside>
        </section>

        <div class="ofqb-actions">
            <button type="submit" name="ofqb_submit_intent" value="generate" class="ofqb-button">Generate Quote</button>
            <button type="submit" name="ofqb_submit_intent" value="draft" class="ofqb-button ofqb-button--secondary" formnovalidate>Save as Draft</button>
            <a class="ofqb-button ofqb-button--danger" href="<?php echo esc_url($base_url); ?>" data-ofqb-safe-leave>Cancel</a>
            <a class="ofqb-button ofqb-button--secondary" href="<?php echo esc_url($base_url); ?>" data-ofqb-safe-leave>Home</a>
        </div>

        <aside class="ofqb-readiness" data-ofqb-readiness aria-live="polite">
            <div class="ofqb-readiness__heading">
                <strong>Quote Readiness</strong>
            </div>
            <ul data-ofqb-readiness-list>
                <li>Start filling out the quote to see what is needed.</li>
            </ul>
        </aside>
    </form>
</div>
