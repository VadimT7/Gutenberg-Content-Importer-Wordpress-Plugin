<?php
/**
 * Abstract Importer Base Class
 *
 * @package GCI\Importers
 */

namespace GCI\Importers;

use GCI\Blocks\Block_Converter;
use GCI\Utils\Image_Handler;
use GCI\Utils\Content_Parser;

abstract class Abstract_Importer implements Importer_Interface {
    /**
     * Block converter instance
     *
     * @var Block_Converter
     */
    protected $block_converter;

    /**
     * Image handler instance
     *
     * @var Image_Handler
     */
    protected $image_handler;

    /**
     * Content parser instance
     *
     * @var Content_Parser
     */
    protected $content_parser;

    /**
     * Constructor
     */
    public function __construct() {
        $this->block_converter = new Block_Converter();
        $this->image_handler = new Image_Handler();
        $this->content_parser = new Content_Parser();
    }

    /**
     * Import content and create post
     *
     * @param string $url_or_content URL or content to import
     * @param array $options Import options
     * @return array Import result
     */
    public function import($url_or_content, $options = []) {
        try {
            // Get content data
            $content_data = $this->fetch_content($url_or_content);

            // Parse content
            $parsed_content = $this->parse_content($content_data);

            // Convert to blocks
            $blocks = $this->convert_to_blocks($parsed_content, $options);

            // Debug the blocks content
            error_log('GCI Debug: Block content before post creation: ' . substr($blocks, 0, 1000));

            // Create post
            $post_id = $this->create_post($parsed_content, $blocks, $options);

            // Handle images
            if (isset($options['download_images']) && $options['download_images'] && !empty($parsed_content['images'])) {
                $this->process_images($post_id, $parsed_content['images']);
            }



            return [
                'success' => true,
                'post_id' => $post_id,
                'post_url' => \get_permalink($post_id),
                'edit_url' => \get_edit_post_link($post_id, 'raw'),
                'title' => isset($parsed_content['title']) ? $parsed_content['title'] : '',
                'source' => $this->get_slug(),
                'url' => $url_or_content,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'source' => $this->get_slug(),
            ];
        }
    }

    /**
     * Fetch content from URL or parse provided content
     *
     * @param string $url_or_content URL or content
     * @return array Content data
     */
    abstract protected function fetch_content($url_or_content);

    /**
     * Parse content into structured data
     *
     * @param array $content_data Raw content data
     * @return array Parsed content
     */
    abstract protected function parse_content($content_data);

    /**
     * Convert parsed content to Gutenberg blocks
     *
     * @param array $parsed_content Parsed content
     * @param array $options Conversion options
     * @return string Block content
     */
    protected function convert_to_blocks($parsed_content, $options = []) {
        $blocks = [];
        $current_list = null;
        $current_list_type = null;

        // Process content sections
        foreach ($parsed_content['sections'] as $i => $section) {
            // Handle list items - combine consecutive items into single list
            if ($section['type'] === 'list') {
                $section_ordered = isset($section['ordered']) ? $section['ordered'] : false;
                
                if ($current_list === null || $current_list_type !== $section_ordered) {
                    // Start new list
                    $current_list = $section;
                    $current_list_type = $section_ordered;
                } else {
                    // Add items to current list
                    if (isset($section['items']) && is_array($section['items'])) {
                        $current_list['items'] = array_merge($current_list['items'], $section['items']);
                    }
                }
                
                // Check if next section is also a list of same type
                $next_section = isset($parsed_content['sections'][$i + 1]) ? $parsed_content['sections'][$i + 1] : null;
                if (!$next_section || $next_section['type'] !== 'list' || (isset($next_section['ordered']) ? $next_section['ordered'] : false) !== $current_list_type) {
                    // End of list, convert and add
                    $block = $this->block_converter->convert($current_list);
                    if ($block) {
                        $blocks[] = $block;
                    }
                    $current_list = null;
                    $current_list_type = null;
                }
            } else {
                // Not a list item - convert normally
                $block = $this->block_converter->convert($section);
                if ($block) {
                    $blocks[] = $block;
                }
            }
        }

        // If no blocks were created, return empty string
        if (empty($blocks)) {
            error_log('GCI Debug: No blocks created from sections: ' . print_r($parsed_content['sections'], true));
            return '';
        }

        // Serialize blocks to Gutenberg format
        if (function_exists('serialize_blocks')) {
            $serialized = serialize_blocks($blocks);
        } else {
            // Fallback if serialize_blocks is not available
            $serialized = '';
            foreach ($blocks as $block) {
                $serialized .= $this->serialize_block($block);
            }
        }
        
        // Debug logging
        error_log('GCI Debug: Created ' . count($blocks) . ' blocks');
        error_log('GCI Debug: Serialized content length: ' . strlen($serialized));
        error_log('GCI Debug: First block: ' . print_r($blocks[0] ?? 'No blocks', true));
        
        return $serialized;
    }

    /**
     * Serialize a single block (fallback method)
     *
     * @param array $block Block data
     * @return string Serialized block
     */
    protected function serialize_block($block) {
        $block_name = $block['blockName'];
        $attrs = $block['attrs'];
        $inner_content = $block['innerHTML'] ?? '';
        
        // Build block comment
        $serialized = '<!-- wp:' . $block_name;
        
        // Add attributes if any
        if (!empty($attrs)) {
            $serialized .= ' ' . json_encode($attrs);
        }
        
        $serialized .= ' -->' . "\n";
        $serialized .= $inner_content . "\n";
        $serialized .= '<!-- /wp:' . $block_name . ' -->' . "\n\n";
        
        return $serialized;
    }

