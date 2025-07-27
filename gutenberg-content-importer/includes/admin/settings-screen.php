<?php
/**
 * Settings Screen
 *
 * @package GCI\Admin
 */

namespace GCI\Admin;

class Settings_Screen {
    /**
     * Render settings screen
     */
    public function render() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }

        $settings = get_option('gci_settings', []);
        ?>
        <div class="wrap">
            <h1><?php _e('Content Importer Settings', 'gutenberg-content-importer'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('gci_settings', 'gci_settings_nonce'); ?>
                
                <div class="gci-card">
                    <h2><?php _e('Default Import Settings', 'gutenberg-content-importer'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Default Post Status', 'gutenberg-content-importer'); ?></th>
                            <td>
                                <select name="default_post_status">
                                    <option value="draft" <?php selected($settings['default_post_status'] ?? 'draft', 'draft'); ?>>
                                        <?php _e('Draft', 'gutenberg-content-importer'); ?>
                                    </option>
                                    <option value="publish" <?php selected($settings['default_post_status'] ?? '', 'publish'); ?>>
                                        <?php _e('Published', 'gutenberg-content-importer'); ?>
                                    </option>
                                    <option value="private" <?php selected($settings['default_post_status'] ?? '', 'private'); ?>>
                                        <?php _e('Private', 'gutenberg-content-importer'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Image Handling', 'gutenberg-content-importer'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="download_images" value="1" <?php checked($settings['download_images'] ?? true); ?>>
                                    <?php _e('Download external images', 'gutenberg-content-importer'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="optimize_images" value="1" <?php checked($settings['optimize_images'] ?? true); ?>>
                                    <?php _e('Optimize images on import', 'gutenberg-content-importer'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="create_featured_image" value="1" <?php checked($settings['create_featured_image'] ?? true); ?>>
                                    <?php _e('Set first image as featured image', 'gutenberg-content-importer'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="gci-card">
                    <h2><?php _e('Platform Settings', 'gutenberg-content-importer'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Medium API Key', 'gutenberg-content-importer'); ?></th>
                            <td>
                                <input type="text" name="medium_api_key" value="<?php echo esc_attr($settings['medium_api_key'] ?? ''); ?>" class="regular-text">
                                <p class="description">
                                    <?php _e('RapidAPI key for Medium content. Get from', 'gutenberg-content-importer'); ?> 
                                    <a href="https://rapidapi.com/nishujain199719-vgIfuFHZxVZ/api/medium2/" target="_blank">RapidAPI</a>.
                                    <?php _e('Default key provided for demo.', 'gutenberg-content-importer'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Notion API Key', 'gutenberg-content-importer'); ?></th>
                            <td>
                                <input type="text" name="notion_api_key" value="<?php echo esc_attr($settings['notion_api_key'] ?? ''); ?>" class="regular-text">
                                <p class="description">
                                    <?php _e('Required for importing from Notion. Get your API key from Notion integrations.', 'gutenberg-content-importer'); ?>
                                </p>
                            </td>
                        </tr>
                        

                    </table>
                </div>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="<?php _e('Save Settings', 'gutenberg-content-importer'); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Save settings
     */
    protected function save_settings() {
        if (!wp_verify_nonce($_POST['gci_settings_nonce'], 'gci_settings')) {
            return;
        }

        $settings = [
            'default_post_status' => sanitize_text_field($_POST['default_post_status'] ?? 'draft'),
            'default_post_type' => 'post',
            'download_images' => !empty($_POST['download_images']),
            'optimize_images' => !empty($_POST['optimize_images']),
            'create_featured_image' => !empty($_POST['create_featured_image']),
            'preserve_formatting' => true,
            'medium_api_key' => sanitize_text_field($_POST['medium_api_key'] ?? ''),
            'notion_api_key' => sanitize_text_field($_POST['notion_api_key'] ?? ''),
        ];

        update_option('gci_settings', $settings);

        add_settings_error(
            'gci_settings',
            'settings_updated',
            __('Settings saved.', 'gutenberg-content-importer'),
            'updated'
        );
    }
} 