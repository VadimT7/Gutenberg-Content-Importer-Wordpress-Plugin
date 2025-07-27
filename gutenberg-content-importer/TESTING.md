# Testing Guide for Gutenberg Content Importer

## 🧪 Test Scenarios

### 1. Medium Import Test (API-Powered)

**Test URLs**: 
- `https://medium.com/@username/any-article-url`
- `https://medium.com/write-a-catalyst/you-are-fired-now-80458d77205a`
- `https://pub.towardsai.net/why-its-super-hard-to-be-an-ml-researcher-or-developer-67fa62fc1971`

**Expected Result**:
- Auto-detects Medium as source
- Uses RapidAPI to fetch complete article data
- Extracts comprehensive metadata:
  - Title, subtitle, author info
  - Publish date, last modified date
  - Claps count, reading time
  - Tags and topics
- Converts all content to Gutenberg blocks:
  - Headings with proper hierarchy
  - Paragraphs with all formatting preserved
  - Images downloaded with alt text
  - Code blocks with syntax highlighting
  - Embedded YouTube videos and other media
  - Quotes with proper attribution
  - Lists (ordered and unordered)
- **No paywall issues** - API handles all content access

**Verification Steps**:
1. Copy any Medium article URL
2. Paste in importer
3. Click "Preview Import"
4. Verify all content appears correctly
5. Click "Import Content"
6. Check post in Gutenberg editor
7. Verify all blocks are properly formatted

### 2. Markdown Import Test

**Test Content**:
```markdown
# My Amazing Article

This is a **bold** statement with *italic* text.

## Key Features

- First item
- Second item
- Third item

### Code Example

```javascript
function hello() {
    console.log("Hello from Gutenberg!");
}
```

> This is a blockquote
> that spans multiple lines

![Alt text](https://example.com/image.jpg)

[Link to WordPress](https://wordpress.org)
```

**Expected Result**:
- H1 becomes post title
- All formatting preserved
- Lists converted to list blocks
- Code block with JavaScript highlighting
- Quote block created
- Image block with alt text
- Links preserved in paragraphs

### 3. Performance Test

**Large Article Test**:
- Find article with 50+ paragraphs
- Multiple images (10+)
- Several embeds

**Success Criteria**:
- Import completes in < 30 seconds
- All images downloaded
- No timeout errors
- Memory usage reasonable

### 4. Edge Cases

#### Empty Content
- Try importing URL with no content
- Should show appropriate error message

#### Invalid URLs
- Test with non-existent URLs
- Test with non-supported platforms
- Should show helpful error messages

#### Network Issues
- Test with slow connection
- Import should handle gracefully
- Show progress indicators

#### Special Characters
- Test with articles containing:
  - Emojis 😀
  - Special quotes ""''
  - Mathematical symbols ∑∏
  - Non-English characters

### 5. Security Tests

#### XSS Prevention
- Try importing content with script tags
- Should be stripped/sanitized

#### SQL Injection
- Test with malicious URLs
- All inputs should be sanitized

#### File Upload
- Only valid image types accepted
- File size limits enforced
- No executable files allowed

## 🔄 Regression Tests

### After Each Update:

1. **Basic Import Flow**
   - Can still import from all sources
   - Preview works correctly
   - Import creates post successfully

2. **UI Functionality**
   - Source selection works
   - Form validation works
   - Loading states display
   - Success/error messages show

3. **API Endpoints**
   - `/import/preview` returns data
   - `/import/process` creates posts
   - `/importers` lists all importers
   - `/history` shows past imports

4. **Database Operations**
   - Import history saved
   - Settings persist
   - Post metadata stored

## 🛠 Manual Testing Checklist

### Pre-Demo Checklist:

- [ ] Fresh WordPress installation
- [ ] Plugin activates without errors
- [ ] Admin menu appears
- [ ] All admin pages load
- [ ] CSS/JS files load correctly
- [ ] No console errors

### Import Flow:

- [ ] Source icons display
- [ ] Source selection highlights
- [ ] Form shows for selected source
- [ ] URL validation works
- [ ] Preview loads content
- [ ] Import button processes
- [ ] Success message appears
- [ ] Post created in WordPress
- [ ] Edit/View links work

### Post Quality:

- [ ] Title imported correctly
- [ ] All paragraphs present
- [ ] Images display properly
- [ ] Formatting preserved
- [ ] No broken elements
- [ ] Mobile responsive

## 📊 Test Data

### Sample URLs for Testing:

**Medium Articles**:
```
https://medium.com/topic/technology
https://medium.com/topic/programming
https://medium.com/@any-user/article-title
```

**Notion Pages** (Demo Mode):
```
https://notion.so/Demo-Page-123456789
```

**Google Docs** (Demo Mode):
```
https://docs.google.com/document/d/demo-doc-id/edit
```

### Expected Import Times:

| Content Type | Size | Expected Time |
|-------------|------|---------------|
| Small Article | < 1000 words | 3-5 seconds |
| Medium Article | 1000-5000 words | 5-10 seconds |
| Large Article | 5000+ words | 10-20 seconds |
| Many Images | 20+ images | 20-30 seconds |

## 🐛 Known Issues & Workarounds

### Medium Rate Limiting
- **Issue**: Too many requests may be rate limited
- **Workaround**: Wait 60 seconds between imports

### Large Images
- **Issue**: Very large images may timeout
- **Workaround**: Import without images first, add manually

### Special Embeds
- **Issue**: Some embeds may not convert
- **Workaround**: They'll appear as links, can embed manually

## 🚀 Demo Script

1. **Setup** (2 minutes)
   - Show WordPress without content
   - Activate plugin
   - Navigate to Content Importer

2. **First Import** (3 minutes)
   - Find trending Medium article
   - Copy URL
   - Show auto-detection
   - Preview import
   - Execute import
   - Show perfect result

3. **Advanced Features** (3 minutes)
   - Show import history
   - Demonstrate settings
   - Show different sources
   - Import Markdown example

4. **Technical Excellence** (2 minutes)
   - Show network tab (API calls)
   - Inspect generated blocks
   - Show extensibility code

**Total Demo Time**: 10 minutes

## ✅ Success Metrics

- **Import Success Rate**: > 95%
- **Average Import Time**: < 10 seconds
- **User Satisfaction**: "Wow!"
- **Code Quality**: Zero errors
- **Performance**: No timeouts 