<?php
/**
 * Google Docs Importer
 *
 * @package GCI\Importers
 */

namespace GCI\Importers;

class Google_Docs_Importer extends Abstract_Importer {
    /**
     * Google OAuth 2.0 endpoints
     */
    const GOOGLE_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const GOOGLE_DOCS_API_BASE = 'https://docs.googleapis.com/v1/documents/';
    const GOOGLE_DRIVE_API_BASE = 'https://www.googleapis.com/drive/v3/files/';
    
    /**
     * OAuth Client Credentials (Hardcoded for the plugin)
     */
    const CLIENT_ID = '929737682525-9ageironag96ktc1bmoqrgqi1jvb1bd4.apps.googleusercontent.com';
    const CLIENT_SECRET = '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';
    
    /**
     * Required OAuth scopes
     */
    const SCOPES = [
        'https://www.googleapis.com/auth/documents.readonly',
        'https://www.googleapis.com/auth/drive.readonly',
    ];
    
    /**
     * Temporary storage for images found during parsing
     */
    protected $current_images = [];

    /**
     * Get importer name
     *
     * @return string
     */
    public function get_name() {
        return __('Google Docs', 'gutenberg-content-importer');
    }

    /**
     * Get importer slug
     *
     * @return string
     */
    public function get_slug() {
        return 'google-docs';
    }

    /**
     * Check if URL can be imported
     *
     * @param string $url_or_content URL to check
     * @return bool
     */
    public function can_import($url_or_content) {
        if (filter_var($url_or_content, FILTER_VALIDATE_URL)) {
            return strpos($url_or_content, 'docs.google.com') !== false;
        }
        return false;
    }

    /**
     * Get OAuth authorization URL
     *
     * @return string Authorization URL
     */
    public function get_auth_url() {
        $redirect_uri = $this->get_redirect_uri();
        
        $params = [
            'client_id' => self::CLIENT_ID,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => \wp_create_nonce('gci_google_auth'),
        ];
        
        return self::GOOGLE_AUTH_URL . '?' . http_build_query($params);
    }
    
    /**
     * Get the redirect URI based on current environment
     *
     * @return string Redirect URI
     */
    protected function get_redirect_uri() {
        $admin_url = \admin_url('admin.php?page=gutenberg-content-importer&google_callback=1');
        
        // For localhost, ensure we use the exact registered URI
        if (strpos($admin_url, 'localhost:8888') !== false) {
            return 'http://localhost:8888/wp-admin/admin.php?page=gutenberg-content-importer&google_callback=1';
        }
        
        return $admin_url;
    }
    
