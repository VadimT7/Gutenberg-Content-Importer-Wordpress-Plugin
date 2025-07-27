<?php
/**
 * Markdown Importer
 *
 * @package GCI\Importers
 */

namespace GCI\Importers;

class Markdown_Importer extends Abstract_Importer {
    /**
     * Get importer name
     *
     * @return string
     */
    public function get_name() {
        return __('Markdown', 'gutenberg-content-importer');
    }

    /**
     * Get importer slug
     *
     * @return string
     */
    public function get_slug() {
        return 'markdown';
    }

    /**
     * Check if content can be imported
     *
     * @param string $url_or_content Content to check
     * @return bool
     */
    public function can_import($url_or_content) {
        // Markdown can be pasted directly, so check for markdown patterns
        if (!filter_var($url_or_content, FILTER_VALIDATE_URL)) {
            return preg_match('/^#{1,6}\s+|^\*{1,3}\s+|^\d+\.\s+|```/m', $url_or_content);
        }
        return false;
    }

    /**
     * Preview import
     *
     * @param string $url_or_content Content to preview
     * @return array Preview data
     */
    public function preview($url_or_content) {
        try {
            $parsed = $this->parse_content(['content' => $url_or_content]);
            
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

            // Generate preview HTML content
            $preview_html = '';
            $char_count = 0;
            $max_chars = 2000; // Increased for better preview
            
            foreach ($parsed['sections'] as $section) {
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
                    case 'separator':
                        $preview_html .= '<hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;" />';
                        break;
                }
            }
            
            return [
                'success' => true,
                'title' => $parsed['title'] ?? 'Markdown Content',
                'content_preview' => $this->get_preview_text($parsed),
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
     * Fetch content (for Markdown, just return as-is)
     *
     * @param string $content Markdown content
     * @return array Content data
     */
    protected function fetch_content($content) {
        return [
            'type' => 'markdown',
            'content' => $content,
        ];
    }

    /**
     * Parse Markdown content
     *
     * @param array $content_data Content data
     * @return array Parsed content
     */
    protected function parse_content($content_data) {
        $markdown = $content_data['content'];
        $sections = [];
        $title = '';

        // Debug logging
        error_log('GCI Markdown Debug: Content length: ' . strlen($markdown));
        error_log('GCI Markdown Debug: First 500 chars: ' . substr($markdown, 0, 500));

        // Split into lines for parsing
        $lines = explode("\n", $markdown);
        error_log('GCI Markdown Debug: Total lines: ' . count($lines));
        $current_block = [];
        $in_code_block = false;
        $code_language = '';

        foreach ($lines as $line) {
            // Code blocks
            if (preg_match('/^```(\w*)/', $line, $matches)) {
                if ($in_code_block) {
                    // End code block
                    $sections[] = [
                        'type' => 'code',
                        'content' => implode("\n", $current_block),
                        'language' => $code_language,
                    ];
                    $current_block = [];
                    $in_code_block = false;
                } else {
                    // Start code block
                    $in_code_block = true;
                    $code_language = $matches[1] ?? '';
                }
                continue;
            }

            if ($in_code_block) {
                $current_block[] = $line;
                continue;
            }

            // Headings
            if (preg_match('/^(#{1,6})\s+(.+)/', $line, $matches)) {
                // Save any pending paragraph
                if (!empty($current_block)) {
                    $sections[] = [
                        'type' => 'paragraph',
                        'content' => $this->parse_inline_markdown(implode(' ', $current_block)),
                    ];
                    $current_block = [];
                }
                
                $level = strlen($matches[1]);
                $heading_text = trim($matches[2]);
                
                // First H1 becomes the title
                if ($level === 1 && empty($title)) {
                    $title = $heading_text;
                }
                
                $sections[] = [
                    'type' => 'heading',
                    'level' => $level,
                    'content' => $this->parse_inline_markdown($heading_text),
                ];
                continue;
            }

            // Images
            if (preg_match('/!\[([^\]]*)\]\(([^)]+)\)/', $line, $matches)) {
                $sections[] = [
                    'type' => 'image',
                    'url' => $matches[2],
                    'alt' => $matches[1],
                ];
                continue;
            }

            // Blockquotes
            if (preg_match('/^>\s+(.+)/', $line, $matches)) {
                $sections[] = [
                    'type' => 'quote',
                    'content' => $matches[1],
                ];
                continue;
            }

            // Lists
            if (preg_match('/^[\*\-\+]\s+(.+)/', $line, $matches)) {
                // Save any pending paragraph
                if (!empty($current_block)) {
                    $sections[] = [
                        'type' => 'paragraph',
                        'content' => $this->parse_inline_markdown(implode(' ', $current_block)),
                    ];
                    $current_block = [];
                }
                
                // Check if we need to start a new list or continue existing one
                $last_section = !empty($sections) ? end($sections) : null;
                if (!$last_section || $last_section['type'] !== 'list' || $last_section['ordered'] === true) {
                    $sections[] = [
                        'type' => 'list',
                        'ordered' => false,
                        'items' => [],
                    ];
                }
                $sections[count($sections) - 1]['items'][] = $this->parse_inline_markdown($matches[1]);
                continue;
            }

            // Ordered lists  
            if (preg_match('/^\d+[\.)\s]+(.+)/', $line, $matches)) {
                // Save any pending paragraph
                if (!empty($current_block)) {
                    $sections[] = [
                        'type' => 'paragraph',
                        'content' => $this->parse_inline_markdown(implode(' ', $current_block)),
                    ];
                    $current_block = [];
                }
                
                // Check if we need to start a new list or continue existing one
                $last_section = !empty($sections) ? end($sections) : null;
                if (!$last_section || $last_section['type'] !== 'list' || $last_section['ordered'] === false) {
                    $sections[] = [
                        'type' => 'list',
                        'ordered' => true,
                        'items' => [],
                    ];
                }
                $sections[count($sections) - 1]['items'][] = $this->parse_inline_markdown($matches[1]);
                continue;
            }

            // Blockquotes
            if (preg_match('/^>\s*(.*)/', $line, $matches)) {
                // Save any pending paragraph
                if (!empty($current_block)) {
                    $sections[] = [
                        'type' => 'paragraph',
                        'content' => $this->parse_inline_markdown(implode(' ', $current_block)),
                    ];
                    $current_block = [];
                }
                
                // Check if we need to start a new quote or continue existing one
                $last_section = !empty($sections) ? end($sections) : null;
                if (!$last_section || $last_section['type'] !== 'quote') {
                    $sections[] = [
                        'type' => 'quote',
                        'content' => '',
                    ];
                }
                
                $quote_content = $sections[count($sections) - 1]['content'];
                if (!empty($quote_content)) {
                    $quote_content .= ' ';
                }
                $sections[count($sections) - 1]['content'] = $quote_content . $this->parse_inline_markdown($matches[1]);
                continue;
            }
            
            // Regular paragraphs
            if (trim($line) !== '') {
                $current_block[] = $line;
            } elseif (!empty($current_block)) {
                $sections[] = [
                    'type' => 'paragraph',
                    'content' => $this->parse_inline_markdown(implode(' ', $current_block)),
                ];
                $current_block = [];
            }
        }

        // Handle any remaining content
        if (!empty($current_block)) {
            $sections[] = [
                'type' => 'paragraph',
                'content' => $this->parse_inline_markdown(implode(' ', $current_block)),
            ];
        }

        // Debug logging
        error_log('GCI Markdown Debug: Total sections created: ' . count($sections));
        error_log('GCI Markdown Debug: Section types: ' . implode(', ', array_column($sections, 'type')));
        
        return [
            'title' => $title ?: 'Imported Markdown',
            'sections' => $sections,
            'images' => array_column(array_filter($sections, fn($s) => $s['type'] === 'image'), 'url'),
        ];
    }

    /**
     * Parse inline Markdown (bold, italic, links)
     *
     * @param string $text Text to parse
     * @return string HTML text
     */
    protected function parse_inline_markdown($text) {
        // Bold
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);
        
        // Italic
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_(.+?)_/', '<em>$1</em>', $text);
        
        // Links
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text);
        
        // Inline code
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        
        return $text;
    }

    /**
     * Get preview text
     *
     * @param array $parsed Parsed content
     * @return string Preview text
     */
    protected function get_preview_text($parsed) {
        $preview = '';
        $char_count = 0;
        
        foreach ($parsed['sections'] as $section) {
            if ($section['type'] === 'paragraph') {
                $preview .= strip_tags($section['content']) . ' ';
                $char_count += strlen($section['content']);
                
                if ($char_count > 500) {
                    break;
                }
            }
        }
        
        return substr($preview, 0, 500) . '...';
    }
} 