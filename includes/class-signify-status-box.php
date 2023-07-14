<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Order_Details_Metabox {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_metabox'));
    }

    // Add metabox to order details page
    public function add_metabox() {
        add_meta_box(
            'order_details_metabox',
            'Signifyd Status',
            array($this, 'render_metabox'),
            'shop_order',
            'side',
            'core'
        );
    }

    // Render metabox content
    public function render_metabox($post) {
        $obj = new Essentials(Signifyd_Meta);
        return print_r($obj->search_status($post->ID));
    }

}

// Instantiate the class
new Order_Details_Metabox();
