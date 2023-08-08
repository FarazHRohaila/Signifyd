<?php
/*
Plugin Name: Signifyd Ecommerce
Plugin URI: #
Description: Signifyd's ecommerce fraud protection platform helps protect from fraudulent payments over WooCommerce using NMI.
Version: 3.0
Author: Faraz Hussain
Author URI: #
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wp-signifyd
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('Signifyd_URL', plugin_dir_url(__FILE__));
define('Signifyd_Meta', 'signify_meta');

// Including required files from includes folder
require_once(plugin_dir_path(__FILE__) . 'includes/class-signify-essentials.php');
require_once(plugin_dir_path(__FILE__) . 'includes/class-signify-settings.php');
require_once(plugin_dir_path(__FILE__) . 'includes/class-signify-init.php');
require_once(plugin_dir_path(__FILE__) . 'includes/class-signify-status-box.php');


//Plugin Activation
function Signfy_Activation()
{
    $args = [
        'signifyApi' => '',
        'signifySessionId' => '',
        'NmiKey' => ''
    ];

    add_option('signfyd_opt', $args);

    $plugin_db = new Essentials(Signifyd_Meta);

    $check = $plugin_db->create_table();     
	
}

register_activation_hook(__FILE__, 'Signfy_Activation');

//Adding Admin Menu For Signifyd Settings
function admin_menu() {
	$SignifySettings= new SignifySettings;
	add_menu_page(
		'Signifyd Orders',            
		'Signifyd', 
		'manage_options',         
		'signify_settings',        
		[$SignifySettings, 'render_page'],   
		'dashicons-chart-pie',
		130                         
	);
}

add_action('admin_menu', 'admin_menu');
