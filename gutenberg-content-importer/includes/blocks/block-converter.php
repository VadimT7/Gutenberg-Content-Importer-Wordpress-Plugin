<?php
/**
 * Block Converter - Converts parsed content to Gutenberg blocks
 *
 * @package GCI\Blocks
 */

namespace GCI\Blocks;

class Block_Converter {
    /**
     * Convert content section to Gutenberg block
     *
     * @param array $section Content section
     * @return array|null Block array
     */
    public function convert($section) {
        if (empty($section['type'])) {
            return null;
        }

        switch ($section['type']) {
            case 'paragraph':
                return $this->create_paragraph_block($section);
            
            case 'heading':
                return $this->create_heading_block($section);
            
            case 'image':
                return $this->create_image_block($section);
            
            case 'quote':
                return $this->create_quote_block($section);
            
            case 'code':
                return $this->create_code_block($section);
            
            case 'list':
                return $this->create_list_block($section);
            
            case 'embed':
                return $this->create_embed_block($section);
            
            case 'separator':
                return $this->create_separator_block();
            
            case 'table':
                return $this->create_table_block($section);
            
            case 'video':
                return $this->create_video_block($section);
            
            case 'audio':
                return $this->create_audio_block($section);
            
            case 'file':
                return $this->create_file_block($section);
            
            default:
                // Fallback to paragraph for unknown types
                return $this->create_paragraph_block([
                    'content' => $section['content'] ?? '',
                ]);
        }
    }

    /**
     * Create paragraph block
     *
     * @param array $section Section data
     * @return array Block array
     */
    protected function create_paragraph_block($section) {
        $content = $section['content'] ?? '';
        
        // Skip empty paragraphs
        if (empty(trim(strip_tags($content)))) {
            return null;
        }
        
        // Process inline formatting
        $content = $this->process_inline_formatting($content);

        return [
            'blockName' => 'core/paragraph',
            'attrs' => [],
            'innerBlocks' => [],
            'innerHTML' => '<p>' . $content . '</p>',
            'innerContent' => ['<p>' . $content . '</p>'],
        ];
    }

    /**
     * Create heading block
     *
     * @param array $section Section data
     * @return array Block array
     */
    protected function create_heading_block($section) {
        $level = isset($section['level']) ? intval($section['level']) : 2;
        $content = $section['content'] ?? '';

        // Ensure level is between 1-6
        $level = max(1, min(6, $level));

        return [
            'blockName' => 'core/heading',
            'attrs' => [
                'level' => $level,
            ],
            'innerBlocks' => [],
            'innerHTML' => '<h' . $level . '>' . esc_html($content) . '</h' . $level . '>',
            'innerContent' => ['<h' . $level . '>' . esc_html($content) . '</h' . $level . '>'],
        ];
    }

    /**
     * Create image block
     *
     * @param array $section Section data
     * @return array Block array
     */
    protected function create_image_block($section) {
        $url = $section['url'] ?? '';
        $alt = $section['alt'] ?? '';
        $caption = $section['caption'] ?? '';

        $attrs = [
            'url' => $url,
            'alt' => $alt,
        ];

        // Add alignment if specified
        if (!empty($section['align'])) {
            $attrs['align'] = $section['align'];
        }

        // Add size if specified
        if (!empty($section['sizeSlug'])) {
            $attrs['sizeSlug'] = $section['sizeSlug'];
        }

        $html = '<figure class="wp-block-image">';
        $html .= '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '"/>';
        
        if (!empty($caption)) {
            $html .= '<figcaption>' . esc_html($caption) . '</figcaption>';
        }
        
        $html .= '</figure>';

        return [
            'blockName' => 'core/image',
            'attrs' => $attrs,
            'innerBlocks' => [],
            'innerHTML' => $html,
            'innerContent' => [$html],
        ];
    }

    /**
     * Create quote block
     *
     * @param array $section Section data
     * @return array Block array
     */
    protected function create_quote_block($section) {
        $content = $section['content'] ?? '';
        $citation = $section['citation'] ?? '';

        $html = '<blockquote class="wp-block-quote">';
        $html .= '<p>' . $this->process_inline_formatting($content) . '</p>';
        
        if (!empty($citation)) {
            $html .= '<cite>' . esc_html($citation) . '</cite>';
        }
        
        $html .= '</blockquote>';

        return [
            'blockName' => 'core/quote',
            'attrs' => [],
            'innerBlocks' => [],
            'innerHTML' => $html,
            'innerContent' => [$html],
        ];
    }

    /**
     * Create code block
     *
     * @param array $section Section data
     * @return array Block array
     */
    protected function create_code_block($section) {
        $content = $section['content'] ?? '';
        $language = $section['language'] ?? '';

        $attrs = [];
        if (!empty($language)) {
            $attrs['language'] = $language;
        }

        $html = '<pre class="wp-block-code"><code>' . esc_html($content) . '</code></pre>';

        return [
            'blockName' => 'core/code',
            'attrs' => $attrs,
            'innerBlocks' => [],
            'innerHTML' => $html,
            'innerContent' => [$html],
        ];
    }

    /**
     * Create list block
     *
     * @param array $section Section data
     * @return array Block array
     */
    protected function create_list_block($section) {
        $items = $section['items'] ?? [];
        $ordered = $section['ordered'] ?? false;

        $tag = $ordered ? 'ol' : 'ul';
        $html = '<' . $tag . '>';
        
        foreach ($items as $item) {
            $html .= '<li>' . $this->process_inline_formatting($item) . '</li>';
        }
        
        $html .= '</' . $tag . '>';

        return [
            'blockName' => 'core/list',
            'attrs' => [
                'ordered' => $ordered,
            ],
            'innerBlocks' => [],
            'innerHTML' => $html,
            'innerContent' => [$html],
        ];
    }

