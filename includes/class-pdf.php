<?php

if (!defined('ABSPATH')) {
    exit;
}

class OFQB_PDF
{
    public static function init()
    {
        add_action('admin_post_ofqb_download_pdf', array(__CLASS__, 'handle_download'));
        add_action('wp_ajax_ofqb_email_pdf', array(__CLASS__, 'ajax_email_pdf'));
    }

    public static function handle_download()
    {
        if (!is_user_logged_in() || !OFQB_Roles::current_user_can_create_quotes()) {
            wp_die('You must be logged in to download quote PDFs.');
        }

        $quote_id = !empty($_GET['quote_id']) ? absint(wp_unslash($_GET['quote_id'])) : 0;

        if (!$quote_id || empty($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'ofqb_download_pdf_' . $quote_id)) {
            wp_die('Quote PDF could not be downloaded. Please refresh the page and try again.');
        }

        $pdf_path = self::generate_pdf_file($quote_id);

        if (is_wp_error($pdf_path)) {
            wp_die(esc_html($pdf_path->get_error_message()));
        }

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($pdf_path) . '"');
        header('Content-Length: ' . filesize($pdf_path));
        readfile($pdf_path);
        exit;
    }

    public static function ajax_email_pdf()
    {
        if (!is_user_logged_in() || !OFQB_Roles::current_user_can_create_quotes()) {
            wp_send_json_error(array('message' => 'Please log in to email quote PDFs.'), 403);
        }

        $quote_id = !empty($_POST['quote_id']) ? absint(wp_unslash($_POST['quote_id'])) : 0;

        if (!$quote_id || empty($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ofqb_email_pdf_' . $quote_id)) {
            wp_send_json_error(array('message' => 'Quote PDF email could not be verified. Please refresh the page and try again.'), 403);
        }

        $to = self::sanitize_email_list(!empty($_POST['email_to']) ? wp_unslash($_POST['email_to']) : '');
        $cc = self::sanitize_email_list(!empty($_POST['email_cc']) ? wp_unslash($_POST['email_cc']) : '');

        if (is_wp_error($to)) {
            wp_send_json_error(array('message' => $to->get_error_message()), 400);
        }

        if (!$to) {
            wp_send_json_error(array('message' => 'Please enter at least one To email address.'), 400);
        }

        if (is_wp_error($cc)) {
            wp_send_json_error(array('message' => $cc->get_error_message()), 400);
        }

        $quote_bundle = OFQB_Quotes::get_quote_with_items($quote_id);

        if (!$quote_bundle) {
            wp_send_json_error(array('message' => 'Quote could not be found.'), 404);
        }

        $quote = $quote_bundle['quote'];

        if (!OFQB_Quotes::current_user_can_modify_quote($quote)) {
            wp_send_json_error(array('message' => 'You can only email quotes you created.'), 403);
        }

        if ('draft' === $quote->status) {
            wp_send_json_error(array('message' => 'Draft quotes must be completed before they can be emailed.'), 400);
        }

        if ('deleted' === $quote->status) {
            wp_send_json_error(array('message' => 'Deleted quotes must be restored before they can be emailed.'), 400);
        }

        $subject = !empty($_POST['email_subject']) ? sanitize_text_field(wp_unslash($_POST['email_subject'])) : '';
        $message = !empty($_POST['email_message']) ? sanitize_textarea_field(wp_unslash($_POST['email_message'])) : '';

        if ('' === trim($subject)) {
            $subject = 'Odor-Free Restoration Service Quote #' . $quote->quote_number . ' for Review';
        }

        if ('' === trim($message)) {
            $message = OFQB_Quotes::get_default_email_message($quote->quote_number);
        }

        $pdf_path = self::generate_pdf_file($quote_id);

        if (is_wp_error($pdf_path)) {
            wp_send_json_error(array('message' => $pdf_path->get_error_message()), 500);
        }

        $headers = array(
            'Bcc: ' . OFQB_Quotes::get_quote_email_bcc(),
            'Content-Type: text/html; charset=UTF-8',
        );

        if ($cc) {
            $headers[] = 'Cc: ' . implode(', ', $cc);
        }

        $sent = wp_mail($to, $subject, self::format_email_message_html($message), $headers, array($pdf_path));

        if (!$sent) {
            wp_send_json_error(array('message' => 'The quote email could not be sent. Please check FluentSMTP settings.'), 500);
        }

        OFQB_Quotes::mark_quote_sent($quote_id);

        wp_send_json_success(array('message' => 'Quote PDF was emailed.'));
    }

    public static function generate_pdf_file($quote_id)
    {
        $quote_bundle = OFQB_Quotes::get_quote_with_items($quote_id);

        if (!$quote_bundle) {
            return new WP_Error('ofqb_pdf_quote_missing', 'Quote could not be found for PDF creation.');
        }

        if (!OFQB_Quotes::current_user_can_modify_quote($quote_bundle['quote'])) {
            return new WP_Error('ofqb_pdf_quote_forbidden', 'You can only create PDFs for quotes you created.');
        }

        if ('draft' === $quote_bundle['quote']->status) {
            return new WP_Error('ofqb_pdf_quote_draft', 'Draft quotes must be completed before a PDF can be created.');
        }

        if ('deleted' === $quote_bundle['quote']->status) {
            return new WP_Error('ofqb_pdf_quote_deleted', 'Deleted quotes must be restored before a PDF can be created.');
        }

        $upload_dir = wp_upload_dir();

        if (!empty($upload_dir['error'])) {
            return new WP_Error('ofqb_pdf_upload_dir', 'WordPress upload directory is not available.');
        }

        $pdf_dir = trailingslashit($upload_dir['basedir']) . 'odorfree-quote-builder';

        if (!wp_mkdir_p($pdf_dir)) {
            return new WP_Error('ofqb_pdf_dir_failed', 'Quote PDF directory could not be created.');
        }

        $quote = $quote_bundle['quote'];
        $filename = sanitize_file_name($quote->quote_number . '.pdf');
        $pdf_path = trailingslashit($pdf_dir) . $filename;
        $pdf_contents = self::render_pdf($quote, $quote_bundle['services'], $quote_bundle['materials']);

        if (false === file_put_contents($pdf_path, $pdf_contents)) {
            return new WP_Error('ofqb_pdf_write_failed', 'Quote PDF could not be written.');
        }

        return $pdf_path;
    }

    private static function sanitize_email_list($raw)
    {
        $emails = array_filter(array_map('trim', explode(',', (string) $raw)));
        $valid = array();

        foreach ($emails as $email) {
            $sanitized = sanitize_email($email);

            if (!$sanitized || !is_email($sanitized)) {
                return new WP_Error('ofqb_invalid_email_list', 'Please enter valid email addresses separated by commas.');
            }

            $valid[] = $sanitized;
        }

        return array_values(array_unique($valid));
    }

    private static function format_email_message_html($message)
    {
        $message = trim((string) $message);
        $header_url = OFQB_PLUGIN_URL . 'assets/images/quote-header.jpg';
        $message_html = nl2br(esc_html($message));

        return '<div style="margin:0;padding:0;background:#f5f7f8;">'
            . '<div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #d8dee4;font-family:Arial, Helvetica, sans-serif;color:#111;">'
            . '<div style="padding:0;border-bottom:4px solid #1f3e73;text-align:left;">'
            . '<img src="' . esc_url($header_url) . '" alt="Odor-Free Restoration LLC" style="display:block;width:100%;height:auto;border:0;">'
            . '</div>'
            . '<div style="padding:22px;font-size:14px;line-height:1.55;">' . $message_html . '</div>'
            . '<div style="border-top:1px solid #d8dee4;margin:0 22px;"></div>'
            . '<div style="padding:18px 22px 22px;font-size:13px;line-height:1.5;color:#1f3e73;">'
            . '<strong>Odor-Free Restoration LLC</strong><br>'
            . 'Permanent odor removal, not odor masking.<br>'
            . '<a href="tel:18664666367" style="color:#1f3e73;text-decoration:none;">866-4-NO-ODOR (466-6367)</a>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    private static function render_pdf($quote, $services, $materials)
    {
        $pdf = new OFQB_Simple_PDF();
        $header_path = OFQB_PLUGIN_DIR . 'assets/images/quote-header.jpg';
        $header_image = file_exists($header_path) ? $pdf->add_jpeg_image($header_path) : null;
        $pages = array();
        $content = '';
        $fields = array();
        $links = array();
        $page_width = 612;
        $page_height = 792;
        $blue = array(31, 62, 115);
        $light_blue = array(217, 237, 248);
        $green = array(23, 163, 74);
        $gray = array(244, 246, 248);
        $line = array(30, 30, 30);
        $left = 54;
        $right = 558;
        $bottom_limit = 712;
        $table_width = $right - $left;

        $quote_creator = !empty($quote->created_by_user_id) ? get_userdata((int) $quote->created_by_user_id) : null;
        $quote_creator_email = $quote_creator && $quote_creator->user_email ? $quote_creator->user_email : $quote->salesperson_email;

        $add_footer = function () use ($pdf, $blue, $green, $left, $right, &$content, &$links) {
            $footer_y = 734;
            $white = array(255, 255, 255);
            $email_icon_x = $left + 10;
            $phone_icon_x = $left + 214;
            $icon_y = $footer_y + 17;
            $content .= $pdf->rect(0, $footer_y, 612, 34, $blue, null);
            $content .= $pdf->circle($email_icon_x, $icon_y, 8, $white, null, 1.2);
            $content .= $pdf->line_color($email_icon_x - 5, $icon_y, $email_icon_x + 5, $icon_y, $white, 0.9);
            $content .= $pdf->line_color($email_icon_x, $icon_y - 5, $email_icon_x, $icon_y + 5, $white, 0.9);
            $content .= $pdf->line_color($email_icon_x - 4, $icon_y - 3, $email_icon_x + 4, $icon_y - 3, $white, 0.7);
            $content .= $pdf->line_color($email_icon_x - 4, $icon_y + 3, $email_icon_x + 4, $icon_y + 3, $white, 0.7);
            $content .= $pdf->circle($phone_icon_x, $icon_y, 8, $white, null, 1.2);
            $content .= $pdf->polyline(
                array(
                    array($phone_icon_x - 4.5, $icon_y - 3.5),
                    array($phone_icon_x - 2, $icon_y + 3.5),
                    array($phone_icon_x + 4.5, $icon_y + 4.5),
                ),
                $white,
                1.4
            );
            $content .= $pdf->line_color($phone_icon_x - 5.3, $icon_y - 4.8, $phone_icon_x - 2.7, $icon_y - 5.4, $white, 1.4);
            $content .= $pdf->line_color($phone_icon_x + 4.6, $icon_y + 4.8, $phone_icon_x + 5.5, $icon_y + 2.2, $white, 1.4);
            $content .= $pdf->text('Email', $left + 24, $footer_y + 14, 6.5, 'B', $green);
            $content .= $pdf->text('Sales@OdorFreeRestoration.com', $left + 24, $footer_y + 24, 6.5, 'F1', array(255, 255, 255));
            $content .= $pdf->text('Phone', $left + 228, $footer_y + 14, 6.5, 'B', $green);
            $content .= $pdf->text('866-4-NO-ODOR (466-6367)', $left + 228, $footer_y + 24, 6.5, 'F1', array(255, 255, 255));
            $content .= $pdf->text('ODOR-FREE', $right - 96, $footer_y + 14, 8.5, 'B', $green);
            $content .= $pdf->text('RESTORATION LLC', $right - 112, $footer_y + 26, 8.5, 'B', array(255, 255, 255));
            $links[] = array('x' => $left + 20, 'y' => $footer_y + 4, 'w' => 145, 'h' => 26, 'url' => 'mailto:sales@odorfreerestoration.com');
            $links[] = array('x' => $left + 224, 'y' => $footer_y + 4, 'w' => 140, 'h' => 26, 'url' => 'tel:18664666367');
        };

        $finish_page = function () use (&$pages, &$content, &$fields, &$links, $add_footer) {
            $add_footer();
            $pages[] = array(
                'content' => $content,
                'fields' => $fields,
                'links' => $links,
            );
            $content = '';
            $fields = array();
            $links = array();
        };

        $draw_header = function ($continued = false) use ($pdf, $header_image, $quote, $blue, &$content) {
            if ($header_image) {
                $content .= $pdf->image($header_image, 0, 0, 612, 86);
            }

            if ($continued) {
                $content .= $pdf->text('Quote: ' . $quote->quote_number, 54, 112, 8, 'B', $blue);
                $content .= $pdf->text('Service Quote Continued', 250, 112, 10, 'B', $blue);
            }
        };

        $new_content_page = function () use ($finish_page, $draw_header) {
            $finish_page();
            $draw_header(true);

            return 136;
        };

        $ensure_space = function ($y, $needed_height) use ($bottom_limit, $new_content_page) {
            if ($y + $needed_height > $bottom_limit) {
                return $new_content_page();
            }

            return $y;
        };

        $draw_meta = function () use ($pdf, $quote, $quote_creator_email, $left, &$content) {
            $y = 114;
            $content .= $pdf->text('Quote: ' . $quote->quote_number, $left, $y, 8, 'B', array(31, 62, 115));
            $content .= $pdf->text('Date: ' . date_i18n(get_option('date_format'), strtotime($quote->created_at)), $left, $y + 14, 8, 'B', array(31, 62, 115));
            $content .= $pdf->text($quote->salesperson_name, $left, $y + 44, 8);
            $content .= $pdf->text('Email: ' . $quote_creator_email, $left, $y + 58, 8, 'B');
            $content .= $pdf->text('Phone: 866-4-NO-ODOR (466-6367)', $left, $y + 72, 8, 'B');
            $content .= $pdf->text('Odor-Free Restoration LLC', $left, $y + 102, 8, 'B', array(23, 163, 74));
            $content .= $pdf->text('3065 Nationwide Parkway', $left, $y + 116, 8);
            $content .= $pdf->text('Brunswick Ohio 44212', $left, $y + 130, 8);

            $x = 342;
            $content .= $pdf->text($quote->customer_name, $x, $y + 14, 8);
            $content .= $pdf->text($quote->customer_company, $x, $y + 28, 8);
            $content .= $pdf->text('Phone: ' . $quote->customer_phone, $x, $y + 42, 8, 'B');
            $content .= $pdf->text('Email: ' . $quote->customer_email, $x, $y + 56, 8, 'B');
            $content .= $pdf->text($quote->customer_address, $x, $y + 86, 8);

            $address_y = $y + 100;
            if (!empty($quote->customer_address_2)) {
                $content .= $pdf->text($quote->customer_address_2, $x, $address_y, 8);
                $address_y += 14;
            }

            $content .= $pdf->text(trim($quote->customer_city . ' ' . $quote->customer_state . ' ' . $quote->customer_zip), $x, $address_y, 8);
            $content .= $pdf->text('Accounts Payable', $x, $address_y + 30, 8, 'B', array(31, 62, 115));
            $content .= $pdf->text($quote->accounts_payable_name, $x, $address_y + 44, 8);

            $ap_y = $address_y + 58;
            if (!empty($quote->customer_tax_id)) {
                $content .= $pdf->text('Tax ID: ' . $quote->customer_tax_id, $x, $ap_y, 8, 'B');
                $ap_y += 14;
            }

            $content .= $pdf->text('Phone: ' . $quote->accounts_payable_phone, $x, $ap_y, 8, 'B');
            $content .= $pdf->text('Email: ' . $quote->accounts_payable_email, $x, $ap_y + 14, 8, 'B');
        };

        $draw_table_header = function ($title, $headers, $widths, $y) use ($pdf, $left, $table_width, $light_blue, $blue, &$content) {
            $content .= $pdf->rect($left, $y, $table_width, 24, $light_blue, null);
            $content .= $pdf->line_color($left, $y, $left + $table_width, $y, array(18, 166, 204));
            $content .= $pdf->line_color($left, $y + 24, $left + $table_width, $y + 24, array(18, 166, 204));
            $x = $left;

            foreach ($headers as $index => $header) {
                $content .= $pdf->centered_text($header, $x, $y + 15, $widths[$index], 7, 'B', $blue);
                $x += $widths[$index];
            }

            return $y + 24;
        };

        $draw_rows = function ($rows, $kind, $y) use ($pdf, $left, $table_width, $bottom_limit, $gray, &$content, $new_content_page, $draw_table_header) {
            if (empty($rows)) {
                return $y;
            }

            $widths = array(302, 70, 70, 62);
            $headers = 'service' === $kind ? array('Service Description', 'Hrs', 'Rate', 'Total') : array('Materials', 'Unit Cost', 'QTY', 'Total');
            $y = $draw_table_header($headers[0], $headers, $widths, $y);

            foreach ($rows as $index => $row) {
                $description = 'service' === $kind ? $row->service_description : $row->material_description;
                $desc_lines = $pdf->wrap_lines_for_width($description, $widths[0] - 18, 8);
                $row_height = max(28, 12 + (min(4, count($desc_lines)) * 10));

                if ($y + $row_height > $bottom_limit) {
                    $y = $new_content_page();
                    $y = $draw_table_header($headers[0], $headers, $widths, $y);
                }

                if (1 === $index % 2) {
                    $content .= $pdf->rect($left, $y, $table_width, $row_height, $gray, null);
                }

                $content .= $pdf->wrapped_text($description, $left + 10, $y + 15, $widths[0] - 18, 8, 10, 'F1', array(0, 0, 0), 4);

                if ('service' === $kind) {
                    $middle = rtrim(rtrim(number_format((float) $row->hours, 2, '.', ''), '0'), '.');
                    $rate = OFQB_Quotes::money($row->rate);
                    $line_total = OFQB_Quotes::money($row->line_total);
                } else {
                    $middle = OFQB_Quotes::money($row->unit_cost);
                    $rate = rtrim(rtrim(number_format((float) $row->quantity, 2, '.', ''), '0'), '.');
                    $line_total = OFQB_Quotes::money($row->line_total);
                }

                $content .= $pdf->centered_text($middle, $left + $widths[0], $y + 18, $widths[1], 8);
                $content .= $pdf->centered_text($rate, $left + $widths[0] + $widths[1], $y + 18, $widths[2], 8);
                $content .= $pdf->centered_text($line_total, $left + $widths[0] + $widths[1] + $widths[2], $y + 18, $widths[3], 8);
                $y += $row_height;
            }

            return $y + 18;
        };

        $draw_header(false);
        $draw_meta();
        $y = 318;
        $y = $draw_rows($services, 'service', $y);
        $y = $draw_rows($materials, 'material', $y);

        $pdf_terms = OFQB_Quotes::strip_default_terms_intro($quote->terms);
        $terms_lines = $pdf->wrap_lines_for_width($pdf_terms, 270, 8);
        $terms_height = max(72, 26 + (min(7, count($terms_lines)) * 10));
        $totals_height = 84;
        $approval_height = 76;
        $approval_gap = 18;
        $terms_block_height = max($terms_height, $totals_height + 12);
        $y = $ensure_space($y, $terms_block_height + $approval_gap + $approval_height);

        $content .= $pdf->line($left, $y, $right, $y);
        $terms_y = $y + 22;
        $content .= $pdf->text('Terms and Conditions:', $left, $terms_y, 8, 'B', $blue);
        $content .= $pdf->wrapped_text($pdf_terms, $left, $terms_y + 12, 270, 8, 10, 'F1', array(0, 0, 0), 7);

        $totals_x = 350;
        $totals_y = $y + 16;
        $content .= $pdf->rect($totals_x, $totals_y, 120, 28, $light_blue, null);
        $content .= $pdf->rect($totals_x, $totals_y + 28, 120, 28, $light_blue, null);
        $content .= $pdf->rect($totals_x, $totals_y + 56, 120, 28, $blue, null);
        $content .= $pdf->rect($totals_x + 120, $totals_y + 28, 72, 28, $gray, null);
        $content .= $pdf->text('Subtotal', $totals_x + 72, $totals_y + 17, 8, 'B', $blue);
        $content .= $pdf->text('Tax Rate', $totals_x + 72, $totals_y + 45, 8, 'B', $blue);
        $content .= $pdf->text('Total', $totals_x + 86, $totals_y + 73, 8, 'B', array(255, 255, 255));
        $content .= $pdf->text(OFQB_Quotes::money($quote->subtotal), $totals_x + 142, $totals_y + 17, 8, 'B');
        $content .= $pdf->text(rtrim(rtrim(number_format((float) $quote->tax_rate, 2, '.', ''), '0'), '.') . '%', $totals_x + 142, $totals_y + 45, 8, 'B');
        $content .= $pdf->text(OFQB_Quotes::money($quote->total), $totals_x + 142, $totals_y + 73, 8, 'B', $green);

        $approval_y = max($y + $terms_block_height + $approval_gap, $totals_y + 94);
        $approval_y = $ensure_space($approval_y, $approval_height);

        $content .= $pdf->rect($left, $approval_y, $table_width, 76, null, $line);
        $content .= $pdf->text('Approval:', $left + 16, $approval_y + 22, 13, 'F1', $blue);
        $content .= $pdf->text('Approved By:', $left + 16, $approval_y + 44, 8);
        $content .= $pdf->line($left + 72, $approval_y + 45, $right - 16, $approval_y + 45);
        $content .= $pdf->text('Signature:', $left + 16, $approval_y + 62, 8);
        $content .= $pdf->line($left + 66, $approval_y + 63, $left + 310, $approval_y + 63);
        $content .= $pdf->text('Date:', $left + 330, $approval_y + 62, 8);
        $content .= $pdf->line($left + 360, $approval_y + 63, $right - 16, $approval_y + 63);
        $fields[] = array('name' => 'approved_by', 'x' => $left + 72, 'y' => $approval_y + 31, 'w' => $table_width - 88, 'h' => 17);
        $fields[] = array('name' => 'approval_signature', 'x' => $left + 66, 'y' => $approval_y + 49, 'w' => 244, 'h' => 17);
        $fields[] = array('name' => 'approval_date', 'x' => $left + 360, 'y' => $approval_y + 49, 'w' => 128, 'h' => 17);

        $finish_page();

        return $pdf->render_pages($pages, $page_width, $page_height);
    }
}

class OFQB_Simple_PDF
{
    private $objects = array();
    private $images = array();

    public function add_jpeg_image($path)
    {
        $size = getimagesize($path);

        if (!$size || IMAGETYPE_JPEG !== $size[2]) {
            return null;
        }

        $id = $this->add_object(sprintf(
            "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
            $size[0],
            $size[1],
            filesize($path),
            file_get_contents($path)
        ));

        $name = 'Im' . (count($this->images) + 1);
        $this->images[$name] = $id;

        return $name;
    }

    public function render_pages($pages, $page_width, $page_height)
    {
        $catalog_id = $this->reserve_object();
        $pages_id = $this->reserve_object();
        $helvetica_id = $this->add_object('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
        $bold_id = $this->add_object('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');
        $xobjects = '';

        foreach ($this->images as $name => $id) {
            $xobjects .= '/' . $name . ' ' . $id . ' 0 R ';
        }

        $resources = sprintf(
            '<< /Font << /F1 %d 0 R /F2 %d 0 R >> /XObject << %s >> >>',
            $helvetica_id,
            $bold_id,
            $xobjects
        );
        $page_ids = array();
        $field_ids = array();

        foreach ($pages as $page) {
            $page_content = isset($page['content']) ? $page['content'] : '';
            $page_links = isset($page['links']) && is_array($page['links']) ? $page['links'] : array();
            $page_fields = isset($page['fields']) && is_array($page['fields']) ? $page['fields'] : array();
            $content_id = $this->add_object("<< /Length " . strlen($page_content) . " >>\nstream\n" . $page_content . "\nendstream");
            $annotation_ids = array();

            foreach ($page_links as $link) {
                $annotation_ids[] = $this->add_object(sprintf(
                    '<< /Type /Annot /Subtype /Link /Rect [%0.2F %0.2F %0.2F %0.2F] /Border [0 0 0] /A << /S /URI /URI %s >> >>',
                    $link['x'],
                    $page_height - $link['y'] - $link['h'],
                    $link['x'] + $link['w'],
                    $page_height - $link['y'],
                    $this->pdf_string($link['url'])
                ));
            }

            foreach ($page_fields as $field) {
                $field_id = $this->add_object(sprintf(
                    '<< /Type /Annot /Subtype /Widget /FT /Tx /T %s /Rect [%0.2F %0.2F %0.2F %0.2F] /F 4 /Border [0 0 0] /BS << /W 0 >> /DA %s /V () >>',
                    $this->pdf_string($field['name']),
                    $field['x'],
                    $page_height - $field['y'] - $field['h'],
                    $field['x'] + $field['w'],
                    $page_height - $field['y'],
                    $this->pdf_string('/F1 8 Tf 0 g')
                ));
                $annotation_ids[] = $field_id;
                $field_ids[] = $field_id;
            }

            $annots = $annotation_ids ? '/Annots [' . implode(' ', array_map(function ($id) {
                return $id . ' 0 R';
            }, $annotation_ids)) . ']' : '';

            $page_ids[] = $this->add_object(sprintf(
                '<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %d %d] /Resources %s /Contents %d 0 R %s >>',
                $pages_id,
                $page_width,
                $page_height,
                $resources,
                $content_id,
                $annots
            ));
        }

        $acroform = '';

        if ($field_ids) {
            $acroform = sprintf(
                ' /AcroForm << /Fields [%s] /NeedAppearances true /DR << /Font << /F1 %d 0 R >> >> /DA %s >>',
                implode(' ', array_map(function ($id) {
                    return $id . ' 0 R';
                }, $field_ids)),
                $helvetica_id,
                $this->pdf_string('/F1 8 Tf 0 g')
            );
        }

        $this->set_object($catalog_id, sprintf('<< /Type /Catalog /Pages %d 0 R%s >>', $pages_id, $acroform));
        $this->set_object($pages_id, sprintf(
            '<< /Type /Pages /Kids [%s] /Count %d >>',
            implode(' ', array_map(function ($id) {
                return $id . ' 0 R';
            }, $page_ids)),
            count($page_ids)
        ));

        return $this->output($catalog_id);
    }

    public function image($name, $x, $y, $w, $h)
    {
        return sprintf("q %0.2F 0 0 %0.2F %0.2F %0.2F cm /%s Do Q\n", $w, $h, $x, 792 - $y - $h, $name);
    }

    public function rect($x, $y, $w, $h, $fill = null, $stroke = null)
    {
        $commands = '';

        if ($fill) {
            $commands .= $this->color($fill, false);
        }

        if ($stroke) {
            $commands .= $this->color($stroke, true);
        }

        $operator = $fill && $stroke ? 'B' : ($fill ? 'f' : 'S');

        return $commands . sprintf("%0.2F %0.2F %0.2F %0.2F re %s\n", $x, 792 - $y - $h, $w, $h, $operator);
    }

    public function line($x1, $y1, $x2, $y2)
    {
        return sprintf("0 0 0 RG %0.2F %0.2F m %0.2F %0.2F l S\n", $x1, 792 - $y1, $x2, 792 - $y2);
    }

    public function line_color($x1, $y1, $x2, $y2, $color = array(0, 0, 0), $line_width = 1)
    {
        return $this->color($color, true) . sprintf("%0.2F w %0.2F %0.2F m %0.2F %0.2F l S\n", $line_width, $x1, 792 - $y1, $x2, 792 - $y2);
    }

    public function polyline($points, $color = array(0, 0, 0), $line_width = 1)
    {
        if (count($points) < 2) {
            return '';
        }

        $first = array_shift($points);
        $content = $this->color($color, true) . sprintf("%0.2F w %0.2F %0.2F m ", $line_width, $first[0], 792 - $first[1]);

        foreach ($points as $point) {
            $content .= sprintf("%0.2F %0.2F l ", $point[0], 792 - $point[1]);
        }

        return $content . "S\n";
    }

    public function circle($cx, $cy, $r, $stroke = array(0, 0, 0), $fill = null, $line_width = 1)
    {
        $k = 0.5522847498;
        $y = 792 - $cy;
        $content = sprintf("%0.2F w\n", $line_width);

        if ($fill) {
            $content .= $this->color($fill, false);
        }

        if ($stroke) {
            $content .= $this->color($stroke, true);
        }

        $content .= sprintf(
            "%0.2F %0.2F m %0.2F %0.2F %0.2F %0.2F %0.2F %0.2F c %0.2F %0.2F %0.2F %0.2F %0.2F %0.2F c %0.2F %0.2F %0.2F %0.2F %0.2F %0.2F c %0.2F %0.2F %0.2F %0.2F %0.2F %0.2F c %s\n",
            $cx + $r,
            $y,
            $cx + $r,
            $y + ($k * $r),
            $cx + ($k * $r),
            $y + $r,
            $cx,
            $y + $r,
            $cx - ($k * $r),
            $y + $r,
            $cx - $r,
            $y + ($k * $r),
            $cx - $r,
            $y,
            $cx - $r,
            $y - ($k * $r),
            $cx - ($k * $r),
            $y - $r,
            $cx,
            $y - $r,
            $cx + ($k * $r),
            $y - $r,
            $cx + $r,
            $y - ($k * $r),
            $cx + $r,
            $y,
            $fill && $stroke ? 'B' : ($fill ? 'f' : 'S')
        );

        return $content;
    }

    public function text($text, $x, $y, $size = 8, $font = 'F1', $color = array(0, 0, 0))
    {
        $font_name = 'B' === $font ? 'F2' : $font;

        return $this->color($color, false) . sprintf(
            "BT /%s %0.2F Tf 1 0 0 1 %0.2F %0.2F Tm %s Tj ET\n",
            $font_name,
            $size,
            $x,
            792 - $y,
            $this->pdf_string($text)
        );
    }

    public function centered_text($text, $x, $y, $width, $size = 8, $font = 'F1', $color = array(0, 0, 0))
    {
        $text_x = $x + max(0, ($width - $this->measure_text_width($text, $size)) / 2);

        return $this->text($text, $text_x, $y, $size, $font, $color);
    }

    public function wrapped_text($text, $x, $y, $width, $size = 8, $line_height = 11, $font = 'F1', $color = array(0, 0, 0), $max_lines = 0)
    {
        $lines = $this->wrap_lines($text, $width, $size);
        $content = '';

        if ($max_lines > 0) {
            $lines = array_slice($lines, 0, $max_lines);
        }

        foreach ($lines as $index => $line) {
            $content .= $this->text($line, $x, $y + ($index * $line_height), $size, $font, $color);
        }

        return $content;
    }

    public function wrap_lines_for_width($text, $width, $size)
    {
        return $this->wrap_lines($text, $width, $size);
    }

    public function measure_text_width($text, $size)
    {
        return strlen((string) $text) * $size * 0.48;
    }

    private function wrap_lines($text, $width, $size)
    {
        $lines = array();
        $paragraphs = preg_split('/\r\n|\r|\n/', (string) $text);

        foreach ($paragraphs as $paragraph) {
            $words = preg_split('/\s+/', trim($paragraph));
            $line = '';

            foreach ($words as $word) {
                if ('' === $word) {
                    continue;
                }

                $candidate = '' === $line ? $word : $line . ' ' . $word;

                if ($this->measure_text_width($candidate, $size) <= $width) {
                    $line = $candidate;
                    continue;
                }

                if ('' !== $line) {
                    $lines[] = $line;
                }

                $line = $word;
            }

            if ('' !== $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function color($rgb, $stroke)
    {
        $parts = array_map(function ($part) {
            return max(0, min(255, (int) $part)) / 255;
        }, $rgb);

        return sprintf("%0.3F %0.3F %0.3F %s\n", $parts[0], $parts[1], $parts[2], $stroke ? 'RG' : 'rg');
    }

    private function reserve_object()
    {
        $this->objects[] = '';
        return count($this->objects);
    }

    private function add_object($content)
    {
        $this->objects[] = $content;
        return count($this->objects);
    }

    private function set_object($id, $content)
    {
        $this->objects[$id - 1] = $content;
    }

    private function output($catalog_id)
    {
        $pdf = "%PDF-1.4\n";
        $offsets = array(0);

        foreach ($this->objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref_offset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($this->objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($this->objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . (count($this->objects) + 1) . " /Root " . (int) $catalog_id . " 0 R >>\n";
        $pdf .= "startxref\n" . $xref_offset . "\n%%EOF";

        return $pdf;
    }

    private function pdf_string($text)
    {
        $text = wp_check_invalid_utf8((string) $text);
        $text = str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $text);

        return '(' . $text . ')';
    }
}
