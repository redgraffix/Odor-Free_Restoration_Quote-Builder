# Odor-Free Restoration Quote Builder

Private WordPress quote-builder plugin for Odor-Free Restoration LLC.

The plugin gives logged-in sales users a dedicated quote workspace where they can create service quotes, save drafts, search existing quotes, generate customer-facing PDF quotes, download/print those PDFs, and email them through WordPress mail/FluentSMTP.

## Project Purpose

Odor-Free Restoration is a service-based business, so this builder intentionally does not include WooCommerce product syncing, price book imports, CSV parsing, or product dropdowns. Quote lines are freeform:

- Services: description, hours, rate, calculated line total
- Materials: description, unit cost, quantity, calculated line total

The final PDF follows the Odor-Free service quote mockup and includes company branding, sales/client/accounting details, line-item tables, editable terms, calculated totals, an approval block, and footer contact information.

## Features

- Frontend shortcode: `[odorfree_quote_builder]`
- Logged-in quote home dashboard
- Create/edit quote form
- Draft and generated quote workflows
- Search quotes with keyword search, sorting, order controls, and creator filtering
- My Quotes view for the current salesperson
- Soft-delete workflow with deleted quote list and restore support
- Admin tools page with quote summary stats and activity charts
- Generated quote preview
- Print-specific layout
- Server-generated PDF download
- Email PDF modal with editable subject/message
- Automatic BCC to `sales@odorfreerestoration.com`
- FluentSMTP-compatible email delivery through `wp_mail()`
- Custom database tables for quotes, service rows, and material rows
- Role/capability setup on plugin activation

## Installation

Install the plugin folder at:

```text
wp-content/plugins/odorfree-quote-builder
```

Then activate **Odor-Free Restoration Quote Builder** in WordPress.

Add the shortcode to the quote-builder page:

```text
[odorfree_quote_builder]
```

The live site currently uses:

```text
https://odorfreerestoration.com/quote-builder/
```

## Requirements

- WordPress
- PHP supported by the host WordPress install
- Logged-in users for quote access
- FluentSMTP or another configured mailer for reliable PDF email delivery

No Composer, npm, WooCommerce, or external PDF library is required.

## Technical Overview

Main plugin bootstrap:

```text
odorfree-quote-builder.php
```

Core classes:

```text
includes/class-database.php   Activation and database schema
includes/class-roles.php      Quote-builder capabilities
includes/class-shortcode.php  Shortcode routing and frontend screen selection
includes/class-quotes.php     Quote CRUD, validation, totals, search, stats
includes/class-pdf.php        PDF rendering, PDF download, PDF email
includes/class-icons.php      Small inline icons used by the dashboard
```

Templates:

```text
templates/quote-home.php       Main dashboard
templates/quote-form.php       Create/revise/draft form
templates/quote-preview.php    Generated quote preview and actions
templates/quote-table.php      Shared quote-list table and filters
templates/quote-search.php     Search and deleted quote views
templates/my-quotes.php        Current-user quote list
templates/admin-tools-placeholder.php
```

Assets:

```text
assets/quote-builder.css       Builder UI, preview, print styles
assets/quote-builder.js        Dynamic rows, totals, readiness, email modal
assets/images/quote-header.jpg PDF/preview header art
assets/images/icon-phone.svg   Quote footer icon
assets/images/icon-globe.svg   Quote footer icon
```

## Data Model

The plugin creates custom WordPress tables using the configured table prefix:

```text
{prefix}ofqb_quotes
{prefix}ofqb_quote_services
{prefix}ofqb_quote_materials
```

Quotes store customer, accounts payable, salesperson, status, terms, tax, totals, creator, editor, and soft-delete metadata.

Service and material rows are stored separately and replaced when a quote is revised.

## Quote Statuses

- `draft`: saved but not ready for PDF/email
- `generated`: completed quote
- `revised`: existing quote regenerated after edits
- `sent`: quote PDF successfully emailed
- `closed`: available status for completed sales workflow
- `deleted`: soft-deleted and hidden from active quote lists

Deleted quotes are not removed from the database. They can be viewed from the deleted quote list and restored from the quote detail page.

## PDF and Email Flow

PDF downloads use:

```text
admin-post.php?action=ofqb_download_pdf
```

Email delivery uses an authenticated AJAX request:

```text
wp_ajax_ofqb_email_pdf
```

The plugin generates a PDF file in WordPress uploads under:

```text
uploads/odorfree-quote-builder/
```

Then sends it with `wp_mail()`. FluentSMTP handles the actual SMTP transport on the live site.

## Development Notes

- Keep the builder independent of the public site header/footer.
- Use the existing shortcode/template/class structure before adding new abstractions.
- Do not add WooCommerce or price book logic; Odor-Free quotes are freeform service/material quotes.
- Preserve print/PDF styling when changing preview markup.
- Keep destructive actions red; use green for primary positive actions and gray/blue for neutral navigation or utility actions.
- PDF rendering is custom PHP in `includes/class-pdf.php`, not browser print or a third-party PDF package.

## Packaging

For deployment, zip the plugin folder contents so WordPress receives:

```text
odorfree-quote-builder/
  odorfree-quote-builder.php
  includes/
  templates/
  assets/
```

Do not zip the parent repository folder if it would add an extra directory level.