    /**
     * Create embed block
     *
     * @param array $section Section data
     * @return array Block array
     */
    protected function create_embed_block($section) {
        $url = $section['url'] ?? '';
        $provider = $section['provider'] ?? '';

        // Map provider to WordPress embed provider name
        $provider_map = [
            'youtube' => 'youtube',
            'twitter' => 'twitter',
            'vimeo' => 'vimeo',
            'instagram' => 'instagram',
            'facebook' => 'facebook',
            'soundcloud' => 'soundcloud',
            'spotify' => 'spotify',
            'github' => 'github',
        ];

        $wp_provider = isset($provider_map[$provider]) ? $provider_map[$provider] : '';

        $attrs = [
            'url' => $url,
            'type' => 'rich',
            'providerNameSlug' => $wp_provider,
        ];

        $html = '<figure class="wp-block-embed">';
        $html .= '<div class="wp-block-embed__wrapper">' . esc_url($url) . '</div>';
        $html .= '</figure>';

        $block_name = $wp_provider ? 'core/embed' : 'core/html';

        return [
            'blockName' => $block_name,
            'attrs' => $attrs,
            'innerBlocks' => [],
            'innerHTML' => $html,
            'innerContent' => [$html],
        ];
    }

    /**
     * Create separator block
     *
     * @return array Block array
     */
    protected function create_separator_block() {
        return [
            'blockName' => 'core/separator',
            'attrs' => [],
            'innerBlocks' => [],
            'innerHTML' => '<hr class="wp-block-separator"/>',
            'innerContent' => ['<hr class="wp-block-separator"/>'],
        ];
    }

    /**
     * Create table block
     *
     * @param array $section Section data
     * @return array Block array
     */
    protected function create_table_block($section) {
        $headers = $section['headers'] ?? [];
        $rows = $section['rows'] ?? [];

        $html = '<figure class="wp-block-table"><table>';
        
        // Add headers
        if (!empty($headers)) {
            $html .= '<thead><tr>';
            foreach ($headers as $header) {
                $html .= '<th>' . esc_html($header) . '</th>';
            }
            $html .= '</tr></thead>';
        }

        // Add rows
        if (!empty($rows)) {
            $html .= '<tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>' . esc_html($cell) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody>';
        }

        $html .= '</table></figure>';

        return [
            'blockName' => 'core/table',
            'attrs' => [],
            'innerBlocks' => [],
            'innerHTML' => $html,
            'innerContent' => [$html],
        ];
    }

    /**
     * Create video block
     *
     * @param array $section Section data
     * @return array Block array
     */
    protected function create_video_block($section) {
        $url = $section['url'] ?? '';
        $caption = $section['caption'] ?? '';

        $html = '<figure class="wp-block-video">';
        $html .= '<video controls src="' . esc_url($url) . '"></video>';
        
        if (!empty($caption)) {
            $html .= '<figcaption>' . esc_html($caption) . '</figcaption>';
        }
        
        $html .= '</figure>';

        return [
            'blockName' => 'core/video',
            'attrs' => [
                'src' => $url,
            ],
            'innerBlocks' => [],
            'innerHTML' => $html,
            'innerContent' => [$html],
        ];
    }

    /**
     * Create audio block
     *
     * @param array $section Section data
     * @return array Block array
     */
    protected function create_audio_block($section) {
        $url = $section['url'] ?? '';
        $caption = $section['caption'] ?? '';

        $html = '<figure class="wp-block-audio">';
        $html .= '<audio controls src="' . esc_url($url) . '"></audio>';
        
        if (!empty($caption)) {
            $html .= '<figcaption>' . esc_html($caption) . '</figcaption>';
        }
        
        $html .= '</figure>';

        return [
            'blockName' => 'core/audio',
            'attrs' => [
                'src' => $url,
            ],
            'innerBlocks' => [],
            'innerHTML' => $html,
            'innerContent' => [$html],
        ];
    }

    /**
     * Create file block
     *
     * @param array $section Section data
     * @return array Block array
     */
    protected function create_file_block($section) {
        $url = $section['url'] ?? '';
        $filename = $section['filename'] ?? basename($url);

        $html = '<div class="wp-block-file">';
        $html .= '<a href="' . esc_url($url) . '">' . esc_html($filename) . '</a>';
        $html .= '<a href="' . esc_url($url) . '" class="wp-block-file__button" download>Download</a>';
        $html .= '</div>';

        return [
            'blockName' => 'core/file',
            'attrs' => [
                'href' => $url,
            ],
            'innerBlocks' => [],
            'innerHTML' => $html,
            'innerContent' => [$html],
        ];
    }

    /**
     * Process inline formatting (bold, italic, links, etc.)
     *
     * @param string $content Content to process
     * @return string Processed content
     */
    protected function process_inline_formatting($content) {
        // If content is empty, return empty string
        if (empty($content)) {
            return '';
        }

        // Check if content already contains valid HTML formatting
        $allowed_tags = ['strong', 'em', 'a', 'code', 'b', 'i', 'u', 's'];
        $has_allowed_html = false;
        
        foreach ($allowed_tags as $tag) {
            if (strpos($content, '<' . $tag) !== false) {
                $has_allowed_html = true;
                break;
            }
        }

        // If it has allowed HTML, sanitize but keep formatting
        if ($has_allowed_html) {
            return wp_kses($content, [
                'strong' => [],
                'em' => [],
                'a' => ['href' => [], 'title' => [], 'target' => [], 'rel' => []],
                'code' => [],
                'b' => [],
                'i' => [],
                'u' => [],
                's' => [],
                'br' => [],
            ]);
        }

        // Otherwise, escape HTML
        return esc_html($content);
    }
} 