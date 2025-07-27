<?php
/**
 * History Screen
 *
 * @package GCI\Admin
 */

namespace GCI\Admin;

class History_Screen {
    /**
     * Render history screen
     */
    public function render() {
        $history = get_option('gci_import_history', []);
        ?>
        <div class="wrap">
            <h1><?php _e('Import History', 'gutenberg-content-importer'); ?></h1>
            
            <div class="gci-history-container">
                <?php if (empty($history)) : ?>
                    <div class="gci-card">
                        <p><?php _e('No imports yet. Start importing content to see your history here.', 'gutenberg-content-importer'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=gutenberg-content-importer'); ?>" class="button button-primary">
                            <?php _e('Import Content', 'gutenberg-content-importer'); ?>
                        </a>
                    </div>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Title', 'gutenberg-content-importer'); ?></th>
                                <th><?php _e('Source', 'gutenberg-content-importer'); ?></th>
                                <th><?php _e('Date', 'gutenberg-content-importer'); ?></th>
                                <th><?php _e('Actions', 'gutenberg-content-importer'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $entry) : ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <a href="<?php echo get_edit_post_link($entry['post_id']); ?>">
                                                <?php echo esc_html($entry['title']); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td><?php echo esc_html(ucfirst($entry['source'])); ?></td>
                                    <td><?php echo esc_html(mysql2date(get_option('date_format'), $entry['date'])); ?></td>
                                    <td>
                                        <a href="<?php echo get_permalink($entry['post_id']); ?>" target="_blank">
                                            <?php _e('View', 'gutenberg-content-importer'); ?>
                                        </a> |
                                        <a href="<?php echo get_edit_post_link($entry['post_id']); ?>">
                                            <?php _e('Edit', 'gutenberg-content-importer'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
} 