<?php
/**
 * Server-Sent Events Controller for Real-time Updates
 *
 * @package GCI\API
 */

namespace GCI\API;

use GCI\Core\Progress_Tracker;

class SSE_Controller {
    /**
     * Initialize SSE endpoints
     */
    public function init() {
        // Register AJAX endpoint for SSE
        add_action('wp_ajax_gci_sse_progress', [$this, 'handle_sse_request']);
        add_action('wp_ajax_nopriv_gci_sse_progress', [$this, 'handle_sse_request']);
    }

    /**
     * Handle SSE request
     */
    public function handle_sse_request() {
        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'gci-sse')) {
            wp_die('Unauthorized', 403);
        }

        // Get import ID
        $import_id = isset($_GET['import_id']) ? sanitize_text_field($_GET['import_id']) : '';
        if (empty($import_id)) {
            wp_die('Missing import ID', 400);
        }

        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable Nginx buffering

        // Disable output buffering
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);
        @ob_end_clean();

        // Send initial connection
        $this->send_sse_message('connected', ['import_id' => $import_id]);

        // Keep connection alive and send updates
        $last_update_time = 0;
        $max_execution_time = ini_get('max_execution_time') ?: 30;
        $start_time = time();
        
        while (true) {
            // Check if client disconnected
            if (connection_aborted()) {
                break;
            }

            // Get current progress
            $progress = Progress_Tracker::get_progress($import_id);
            
            if ($progress) {
                // Check for updates
                if ($progress['updated_at'] > $last_update_time) {
                    $this->send_sse_message('progress', $progress);
                    $last_update_time = $progress['updated_at'];
                    
                    // If import is complete, failed, or cancelled, close connection
                    if (in_array($progress['state'], [
                        Progress_Tracker::STATE_COMPLETED,
                        Progress_Tracker::STATE_FAILED,
                        Progress_Tracker::STATE_CANCELLED
                    ])) {
                        $this->send_sse_message('close', ['reason' => $progress['state']]);
                        break;
                    }
                }
            } else {
                // Import not found
                $this->send_sse_message('error', ['message' => 'Import not found']);
                break;
            }

            // Send heartbeat every 15 seconds
            if (time() - $start_time > 0 && (time() - $start_time) % 15 === 0) {
                $this->send_sse_message('heartbeat', ['timestamp' => time()]);
            }

            // Prevent timeout (leave 5 seconds buffer)
            if (time() - $start_time > $max_execution_time - 5) {
                $this->send_sse_message('reconnect', ['reason' => 'timeout']);
                break;
            }

            // Sleep for 500ms
            usleep(500000);
            
            // Flush output
            @ob_flush();
            @flush();
        }

        exit;
    }

    /**
     * Send SSE message
     *
     * @param string $event Event name
     * @param mixed $data Event data
     * @param string $id Optional event ID
     */
    private function send_sse_message($event, $data, $id = null) {
        if ($id) {
            echo "id: $id\n";
        }
        
        echo "event: $event\n";
        echo "data: " . json_encode($data) . "\n\n";
        
        @ob_flush();
        @flush();
    }

    /**
     * Generate SSE URL
     *
     * @param string $import_id Import ID
     * @return string SSE URL
     */
    public static function get_sse_url($import_id) {
        return add_query_arg([
            'action' => 'gci_sse_progress',
            'import_id' => $import_id,
            'nonce' => wp_create_nonce('gci-sse'),
        ], admin_url('admin-ajax.php'));
    }
}

