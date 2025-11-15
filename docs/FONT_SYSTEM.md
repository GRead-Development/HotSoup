# Font Unlock System Documentation

## Overview

The Font Unlock System allows users to unlock and apply custom fonts across the HotSoup site and app. It works similarly to the themes system, with fonts being unlockable based on user achievements (pages read, books completed, points earned, etc.).

## Features

- **Admin Panel**: Upload and manage custom fonts with unlock requirements
- **User Profile Integration**: Users can select fonts from their BuddyPress profile settings
- **REST API**: Full API support for mobile app integration
- **Achievement-Based Unlocks**: Lock fonts behind reading milestones
- **Multiple Font Formats**: Support for WOFF2, WOFF, TTF, and OTF formats
- **Google Fonts Support**: Easy integration with Google Fonts CDN

## Admin Panel

### Accessing Font Manager

Navigate to **WordPress Admin → Font Manager** to manage fonts.

### Adding a New Font

1. Click "Add New Font"
2. Fill in the required fields:
   - **Font Name**: Display name (e.g., "Roboto")
   - **Slug**: URL-friendly identifier (e.g., "roboto")
   - **Font Family**: CSS font-family value (e.g., "Roboto, sans-serif")
   - **Font Files**: Upload files or provide CDN URLs
   - **Unlock Metric**: None, Points, Books Read, or Pages Read
   - **Unlock Value**: Threshold value for unlocking
   - **Unlock Message**: Message shown when font is locked
   - **Default Font**: Check if this is the default font

3. Click "Save Font"

### Using Google Fonts

For Google Fonts, you can use CDN URLs instead of uploading files:

**Example for Roboto:**
- Font Family: `Roboto, sans-serif`
- WOFF2 URL: `https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK.woff2`

**Or use a CSS import:**
- Just add the font family name and the browser will fall back to system fonts
- Users can add custom CSS to import Google Fonts

### Example Font Configurations

#### Open Sans (Google Font)
- Name: Open Sans
- Slug: open-sans
- Font Family: `'Open Sans', sans-serif`
- WOFF2: `https://fonts.gstatic.com/s/opensans/v34/memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsjZ0C4nY1M2xLER.woff2`
- Unlock: 500 pages read

#### Playfair Display (Unlocked by Points)
- Name: Playfair Display
- Slug: playfair
- Font Family: `'Playfair Display', serif`
- WOFF2: (Google Fonts URL)
- Unlock: 1000 points

#### Comic Sans (Always Available)
- Name: Comic Sans
- Slug: comic-sans
- Font Family: `'Comic Sans MS', cursive`
- Unlock Metric: None
- Files: (System font, no files needed)

## User Experience

### Selecting a Font

1. Go to **Profile → Settings → Fonts**
2. View available fonts with preview
3. Locked fonts show unlock requirements
4. Select an unlocked font
5. Click "Save Font"
6. Page reloads with new font applied

### How Fonts Are Applied

Fonts are applied site-wide to:
- Body text
- Headings (h1-h6)
- Paragraphs
- Links
- Buttons
- Form inputs
- All UI elements

## API Documentation

### Base URL
```
/wp-json/hotsoup/v1/
```

### Endpoints

#### Get Available Fonts
```
GET /fonts
```

**Authentication**: Required (logged-in user)

**Response**:
```json
[
  {
    "id": 1,
    "slug": "default",
    "name": "Default (System Font)",
    "font_family": "-apple-system, BlinkMacSystemFont, sans-serif",
    "unlocked": true,
    "unlock_metric": "none",
    "unlock_value": 0,
    "unlock_message": "",
    "is_default": 1
  },
  {
    "id": 2,
    "slug": "roboto",
    "name": "Roboto",
    "font_family": "Roboto, sans-serif",
    "unlocked": false,
    "unlock_metric": "pages_read",
    "unlock_value": 1000,
    "unlock_message": "Read 1,000 pages to unlock this font!",
    "is_default": 0
  }
]
```

#### Select Font
```
POST /fonts/select
```

**Authentication**: Required

**Body**:
```json
{
  "font_slug": "roboto"
}
```

