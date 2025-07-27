<?php
/**
 * Main Plugin Class
 *
 * @package GCI\Core
 */

namespace GCI\Core;

use GCI\Admin\Admin_Menu;
use GCI\Admin\Import_Screen;
use GCI\API\REST_Controller;
use GCI\Importers\Importer_Factory;

class Plugin {
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private static $instance = null;

    /**
     * Get plugin instance
     *
     * @return Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain
        add_action('init', [$this, 'load_textdomain']);

        // Initialize components
        $this->init_hooks();
        $this->init_admin();
        $this->init_api();
        $this->init_importers();

        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'gutenberg-content-importer',
            false,
            dirname(GCI_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add plugin action links
        add_filter('plugin_action_links_' . GCI_PLUGIN_BASENAME, [$this, 'add_action_links']);

        // AJAX handlers
        add_action('wp_ajax_gci_preview_import', [$this, 'ajax_preview_import']);
        add_action('wp_ajax_gci_process_import', [$this, 'ajax_process_import']);
        add_action('wp_ajax_gci_get_import_history', [$this, 'ajax_get_import_history']);
    }

    /**
     * Initialize admin interface
     */
    private function init_admin() {
        if (!is_admin()) {
            return;
        }

        $admin_menu = new Admin_Menu();
        $admin_menu->init();
    }

    /**
     * Initialize REST API
     */
    private function init_api() {
        $rest_controller = new REST_Controller();
        $rest_controller->init();
    }

    /**
     * Initialize importers
     */
    private function init_importers() {
        Importer_Factory::init();
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'gutenberg-content-importer') === false) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'gci-admin',
            GCI_PLUGIN_URL . 'assets/css/admin.css',
            [],
            GCI_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'gci-admin',
            GCI_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-api-fetch', 'wp-components', 'wp-element', 'wp-blocks', 'wp-block-editor'],
            GCI_VERSION,
            true
        );

        // Localize script
        wp_localize_script('gci-admin', 'gciAdmin', [
            'apiUrl' => rest_url('gci/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'ajaxNonce' => wp_create_nonce('gci-ajax'),
            'strings' => [
                'importing' => __('Importing...', 'gutenberg-content-importer'),
                'import_complete' => __('Import complete!', 'gutenberg-content-importer'),
                'import_error' => __('Import failed. Please try again.', 'gutenberg-content-importer'),
                'preview_loading' => __('Loading preview...', 'gutenberg-content-importer'),
            ],
        ]);
    }

    /**
     * Add plugin action links
     *
     * @param array $links Existing links
     * @return array
     */
    public function add_action_links($links) {
        $action_links = [
            '<a href="' . admin_url('admin.php?page=gutenberg-content-importer') . '">' . 
                __('Import Content', 'gutenberg-content-importer') . '</a>',
            '<a href="' . admin_url('admin.php?page=gci-settings') . '">' . 
                __('Settings', 'gutenberg-content-importer') . '</a>',
        ];

        return array_merge($action_links, $links);
    }

    /**
     * AJAX handler for import preview
     */
    public function ajax_preview_import() {
        check_ajax_referer('gci-ajax', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'gutenberg-content-importer'));
        }

        $source = sanitize_text_field($_POST['source'] ?? '');
        $url = esc_url_raw($_POST['url'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');

        try {
            $importer = Importer_Factory::create($source);
            $preview = $importer->preview($url ?: $content);

            wp_send_json_success($preview);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX handler for processing import
     */
    public function ajax_process_import() {
        check_ajax_referer('gci-ajax', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'gutenberg-content-importer'));
        }

        $source = sanitize_text_field($_POST['source'] ?? '');
        $url = esc_url_raw($_POST['url'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        $options = json_decode(stripslashes($_POST['options'] ?? '{}'), true);

        try {
            $importer = Importer_Factory::create($source);
            $result = $importer->import($url ?: $content, $options);

            // Save to import history
            $this->save_import_history($result);

            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX handler for getting import history
     */
    public function ajax_get_import_history() {
        check_ajax_referer('gci-ajax', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'gutenberg-content-importer'));
        }

        $history = get_option('gci_import_history', []);
        wp_send_json_success($history);
    }

    /**
     * Save import to history
     *
     * @param array $result Import result
     */
    private function save_import_history($result) {
        $history = get_option('gci_import_history', []);
        
        $entry = [
            'id' => uniqid('import_'),
            'date' => current_time('mysql'),
            'source' => $result['source'],
            'title' => $result['title'],
            'post_id' => $result['post_id'],
            'url' => $result['url'] ?? '',
            'user_id' => get_current_user_id(),
        ];

        array_unshift($history, $entry);

        // Keep only last 100 imports
        $history = array_slice($history, 0, 100);

        update_option('gci_import_history', $history);
    }
} 