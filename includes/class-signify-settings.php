<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class SignifySettings {
    public function __construct() {
        // Add hooks or initialization code for the settings page
    }

    public function render_page() {
       // Check if the form is submitted
    if (isset($_POST['submit']) && isset($_POST['signify_nonce_field']) && wp_verify_nonce($_POST['signify_nonce_field'], 'settings')) {
        extract($_POST);
        // Save the submitted options
        $args = [
            "signifyApi" => sanitize_text_field($apikey),
            "signifySessionId" => sanitize_text_field($sessionId),
            "NmiKey" => sanitize_text_field($NmiSecurityKey),
        ];
        update_option('signfyd_opt', $args);
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
    }

    // Retrieve the saved options
    $settings = get_option('signfyd_opt');

    // Display the form
    ?>
    <style>
        /* Add CSS styles to customize the form appearance */
        table.form-table {
            width: 100%;
			max-width:600px;
            border-collapse: collapse;
        }
        
        table.form-table td {
            padding: 10px;
        }
        
        table.form-table input[type="text"],
        table.form-table input[type="email"],
        table.form-table textarea {
            width: 100%;
            padding: 5px 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        table.form-table button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
		table.form-table label{
			font-weight:bold;
		}
        table.form-table button:hover {
            background-color: #45a049;
        }
    </style>
    <div class="wrap">
        <h1>Signifyd Settings</h1>
        <form method="POST" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
            <?php wp_nonce_field('settings', 'signify_nonce_field'); ?>
            <table class="form-table">
            <tr>
                <td><label for="apikey">Api Key:</label></td>
                <td><input type="text" id="apikey" name="apikey" value="<?php echo esc_attr($settings['signifyApi']); ?>" /></td>
            </tr>
			<tr>
                <td><label for="sessionId">Session ID:</label></td>
                <td><input type="text" id="sessionId" name="sessionId" value="<?php echo esc_attr($settings['signifySessionId']); ?>" /></td>
            </tr>
			<tr>
                <td><label for="NmiSecurityKey">Nmi Security Key:</label></td>
                <td><input type="text" id="NmiSecurityKey" name="NmiSecurityKey" value="<?php echo esc_attr($settings['NmiKey']); ?>" /></td>
            </tr>
            <tr>
                <td colspan="2"><input type="submit" name="submit" value="Save" class="button button-primary" />
</td>
            </tr>
            </table>
        </form>
    </div>
    <?php
    }
}