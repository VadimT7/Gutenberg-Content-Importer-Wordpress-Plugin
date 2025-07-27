<?php
/**
 * Plugin Activation Handler
 *
 * @package GCI\Core
 */

namespace GCI\Core;

class Activator {
    /**
     * Activate the plugin
     */
    public static function activate() {
        // Create database tables if needed
        self::create_tables();

        // Set default options
        self::set_default_options();

        // Create upload directory
        self::create_upload_directory();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Import queue table for batch processing
        $table_name = $wpdb->prefix . 'gci_import_queue';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source varchar(50) NOT NULL,
            url text,
            content longtext,
            options longtext,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            post_id bigint(20) UNSIGNED DEFAULT NULL,
            error_message text,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $defaults = [
            'gci_version' => GCI_VERSION,
            'gci_import_history' => [],
            'gci_settings' => [
                'default_post_status' => 'draft',
                'default_post_type' => 'post',
                'preserve_formatting' => true,
                'download_images' => true,
                'optimize_images' => true,

                'preserve_metadata' => true,
                'notion_api_key' => '',
                'google_client_id' => '',
                'google_client_secret' => '',
            ],
        ];

        foreach ($defaults as $option_name => $default_value) {
            if (false === get_option($option_name)) {
                add_option($option_name, $default_value);
            }
        }
    }

    /**
     * Create upload directory for imported content
     */
    private static function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $gci_dir = $upload_dir['basedir'] . '/gci-imports';

        if (!file_exists($gci_dir)) {
            wp_mkdir_p($gci_dir);
            
            // Add .htaccess for security
            $htaccess = $gci_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'Options -Indexes');
            }
        }
    }
} 