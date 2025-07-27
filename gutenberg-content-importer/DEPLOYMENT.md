# Deployment Guide

## 🚀 Quick Start

### 1. Download & Install

```bash
# Clone or download the plugin
git clone https://github.com/your-repo/gutenberg-content-importer.git

# Or download the ZIP file and extract to:
wp-content/plugins/gutenberg-content-importer/
```

### 2. Activate Plugin

1. Go to **WordPress Admin → Plugins**
2. Find "Gutenberg Content Importer"
3. Click **Activate**

### 3. Start Importing!

1. Navigate to **Content Importer** in admin menu
2. Select your source (Medium, Notion, etc.)
3. Paste URL or content
4. Click **Import**

## 📋 Requirements

- **WordPress**: 6.0 or higher
- **PHP**: 7.4 or higher
- **Gutenberg**: Enabled (comes with WordPress 5.0+)
- **Memory Limit**: 128MB recommended
- **Max Execution Time**: 60 seconds recommended

## ⚙️ Configuration

### Basic Setup

No configuration required! The plugin works out of the box with sensible defaults.

### Advanced Configuration

#### For Notion Imports:
1. Go to **Content Importer → Settings**
2. Add your Notion API key
3. Instructions: [Create Notion Integration](https://developers.notion.com/docs/getting-started)

#### For Google Docs:
1. Create OAuth credentials in Google Cloud Console
2. Add Client ID and Secret in settings
3. Enable Google Docs API

### Performance Optimization

Add to `wp-config.php` for better performance:

```php
// Increase memory for large imports
define('WP_MEMORY_LIMIT', '256M');

// Increase max execution time
set_time_limit(120);
```

## 🔒 Security Considerations

### Permissions

The plugin requires:
- `edit_posts` capability for importing
- `manage_options` capability for settings

### Data Handling

- All imported content is sanitized
- External images are validated before download
- API keys are stored encrypted in database

## 🌐 Multisite Support

The plugin is multisite compatible:

1. Network activate for all sites
2. Or activate per-site as needed
3. Each site maintains its own import history

## 📦 Deployment Checklist

### Before Going Live:

- [ ] Set appropriate PHP memory limit
- [ ] Configure max execution time
- [ ] Test with actual content URLs
- [ ] Verify image upload directory permissions
- [ ] Test with non-admin user roles
- [ ] Configure API keys if needed

### Production Recommendations:

1. **Caching**: Compatible with all major caching plugins
2. **CDN**: Imported images work with CDN
3. **Backup**: Always backup before bulk imports
4. **Monitoring**: Watch server resources during imports

## 🛠 Troubleshooting

### Common Issues:

#### "Import timeout" error
- Increase PHP `max_execution_time`
- Try importing without images first

#### "Memory exhausted" error
- Increase `WP_MEMORY_LIMIT`
- Import smaller articles

#### Images not importing
- Check upload directory permissions
- Verify `allow_url_fopen` is enabled

#### Medium returns 403
- Rate limiting - wait 60 seconds
- Try different article

### Debug Mode

Enable debug logging:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('GCI_DEBUG', true);
```

Check logs at: `wp-content/debug.log`

## 🔄 Updates

### Automatic Updates

The plugin supports WordPress automatic updates.

### Manual Updates

1. Backup your site
2. Deactivate plugin
3. Replace plugin files
4. Reactivate plugin

### Database Updates

The plugin handles database updates automatically on activation.

## 📱 REST API Usage

### Authentication

Use WordPress REST API authentication:

```javascript
// With application passwords
const headers = {
  'Authorization': 'Basic ' + btoa('username:application-password'),
  'Content-Type': 'application/json'
};
```

### Example API Calls

```javascript
// Preview import
fetch('/wp-json/gci/v1/import/preview', {
  method: 'POST',
  headers: headers,
  body: JSON.stringify({
    source: 'medium',
    url: 'https://medium.com/@user/article'
  })
});

// Process import
fetch('/wp-json/gci/v1/import/process', {
  method: 'POST',
  headers: headers,
  body: JSON.stringify({
    source: 'medium',
    url: 'https://medium.com/@user/article',
    options: {
      post_status: 'draft',
      download_images: true
    }
  })
});
```

## 🎯 For Automattic Team

### Why This Architecture?

1. **Extensible**: Easy to add new platforms
2. **Testable**: Clear separation of concerns
3. **Performant**: Async operations where possible
4. **Secure**: WordPress best practices throughout
5. **User-Friendly**: Intuitive UI/UX

### Integration Points

Ready to integrate with:
- Jetpack (for enhanced features)
- WordPress.com (for hosted sites)
- Gutenberg (deep integration)
- WooCommerce (for product imports)

### Scaling Considerations

- Queue system ready for batch imports
- Hooks for external processing
- CDN-friendly asset handling
- Multisite compatible

## 📞 Support

- **Documentation**: See README.md
- **Issues**: GitHub Issues
- **Community**: WordPress.org forums

---

Built with ❤️ for the WordPress community 