    /**
     * Handle OAuth callback
     *
     * @param string $code Authorization code
     * @param string $state State parameter
     * @return bool Success
     */
    public function handle_oauth_callback($code, $state) {
        // Verify state
        if (!\wp_verify_nonce($state, 'gci_google_auth')) {
            return false;
        }
        
        $redirect_uri = $this->get_redirect_uri();
        
        // Exchange code for token
        $response = \wp_remote_post(self::GOOGLE_TOKEN_URL, [
            'body' => [
                'code' => $code,
                'client_id' => self::CLIENT_ID,
                'client_secret' => self::CLIENT_SECRET,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code',
            ],
            'timeout' => 30,
        ]);
        
        if (\is_wp_error($response)) {
            error_log('GCI Google OAuth error: ' . $response->get_error_message());
            return false;
        }
        
        $body = json_decode(\wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            // Store tokens
            $tokens = [
                'access_token' => $body['access_token'],
                'refresh_token' => $body['refresh_token'] ?? '',
                'expires_at' => time() + ($body['expires_in'] ?? 3600),
            ];
            
            \update_option('gci_google_tokens', $tokens);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get valid access token
     *
     * @return string|false Access token or false
     */
    protected function get_access_token() {
        $tokens = \get_option('gci_google_tokens', []);
        
        if (empty($tokens['access_token'])) {
            return false;
        }
        
        // Check if token is expired
        if (isset($tokens['expires_at']) && $tokens['expires_at'] < time()) {
            // Try to refresh token
            if (!empty($tokens['refresh_token'])) {
                $new_token = $this->refresh_access_token($tokens['refresh_token']);
                if ($new_token) {
                    return $new_token;
                }
            }
            return false;
        }
        
        return $tokens['access_token'];
    }
    
    /**
     * Refresh access token
     *
     * @param string $refresh_token Refresh token
     * @return string|false New access token or false
     */
    protected function refresh_access_token($refresh_token) {
        $response = \wp_remote_post(self::GOOGLE_TOKEN_URL, [
            'body' => [
                'refresh_token' => $refresh_token,
                'client_id' => self::CLIENT_ID,
                'client_secret' => self::CLIENT_SECRET,
                'grant_type' => 'refresh_token',
            ],
            'timeout' => 30,
        ]);
        
        if (\is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(\wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            // Update stored tokens
            $tokens = \get_option('gci_google_tokens', []);
            $tokens['access_token'] = $body['access_token'];
            $tokens['expires_at'] = time() + ($body['expires_in'] ?? 3600);
            \update_option('gci_google_tokens', $tokens);
            
            return $body['access_token'];
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
            $parsed = $this->parse_content($content_data);
            
            // Calculate statistics from parsed sections
            $stats = [
                'paragraphs' => 0,
                'images' => 0,
                'embeds' => 0,
                'headings' => 0,
                'lists' => 0,
                'tables' => 0,
            ];
            
            foreach ($parsed['sections'] as $section) {
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
            
            // Don't double-count images - they're already counted in sections above
            // The $parsed['images'] array is for tracking URLs, not for counting
            
            // Generate preview text and HTML content
            $preview_text = '';
            $preview_html = '';
            $char_count = 0;
            $max_chars = 500;
            
            foreach ($parsed['sections'] as $section) {
                if ($char_count >= $max_chars) {
                    break;
                }
                
                switch ($section['type']) {
                    case 'paragraph':
                        $text = strip_tags($section['content']);
                        $preview_text .= $text . ' ';
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
                    case 'table':
                        $preview_html .= '<table style="border-collapse: collapse; width: 100%;">';
                        foreach ($section['rows'] as $row) {
                            $preview_html .= '<tr>';
                            foreach ($row as $cell) {
                                $preview_html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $cell . '</td>';
                            }
                            $preview_html .= '</tr>';
                        }
                        $preview_html .= '</table>';
                        break;
                    case 'footnote':
                        $preview_html .= '<div style="font-size: 0.9em; color: #666; border-left: 3px solid #ddd; padding-left: 10px; margin: 10px 0;">' . $section['content'] . '</div>';
                        break;
                }
            }
            
            return [
                'success' => true,
                'title' => $parsed['title'],
                'excerpt' => $parsed['excerpt'],
                'content_preview' => trim(substr($preview_text, 0, $max_chars)) . '...',
                'preview_html' => $preview_html,
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
     * Extract document ID from Google Docs URL
     *
     * @param string $url Google Docs URL
     * @return string|false Document ID or false
     */
    protected function extract_document_id($url) {
        // Pattern: https://docs.google.com/document/d/{DOCUMENT_ID}/edit
        if (preg_match('/docs\.google\.com\/document\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            return $matches[1];
        }
        return false;
    }

    /**
     * Fetch content from Google Docs
     *
     * @param string $url URL to fetch
     * @return array Content data
     */
    protected function fetch_content($url) {
        $access_token = $this->get_access_token();
        if (!$access_token) {
            throw new \Exception(__('Not authenticated with Google. Please connect your Google account in settings.', 'gutenberg-content-importer'));
        }
        
        $document_id = $this->extract_document_id($url);
        if (!$document_id) {
            throw new \Exception(__('Invalid Google Docs URL', 'gutenberg-content-importer'));
        }
        
        // Fetch document metadata and content
        $response = \wp_remote_get(self::GOOGLE_DOCS_API_BASE . $document_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'timeout' => 30,
        ]);
        
        if (\is_wp_error($response)) {
            throw new \Exception(__('Failed to fetch Google Doc: ', 'gutenberg-content-importer') . $response->get_error_message());
        }
        
        $code = \wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $body = json_decode(\wp_remote_retrieve_body($response), true);
            $error_message = $body['error']['message'] ?? __('Unknown error', 'gutenberg-content-importer');
            throw new \Exception(__('Google Docs API error: ', 'gutenberg-content-importer') . $error_message);
        }
        
        $document = json_decode(\wp_remote_retrieve_body($response), true);
        
        // Debug logging for document structure
        error_log('GCI Google Docs - Document keys: ' . implode(', ', array_keys($document)));
        if (isset($document['inlineObjects'])) {
            error_log('GCI Google Docs - Inline objects count: ' . count($document['inlineObjects']));
        }
        if (isset($document['objects'])) {
            error_log('GCI Google Docs - Objects count: ' . count($document['objects']));
        }
        
        return [
            'type' => 'google-docs',
            'document_id' => $document_id,
            'url' => $url,
            'document' => $document,
        ];
    }

    /**
     * Parse Google Docs content
     *
     * @param array $content_data Content data
     * @return array Parsed content
     */
    protected function parse_content($content_data) {
        $document = $content_data['document'];
        
        $parsed = [
            'title' => $document['title'] ?? __('Untitled Document', 'gutenberg-content-importer'),
            'excerpt' => '',
            'sections' => [],
            'images' => [],
            'embeds' => [],
            'tags' => [],
            'url' => $content_data['url'],
        ];
        
        // Parse document body
        $this->current_images = [];
        if (isset($document['body']['content'])) {
            $this->parse_structural_elements($document['body']['content'], $parsed, $document);
        }
        
        // Add collected images
        $parsed['images'] = array_unique($this->current_images);
        
        // Debug logging
        error_log('GCI Google Docs - Parsed sections count: ' . count($parsed['sections']));
        error_log('GCI Google Docs - Images found: ' . count($parsed['images']));
        error_log('GCI Google Docs - Document title: ' . $parsed['title']);
        
        // Log image URLs for debugging
        foreach ($parsed['images'] as $index => $image_url) {
            error_log('GCI Google Docs - Image ' . ($index + 1) . ': ' . $image_url);
        }
        
        return $parsed;
    }
    
    /**
     * Parse structural elements from Google Docs
     *
     * @param array $elements Structural elements
     * @param array &$parsed Parsed content array
     * @param array $document Full document data
     */
    protected function parse_structural_elements($elements, &$parsed, $document) {
        $current_list = null;
        $current_list_type = null;
        
        // Debug logging
        error_log('GCI Google Docs - Processing ' . count($elements) . ' structural elements');
        
        foreach ($elements as $element) {
            // Debug logging for element types
            $element_types = array_keys($element);
            error_log('GCI Google Docs - Element types: ' . implode(', ', $element_types));
            
            if (isset($element['paragraph'])) {
                $paragraph = $element['paragraph'];
                
                // Check if it's a list item
                if (isset($paragraph['bullet'])) {
                    $parsed_result = $this->parse_paragraph_elements($paragraph['elements'], $document);
                    $list_item = $parsed_result['html'];
                    $is_ordered = isset($paragraph['bullet']['listId']);
                    
                    // Handle list grouping
                    if ($current_list === null || $current_list_type !== $is_ordered) {
                        if ($current_list !== null) {
                            $parsed['sections'][] = $current_list;
                        }
                        
                        $current_list = [
                            'type' => 'list',
                            'ordered' => $is_ordered,
                            'items' => [$list_item],
                        ];
                        $current_list_type = $is_ordered;
                    } else {
                        $current_list['items'][] = $list_item;
                    }
                    
                    // Add any images found in the list item
                    if (!empty($parsed_result['images'])) {
                        foreach ($parsed_result['images'] as $image) {
                            $parsed['sections'][] = $image;
                        }
                    }
                } else {
                    // Save any pending list
                    if ($current_list !== null) {
                        $parsed['sections'][] = $current_list;
                        $current_list = null;
                        $current_list_type = null;
                    }
                    
                    // Parse as regular paragraph or heading
                    $style = $paragraph['paragraphStyle']['namedStyleType'] ?? 'NORMAL_TEXT';
                    $parsed_result = $this->parse_paragraph_elements($paragraph['elements'], $document);
                    $content = $parsed_result['html'];
                    
                    // Only skip if content is truly empty (no text, no images)
                    $stripped_content = trim(strip_tags($content));
                    if (empty($stripped_content) && empty($parsed_result['images'])) {
                        continue;
                    }
                    
                    if (strpos($style, 'HEADING_') === 0) {
                        // It's a heading
                        $level = intval(substr($style, 8)) ?: 2;
                        $parsed['sections'][] = [
                            'type' => 'heading',
                            'level' => $level,
                            'content' => $content,
                        ];
                    } elseif ($style === 'SUBTITLE') {
                        // Subtitle becomes excerpt
                        if (empty($parsed['excerpt'])) {
                            $parsed['excerpt'] = strip_tags($content);
                        } else {
                            $parsed['sections'][] = [
                                'type' => 'paragraph',
                                'content' => '<em>' . $content . '</em>',
                            ];
                        }
                    } else {
                        // Regular paragraph
                        $parsed['sections'][] = [
                            'type' => 'paragraph',
                            'content' => $content,
                        ];
                    }
                    
                    // Add any images found in the paragraph
                    if (!empty($parsed_result['images'])) {
                        foreach ($parsed_result['images'] as $image) {
                            $parsed['sections'][] = $image;
                        }
                    }
                }
            } elseif (isset($element['table'])) {
                // Save any pending list
                if ($current_list !== null) {
                    $parsed['sections'][] = $current_list;
                    $current_list = null;
                    $current_list_type = null;
                }
                
                // Parse table
                $table_section = $this->parse_table($element['table'], $document);
                if ($table_section) {
                    $parsed['sections'][] = $table_section;
                }
            } elseif (isset($element['positionedObject'])) {
                // Save any pending list
                if ($current_list !== null) {
                    $parsed['sections'][] = $current_list;
                    $current_list = null;
                    $current_list_type = null;
                }
                
                // Parse positioned objects (floating images, etc.)
                $positioned_section = $this->parse_positioned_object($element['positionedObject'], $document);
                if ($positioned_section) {
                    $parsed['sections'][] = $positioned_section;
                }
            } elseif (isset($element['footnoteReference'])) {
                // Handle footnote references
                $footnote_section = $this->parse_footnote_reference($element['footnoteReference'], $document);
                if ($footnote_section) {
                    $parsed['sections'][] = $footnote_section;
                }
            } elseif (isset($element['sectionBreak'])) {
                // Save any pending list
                if ($current_list !== null) {
                    $parsed['sections'][] = $current_list;
                    $current_list = null;
                    $current_list_type = null;
                }
                
                // Add separator
                $parsed['sections'][] = [
                    'type' => 'separator',
                ];
            }
        }
        
        // Don't forget the last list
        if ($current_list !== null) {
            $parsed['sections'][] = $current_list;
        }
    }
    
    /**
     * Parse paragraph elements
     *
     * @param array $elements Paragraph elements
     * @param array $document Full document data
     * @return string HTML content
     */
    protected function parse_paragraph_elements($elements, $document) {
        $html = '';
        $images = [];
        
        foreach ($elements as $element) {
            if (isset($element['textRun'])) {
                $text = $element['textRun']['content'];
                $style = $element['textRun']['textStyle'] ?? [];
                
                // Apply formatting
                if (isset($style['bold']) && $style['bold']) {
                    $text = '<strong>' . $text . '</strong>';
                }
                if (isset($style['italic']) && $style['italic']) {
                    $text = '<em>' . $text . '</em>';
                }
                if (isset($style['underline']) && $style['underline']) {
                    $text = '<u>' . $text . '</u>';
                }
                if (isset($style['strikethrough']) && $style['strikethrough']) {
                    $text = '<s>' . $text . '</s>';
                }
                if (isset($style['link'])) {
                    $url = $style['link']['url'] ?? '';
                    if (!empty($url)) {
                        $text = '<a href="' . \esc_url($url) . '">' . $text . '</a>';
                    }
                }
                
                // Handle baseline offset (superscript/subscript)
                if (isset($style['baselineOffset'])) {
                    if ($style['baselineOffset'] === 'SUPERSCRIPT') {
                        $text = '<sup>' . $text . '</sup>';
                    } elseif ($style['baselineOffset'] === 'SUBSCRIPT') {
                        $text = '<sub>' . $text . '</sub>';
                    }
                }
                
                $html .= $text;
            } elseif (isset($element['inlineObjectElement'])) {
                // Handle inline images - extract them separately
                $object_id = $element['inlineObjectElement']['inlineObjectId'];
                if (isset($document['inlineObjects'][$object_id])) {
                    $inline_object = $document['inlineObjects'][$object_id];
                    if (isset($inline_object['inlineObjectProperties']['embeddedObject']['imageProperties'])) {
                        $image_props = $inline_object['inlineObjectProperties']['embeddedObject']['imageProperties'];
                        $content_uri = $image_props['contentUri'] ?? '';
                        if (!empty($content_uri)) {
                            $processed_url = $this->process_image_url($content_uri);
                            
                            // Track the image URL for downloading
                            $this->current_images[] = $processed_url;
                            
                            // Add image to images array for separate processing
                            $images[] = [
                                'type' => 'image',
                                'url' => $processed_url,
                                'alt' => '',
                            ];
                        }
                    }
                }
            } elseif (isset($element['horizontalRule'])) {
                $html .= '<hr />';
            }
        }
        
        return [
            'html' => $html,
            'images' => $images,
        ];
    }
    
    /**
     * Parse table
     *
     * @param array $table Table data
     * @param array $document Full document data
     * @return array|null Table section
     */
    protected function parse_table($table, $document) {
        if (!isset($table['tableRows']) || empty($table['tableRows'])) {
            return null;
        }
        
        $rows = [];
        foreach ($table['tableRows'] as $tableRow) {
            $cells = [];
            if (isset($tableRow['tableCells'])) {
                foreach ($tableRow['tableCells'] as $tableCell) {
                    $cell_content = '';
                    if (isset($tableCell['content'])) {
                        foreach ($tableCell['content'] as $element) {
                            if (isset($element['paragraph'])) {
                                $parsed_result = $this->parse_paragraph_elements(
                                    $element['paragraph']['elements'],
                                    $document
                                );
                                $cell_content .= $parsed_result['html'];
                            }
                        }
                    }
                    $cells[] = trim($cell_content);
                }
            }
            if (!empty($cells)) {
                $rows[] = $cells;
            }
        }
        
        if (empty($rows)) {
            return null;
        }
        
        return [
            'type' => 'table',
            'rows' => $rows,
        ];
    }
    
    /**
     * Parse positioned object (floating images, etc.)
     *
     * @param array $positioned_object Positioned object data
     * @param array $document Full document data
     * @return array|null Positioned object section
     */
    protected function parse_positioned_object($positioned_object, $document) {
        if (!isset($positioned_object['objectId'])) {
            return null;
        }
        
        $object_id = $positioned_object['objectId'];
        
        // Check if it's an image
        if (isset($document['objects'][$object_id])) {
            $object = $document['objects'][$object_id];
            if (isset($object['imageProperties'])) {
                $image_props = $object['imageProperties'];
                $content_uri = $image_props['contentUri'] ?? '';
                if (!empty($content_uri)) {
                    $processed_url = $this->process_image_url($content_uri);
                    
                    // Track the image URL for downloading
                    $this->current_images[] = $processed_url;
                    
                    return [
                        'type' => 'image',
                        'url' => $processed_url,
                        'alt' => '',
                    ];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Process Google Docs image URL
     *
     * @param string $content_uri Original content URI
     * @return string Processed image URL
     */
    protected function process_image_url($content_uri) {
        // Debug logging
        error_log('GCI Google Docs - Processing image URL: ' . $content_uri);
        
        // Google Docs images often have special URLs that need processing
        // The contentUri is usually a direct link to the image
        if (strpos($content_uri, 'https://') === 0) {
            error_log('GCI Google Docs - Image URL is already absolute: ' . $content_uri);
            return $content_uri;
        }
        
        // If it's a relative URL, construct the full URL
        if (strpos($content_uri, '/') === 0) {
            $full_url = 'https://docs.google.com' . $content_uri;
            error_log('GCI Google Docs - Constructed full URL: ' . $full_url);
            return $full_url;
        }
        
        error_log('GCI Google Docs - Returning original URL: ' . $content_uri);
        return $content_uri;
    }
    
    /**
     * Parse footnote reference
     *
     * @param array $footnote_ref Footnote reference data
     * @param array $document Full document data
     * @return array|null Footnote section
     */
    protected function parse_footnote_reference($footnote_ref, $document) {
        if (!isset($footnote_ref['footnoteId'])) {
            return null;
        }
        
        $footnote_id = $footnote_ref['footnoteId'];
        
        // Check if footnote content exists
        if (isset($document['footnotes'][$footnote_id])) {
            $footnote = $document['footnotes'][$footnote_id];
            $content = '';
            
            if (isset($footnote['content'])) {
                foreach ($footnote['content'] as $element) {
                    if (isset($element['paragraph'])) {
                        $parsed_result = $this->parse_paragraph_elements(
                            $element['paragraph']['elements'],
                            $document
                        );
                        $content .= $parsed_result['html'];
                    }
                }
            }
            
            if (!empty(trim($content))) {
                return [
                    'type' => 'footnote',
                    'content' => $content,
                ];
            }
        }
        
        return null;
    }
} 