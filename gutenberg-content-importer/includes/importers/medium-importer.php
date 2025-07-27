<?php
/**
 * Medium Importer using RapidAPI
 *
 * @package GCI\Importers
 */

namespace GCI\Importers;

/**
 * Medium importer class using the Medium API from RapidAPI
 */
class Medium_Importer extends Abstract_Importer {
    
    /**
     * RapidAPI configuration
     */
    const RAPIDAPI_HOST = 'medium2.p.rapidapi.com';
    const RAPIDAPI_BASE_URL = 'https://medium2.p.rapidapi.com';
    const RAPIDAPI_KEY = 'cc4f31d2cbmshb03f54cf7e15ab9p1dbb41jsn452699b25d5c';

    /**
     * Get importer name
     *
     * @return string
     */
    public function get_name() {
        return __('Medium', 'gutenberg-content-importer');
    }

    /**
     * Get importer slug
     *
     * @return string
     */
    public function get_slug() {
        return 'medium';
    }

    /**
     * Check if URL/content can be imported
     *
     * @param string $url_or_content URL or content to check
     * @return bool
     */
    public function can_import($url_or_content) {
        // Check if it's a Medium URL
        if (filter_var($url_or_content, FILTER_VALIDATE_URL)) {
            $parsed_url = parse_url($url_or_content);
            $host = $parsed_url['host'] ?? '';
            
            return (
                strpos($host, 'medium.com') !== false ||
                strpos($host, 'towardsdatascience.com') !== false ||
                strpos($host, 'hackernoon.com') !== false ||
                strpos($host, 'pub.') === 0 || // Publications like pub.towardsai.net
                preg_match('/^[\w-]+\.medium\.com$/', $host)
            );
        }

        return false;
    }

