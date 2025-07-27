<?php
/**
 * Image Handler - Downloads and processes images
 *
 * @package GCI\Utils
 */

namespace GCI\Utils;

// Ensure media functions are available
if (!function_exists('download_url')) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
}
if (!function_exists('media_handle_sideload')) {
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
}

class Image_Handler {
    /**
     * Import image from URL
     *
     * @param string $url Image URL
     * @param int $post_id Associated post ID
     * @return int|false Attachment ID or false on failure
     */
    public function import_image($url, $post_id = 0) {
        if (empty($url) || !is_string($url)) {
            error_log('GCI Image Handler: Invalid URL provided');
            return false;
        }

        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            error_log('GCI Image Handler: Invalid URL format: ' . $url);
            return false;
        }

        // Check if image already imported
        $existing_id = $this->get_attachment_id_by_url($url);
        if ($existing_id) {
            return $existing_id;
        }

        // Download image
        $tmp_file = \download_url($url);

        if (\is_wp_error($tmp_file)) {
            error_log('GCI Image Handler: Failed to download image from ' . $url . ' - ' . $tmp_file->get_error_message());
            return false;
        }

        // Get file info
        $file_info = pathinfo($url);
        $file_name = $file_info['basename'];

        // Sanitize filename
        $file_name = \sanitize_file_name($file_name);

        // Make sure we have a proper extension
        if (empty($file_info['extension'])) {
            $file_type = \wp_check_filetype($tmp_file);
            if (!empty($file_type['ext'])) {
                $file_name .= '.' . $file_type['ext'];
            }
        }

        // Prepare file array
        $file = [
            'name' => $file_name,
            'tmp_name' => $tmp_file,
        ];

        // Upload file
        $upload = \media_handle_sideload($file, $post_id);

        // Clean up temp file
        @unlink($tmp_file);

        if (\is_wp_error($upload)) {
            error_log('GCI Image Handler: Failed to upload image - ' . $upload->get_error_message());
            return false;
        }

        // Store original URL as meta
        \update_post_meta($upload, '_gci_original_url', $url);

        // Optimize image if enabled
        $settings = \get_option('gci_settings', []);
        if (!empty($settings['optimize_images'])) {
            $this->optimize_image($upload);
        }

        return $upload;
    }

    /**
     * Get attachment ID by original URL
     *
     * @param string $url Original URL
     * @return int|false Attachment ID or false
     */
    protected function get_attachment_id_by_url($url) {
        global $wpdb;

        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta 
            WHERE meta_key = '_gci_original_url' 
            AND meta_value = %s 
            LIMIT 1",
            $url
        ));

        return $attachment_id ? intval($attachment_id) : false;
    }

    /**
     * Optimize image
     *
     * @param int $attachment_id Attachment ID
     */
    protected function optimize_image($attachment_id) {
        $metadata = \wp_get_attachment_metadata($attachment_id);
        
        if (!$metadata || empty($metadata['file'])) {
            return;
        }

        $upload_dir = \wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $metadata['file'];

        // Only optimize JPEG images
        if (!in_array($metadata['mime-type'], ['image/jpeg', 'image/jpg'])) {
            return;
        }

        // Check if file exists
        if (!file_exists($file_path)) {
            return;
        }

        // Load image
        $image = imagecreatefromjpeg($file_path);
        if (!$image) {
            return;
        }

        // Save with compression
        imagejpeg($image, $file_path, 85);
        imagedestroy($image);

        // Regenerate thumbnails
        \wp_update_attachment_metadata($attachment_id, \wp_generate_attachment_metadata($attachment_id, $file_path));
    }

    /**
     * Extract images from content
     *
     * @param string $content HTML content
     * @return array Image URLs
     */
    public function extract_images_from_content($content) {
        $images = [];

        // Match img tags
        preg_match_all('/<img[^>]+src=["\'](https?:\/\/[^"\']+)["\'][^>]*>/i', $content, $matches);
        if (!empty($matches[1])) {
            $images = array_merge($images, $matches[1]);
        }

        // Match markdown images
        preg_match_all('/!\[[^\]]*\]\((https?:\/\/[^)]+)\)/i', $content, $matches);
        if (!empty($matches[1])) {
            $images = array_merge($images, $matches[1]);
        }

        return array_unique($images);
    }

    /**
     * Replace image URLs in content
     *
     * @param string $content Content
     * @param array $replacements URL replacements (old => new)
     * @return string Updated content
     */
    public function replace_image_urls($content, $replacements) {
        foreach ($replacements as $old_url => $new_url) {
            $content = str_replace($old_url, $new_url, $content);
        }

        return $content;
    }
} 