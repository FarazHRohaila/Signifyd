<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Class to create logs

class Logger {

    private $logs = [];

    public function log($message) {
        $this->logs[] = $message;
    }

    public function saveLogsToFile($order_file) {
        if (empty($this->logs)) {
            return false; // No logs to save
        }

        $logContent = implode(PHP_EOL, $this->logs);

        // Save logs to file
        $upload_dir = wp_upload_dir();
        $upload_dir = $upload_dir['basedir'];
        $upload_dir = $upload_dir. "/signifylogs/";
        if ( !file_exists( $upload_dir ) && !is_dir( $upload_dir ) ) {
            mkdir( $upload_dir );       
        } 
        $filename = $upload_dir.$order_file.".log";
        file_put_contents($filename, $logContent, FILE_APPEND | LOCK_EX);

        // Clear the logs after saving
        $this->logs = [];

        return true;
    }
}