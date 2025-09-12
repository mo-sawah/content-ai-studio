<?php
/**
 * Plugin Name: Content AI Studio
 * Description: Your all-in-one AI toolkit to transform articles into compelling images, podcasts, and more.
 * Version: 1.6.393
 * Author: Mohamed Sawah
 * Author URI: https://sawahsolutions.com/content-ai-studio
 * Text Domain: content-ai-studio
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ATM_VERSION', '1.6.393');
define('ATM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ATM_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include all the necessary files
require_once ATM_PLUGIN_PATH . 'includes/class-atm-main.php';

// Initialize the main plugin class
function atm_run_plugin() {
    new ATM_Main();
}
add_action('plugins_loaded', 'atm_run_plugin');

// Activation hook for creating assets
register_activation_hook(__FILE__, array('ATM_Main', 'activate'));