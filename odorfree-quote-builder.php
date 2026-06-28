<?php
/**
 * Plugin Name: Odor-Free Restoration Quote Builder
 * Description: Private quote builder for Odor-Free Restoration service quotes.
 * Version: 0.3.7
 * Author: Redgraffix
 * Text Domain: odorfree-quote-builder
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OFQB_VERSION', '0.3.7');
define('OFQB_PLUGIN_FILE', __FILE__);
define('OFQB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OFQB_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once OFQB_PLUGIN_DIR . 'includes/class-database.php';
require_once OFQB_PLUGIN_DIR . 'includes/class-roles.php';
require_once OFQB_PLUGIN_DIR . 'includes/class-icons.php';
require_once OFQB_PLUGIN_DIR . 'includes/class-quotes.php';
require_once OFQB_PLUGIN_DIR . 'includes/class-pdf.php';
require_once OFQB_PLUGIN_DIR . 'includes/class-shortcode.php';

register_activation_hook(__FILE__, function () {
    OFQB_Database::activate();
    OFQB_Roles::activate();
});

add_action('plugins_loaded', function () {
    OFQB_Database::maybe_upgrade();
    OFQB_Roles::activate();
    OFQB_Roles::init();
    OFQB_PDF::init();
    OFQB_Shortcode::init();
});
