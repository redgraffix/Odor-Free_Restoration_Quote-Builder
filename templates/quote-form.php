<?php

if (!defined('ABSPATH')) {
    exit;
}

$states = array('AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY');
$salesperson_email = isset($current_user->user_email) ? $current_user->user_email : '';
?>

<div class="odorfree-quote-builder" data-ofqb>
    <div class="ofqb-header">
        <div>
            <h2>Service Quote Form - Quote # Pending</h2>
            <p>Build a service quote with labor, materials, tax, terms, and approval details.</p>
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
        <div class="ofqb-message ofqb-message--notice">
            Quote saving and PDF generation are coming next. This form pass is ready for layout and math testing.
        </div>

        <section class="ofqb-section" aria-labelledby="ofqb-client-heading">
            <h3 id="ofqb-client-heading">1. Client Information</h3>

            <div class="ofqb-grid">
                <label>
                    <span>Name*</span>
                    <input type="text" name="customer_name" required data-ofqb-required>
                </label>

                <label>
                    <span>Company*</span>
                    <input type="text" name="customer_company" required data-ofqb-required>
                </label>

                <label>
                    <span>Phone*</span>
                    <input type="tel" name="customer_phone" inputmode="numeric" autocomplete="tel" maxlength="12" placeholder="___-___-____" pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" required data-ofqb-phone data-ofqb-required>
                </label>

                <label>
                    <span>Email*</span>
                    <input type="email" name="customer_email" required data-ofqb-email data-ofqb-required>
                </label>

                <label class="ofqb-grid-wide">
                    <span>Address*</span>
                    <input type="text" name="customer_address" required data-ofqb-required>
                </label>

                <label class="ofqb-grid-wide">
                    <span>Address 2</span>
                    <input type="text" name="customer_address_2" placeholder="Apartment, suite, unit, etc.">
                </label>

                <label>
                    <span>City*</span>
                    <input type="text" name="customer_city" required data-ofqb-required>
                </label>

                <label>
                    <span>State*</span>
                    <select name="customer_state" required data-ofqb-required>
                        <option value="">Select state</option>
                        <?php foreach ($states as $state) : ?>
                            <option value="<?php echo esc_attr($state); ?>" <?php selected('OH', $state); ?>><?php echo esc_html($state); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Zip*</span>
                    <input type="text" name="customer_zip" required data-ofqb-required>
                </label>
            </div>

            <div class="ofqb-subsection">
                <h4>Accounts Payable</h4>
                <div class="ofqb-grid">
                    <label>
                        <span>Name*</span>
                        <input type="text" name="accounts_payable_name" required data-ofqb-required>
                    </label>

                    <label>
                        <span>Phone*</span>
                        <input type="tel" name="accounts_payable_phone" inputmode="numeric" autocomplete="tel" maxlength="12" placeholder="___-___-____" pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" required data-ofqb-phone data-ofqb-required>
                    </label>

                    <label>
                        <span>Email*</span>
                        <input type="email" name="accounts_payable_email" required data-ofqb-email data-ofqb-required>
                    </label>

                    <label>
                        <span>Tax ID</span>
                        <input type="text" name="customer_tax_id">
                    </label>
                </div>
            </div>
        </section>

        <section class="ofqb-section" aria-labelledby="ofqb-services-heading">
            <div class="ofqb-section-heading">
                <h3 id="ofqb-services-heading">2. Services</h3>
                <button type="button" class="ofqb-button ofqb-button--small" data-ofqb-add-service>Add Service</button>
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
                        <tr data-ofqb-service-row>
                            <td data-label="Service Description"><textarea name="services[0][description]" rows="3" placeholder="Input service type or description"></textarea></td>
                            <td data-label="Hrs"><input type="number" name="services[0][hours]" min="0" step="0.25" value="0" data-ofqb-hours></td>
                            <td data-label="Rate"><input type="number" name="services[0][rate]" min="0" step="0.01" value="0.00" data-ofqb-rate></td>
                            <td data-label="Total"><strong data-ofqb-line-total>$0.00</strong></td>
                            <td data-label="Actions"><button type="button" class="ofqb-remove-button" data-ofqb-remove-row>Remove</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="ofqb-section" aria-labelledby="ofqb-materials-heading">
            <div class="ofqb-section-heading">
                <h3 id="ofqb-materials-heading">3. Materials</h3>
                <button type="button" class="ofqb-button ofqb-button--small" data-ofqb-add-material>Add Material</button>
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
                        <tr data-ofqb-material-row>
                            <td data-label="Materials"><input type="text" name="materials[0][description]" placeholder="Input product or material item"></td>
                            <td data-label="Unit Cost"><input type="number" name="materials[0][unit_cost]" min="0" step="0.01" value="0.00" data-ofqb-unit-cost></td>
                            <td data-label="QTY"><input type="number" name="materials[0][quantity]" min="0" step="1" value="0" data-ofqb-quantity></td>
                            <td data-label="Total"><strong data-ofqb-line-total>$0.00</strong></td>
                            <td data-label="Actions"><button type="button" class="ofqb-remove-button" data-ofqb-remove-row>Remove</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="ofqb-section ofqb-summary-section" aria-labelledby="ofqb-summary-heading">
            <div>
                <h3 id="ofqb-summary-heading">4. Terms and Approval</h3>
                <label class="ofqb-terms-field">
                    <span>Terms and Conditions</span>
                    <textarea name="terms" rows="7"><?php echo esc_textarea(OFQB_Quotes::get_default_terms()); ?></textarea>
                </label>

                <div class="ofqb-approval-box">
                    <h4>Approval</h4>
                    <div class="ofqb-grid">
                        <label class="ofqb-grid-wide">
                            <span>Approved By</span>
                            <input type="text" name="approved_by">
                        </label>
                        <label>
                            <span>Signature</span>
                            <input type="text" name="approval_signature">
                        </label>
                        <label>
                            <span>Date</span>
                            <input type="date" name="approval_date">
                        </label>
                    </div>
                </div>
            </div>

            <aside class="ofqb-totals" aria-label="Quote totals">
                <div class="ofqb-total-row">
                    <span>Subtotal</span>
                    <strong data-ofqb-subtotal>$0.00</strong>
                </div>
                <label class="ofqb-total-row">
                    <span>Tax Rate</span>
                    <input type="number" name="tax_rate" value="8" min="0" max="100" step="0.01" data-ofqb-tax-rate>
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
            <button type="submit" class="ofqb-button">Generate Quote</button>
            <button type="submit" class="ofqb-button ofqb-button--secondary" formnovalidate>Save as Draft</button>
            <a class="ofqb-button ofqb-button--secondary" href="<?php echo esc_url($base_url); ?>">Home</a>
        </div>
    </form>
</div>
