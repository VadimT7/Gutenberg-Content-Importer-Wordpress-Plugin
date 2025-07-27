<?php
/**
 * Content Parser - General content parsing utilities
 *
 * @package GCI\Utils
 */

namespace GCI\Utils;

class Content_Parser {
    /**
     * Clean and normalize HTML content
     *
     * @param string $html HTML content
     * @return string Cleaned HTML
     */
    public function clean_html($html) {
        // Remove script tags
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        
        // Remove style tags
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        
        // Remove HTML comments
        $html = preg_replace('/<!--(.|\s)*?-->/', '', $html);
        
        // Fix broken tags
        $html = $this->fix_broken_tags($html);
        
        return trim($html);
    }

    /**
     * Extract text content from HTML
     *
     * @param string $html HTML content
     * @return string Plain text
     */
    public function extract_text($html) {
        // Remove scripts and styles first
        $html = $this->clean_html($html);
        
        // Convert breaks to newlines
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        
        // Strip tags
        $text = strip_tags($html);
        
        // Decode entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return $text;
    }

    /**
     * Parse metadata from HTML head
     *
     * @param string $html Full HTML document
     * @return array Metadata
     */
    public function parse_metadata($html) {
        $metadata = [
            'title' => '',
            'description' => '',
            'author' => '',
            'published_date' => '',
            'modified_date' => '',
            'image' => '',
            'keywords' => [],
        ];

        $doc = new \DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new \DOMXPath($doc);

        // Title
        $title = $xpath->query('//meta[@property="og:title"]/@content')->item(0);
        if (!$title) {
            $title = $xpath->query('//title')->item(0);
        }
        $metadata['title'] = $title ? trim($title->textContent) : '';

        // Description
        $desc = $xpath->query('//meta[@property="og:description"]/@content')->item(0);
        if (!$desc) {
            $desc = $xpath->query('//meta[@name="description"]/@content')->item(0);
        }
        $metadata['description'] = $desc ? trim($desc->value) : '';

        // Author
        $author = $xpath->query('//meta[@name="author"]/@content')->item(0);
        $metadata['author'] = $author ? trim($author->value) : '';

        // Published date
        $published = $xpath->query('//meta[@property="article:published_time"]/@content')->item(0);
        $metadata['published_date'] = $published ? trim($published->value) : '';

        // Modified date
        $modified = $xpath->query('//meta[@property="article:modified_time"]/@content')->item(0);
        $metadata['modified_date'] = $modified ? trim($modified->value) : '';

        // Featured image
        $image = $xpath->query('//meta[@property="og:image"]/@content')->item(0);
        $metadata['image'] = $image ? trim($image->value) : '';

        // Keywords
        $keywords = $xpath->query('//meta[@name="keywords"]/@content')->item(0);
        if ($keywords) {
            $metadata['keywords'] = array_map('trim', explode(',', $keywords->value));
        }

        return $metadata;
    }

    /**
     * Fix broken HTML tags
     *
     * @param string $html HTML content
     * @return string Fixed HTML
     */
    protected function fix_broken_tags($html) {
        // Use DOMDocument to fix HTML
        $doc = new \DOMDocument();
        $doc->encoding = 'UTF-8';
        
        // Suppress warnings
        libxml_use_internal_errors(true);
        
        // Load HTML with proper encoding
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // Clear errors
        libxml_clear_errors();
        
        // Save and return
        $fixed = $doc->saveHTML();
        
        // Remove the XML declaration we added
        $fixed = str_replace('<?xml encoding="UTF-8">', '', $fixed);
        
        return $fixed;
    }

    /**
     * Convert relative URLs to absolute
     *
     * @param string $html HTML content
     * @param string $base_url Base URL
     * @return string HTML with absolute URLs
     */
    public function make_urls_absolute($html, $base_url) {
        $base_parts = parse_url($base_url);
        $base_scheme = $base_parts['scheme'] ?? 'https';
        $base_host = $base_parts['host'] ?? '';
        $base_path = $base_parts['path'] ?? '/';

        // Remove filename from base path
        $base_path = dirname($base_path);
        if ($base_path === '.') {
            $base_path = '/';
        }

        $doc = new \DOMDocument();
        @$doc->loadHTML($html);

        // Process links
        $links = $doc->getElementsByTagName('a');
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if ($href) {
                $absolute = $this->resolve_url($href, $base_scheme, $base_host, $base_path);
                $link->setAttribute('href', $absolute);
            }
        }

        // Process images
        $images = $doc->getElementsByTagName('img');
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            if ($src) {
                $absolute = $this->resolve_url($src, $base_scheme, $base_host, $base_path);
                $img->setAttribute('src', $absolute);
            }
        }

        return $doc->saveHTML();
    }

    /**
     * Resolve relative URL to absolute
     *
     * @param string $url URL to resolve
     * @param string $scheme Base scheme
     * @param string $host Base host
     * @param string $path Base path
     * @return string Absolute URL
     */
    protected function resolve_url($url, $scheme, $host, $path) {
        // Already absolute
        if (preg_match('/^https?:\/\//', $url)) {
            return $url;
        }

        // Protocol relative
        if (strpos($url, '//') === 0) {
            return $scheme . ':' . $url;
        }

        // Absolute path
        if (strpos($url, '/') === 0) {
            return $scheme . '://' . $host . $url;
        }

        // Relative path
        return $scheme . '://' . $host . $path . '/' . $url;
    }
} 