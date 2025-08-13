<?php
/**
 * REST API Controller
 *
 * @package GCI\API
 */

namespace GCI\API;

use GCI\Importers\Importer_Factory;
use GCI\API\SSE_Controller;

class REST_Controller {
    /**
     * Namespace
     *
     * @var string
     */
    protected $namespace = 'gci/v1';

    /**
     * Initialize REST API
     */
    public function init() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST routes
     */
    public function register_routes() {
        // Import preview
        register_rest_route($this->namespace, '/import/preview', [
            'methods' => 'POST',
            'callback' => [$this, 'preview_import'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'source' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'url' => [
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
                'content' => [
                    'type' => 'string',
                    'sanitize_callback' => 'wp_kses_post',
                ],
            ],
        ]);

        // Process import
        register_rest_route($this->namespace, '/import/process', [
            'methods' => 'POST',
            'callback' => [$this, 'process_import'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'source' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'url' => [
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
                'content' => [
                    'type' => 'string',
                    'sanitize_callback' => 'wp_kses_post',
                ],
                'options' => [
                    'type' => 'object',
                    'default' => [],
                ],
            ],
        ]);

        // Get importers
        register_rest_route($this->namespace, '/importers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_importers'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Get import history
        register_rest_route($this->namespace, '/history', [
            'methods' => 'GET',
            'callback' => [$this, 'get_history'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Delete history entry
        register_rest_route($this->namespace, '/history/(?P<id>[\w-]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_history_entry'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);

        // Detect importer
        register_rest_route($this->namespace, '/detect', [
            'methods' => 'POST',
            'callback' => [$this, 'detect_importer'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'url' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
            ],
        ]);
    }

    /**
     * Check permission
     *
     * @return bool
     */
    public function check_permission() {
        return \current_user_can('edit_posts');
    }

    /**
     * Preview import
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function preview_import($request) {
        $source = $request->get_param('source');
        $url = $request->get_param('url');
        $content = $request->get_param('content');

        try {
            $importer = Importer_Factory::create($source);
            
            // For Markdown, always use content; for others, use URL if available
            $input = ($source === 'markdown') ? $content : ($url ?: $content);
            $preview = $importer->preview($input);

            return new \WP_REST_Response($preview, 200);
        } catch (\Exception $e) {
            return new \WP_Error(
                'import_preview_failed',
                $e->getMessage(),
                ['status' => 400]
            );
        }
    }

    /**
     * Process import
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function process_import($request) {
        $source = $request->get_param('source');
        $url = $request->get_param('url');
        $content = $request->get_param('content');
        $options = $request->get_param('options');

        // Generate import ID for progress tracking
        $import_id = uniqid('import_');
        $options['import_id'] = $import_id;

        // Schedule the import to run in background
        wp_schedule_single_event(time() + 1, 'gci_process_import_background', [
            'source' => $source,
            'url' => $url,
            'content' => $content,
            'options' => $options,
        ]);

        // Return import ID immediately for progress tracking
        return new \WP_REST_Response([
            'import_id' => $import_id,
            'sse_url' => SSE_Controller::get_sse_url($import_id),
        ], 200);
    }

    /**
     * Get available importers
     *
     * @return WP_REST_Response
     */
    public function get_importers() {
        $importers = Importer_Factory::get_importers();
        return new \WP_REST_Response($importers, 200);
    }

    /**
     * Get import history
     *
     * @return WP_REST_Response
     */
    public function get_history() {
        $history = get_option('gci_import_history', []);
        return new \WP_REST_Response($history, 200);
    }

    /**
     * Delete history entry
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function delete_history_entry($request) {
        $id = $request->get_param('id');
        $history = get_option('gci_import_history', []);

        // Filter out the entry
        $history = array_filter($history, function($entry) use ($id) {
            return $entry['id'] !== $id;
        });

        // Re-index array
        $history = array_values($history);

        update_option('gci_import_history', $history);

        return new \WP_REST_Response(['success' => true], 200);
    }

    /**
     * Detect importer for URL
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function detect_importer($request) {
        $url = $request->get_param('url');
        
        $detected = Importer_Factory::detect_importer($url);

        if ($detected) {
            return new \WP_REST_Response([
                'success' => true,
                'importer' => $detected,
            ], 200);
        }

        return new \WP_REST_Response([
            'success' => false,
            'message' => __('No suitable importer found for this URL', 'gutenberg-content-importer'),
        ], 200);
    }

    /**
     * Save import to history
     *
     * @param array $result Import result
     */
    protected function save_import_history($result) {
        if (!$result['success']) {
            return;
        }

        $history = \get_option('gci_import_history', []);
        
        $entry = [
            'id' => uniqid('import_'),
            'date' => \current_time('mysql'),
            'source' => $result['source'],
            'title' => $result['title'],
            'post_id' => $result['post_id'],
            'url' => $result['url'] ?? '',
            'user_id' => \get_current_user_id(),
        ];

        array_unshift($history, $entry);

        // Keep only last 100 imports
        $history = array_slice($history, 0, 100);

        \update_option('gci_import_history', $history);
    }
} 