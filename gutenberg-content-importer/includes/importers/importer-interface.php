<?php
/**
 * Importer Interface
 *
 * @package GCI\Importers
 */

namespace GCI\Importers;

interface Importer_Interface {
    /**
     * Get importer name
     *
     * @return string
     */
    public function get_name();

    /**
     * Get importer slug
     *
     * @return string
     */
    public function get_slug();

    /**
     * Check if URL/content can be imported by this importer
     *
     * @param string $url_or_content URL or content to check
     * @return bool
     */
    public function can_import($url_or_content);

    /**
     * Preview import without creating post
     *
     * @param string $url_or_content URL or content to preview
     * @return array Preview data
     */
    public function preview($url_or_content);

    /**
     * Import content and create post
     *
     * @param string $url_or_content URL or content to import
     * @param array $options Import options
     * @return array Import result
     */
    public function import($url_or_content, $options = []);

    /**
     * Get supported features
     *
     * @return array
     */
    public function get_supported_features();
} 