**Success Response**:
```json
{
  "success": true,
  "message": "Font updated successfully",
  "font": {
    "slug": "roboto",
    "name": "Roboto",
    "font_family": "Roboto, sans-serif"
  }
}
```

**Error Response** (locked):
```json
{
  "error": "Font is locked",
  "message": "Read 1,000 pages to unlock this font!"
}
```

#### Get User's Selected Font
```
GET /fonts/selected
```

**Authentication**: Required

**Response**:
```json
{
  "slug": "roboto",
  "name": "Roboto",
  "font_family": "Roboto, sans-serif",
  "file_woff2": "https://example.com/fonts/roboto.woff2",
  "file_woff": "https://example.com/fonts/roboto.woff",
  "file_ttf": "",
  "file_otf": ""
}
```

## Database Schema

### Table: `wp_hs_fonts`

```sql
CREATE TABLE wp_hs_fonts (
  id mediumint(9) NOT NULL AUTO_INCREMENT,
  slug varchar(50) NOT NULL,
  name varchar(100) NOT NULL,
  font_family varchar(100) NOT NULL,
  file_woff2 varchar(255) DEFAULT '',
  file_woff varchar(255) DEFAULT '',
  file_ttf varchar(255) DEFAULT '',
  file_otf varchar(255) DEFAULT '',
  unlock_metric varchar(50) DEFAULT 'none',
  unlock_value int(11) DEFAULT 0,
  unlock_message text,
  is_default tinyint(1) DEFAULT 0,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY slug (slug)
);
```

### User Meta

- **Key**: `hs_selected_font`
- **Value**: Font slug (e.g., "roboto")

## Unlock Metrics

The system supports the following unlock metrics:

| Metric | User Meta Key | Description |
|--------|--------------|-------------|
| `none` | N/A | Always unlocked |
| `points` | `user_points` | Total points earned |
| `books_read` | `hs_completed_books_count` | Completed books |
| `pages_read` | `hs_total_pages_read` | Total pages read |

## Developer Notes

### Adding Custom Unlock Metrics

To add a custom unlock metric:

1. Ensure the metric is tracked in user meta
2. Add the metric to the admin dropdown in `font_manager.php`
3. Map the metric in the `hs_is_font_unlocked()` function

### Customizing Font Application

Fonts are applied via dynamic CSS in `hs_generate_font_css()`. To customize which elements receive the font, edit this function in `/includes/admin/font_manager.php`.

### Security

- All font uploads are handled via `wp_handle_upload()`
- MIME type validation for font files
- Nonce verification for all AJAX requests
- Permission checks for admin functions

## Troubleshooting

### Font Not Displaying

1. **Check browser console** for font loading errors
2. **Verify font files** are accessible (check URLs)
3. **Clear browser cache** and reload
4. **Check font-family name** matches exactly

### Font Locked When It Shouldn't Be

1. **Verify user stats** are up to date (run `hs_update_user_stats()`)
2. **Check unlock requirements** in admin panel
3. **Review user meta** for required metrics

### CORS Issues with Font Files

If using external CDN:
1. Ensure CDN allows cross-origin requests
2. Check for proper CORS headers
3. Consider hosting fonts locally

## Examples

### Adding a Custom Font via Code

```php
// Add font programmatically
global $wpdb;
$fonts_table = $wpdb->prefix . 'hs_fonts';

$wpdb->insert($fonts_table, [
    'slug' => 'montserrat',
    'name' => 'Montserrat',
    'font_family' => 'Montserrat, sans-serif',
    'file_woff2' => 'https://cdn.example.com/fonts/montserrat.woff2',
    'unlock_metric' => 'books_read',
    'unlock_value' => 10,
    'unlock_message' => 'Complete 10 books to unlock Montserrat!',
    'is_default' => 0,
]);
```

### Checking if Font is Unlocked

```php
$user_id = get_current_user_id();
$font = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}hs_fonts WHERE slug = 'roboto'");

if (hs_is_font_unlocked($font, $user_id)) {
    echo "Font is unlocked!";
}
```

## Future Enhancements

- Font preview in admin panel
- Font weight/style variations
- Per-element font customization
- Font pairing suggestions
- User-uploaded custom fonts
- Font performance metrics
