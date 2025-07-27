<?php
/**
 * Admin Menu Handler
 *
 * @package GCI\Admin
 */

namespace GCI\Admin;

class Admin_Menu {
    /**
     * Initialize admin menu
     */
    public function init() {
        add_action('admin_menu', [$this, 'add_menu_pages']);
    }

    /**
     * Add menu pages
     */
    public function add_menu_pages() {
        // Main menu page
        add_menu_page(
            __('Content Importer', 'gutenberg-content-importer'),
            __('Content Importer', 'gutenberg-content-importer'),
            'edit_posts',
            'gutenberg-content-importer',
            [$this, 'render_import_page'],
            'dashicons-download',
            30
        );

        // Import page (same as main)
        add_submenu_page(
            'gutenberg-content-importer',
            __('Import Content', 'gutenberg-content-importer'),
            __('Import', 'gutenberg-content-importer'),
            'edit_posts',
            'gutenberg-content-importer',
            [$this, 'render_import_page']
        );

        // History page
        add_submenu_page(
            'gutenberg-content-importer',
            __('Import History', 'gutenberg-content-importer'),
            __('History', 'gutenberg-content-importer'),
            'edit_posts',
            'gci-history',
            [$this, 'render_history_page']
        );

        // Settings page
        add_submenu_page(
            'gutenberg-content-importer',
            __('Importer Settings', 'gutenberg-content-importer'),
            __('Settings', 'gutenberg-content-importer'),
            'manage_options',
            'gci-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Render import page
     */
    public function render_import_page() {
        $import_screen = new Import_Screen();
        $import_screen->render();
    }

    /**
     * Render history page
     */
    public function render_history_page() {
        $history_screen = new History_Screen();
        $history_screen->render();
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $settings_screen = new Settings_Screen();
        $settings_screen->render();
    }
} 