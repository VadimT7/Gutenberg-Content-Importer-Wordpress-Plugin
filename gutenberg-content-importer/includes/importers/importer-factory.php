<?php
/**
 * Importer Factory
 *
 * @package GCI\Importers
 */

namespace GCI\Importers;

class Importer_Factory {
    /**
     * Available importers
     *
     * @var array
     */
    private static $importers = [];

    /**
     * Initialize factory and register importers
     */
    public static function init() {
        self::register_importers();
    }

    /**
     * Register all available importers
     */
    private static function register_importers() {
        // Register Medium importer
        self::register('medium', 'GCI\Importers\Medium_Importer');

        // Register Notion importer
        self::register('notion', 'GCI\Importers\Notion_Importer');

        // Register Google Docs importer
        self::register('google-docs', 'GCI\Importers\Google_Docs_Importer');

        // Register Markdown importer
        self::register('markdown', 'GCI\Importers\Markdown_Importer');

        // Allow third-party importers to register
        do_action('gci_register_importers', self::class);
    }

    /**
     * Register an importer
     *
     * @param string $slug Importer slug
     * @param string $class_name Importer class name
     */
    public static function register($slug, $class_name) {
        self::$importers[$slug] = $class_name;
    }

    /**
     * Create importer instance
     *
     * @param string $slug Importer slug
     * @return Importer_Interface
     * @throws \Exception If importer not found
     */
    public static function create($slug) {
        if (!isset(self::$importers[$slug])) {
            throw new \Exception(
                sprintf(
                    __('Importer "%s" not found', 'gutenberg-content-importer'),
                    $slug
                )
            );
        }

        $class_name = self::$importers[$slug];

        if (!class_exists($class_name)) {
            throw new \Exception(
                sprintf(
                    __('Importer class "%s" not found', 'gutenberg-content-importer'),
                    $class_name
                )
            );
        }

        $importer = new $class_name();

        if (!$importer instanceof Importer_Interface) {
            throw new \Exception(
                sprintf(
                    __('Importer "%s" must implement Importer_Interface', 'gutenberg-content-importer'),
                    $class_name
                )
            );
        }

        return $importer;
    }

    /**
     * Get all registered importers
     *
     * @return array
     */
    public static function get_importers() {
        $importers = [];

        foreach (self::$importers as $slug => $class_name) {
            try {
                $importer = self::create($slug);
                $importers[$slug] = [
                    'name' => $importer->get_name(),
                    'slug' => $importer->get_slug(),
                    'features' => $importer->get_supported_features(),
                ];
            } catch (\Exception $e) {
                // Skip invalid importers
                continue;
            }
        }

        return $importers;
    }

    /**
     * Detect importer from URL or content
     *
     * @param string $url_or_content URL or content to check
     * @return string|null Importer slug or null if not detected
     */
    public static function detect_importer($url_or_content) {
        foreach (self::$importers as $slug => $class_name) {
            try {
                $importer = self::create($slug);
                if ($importer->can_import($url_or_content)) {
                    return $slug;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }
} 