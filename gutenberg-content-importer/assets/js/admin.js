/**
 * Gutenberg Content Importer Admin JavaScript
 */

(function($) {
    'use strict';

    const GCI = {
        activeImports: new Map(),
        
        init: function() {
            this.bindEvents();
            this.initSourceSelection();
            this.checkActiveImports();
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
            
            // Cancel import button
            $(document).on('click', '.gci-cancel-import', this.cancelImport);
        },

        initSourceSelection: function() {
            // Highlight first source by default
            $('.gci-source-item:first').addClass('selected');
        },
        
        checkActiveImports: function() {
            // Check for any active imports on page load
            $.post(gciAdmin.ajaxUrl, {
                action: 'gci_get_active_imports',
                nonce: gciAdmin.ajaxNonce
            }, function(response) {
                if (response.success && response.data.length > 0) {
                    response.data.forEach(importData => {
                        GCI.resumeProgressTracking(importData.id);
                    });
                }
            });
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

            // Clear URL and content fields when switching sources
            $('#gci-url').val('');
            $('#gci-content').val('');
            
            // Clear any existing preview or results
            $('.gci-preview-area').slideUp();
            $('.gci-results-area').slideUp();

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

            const source = $('#gci-source').val();
            const url = $('#gci-url').val();
            const content = $('#gci-content').val();

            if (!source || (!url && !content)) {
                GCI.showNotice('Please select a source and provide content', 'error');
                return;
            }

            // Collect options
            const options = {
                download_images: $('#download_images').is(':checked'),
                preserve_formatting: $('#preserve_formatting').is(':checked'),
                post_status: $('#post_status').val(),
                post_type: $('#post_type').val()
            };

            // Initiate import via AJAX (will return import ID for tracking)
            $.post(gciAdmin.ajaxUrl, {
                action: 'gci_process_import',
                nonce: gciAdmin.ajaxNonce,
                source: source,
                url: url,
                content: content,
                options: JSON.stringify(options)
            }, function(response) {
                if (response.success && response.data.import_id) {
                    // Start tracking progress
                    GCI.trackImportProgress(response.data.import_id, response.data.sse_url);
                } else {
                    GCI.showNotice('Failed to start import', 'error');
                }
            }).fail(function() {
                GCI.showNotice('Failed to start import', 'error');
            });
        },
        
        trackImportProgress: function(importId, sseUrl) {
            // Create progress UI
            const progressHtml = `
                <div class="gci-import-progress" id="import-${importId}">
                    <div class="gci-progress-header">
                        <h3>Importing Content...</h3>
                        <button type="button" class="button button-small gci-cancel-import" data-import-id="${importId}">
                            Cancel
                        </button>
                    </div>
                    <div class="gci-progress-bar-container">
                        <div class="gci-progress-bar" style="width: 0%"></div>
                    </div>
                    <div class="gci-progress-status">Initializing...</div>
                    <div class="gci-progress-details"></div>
                </div>
            `;
            
            // Hide form and show progress
            $('.gci-import-form').slideUp();
            $('.gci-preview-area').slideUp();
            
            // Add progress UI
            const $progressArea = $('.gci-progress-area');
            if ($progressArea.length === 0) {
                $('.gci-import-form').after('<div class="gci-progress-area"></div>');
            }
            $('.gci-progress-area').html(progressHtml).slideDown();
            
            // Start SSE connection
            const eventSource = new EventSource(sseUrl);
            this.activeImports.set(importId, eventSource);
            
            eventSource.addEventListener('progress', function(event) {
                const data = JSON.parse(event.data);
                GCI.updateProgress(importId, data);
            });
            
            eventSource.addEventListener('completed', function(event) {
                const data = JSON.parse(event.data);
                GCI.importCompleted(importId, data);
                eventSource.close();
                GCI.activeImports.delete(importId);
            });
            
            eventSource.addEventListener('error', function(event) {
                const data = event.data ? JSON.parse(event.data) : {};
                GCI.importFailed(importId, data.message || 'Import failed');
                eventSource.close();
                GCI.activeImports.delete(importId);
            });
            
            eventSource.addEventListener('close', function() {
                eventSource.close();
                GCI.activeImports.delete(importId);
            });
            
            eventSource.onerror = function() {
                // Reconnect if connection lost
                if (eventSource.readyState === EventSource.CLOSED) {
                    setTimeout(() => {
                        GCI.resumeProgressTracking(importId);
                    }, 3000);
                }
            };
        },
        
        resumeProgressTracking: function(importId) {
            // Generate SSE URL for existing import
            const sseUrl = gciAdmin.ajaxUrl + '?action=gci_sse_progress&import_id=' + importId + '&nonce=' + gciAdmin.sseNonce;
            this.trackImportProgress(importId, sseUrl);
        },
        
        updateProgress: function(importId, data) {
            const $progress = $(`#import-${importId}`);
            if (!$progress.length) return;
            
            // Update progress bar
            $progress.find('.gci-progress-bar').css('width', data.progress + '%');
            
            // Update status message
            $progress.find('.gci-progress-status').text(data.message);
            
            // Update details if available
            if (data.details) {
                $progress.find('.gci-progress-details').html(data.details);
            }
            
            // Update state-specific styling
            $progress.removeClass('state-idle state-fetching state-parsing state-converting state-downloading_images state-creating_post')
                     .addClass('state-' + data.state);
        },
        
        importCompleted: function(importId, data) {
            const $progress = $(`#import-${importId}`);
            
            // Parse the details JSON if it's a string
            let result = data.details;
            if (typeof result === 'string') {
                try {
                    result = JSON.parse(result);
                } catch (e) {
                    console.error('Failed to parse import result:', e);
                }
            }
            
            // Update progress to completion
            $progress.find('.gci-progress-bar').css('width', '100%');
            $progress.find('.gci-progress-status').text('Import completed!');
            
            // Show success and results
            setTimeout(() => {
                $progress.slideUp();
                if (result && result.success) {
                    this.displayResults(result);
                    this.showNotice('Content imported successfully!', 'success');
                }
            }, 1000);
        },
        
        importFailed: function(importId, errorMessage) {
            const $progress = $(`#import-${importId}`);
            
            $progress.addClass('import-failed');
            $progress.find('.gci-progress-status').text('Import failed: ' + errorMessage);
            $progress.find('.gci-cancel-import').text('Close').off('click').on('click', function() {
                $progress.slideUp();
                $('.gci-import-form').slideDown();
            });
            
            this.showNotice('Import failed: ' + errorMessage, 'error');
        },
        
        cancelImport: function(e) {
            e.preventDefault();
            const importId = $(this).data('import-id');
            
            if (!confirm('Are you sure you want to cancel this import?')) {
                return;
            }
            
            // Send cancel request
            $.post(gciAdmin.ajaxUrl, {
                action: 'gci_cancel_import',
                nonce: gciAdmin.ajaxNonce,
                import_id: importId
            }, function(response) {
                if (response.success) {
                    // Close SSE connection
                    const eventSource = GCI.activeImports.get(importId);
                    if (eventSource) {
                        eventSource.close();
                        GCI.activeImports.delete(importId);
                    }
                    
                    // Update UI
                    const $progress = $(`#import-${importId}`);
                    $progress.addClass('import-cancelled');
                    $progress.find('.gci-progress-status').text('Import cancelled');
                    
                    setTimeout(() => {
                        $progress.slideUp();
                        $('.gci-import-form').slideDown();
                    }, 1500);
                }
            });
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

            // Use HTML preview if available (for Google Docs), otherwise use text preview
            if (data.preview_html) {
                html += '<div class="gci-preview-content">' + data.preview_html + '</div>';
            } else {
                html += '<div class="gci-preview-content">' + data.content_preview + '</div>';
            }

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