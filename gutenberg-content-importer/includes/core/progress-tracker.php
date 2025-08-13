<?php
/**
 * Progress Tracker for Import Operations
 *
 * @package GCI\Core
 */

namespace GCI\Core;

class Progress_Tracker {
    /**
     * Progress data storage
     */
    private static $progress = [];

    /**
     * Import states
     */
    const STATE_IDLE = 'idle';
    const STATE_FETCHING = 'fetching';
    const STATE_PARSING = 'parsing';
    const STATE_CONVERTING = 'converting';
    const STATE_DOWNLOADING_IMAGES = 'downloading_images';
    const STATE_CREATING_POST = 'creating_post';
    const STATE_COMPLETED = 'completed';
    const STATE_FAILED = 'failed';
    const STATE_CANCELLED = 'cancelled';

    /**
     * Initialize progress for an import
     *
     * @param string $import_id Unique import ID
     * @param array $data Initial data
     */
    public static function init_progress($import_id, $data = []) {
        $progress_data = [
            'id' => $import_id,
            'state' => self::STATE_IDLE,
            'progress' => 0,
            'message' => __('Initializing import...', 'gutenberg-content-importer'),
            'details' => '',
            'started_at' => current_time('timestamp'),
            'updated_at' => current_time('timestamp'),
            'data' => $data,
            'cancellable' => true,
            'cancelled' => false,
            'error' => null,
        ];

        // Store in transient for persistence
        set_transient('gci_import_progress_' . $import_id, $progress_data, HOUR_IN_SECONDS);
        self::$progress[$import_id] = $progress_data;

        return $import_id;
    }

    /**
     * Update progress
     *
     * @param string $import_id Import ID
     * @param string $state Current state
     * @param int $progress Progress percentage (0-100)
     * @param string $message Status message
     * @param string $details Additional details
     */
    public static function update_progress($import_id, $state, $progress, $message, $details = '') {
        $progress_data = self::get_progress($import_id);
        
        if (!$progress_data) {
            return false;
        }

        // Check if import was cancelled
        if ($progress_data['cancelled']) {
            return false;
        }

        $progress_data['state'] = $state;
        $progress_data['progress'] = max(0, min(100, $progress));
        $progress_data['message'] = $message;
        $progress_data['details'] = $details;
        $progress_data['updated_at'] = current_time('timestamp');

        // Update transient
        set_transient('gci_import_progress_' . $import_id, $progress_data, HOUR_IN_SECONDS);
        self::$progress[$import_id] = $progress_data;

        // Trigger SSE update
        self::trigger_sse_update($import_id, $progress_data);

        return true;
    }

    /**
     * Get progress for an import
     *
     * @param string $import_id Import ID
     * @return array|null Progress data or null
     */
    public static function get_progress($import_id) {
        // Check memory first
        if (isset(self::$progress[$import_id])) {
            return self::$progress[$import_id];
        }

        // Check transient
        $progress_data = get_transient('gci_import_progress_' . $import_id);
        if ($progress_data) {
            self::$progress[$import_id] = $progress_data;
            return $progress_data;
        }

        return null;
    }

    /**
     * Mark import as completed
     *
     * @param string $import_id Import ID
     * @param array $result Import result
     */
    public static function complete_import($import_id, $result = []) {
        self::update_progress(
            $import_id,
            self::STATE_COMPLETED,
            100,
            __('Import completed successfully!', 'gutenberg-content-importer'),
            json_encode($result)
        );

        // Clean up after delay
        wp_schedule_single_event(time() + 300, 'gci_cleanup_progress', [$import_id]);
    }

    /**
     * Mark import as failed
     *
     * @param string $import_id Import ID
     * @param string $error Error message
     */
    public static function fail_import($import_id, $error) {
        $progress_data = self::get_progress($import_id);
        
        if ($progress_data) {
            $progress_data['state'] = self::STATE_FAILED;
            $progress_data['error'] = $error;
            $progress_data['updated_at'] = current_time('timestamp');
            
            set_transient('gci_import_progress_' . $import_id, $progress_data, HOUR_IN_SECONDS);
            self::$progress[$import_id] = $progress_data;
            
            self::trigger_sse_update($import_id, $progress_data);
        }

        // Clean up after delay
        wp_schedule_single_event(time() + 300, 'gci_cleanup_progress', [$import_id]);
    }

    /**
     * Cancel an import
     *
     * @param string $import_id Import ID
     * @return bool Success
     */
    public static function cancel_import($import_id) {
        $progress_data = self::get_progress($import_id);
        
        if (!$progress_data || !$progress_data['cancellable']) {
            return false;
        }

        $progress_data['cancelled'] = true;
        $progress_data['state'] = self::STATE_CANCELLED;
        $progress_data['message'] = __('Import cancelled by user', 'gutenberg-content-importer');
        $progress_data['updated_at'] = current_time('timestamp');

        set_transient('gci_import_progress_' . $import_id, $progress_data, HOUR_IN_SECONDS);
        self::$progress[$import_id] = $progress_data;

        self::trigger_sse_update($import_id, $progress_data);

        // Clean up
        wp_schedule_single_event(time() + 60, 'gci_cleanup_progress', [$import_id]);

        return true;
    }

    /**
     * Clean up old progress data
     *
     * @param string $import_id Import ID
     */
    public static function cleanup_progress($import_id) {
        delete_transient('gci_import_progress_' . $import_id);
        unset(self::$progress[$import_id]);
    }

    /**
     * Get all active imports
     *
     * @return array Active imports
     */
    public static function get_active_imports() {
        global $wpdb;
        
        $active_imports = [];
        
        // Query transients for active imports
        $transients = $wpdb->get_results(
            "SELECT option_name, option_value 
             FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_gci_import_progress_%' 
             AND option_name NOT LIKE '_transient_timeout_%'"
        );

        foreach ($transients as $transient) {
            $progress_data = maybe_unserialize($transient->option_value);
            if (is_array($progress_data) && 
                !in_array($progress_data['state'], [self::STATE_COMPLETED, self::STATE_FAILED, self::STATE_CANCELLED])) {
                $active_imports[] = $progress_data;
            }
        }

        return $active_imports;
    }

    /**
     * Trigger SSE update
     *
     * @param string $import_id Import ID
     * @param array $progress_data Progress data
     */
    private static function trigger_sse_update($import_id, $progress_data) {
        // Store update in a transient for SSE to pick up
        $sse_data = [
            'import_id' => $import_id,
            'data' => $progress_data,
            'timestamp' => microtime(true),
        ];
        
        set_transient('gci_sse_update_' . $import_id, $sse_data, 30);
        
        // Trigger custom action for extensibility
        do_action('gci_progress_updated', $import_id, $progress_data);
    }

    /**
     * Get SSE updates for an import
     *
     * @param string $import_id Import ID
     * @return array|null SSE update data
     */
    public static function get_sse_update($import_id) {
        $sse_data = get_transient('gci_sse_update_' . $import_id);
        if ($sse_data) {
            delete_transient('gci_sse_update_' . $import_id);
            return $sse_data;
        }
        return null;
    }
}

// Register cleanup action
add_action('gci_cleanup_progress', ['GCI\Core\Progress_Tracker', 'cleanup_progress']);

