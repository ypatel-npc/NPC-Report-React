<?php
/**
 * Plugin Name: NPC Report React V2
 * Description: NPC REPORTS
 * Version: 2.0.0
 * Author: Yash patel
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NPC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('NPC_PLUGIN_URL', plugin_dir_url(__FILE__));

// // Register REST API routes
// add_action('rest_api_init', function() {
//     register_rest_route('npc-report/v1', '/stats', [
//         'methods' => 'GET',
//         'callback' => function() {
//             return rest_ensure_response([
//                 [
//                     'title' => 'Total Sales',
//                     'value' => '$45,250.00',
//                     'icon' => 'dashicons-money-alt',
//                     'color' => '#4CAF50'
//                 ],
//                 [
//                     'title' => 'Total Orders',
//                     'value' => '156',
//                     'icon' => 'dashicons-cart',
//                     'color' => '#2196F3'
//                 ],
//                 [
//                     'title' => 'Total Customers',
//                     'value' => '89',
//                     'icon' => 'dashicons-groups',
//                     'color' => '#FF9800'
//                 ],
//                 [
//                     'title' => 'Average Order',
//                     'value' => '$290.06',
//                     'icon' => 'dashicons-chart-bar',
//                     'color' => '#9C27B0'
//                 ]
//             ]);
//         },
//         'permission_callback' => '__return_true'
//     ]);
// });

// Require the autoloader
require_once NPC_PLUGIN_PATH . 'vendor/autoload.php';

// Initialize plugin
function npc_init() {
    $loader = new MyWPReact\Core\Loader();
    $loader->init();
}
add_action('plugins_loaded', 'npc_init');