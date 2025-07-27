/**
 * Gutenberg Content Importer Admin JavaScript
 */

(function($) {
    'use strict';

    const GCI = {
        init: function() {
            this.bindEvents();
            this.initSourceSelection();
        },

        bindEvents: function() {
            // Source selection
            $(document).on('click', '.gci-source-item', this.selectSource);

            // Form submission
            $('#gci-import-form').on('submit', this.processImport);

            // Preview button
            $('#gci-preview-btn').on('click', this.previewImport);

            // URL input change - detect importer
            $('#gci-url').on('blur', this.detectImporter);

            // Toggle between URL and paste content
            $('input[name="import_method"]').on('change', this.toggleImportMethod);
            
            // Handle Google connect button separately
            $(document).on('click', '.gci-google-connect', function(e) {
                e.stopPropagation();
                // The onclick handler will take care of navigation
            });
        },

        initSourceSelection: function() {
            // Highlight first source by default
            $('.gci-source-item:first').addClass('selected');
        },

        selectSource: function(e) {
            e.preventDefault();
            const $item = $(this);
            const source = $item.data('source');
            
            // Don't proceed if clicking on the Google connect button
            if ($(e.target).hasClass('gci-google-connect') || $(e.target).parent().hasClass('gci-google-connect')) {
                return;
            }
            
            // Check if Google Docs and not authenticated
            if (source === 'google-docs' && $item.find('.gci-auth-disconnected').length > 0) {
                // Don't show the form, let user authenticate first
                alert('Please connect your Google account first by clicking the "Connect Google" button.');
                return;
            }

            // Update UI
            $('.gci-source-item').removeClass('selected');
            $item.addClass('selected');

            // Update form
            $('#gci-source').val(source);

            // Show import form
            $('.gci-import-form').slideDown();

            // Customize form based on source
            GCI.customizeFormForSource(source);
        },

        customizeFormForSource: function(source) {
            // Reset form
            $('.gci-url-field, .gci-content-field').show();

            switch(source) {
                case 'medium':
                    $('.gci-content-field').hide();
                    $('#gci-url').attr('placeholder', 'https://medium.com/@username/article-title');
                    break;
                
                case 'notion':
                    $('.gci-content-field').hide();
                    $('#gci-url').attr('placeholder', 'https://notion.so/page-id');
                    break;
                
                case 'google-docs':
                    $('.gci-content-field').hide();
                    $('#gci-url').attr('placeholder', 'https://docs.google.com/document/d/...');
                    break;
                
                case 'markdown':
                    $('.gci-url-field').hide();
                    $('#gci-content').attr('placeholder', 'Paste your Markdown content here...');
                    break;
            }
        },

        detectImporter: async function() {
            const url = $('#gci-url').val();
            if (!url) return;

            try {
                const response = await wp.apiFetch({
                    path: '/gci/v1/detect',
                    method: 'POST',
                    data: { url: url }
                });

                if (response.success) {
                    // Auto-select the detected importer
                    $(`.gci-source-item[data-source="${response.importer}"]`).click();
                    GCI.showNotice('Detected ' + response.importer + ' content', 'success');
                }
            } catch (error) {
                console.error('Detection error:', error);
            }
        },

        previewImport: async function(e) {
            e.preventDefault();
            
            const source = $('#gci-source').val();
            const url = $('#gci-url').val();
            const content = $('#gci-content').val();

            if (!source || (!url && !content)) {
                GCI.showNotice('Please select a source and provide content', 'error');
                return;
            }

            // Show loading
            GCI.showLoading('preview');

            try {
                const response = await wp.apiFetch({
                    path: '/gci/v1/import/preview',
                    method: 'POST',
                    data: {
                        source: source,
                        url: url,
                        content: content
                    }
                });

                if (response.success) {
                    GCI.displayPreview(response);
                } else {
                    GCI.showNotice(response.error || 'Preview failed', 'error');
                }
            } catch (error) {
                GCI.showNotice('Preview failed: ' + error.message, 'error');
            } finally {
                GCI.hideLoading();
            }
        },

        processImport: async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const source = formData.get('source');
            const url = formData.get('url');
            const content = formData.get('content');

            if (!source || (!url && !content)) {
                GCI.showNotice('Please select a source and provide content', 'error');
                return;
            }

            // Collect options
            const options = {
                download_images: formData.get('download_images') === '1',
                create_featured_image: formData.get('create_featured_image') === '1',
                preserve_formatting: formData.get('preserve_formatting') === '1',
                post_status: formData.get('post_status'),
                post_type: formData.get('post_type')
            };

            // Show loading
            GCI.showLoading('import');

            try {
                const response = await wp.apiFetch({
                    path: '/gci/v1/import/process',
                    method: 'POST',
                    data: {
                        source: source,
                        url: url,
                        content: content,
                        options: options
                    }
                });

                if (response.success) {
                    GCI.displayResults(response);
                    GCI.showNotice('Content imported successfully!', 'success');
                } else {
                    GCI.showNotice(response.error || 'Import failed', 'error');
                }
            } catch (error) {
                GCI.showNotice('Import failed: ' + error.message, 'error');
            } finally {
                GCI.hideLoading();
            }
        },

        displayPreview: function(data) {
            const $preview = $('#gci-preview-content');
            
            let html = `
                <div class="gci-preview-header">
                    <h3>${data.title}</h3>
                    ${data.author ? `<p class="gci-meta">By ${data.author}</p>` : ''}
                    ${data.published_date ? `<p class="gci-meta">Published: ${data.published_date}</p>` : ''}
                </div>
            `;

            if (data.featured_image) {
                html += `<img src="${data.featured_image}" class="gci-preview-image" alt="">`;
            }

            if (data.excerpt) {
                html += `<div class="gci-preview-excerpt">${data.excerpt}</div>`;
            }

            if (data.stats) {
                html += '<div class="gci-preview-stats">';
                html += `<span>${data.stats.paragraphs} paragraphs</span>`;
                html += `<span>${data.stats.images} images</span>`;
                html += `<span>${data.stats.embeds} embeds</span>`;
                html += '</div>';
            }

            if (data.tags && data.tags.length) {
                html += '<div class="gci-preview-tags">';
                data.tags.forEach(tag => {
                    html += `<span class="gci-tag">${tag}</span>`;
                });
                html += '</div>';
            }

            html += '<div class="gci-preview-content">' + data.content_preview + '</div>';

            $preview.html(html);
            $('.gci-preview-area').slideDown();
        },

        displayResults: function(data) {
            const $results = $('#gci-results-content');
            
            let html = `
                <div class="gci-success-message">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <h3>Import Successful!</h3>
                </div>
                <div class="gci-result-details">
                    <p><strong>Title:</strong> ${data.title}</p>
                    <p><strong>Source:</strong> ${data.source}</p>
                    <div class="gci-result-actions">
                        <a href="${data.edit_url}" class="button button-primary">
                            <span class="dashicons dashicons-edit"></span> Edit Post
                        </a>
                        <a href="${data.post_url}" class="button button-secondary" target="_blank">
                            <span class="dashicons dashicons-visibility"></span> View Post
                        </a>
                        <button type="button" class="button" onclick="location.reload()">
                            <span class="dashicons dashicons-plus-alt"></span> Import Another
                        </button>
                    </div>
                </div>
            `;

            $results.html(html);
            $('.gci-results-area').slideDown();

            // Scroll to results
            $('html, body').animate({
                scrollTop: $('.gci-results-area').offset().top - 50
            }, 500);
        },

        showLoading: function(action) {
            const text = action === 'preview' ? 'Loading preview...' : 'Importing content...';
            
            const $overlay = $('<div class="gci-loading-overlay"><div class="gci-spinner"></div><p>' + text + '</p></div>');
            $('body').append($overlay);
        },

        hideLoading: function() {
            $('.gci-loading-overlay').remove();
        },

        showNotice: function(message, type = 'info') {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible gci-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);

            $('.wrap > h1').after($notice);

            // Auto dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);

            // Manual dismiss
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(() => $notice.remove());
            });
        },

        toggleImportMethod: function() {
            const method = $(this).val();
            
            if (method === 'url') {
                $('.gci-url-field').show();
                $('.gci-content-field').hide();
            } else {
                $('.gci-url-field').hide();
                $('.gci-content-field').show();
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        GCI.init();
    });

})(jQuery); 