    /**
     * Create WordPress post
     *
     * @param array $parsed_content Parsed content
     * @param string $block_content Block content
     * @param array $options Post options
     * @return int Post ID
     */
    protected function create_post($parsed_content, $block_content, $options = []) {
        // Debug logging
        error_log('GCI Debug: Creating post with content length: ' . strlen($block_content));
        error_log('GCI Debug: First 500 chars of content: ' . substr($block_content, 0, 500));
        
        // Ensure block content is properly formatted
        if (!empty($block_content) && strpos($block_content, '<!-- wp:') === false) {
            // If no block markers found, wrap in a paragraph block
            error_log('GCI Warning: No block markers found, wrapping content');
            $block_content = '<!-- wp:paragraph -->' . "\n" . 
                           '<p>' . wp_kses_post($block_content) . '</p>' . "\n" . 
                           '<!-- /wp:paragraph -->';
        }
        
        // Ensure options is an array
        if (!is_array($options)) {
            $options = [];
        }
        
        $post_data = [
            'post_title' => $parsed_content['title'] ?? __('Imported Content', 'gutenberg-content-importer'),
            'post_content' => $block_content,
            'post_status' => isset($options['post_status']) ? $options['post_status'] : 'draft',
            'post_type' => isset($options['post_type']) ? $options['post_type'] : 'post',
            'post_author' => isset($options['author_id']) ? $options['author_id'] : \get_current_user_id(),
        ];

        // Add excerpt if available
        if (!empty($parsed_content['excerpt'])) {
            $post_data['post_excerpt'] = $parsed_content['excerpt'];
        }

        // Add metadata
        if (!empty($parsed_content['meta'])) {
            $post_data['meta_input'] = $parsed_content['meta'];
        }

        // Ensure the post type supports the block editor
        if (!\use_block_editor_for_post_type($post_data['post_type'])) {
            error_log('GCI Warning: Post type ' . $post_data['post_type'] . ' does not support block editor');
        }

        // Create post
        $post_id = \wp_insert_post($post_data);

        if (\is_wp_error($post_id)) {
            throw new \Exception($post_id->get_error_message());
        }

        // Verify the content was saved
        $saved_post = \get_post($post_id);
        error_log('GCI Debug: Saved post content length: ' . strlen($saved_post->post_content));
        error_log('GCI Debug: Saved post content (first 500 chars): ' . substr($saved_post->post_content, 0, 500));

        // Add categories and tags
        if (!empty($parsed_content['categories'])) {
            \wp_set_post_categories($post_id, $this->process_terms($parsed_content['categories'], 'category'));
        }

        if (!empty($parsed_content['tags'])) {
            \wp_set_post_tags($post_id, $this->process_terms($parsed_content['tags'], 'post_tag'));
        }

        // Store import metadata
        \update_post_meta($post_id, '_gci_import_source', $this->get_slug());
        \update_post_meta($post_id, '_gci_import_url', $parsed_content['url'] ?? '');
        \update_post_meta($post_id, '_gci_import_date', \current_time('mysql'));

        return $post_id;
    }

    /**
     * Process and download images
     *
     * @param int $post_id Post ID
     * @param array $images Image URLs
     */
    protected function process_images($post_id, $images) {
        if (!is_array($images)) {
            error_log('GCI Warning: Images parameter is not an array');
            return;
        }
        
        foreach ($images as $image_url) {
            if (empty($image_url) || !is_string($image_url)) {
                continue;
            }
            
            try {
                $attachment_id = $this->image_handler->import_image($image_url, $post_id);
                
                if ($attachment_id) {
                    // Update image URLs in post content
                    $new_url = \wp_get_attachment_url($attachment_id);
                    if ($new_url) {
                        $this->update_image_urls($post_id, $image_url, $new_url);
                    }
                }
            } catch (\Exception $e) {
                error_log('GCI Error processing image ' . $image_url . ': ' . $e->getMessage());
            }
        }
    }



    /**
     * Process terms (categories/tags)
     *
     * @param array $terms Terms to process
     * @param string $taxonomy Taxonomy name
     * @return array Term IDs
     */
    protected function process_terms($terms, $taxonomy) {
        $term_ids = [];

        foreach ($terms as $term_name) {
            $term = \term_exists($term_name, $taxonomy);
            
            if (!$term) {
                $term = \wp_insert_term($term_name, $taxonomy);
            }

            if (!\is_wp_error($term)) {
                $term_ids[] = is_array($term) ? $term['term_id'] : $term;
            }
        }

        return $term_ids;
    }

    /**
     * Update image URLs in post content
     *
     * @param int $post_id Post ID
     * @param string $old_url Old image URL
     * @param string $new_url New image URL
     */
    protected function update_image_urls($post_id, $old_url, $new_url) {
        $post = \get_post($post_id);
        $content = str_replace($old_url, $new_url, $post->post_content);
        
        \wp_update_post([
            'ID' => $post_id,
            'post_content' => $content,
        ]);
    }

    /**
     * Get default supported features
     *
     * @return array
     */
    public function get_supported_features() {
        return [
            'title' => true,
            'content' => true,
            'excerpt' => true,
            'images' => true,
            'categories' => true,
            'tags' => true,
            'metadata' => true,
            'author' => true,
            'date' => true,
        ];
    }
} 