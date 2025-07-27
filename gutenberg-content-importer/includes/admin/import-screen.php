<?php
/**
 * Import Screen
 *
 * @package GCI\Admin
 */

namespace GCI\Admin;

use GCI\Importers\Importer_Factory;

class Import_Screen {
    /**
     * Render import screen
     */
    public function render() {
        $importers = Importer_Factory::get_importers();
        ?>
        <div class="wrap">
            <h1><?php _e('Import Content to Gutenberg', 'gutenberg-content-importer'); ?></h1>
            
            <div class="gci-import-container">
                <!-- Import Source Selection -->
                <div class="gci-card">
                    <h2><?php _e('Select Import Source', 'gutenberg-content-importer'); ?></h2>
                    <div class="gci-source-grid">
                        <?php foreach ($importers as $slug => $importer) : ?>
                            <div class="gci-source-item" data-source="<?php echo esc_attr($slug); ?>">
                                <div class="gci-source-icon">
                                    <?php echo $this->get_source_icon($slug); ?>
                                </div>
                                <h3><?php echo esc_html($importer['name']); ?></h3>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Import Form -->
                <div class="gci-card gci-import-form" style="display: none;">
                    <h2><?php _e('Import Details', 'gutenberg-content-importer'); ?></h2>
                    
                    <form id="gci-import-form">
                        <input type="hidden" id="gci-source" name="source" value="">
                        
                        <!-- URL Input -->
                        <div class="gci-form-field gci-url-field">
                            <label for="gci-url"><?php _e('Content URL', 'gutenberg-content-importer'); ?></label>
                            <input type="url" id="gci-url" name="url" class="regular-text" placeholder="https://example.com/article">
                            <p class="description"><?php _e('Enter the URL of the content you want to import', 'gutenberg-content-importer'); ?></p>
                        </div>

                        <!-- Paste Content -->
                        <div class="gci-form-field gci-content-field" style="display: none;">
                            <label for="gci-content"><?php _e('Paste Content', 'gutenberg-content-importer'); ?></label>
                            <textarea id="gci-content" name="content" rows="10" class="large-text"></textarea>
                            <p class="description"><?php _e('Paste the content you want to import', 'gutenberg-content-importer'); ?></p>
                        </div>

                        <!-- Import Options -->
                        <div class="gci-form-field">
                            <h3><?php _e('Import Options', 'gutenberg-content-importer'); ?></h3>
                            
                            <label>
                                <input type="checkbox" name="download_images" value="1" checked>
                                <?php _e('Download and import images', 'gutenberg-content-importer'); ?>
                            </label>
                            
                            <label>
                                <input type="checkbox" name="create_featured_image" value="1" checked>
                                <?php _e('Set first image as featured image', 'gutenberg-content-importer'); ?>
                            </label>
                            
                            <label>
                                <input type="checkbox" name="preserve_formatting" value="1" checked>
                                <?php _e('Preserve original formatting', 'gutenberg-content-importer'); ?>
                            </label>
                        </div>

                        <!-- Post Settings -->
                        <div class="gci-form-field">
                            <h3><?php _e('Post Settings', 'gutenberg-content-importer'); ?></h3>
                            
                            <label for="gci-post-status"><?php _e('Post Status', 'gutenberg-content-importer'); ?></label>
                            <select id="gci-post-status" name="post_status">
                                <option value="draft"><?php _e('Draft', 'gutenberg-content-importer'); ?></option>
                                <option value="publish"><?php _e('Published', 'gutenberg-content-importer'); ?></option>
                                <option value="private"><?php _e('Private', 'gutenberg-content-importer'); ?></option>
                            </select>
                            
                            <label for="gci-post-type"><?php _e('Post Type', 'gutenberg-content-importer'); ?></label>
                            <select id="gci-post-type" name="post_type">
                                <?php
                                $post_types = get_post_types(['public' => true], 'objects');
                                foreach ($post_types as $post_type) :
                                    if ($post_type->name === 'attachment') continue;
                                ?>
                                    <option value="<?php echo esc_attr($post_type->name); ?>">
                                        <?php echo esc_html($post_type->labels->singular_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Action Buttons -->
                        <div class="gci-form-actions">
                            <button type="button" id="gci-preview-btn" class="button button-secondary">
                                <?php _e('Preview Import', 'gutenberg-content-importer'); ?>
                            </button>
                            <button type="submit" id="gci-import-btn" class="button button-primary">
                                <?php _e('Import Content', 'gutenberg-content-importer'); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Preview Area -->
                <div class="gci-card gci-preview-area" style="display: none;">
                    <h2><?php _e('Import Preview', 'gutenberg-content-importer'); ?></h2>
                    <div id="gci-preview-content"></div>
                </div>

                <!-- Import Results -->
                <div class="gci-card gci-results-area" style="display: none;">
                    <h2><?php _e('Import Results', 'gutenberg-content-importer'); ?></h2>
                    <div id="gci-results-content"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get source icon
     *
     * @param string $source Source slug
     * @return string Icon HTML
     */
    protected function get_source_icon($source) {
        $icons = [
            'medium' => '<span class="dashicons dashicons-media-text"></span>',
            'notion' => '<span class="dashicons dashicons-welcome-write-blog"></span>',
            'google-docs' => '<span class="dashicons dashicons-media-document"></span>',
            'markdown' => '<span class="dashicons dashicons-editor-code"></span>',
        ];

        return $icons[$source] ?? '<span class="dashicons dashicons-admin-page"></span>';
    }
} 