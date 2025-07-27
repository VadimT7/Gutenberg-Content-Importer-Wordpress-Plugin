<?php
/**
 * Notion Importer
 *
 * @package GCI\Importers
 */

namespace GCI\Importers;

class Notion_Importer extends Abstract_Importer {
    /**
     * Get importer name
     *
     * @return string
     */
    public function get_name() {
        return __('Notion', 'gutenberg-content-importer');
    }

    /**
     * Get importer slug
     *
     * @return string
     */
    public function get_slug() {
        return 'notion';
    }

    /**
     * Check if URL can be imported
     *
     * @param string $url_or_content URL to check
     * @return bool
     */
    public function can_import($url_or_content) {
        if (filter_var($url_or_content, FILTER_VALIDATE_URL)) {
            return strpos($url_or_content, 'notion.so') !== false;
        }
        return false;
    }

    /**
     * Preview import
     *
     * @param string $url_or_content URL to preview
     * @return array Preview data
     */
    public function preview($url_or_content) {
        try {
            $content_data = $this->fetch_content($url_or_content);
            $parsed_content = $this->parse_content($content_data);
            $blocks = $this->convert_to_blocks($parsed_content);

            // Calculate statistics from parsed sections
            $stats = [
                'paragraphs' => 0,
                'images' => 0,
                'embeds' => 0,
                'headings' => 0,
                'lists' => 0,
                'tables' => 0,
            ];
            
            foreach ($parsed_content['sections'] as $section) {
                switch ($section['type']) {
                    case 'paragraph':
                        $stats['paragraphs']++;
                        break;
                    case 'heading':
                        $stats['headings']++;
                        break;
                    case 'list':
                        $stats['lists']++;
                        break;
                    case 'table':
                        $stats['tables']++;
                        break;
                    case 'image':
                        $stats['images']++;
                        break;
                }
            }

            // Generate preview HTML content
            $preview_html = '';
            $char_count = 0;
            $max_chars = 2000; // Increased for better preview
            
            foreach ($parsed_content['sections'] as $section) {
                if ($char_count >= $max_chars) {
                    break;
                }
                
                switch ($section['type']) {
                    case 'paragraph':
                        $text = strip_tags($section['content']);
                        $preview_html .= '<p>' . $section['content'] . '</p>';
                        $char_count += strlen($text);
                        break;
                    case 'heading':
                        $text = strip_tags($section['content']);
                        $preview_html .= '<h' . $section['level'] . '>' . $section['content'] . '</h' . $section['level'] . '>';
                        $char_count += strlen($text);
                        break;
                    case 'image':
                        $preview_html .= '<img src="' . esc_url($section['url']) . '" alt="' . esc_attr($section['alt'] ?? '') . '" style="max-width: 100%; height: auto;" />';
                        break;
                    case 'list':
                        $list_tag = $section['ordered'] ? 'ol' : 'ul';
                        $preview_html .= '<' . $list_tag . '>';
                        foreach ($section['items'] as $item) {
                            $preview_html .= '<li>' . $item . '</li>';
                        }
                        $preview_html .= '</' . $list_tag . '>';
                        break;
                    case 'quote':
                        $preview_html .= '<blockquote style="border-left: 4px solid #ddd; padding-left: 15px; margin: 15px 0; font-style: italic;">' . $section['content'] . '</blockquote>';
                        break;
                    case 'code':
                        $preview_html .= '<pre style="background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto;"><code>' . esc_html($section['content']) . '</code></pre>';
                        break;
                    case 'callout':
                        $preview_html .= '<div style="background: #f8f9fa; border-left: 4px solid #007cba; padding: 15px; margin: 15px 0; border-radius: 4px;">' . $section['content'] . '</div>';
                        break;
                    case 'toggle':
                        $preview_html .= '<details style="margin: 15px 0;"><summary style="cursor: pointer; font-weight: bold;">' . $section['title'] . '</summary><div style="margin-top: 10px;">' . $section['content'] . '</div></details>';
                        break;
                }
            }

            return [
                'success' => true,
                'title' => $parsed_content['title'],
                'excerpt' => $parsed_content['excerpt'] ?? '',
                'author' => $parsed_content['author'] ?? '',
                'published_date' => $parsed_content['published_date'] ?? '',
                'featured_image' => $parsed_content['featured_image'] ?? '',
                'tags' => $parsed_content['tags'] ?? [],
                'content_preview' => substr(strip_tags($blocks), 0, 500) . '...',
                'preview_html' => $preview_html,
                'blocks' => $blocks,
                'stats' => $stats,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch content from Notion
     *
     * @param string $url URL to fetch
     * @return array Content data
     */
    protected function fetch_content($url) {
        // Get API key from settings
        $settings = get_option('gci_settings', []);
        $api_key = $settings['notion_api_key'] ?? '';
        
        if (empty($api_key)) {
            throw new \Exception(__('Notion API key not configured. Please add it in Content Importer settings.', 'gutenberg-content-importer'));
        }
        
        // Extract page ID from URL
        $page_id = $this->extract_page_id($url);
        
        if (empty($page_id)) {
            throw new \Exception(__('Invalid Notion URL. Could not extract page ID.', 'gutenberg-content-importer'));
        }

        // Fetch page metadata
        $page_response = wp_remote_get('https://api.notion.com/v1/pages/' . $page_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Notion-Version' => '2022-06-28',
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($page_response)) {
            throw new \Exception(__('Failed to connect to Notion API: ', 'gutenberg-content-importer') . $page_response->get_error_message());
        }

        $page_data = json_decode(wp_remote_retrieve_body($page_response), true);
        
        if (isset($page_data['status']) && $page_data['status'] === 401) {
            throw new \Exception(__('Invalid Notion API key. Please check your settings.', 'gutenberg-content-importer'));
        }

        // Fetch page content blocks
        $blocks_response = wp_remote_get('https://api.notion.com/v1/blocks/' . $page_id . '/children', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Notion-Version' => '2022-06-28',
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($blocks_response)) {
            throw new \Exception(__('Failed to fetch Notion blocks', 'gutenberg-content-importer'));
        }

        $blocks_data = json_decode(wp_remote_retrieve_body($blocks_response), true);
        
        // Fetch blocks with hierarchy preserved
        $all_blocks = [];
        if (isset($blocks_data['results'])) {
            $all_blocks = $this->build_block_tree($blocks_data['results'], $api_key);
        }

        return [
            'type' => 'notion',
            'page_id' => $page_id,
            'url' => $url,
            'page' => $page_data,
            'blocks' => $all_blocks,
        ];
    }
    
    /**
     * Build block tree with hierarchy
     *
     * @param array $blocks Blocks array
     * @param string $api_key API key
     * @return array Flattened blocks with hierarchy info
     */
    protected function build_block_tree($blocks, $api_key, $depth = 0) {
        $result = [];
        
        foreach ($blocks as $block) {
            // Add depth info to track nesting
            $block['_depth'] = $depth;
            $result[] = $block;
            
            // Fetch and add children if they exist
            if (isset($block['has_children']) && $block['has_children']) {
                $children = $this->fetch_child_blocks($block['id'], $api_key);
                if (!empty($children)) {
                    $child_tree = $this->build_block_tree($children, $api_key, $depth + 1);
                    $result = array_merge($result, $child_tree);
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Fetch child blocks
     *
     * @param string $block_id Block ID
     * @param string $api_key API key
     * @return array Child blocks
     */
    protected function fetch_child_blocks($block_id, $api_key) {
        $response = wp_remote_get('https://api.notion.com/v1/blocks/' . $block_id . '/children', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Notion-Version' => '2022-06-28',
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('GCI Notion: Failed to fetch child blocks for ' . $block_id);
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        return isset($data['results']) ? $data['results'] : [];
    }

    /**
     * Parse Notion content
     *
     * @param array $content_data Content data
     * @return array Parsed content
     */
    protected function parse_content($content_data) {
        $parsed = [
            'title' => $this->extract_page_title($content_data['page']),
            'excerpt' => '',
            'author' => $this->extract_page_author($content_data['page']),
            'published_date' => $content_data['page']['created_time'] ?? '',
            'sections' => [],
            'images' => [],
            'embeds' => [],
            'tags' => [],
            'url' => $content_data['url'],
        ];

        // Parse blocks and group consecutive list items
        $current_list = null;
        $current_list_type = null;
        
        foreach ($content_data['blocks'] as $block) {
            $block_type = $block['type'] ?? '';
            
            // Handle list items by grouping them
            if ($block_type === 'bulleted_list_item' || $block_type === 'numbered_list_item') {
                $is_ordered = ($block_type === 'numbered_list_item');
                $text = $this->extract_rich_text($block[$block_type]['rich_text'] ?? []);
                $depth = $block['_depth'] ?? 0;
                
                // Add indentation for nested items
                if ($depth > 0) {
                    $text = str_repeat('    ', $depth) . $text;
                }
                
                // If we're starting a new list or switching list types at the same depth
                if ($current_list === null || ($current_list_type !== $is_ordered && $depth === 0)) {
                    // Save the previous list if it exists
                    if ($current_list !== null) {
                        $parsed['sections'][] = $current_list;
                    }
                    
                    // Start a new list
                    $current_list = [
                        'type' => 'list',
                        'ordered' => $is_ordered,
                        'items' => [$text],
                    ];
                    $current_list_type = $is_ordered;
                } else {
                    // Add to the current list
                    $current_list['items'][] = $text;
                }
            } else {
                // If we have a pending list, add it first
                if ($current_list !== null) {
                    $parsed['sections'][] = $current_list;
                    $current_list = null;
                    $current_list_type = null;
                }
                
                // Parse non-list block
                $section = $this->parse_notion_block($block);
                if ($section) {
                    // Handle nested content by adding indentation for nested paragraphs
                    $depth = $block['_depth'] ?? 0;
                    if ($depth > 0 && $section['type'] === 'paragraph') {
                        // Add visual indentation for nested paragraphs
                        $section['content'] = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth) . $section['content'];
                    }
                    
                    $parsed['sections'][] = $section;
                    
                    // Collect images
                    if ($section['type'] === 'image' && !empty($section['url'])) {
                        $parsed['images'][] = $section['url'];
                    }
                }
            }
        }
        
        // Don't forget to add the last list if there is one
        if ($current_list !== null) {
            $parsed['sections'][] = $current_list;
        }

        return $parsed;
    }

    /**
     * Extract page title from Notion page data
     *
     * @param array $page Page data
     * @return string Title
     */
    protected function extract_page_title($page) {
        // Check properties for title
        if (isset($page['properties']['title']['title'][0]['plain_text'])) {
            return $page['properties']['title']['title'][0]['plain_text'];
        }
        
        if (isset($page['properties']['Name']['title'][0]['plain_text'])) {
            return $page['properties']['Name']['title'][0]['plain_text'];
        }

        // Fallback to any title property
        foreach ($page['properties'] as $prop) {
            if ($prop['type'] === 'title' && !empty($prop['title'][0]['plain_text'])) {
                return $prop['title'][0]['plain_text'];
            }
        }

        return __('Untitled Notion Page', 'gutenberg-content-importer');
    }

    /**
     * Extract page author from Notion page data
     *
     * @param array $page Page data
     * @return string Author
     */
    protected function extract_page_author($page) {
        if (isset($page['created_by']['name'])) {
            return $page['created_by']['name'];
        }
        return '';
    }

    /**
     * Parse individual Notion block
     *
     * @param array $block Block data
     * @return array|null Parsed section
     */
    protected function parse_notion_block($block) {
        $type = $block['type'] ?? '';

        switch ($type) {
            case 'paragraph':
                return $this->parse_notion_text_block($block, 'paragraph');
            
            case 'heading_1':
                return $this->parse_notion_heading($block, 1);
            
            case 'heading_2':
                return $this->parse_notion_heading($block, 2);
            
            case 'heading_3':
                return $this->parse_notion_heading($block, 3);
            
            case 'bulleted_list_item':
            case 'numbered_list_item':
                return $this->parse_notion_list_item($block, $type === 'numbered_list_item');
            
            case 'quote':
                return $this->parse_notion_text_block($block, 'quote');
            
            case 'code':
                return $this->parse_notion_code_block($block);
            
            case 'image':
                return $this->parse_notion_image($block);
            
            case 'divider':
                return ['type' => 'separator'];
            
            case 'callout':
                // Convert callout to a styled paragraph
                return $this->parse_notion_callout($block);
            
            case 'toggle':
                // Convert toggle to heading + content
                return $this->parse_notion_toggle($block);
            
            default:
                // For unsupported blocks, try to extract text
                return $this->parse_notion_text_block($block, 'paragraph');
        }
    }

    /**
     * Parse Notion text block (paragraph, quote, etc)
     *
     * @param array $block Block data
     * @param string $type Block type
     * @return array|null Parsed section
     */
    protected function parse_notion_text_block($block, $type) {
        $text = $this->extract_rich_text($block[$block['type']]['rich_text'] ?? []);
        
        if (empty($text)) {
            return null;
        }

        return [
            'type' => $type,
            'content' => $text,
        ];
    }

    /**
     * Parse Notion heading
     *
     * @param array $block Block data
     * @param int $level Heading level
     * @return array Parsed section
     */
    protected function parse_notion_heading($block, $level) {
        $text = $this->extract_rich_text($block[$block['type']]['rich_text'] ?? []);
        
        return [
            'type' => 'heading',
            'level' => $level,
            'content' => $text,
        ];
    }

    /**
     * Parse Notion code block
     *
     * @param array $block Block data
     * @return array Parsed section
     */
    protected function parse_notion_code_block($block) {
        $text = $this->extract_rich_text($block['code']['rich_text'] ?? []);
        $language = $block['code']['language'] ?? '';
        
        return [
            'type' => 'code',
            'content' => $text,
            'language' => $language,
        ];
    }

    /**
     * Parse Notion image
     *
     * @param array $block Block data
     * @return array Parsed section
     */
    protected function parse_notion_image($block) {
        $url = '';
        $caption = '';

        if ($block['image']['type'] === 'external') {
            $url = $block['image']['external']['url'] ?? '';
        } elseif ($block['image']['type'] === 'file') {
            $url = $block['image']['file']['url'] ?? '';
        }

        if (!empty($block['image']['caption'])) {
            $caption = $this->extract_rich_text($block['image']['caption']);
        }

        return [
            'type' => 'image',
            'url' => $url,
            'alt' => $caption,
            'caption' => $caption,
        ];
    }

    /**
     * Extract rich text from Notion format
     *
     * @param array $rich_text Rich text array
     * @return string HTML text
     */
    protected function extract_rich_text($rich_text) {
        $html = '';

        foreach ($rich_text as $text) {
            $content = $text['plain_text'] ?? '';
            $annotations = $text['annotations'] ?? [];

            // Apply formatting
            if ($annotations['bold'] ?? false) {
                $content = '<strong>' . $content . '</strong>';
            }
            if ($annotations['italic'] ?? false) {
                $content = '<em>' . $content . '</em>';
            }
            if ($annotations['strikethrough'] ?? false) {
                $content = '<s>' . $content . '</s>';
            }
            if ($annotations['underline'] ?? false) {
                $content = '<u>' . $content . '</u>';
            }
            if ($annotations['code'] ?? false) {
                $content = '<code>' . $content . '</code>';
            }

            // Handle links
            if (!empty($text['href'])) {
                $content = '<a href="' . esc_url($text['href']) . '">' . $content . '</a>';
            }

            $html .= $content;
        }

        return $html;
    }

    /**
     * Parse Notion list item
     *
     * @param array $block Block data
     * @param bool $ordered Whether it's an ordered list
     * @return array Parsed section
     */
    protected function parse_notion_list_item($block, $ordered = false) {
        $text = $this->extract_rich_text($block[$block['type']]['rich_text'] ?? []);
        
        // Note: This is now handled in parse_content to group consecutive list items
        return [
            'type' => 'list',
            'ordered' => $ordered,
            'items' => [$text],
        ];
    }

    /**
     * Parse Notion callout
     *
     * @param array $block Block data
     * @return array Parsed section
     */
    protected function parse_notion_callout($block) {
        $text = $this->extract_rich_text($block['callout']['rich_text'] ?? []);
        $icon = $block['callout']['icon']['emoji'] ?? '💡';
        
        // Convert to a styled paragraph with the icon
        return [
            'type' => 'paragraph',
            'content' => $icon . ' <strong>' . $text . '</strong>',
        ];
    }

    /**
     * Parse Notion toggle
     *
     * @param array $block Block data
     * @return array Parsed section
     */
    protected function parse_notion_toggle($block) {
        $text = $this->extract_rich_text($block['toggle']['rich_text'] ?? []);
        
        // For now, just convert to a bold paragraph
        // In a full implementation, we'd handle the toggle content
        return [
            'type' => 'paragraph',
            'content' => '<strong>▸ ' . $text . '</strong>',
        ];
    }

    /**
     * Extract page ID from Notion URL
     *
     * @param string $url Notion URL
     * @return string Page ID
     */
    protected function extract_page_id($url) {
        // Remove query parameters
        $url = strtok($url, '?');
        
        // Pattern 1: URLs ending with 32-character hex ID
        if (preg_match('/([a-f0-9]{32})(?:#.*)?$/', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern 2: URLs with hyphenated format (title-id)
        if (preg_match('/-([a-f0-9]{32})(?:#.*)?$/', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern 3: Database/collection URLs
        if (preg_match('/\/([a-f0-9]{32})(?:\/|$)/', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern 4: Newer format with 32 chars that might include uppercase
        if (preg_match('/([a-fA-F0-9]{32})/', $url, $matches)) {
            // Notion IDs should be lowercase
            return strtolower($matches[1]);
        }
        
        // Pattern 5: UUID format (with dashes)
        if (preg_match('/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})/', $url, $matches)) {
            // Remove dashes for API
            return str_replace('-', '', $matches[1]);
        }
        
        return '';
    }
} 