    /**
     * Preview import without creating post
     *
     * @param string $url_or_content URL to preview
     * @return array Preview data
     */
    public function preview($url_or_content) {
        try {
            $content_data = $this->fetch_content($url_or_content);
            $parsed_content = $this->parse_content($content_data);
            $blocks = $this->convert_to_blocks($parsed_content);

            return [
                'success' => true,
                'title' => $parsed_content['title'],
                'excerpt' => $parsed_content['excerpt'] ?? '',
                'author' => $parsed_content['author'] ?? '',
                'published_date' => $parsed_content['published_date'] ?? '',
                'featured_image' => $parsed_content['featured_image'] ?? '',
                'tags' => $parsed_content['tags'] ?? [],
                'content_preview' => $this->get_preview_text($parsed_content),
                'blocks' => $blocks,
                'stats' => [
                    'paragraphs' => count($parsed_content['sections']),
                    'images' => count($parsed_content['images'] ?? []),
                    'embeds' => count($parsed_content['embeds'] ?? []),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract article ID from Medium URL
     *
     * @param string $url Medium URL
     * @return string|false Article ID or false
     */
    protected function extract_article_id($url) {
        // Remove query parameters and hash
        $url = strtok($url, '?');
        $url = strtok($url, '#');
        
        // Pattern 1: URLs ending with article ID (most common)
        // e.g., https://medium.com/@username/title-67fa62fc1971
        if (preg_match('/([a-f0-9]{8,16})$/', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern 2: URLs with /p/ format
        // e.g., https://medium.com/p/67fa62fc1971
        if (preg_match('/\/p\/([a-f0-9]{8,16})/', $url, $matches)) {
            return $matches[1];
        }
        
        // Pattern 3: Extract from any part of the URL that looks like an article ID
        if (preg_match('/([a-f0-9]{12})/', $url, $matches)) {
            return $matches[1];
        }
        
        return false;
    }

    /**
     * Make API request to Medium API
     *
     * @param string $endpoint API endpoint
     * @return array API response
     */
    protected function make_api_request($endpoint) {
        // Get API key from settings, fallback to default
        $settings = \get_option('gci_settings', []);
        $api_key = !empty($settings['medium_api_key']) ? $settings['medium_api_key'] : self::RAPIDAPI_KEY;
        
        $response = wp_remote_get(self::RAPIDAPI_BASE_URL . $endpoint, [
            'timeout' => 30,
            'headers' => [
                'X-RapidAPI-Key' => $api_key,
                'X-RapidAPI-Host' => self::RAPIDAPI_HOST,
            ],
        ]);

        if (is_wp_error($response)) {
            throw new \Exception(__('Failed to connect to Medium API: ', 'gutenberg-content-importer') . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            error_log('GCI Medium API Error - Code: ' . $code . ', Response: ' . substr($body, 0, 500));
            throw new \Exception(sprintf(__('Medium API returned error code %d', 'gutenberg-content-importer'), $code));
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('GCI Medium API JSON Error: ' . json_last_error_msg());
            throw new \Exception(__('Invalid API response from Medium', 'gutenberg-content-importer'));
        }

        return $data;
    }

    /**
     * Fetch content from Medium URL using RapidAPI
     *
     * @param string $url URL to fetch
     * @return array Content data
     */
    protected function fetch_content($url) {
        $article_id = $this->extract_article_id($url);
        
        if (!$article_id) {
            throw new \Exception(__('Could not extract article ID from URL', 'gutenberg-content-importer'));
        }

        try {
            // Fetch article info
            $article_info = $this->make_api_request('/article/' . $article_id);
            
            // Fetch article markdown content (preferred for better formatting)
            $markdown_response = $this->make_api_request('/article/' . $article_id . '/markdown');
            
            // Fetch article assets
            $assets = $this->make_api_request('/article/' . $article_id . '/assets');
            
            // Get author info if available
            $author_info = null;
            if (!empty($article_info['author'])) {
                try {
                    $author_info = $this->make_api_request('/user/' . $article_info['author']);
                } catch (\Exception $e) {
                    // Author info is optional, don't fail if it's not available
                }
            }

            return [
                'type' => 'api',
                'article_id' => $article_id,
                'url' => $url,
                'info' => $article_info,
                'markdown' => $markdown_response['markdown'] ?? '',
                'assets' => $assets['assets'] ?? [],
                'author' => $author_info,
            ];
        } catch (\Exception $e) {
            throw new \Exception(__('Failed to fetch article from Medium API: ', 'gutenberg-content-importer') . $e->getMessage());
        }
    }

    /**
     * Parse Medium content from API response
     *
     * @param array $content_data Content data from API
     * @return array Parsed content
     */
    protected function parse_content($content_data) {
        $info = $content_data['info'] ?? [];
        $markdown = $content_data['markdown'] ?? '';
        $assets = $content_data['assets'] ?? [];
        $author = $content_data['author'] ?? [];

        $parsed = [
            'title' => isset($info['title']) ? $info['title'] : '',
            'excerpt' => isset($info['subtitle']) ? $info['subtitle'] : '',
            'author' => '',
            'author_id' => isset($info['author']) ? $info['author'] : '',
            'published_date' => isset($info['published_at']) ? $info['published_at'] : '',
            'last_modified' => isset($info['last_modified_at']) ? $info['last_modified_at'] : '',
            'featured_image' => isset($info['image_url']) ? $info['image_url'] : '',
            'sections' => [],
            'images' => [],
            'embeds' => [],
            'tags' => isset($info['tags']) ? $info['tags'] : [],
            'topics' => isset($info['topics']) ? $info['topics'] : [],
            'url' => isset($info['url']) ? $info['url'] : $content_data['url'],
            'claps' => isset($info['claps']) ? $info['claps'] : 0,
            'reading_time' => isset($info['reading_time']) ? $info['reading_time'] : 0,
        ];
        
        // Set author name if available
        if (!empty($author)) {
            if (isset($author['fullname'])) {
                $parsed['author'] = $author['fullname'];
            } elseif (isset($author['username'])) {
                $parsed['author'] = $author['username'];
            }
        }

        // Add featured image to images array
        if (!empty($parsed['featured_image'])) {
            $parsed['images'][] = $parsed['featured_image'];
        }

        // Parse markdown content into sections
        if (!empty($markdown)) {
            $sections = $this->parse_markdown_to_sections($markdown);
            $parsed['sections'] = $sections;
        }

        // Add images from assets
        if (!empty($assets['images'])) {
            $parsed['images'] = array_merge($parsed['images'], $assets['images']);
            $parsed['images'] = array_unique($parsed['images']);
        }

        // Add embeds from assets
        if (!empty($assets['youtube'])) {
            foreach ($assets['youtube'] as $youtube) {
                $parsed['embeds'][] = [
                    'type' => 'youtube',
                    'url' => $youtube['href'] ?? '',
                    'title' => $youtube['title'] ?? '',
                ];
            }
        }

        // Add other embeds
        if (!empty($assets['other_embeds'])) {
            foreach ($assets['other_embeds'] as $domain => $embeds) {
                foreach ($embeds as $embed) {
                    $parsed['embeds'][] = [
                        'type' => 'other',
                        'url' => $embed['href'] ?? '',
                        'title' => $embed['title'] ?? '',
                        'domain' => $domain,
                    ];
                }
            }
        }

        return $parsed;
    }

    /**
     * Parse markdown content into sections
     *
     * @param string $markdown Markdown content
     * @return array Sections
     */
    protected function parse_markdown_to_sections($markdown) {
        $sections = [];
        $lines = explode("\n", $markdown);
        $current_paragraph = [];
        $in_code_block = false;
        $code_content = [];
        $code_language = '';
        $in_list = false;
        $list_items = [];
        $list_ordered = false;

        foreach ($lines as $line) {
            // Handle code blocks
            if (preg_match('/^```(\w*)/', $line, $matches)) {
                if ($in_code_block) {
                    // End code block
                    $sections[] = [
                        'type' => 'code',
                        'content' => implode("\n", $code_content),
                        'language' => $code_language,
                    ];
                    $code_content = [];
                    $in_code_block = false;
                } else {
                    // Save any pending paragraph
                    if (!empty($current_paragraph)) {
                        $sections[] = [
                            'type' => 'paragraph',
                            'content' => $this->process_markdown_inline(implode(' ', $current_paragraph)),
                        ];
                        $current_paragraph = [];
                    }
                    // Start code block
                    $in_code_block = true;
                    $code_language = $matches[1] ?? '';
                }
                continue;
            }

            if ($in_code_block) {
                $code_content[] = $line;
                continue;
            }

            // Handle headings
            if (preg_match('/^(#{1,6})\s+(.+)/', $line, $matches)) {
                // Save any pending content
                $this->save_pending_content($sections, $current_paragraph, $in_list, $list_items, $list_ordered);
                $current_paragraph = [];
                $in_list = false;

                $level = strlen($matches[1]);
                $sections[] = [
                    'type' => 'heading',
                    'level' => $level,
                    'content' => $this->process_markdown_inline(trim($matches[2])),
                ];
                continue;
            }

            // Handle images
            if (preg_match('/!\[([^\]]*)\]\(([^)]+)\)/', $line, $matches)) {
                // Save any pending content
                $this->save_pending_content($sections, $current_paragraph, $in_list, $list_items, $list_ordered);
                $current_paragraph = [];
                $in_list = false;

                $sections[] = [
                    'type' => 'image',
                    'url' => $matches[2],
                    'alt' => $matches[1],
                ];
                continue;
            }

            // Handle blockquotes
            if (preg_match('/^>\s*(.*)/', $line, $matches)) {
                // Save any pending content
                $this->save_pending_content($sections, $current_paragraph, $in_list, $list_items, $list_ordered);
                $current_paragraph = [];
                $in_list = false;

                // Check if we need to start or continue a quote
                $last_section = !empty($sections) ? end($sections) : null;
                if ($last_section && $last_section['type'] === 'quote') {
                    // Continue existing quote
                    $sections[count($sections) - 1]['content'] .= ' ' . $this->process_markdown_inline($matches[1]);
                } else {
                    // Start new quote
                    $sections[] = [
                        'type' => 'quote',
                        'content' => $this->process_markdown_inline($matches[1]),
                    ];
                }
                continue;
            }

            // Handle unordered lists
            if (preg_match('/^[\*\-\+]\s+(.+)/', $line, $matches)) {
                // Save any pending paragraph
                if (!empty($current_paragraph)) {
                    $sections[] = [
                        'type' => 'paragraph',
                        'content' => $this->process_markdown_inline(implode(' ', $current_paragraph)),
                    ];
                    $current_paragraph = [];
                }

                if (!$in_list || $list_ordered) {
                    // Save previous list if it was ordered
                    if ($in_list && $list_ordered && !empty($list_items)) {
                        $sections[] = [
                            'type' => 'list',
                            'ordered' => true,
                            'items' => $list_items,
                        ];
                    }
                    // Start new unordered list
                    $in_list = true;
                    $list_ordered = false;
                    $list_items = [];
                }
                $list_items[] = $this->process_markdown_inline($matches[1]);
                continue;
            }

            // Handle ordered lists
            if (preg_match('/^\d+[\.)\s]+(.+)/', $line, $matches)) {
                // Save any pending paragraph
                if (!empty($current_paragraph)) {
                    $sections[] = [
                        'type' => 'paragraph',
                        'content' => $this->process_markdown_inline(implode(' ', $current_paragraph)),
                    ];
                    $current_paragraph = [];
                }

                if (!$in_list || !$list_ordered) {
                    // Save previous list if it was unordered
                    if ($in_list && !$list_ordered && !empty($list_items)) {
                        $sections[] = [
                            'type' => 'list',
                            'ordered' => false,
                            'items' => $list_items,
                        ];
                    }
                    // Start new ordered list
                    $in_list = true;
                    $list_ordered = true;
                    $list_items = [];
                }
                $list_items[] = $this->process_markdown_inline($matches[1]);
                continue;
            }

            // Handle horizontal rules
            if (preg_match('/^---+$|^\*\*\*+$|^___+$/', trim($line))) {
                // Save any pending content
                $this->save_pending_content($sections, $current_paragraph, $in_list, $list_items, $list_ordered);
                $current_paragraph = [];
                $in_list = false;

                $sections[] = [
                    'type' => 'separator',
                ];
                continue;
            }

            // Regular paragraphs
            if (trim($line) !== '') {
                // If we were in a list, save it first
                if ($in_list && !empty($list_items)) {
                    $sections[] = [
                        'type' => 'list',
                        'ordered' => $list_ordered,
                        'items' => $list_items,
                    ];
                    $in_list = false;
                    $list_items = [];
                }
                $current_paragraph[] = $line;
            } elseif (!empty($current_paragraph)) {
                // Empty line - save current paragraph
                $sections[] = [
                    'type' => 'paragraph',
                    'content' => $this->process_markdown_inline(implode(' ', $current_paragraph)),
                ];
                $current_paragraph = [];
            }
        }

        // Save any remaining content
        $this->save_pending_content($sections, $current_paragraph, $in_list, $list_items, $list_ordered);

        return $sections;
    }

    /**
     * Save pending content (paragraph or list)
     *
     * @param array &$sections Sections array
     * @param array &$current_paragraph Current paragraph lines
     * @param bool &$in_list Whether in a list
     * @param array &$list_items List items
     * @param bool $list_ordered Whether list is ordered
     */
    protected function save_pending_content(&$sections, &$current_paragraph, &$in_list, &$list_items, $list_ordered) {
        // Save pending paragraph
        if (!empty($current_paragraph)) {
            $sections[] = [
                'type' => 'paragraph',
                'content' => $this->process_markdown_inline(implode(' ', $current_paragraph)),
            ];
            $current_paragraph = [];
        }

        // Save pending list
        if ($in_list && !empty($list_items)) {
            $sections[] = [
                'type' => 'list',
                'ordered' => $list_ordered,
                'items' => $list_items,
            ];
            $list_items = [];
            $in_list = false;
        }
    }

    /**
     * Process inline markdown formatting
     *
     * @param string $text Text to process
     * @return string HTML formatted text
     */
    protected function process_markdown_inline($text) {
        // Bold
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);
        
        // Italic
        $text = preg_replace('/\*([^\*]+?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_([^_]+?)_/', '<em>$1</em>', $text);
        
        // Links
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text);
        
        // Inline code
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        
        // Strikethrough
        $text = preg_replace('/~~(.+?)~~/', '<s>$1</s>', $text);
        
        return $text;
    }

    /**
     * Get preview text from parsed content
     *
     * @param array $parsed Parsed content
     * @return string Preview text
     */
    protected function get_preview_text($parsed) {
        $preview = '';
        $char_count = 0;
        $max_chars = 500;

        foreach ($parsed['sections'] as $section) {
            if ($char_count >= $max_chars) {
                break;
            }

            switch ($section['type']) {
                case 'paragraph':
                case 'quote':
                    $text = strip_tags($section['content']);
                    $remaining = $max_chars - $char_count;
                    if (strlen($text) > $remaining) {
                        $preview .= substr($text, 0, $remaining) . '...';
                        $char_count = $max_chars;
                    } else {
                        $preview .= $text . ' ';
                        $char_count += strlen($text) + 1;
                    }
                    break;
                
                case 'heading':
                    $text = strip_tags($section['content']);
                    $preview .= $text . '. ';
                    $char_count += strlen($text) + 2;
                    break;
            }
        }

        return trim($preview);
    }

    /**
     * Get supported features
     *
     * @return array
     */
    public function get_supported_features() {
        return [
            'title' => true,
            'content' => true,
            'excerpt' => true,
            'author' => true,
            'date' => true,
            'categories' => false,
            'tags' => true,
            'featured_image' => true,
            'images' => true,
            'formatting' => true,
            'embeds' => true,
        ];
    }
} 