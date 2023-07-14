<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Class to manage create, read and update functions into the database structure

class Essentials {
    private $table_name;

    public function __construct($table_name) {
         $this->table_name = $table_name;
    }

    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_name = $wpdb->prefix . $this->table_name;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
			$sql = "CREATE TABLE IF NOT EXISTS $table_name  (
				id INT(11) NOT NULL AUTO_INCREMENT,
				order_id INT(11),
				status VARCHAR(255) NULL,
				response TEXT NULL,
				sig_status TEXT NULL,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id)
			) ENGINE=InnoDB $charset_collate;";

            require_once(ABSPATH ."wp-admin/includes/upgrade.php");
            $result = dbDelta($sql);
			if ( empty( $result ) ) {
				echo "The table was created successfully!";
			} else {
				echo "There was an error creating the table: " . $wpdb->last_error;
			}
        }
    }
	
	public function drop_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . $this->table_name;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if ($table_exists) {
            $sql = "DROP TABLE IF EXISTS $this->table_name;";
            $wpdb->query($sql);
        }    
    }
    
    public function insert_data($order_id, $status, $sig_status, $data){ 

	global $wpdb;     
    $table_name = $wpdb->prefix . $this->table_name;       
    $wpdb->insert($table_name, array(
        'order_id' => $order_id,
        'status' => $status,
        'response' => $data,
		'sig_status' => $sig_status
        )
    ); 
    
    }
	public function update_data($order_id, $status, $sig_status, $data){ 

	global $wpdb;     
    $table_name = $wpdb->prefix . $this->table_name;       
    $wpdb->update($table_name, array(
        'order_id' => $order_id,
        'status' => $status,
        'response' => $data,
		'sig_status' => $sig_status
        ),
		array(
			'order_id' => $order_id,
		)
    ); 
    
    }

    public function search_status($order_id){ 

        global $wpdb;     
        $table_name = $wpdb->prefix . $this->table_name;       
        $query = $wpdb->prepare("SELECT sig_status FROM $table_name WHERE order_id = %d", $order_id);
        $row = $wpdb->get_row($query);
		if (!empty($row)) {
			return ($row->sig_status);
		}else{
			return "Status not found";
		}
        
    }
    public function get_data() {
        
        global $wpdb;
    
        $table_name = $wpdb->prefix . $this->table_name;       
        
        $data = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
    
        return $data;
    }

}
