# Multiple ISBN Support with GID Integration

This document explains the new multiple ISBN support feature integrated with the Global ID (GID) system.

## Overview

The HotSoup book database now supports **multiple ISBNs per book**, allowing different editions and printings of the same book to be tracked under a single book entry. This eliminates the problem of having duplicate pages for the same book with different ISBNs.

## How It Works

### 1. GID (Global ID) System
- Each book is assigned a unique Global ID (GID)
- Multiple books/editions with different ISBNs can share the same GID
- One book is marked as "canonical" for the GID

### 2. ISBN Storage
- ISBNs are stored in the `hs_book_isbns` table
- Each ISBN is unique across the entire database
- Multiple ISBNs can be linked to the same GID
- One ISBN per GID can be marked as "primary" for display purposes

### 3. Database Schema

**`wp_hs_gid` table:**
```sql
id                INT PRIMARY KEY AUTO_INCREMENT
post_id           INT UNIQUE           -- Links to wp_posts.ID
gid               INT                  -- Global ID for grouping editions
merged_by         INT                  -- User who merged
merge_reason      TEXT                 -- Reason for merge
date_merged       DATETIME             -- When merged
is_canonical      TINYINT(1)           -- Is this the canonical book?
```

**`wp_hs_book_isbns` table:**
```sql
id                BIGINT(20) PRIMARY KEY AUTO_INCREMENT
gid               INT NOT NULL         -- Links to hs_gid.gid
post_id           BIGINT(20) NOT NULL  -- Links to wp_posts.ID
isbn              VARCHAR(13) UNIQUE   -- The ISBN (unique)
is_primary        TINYINT(1)           -- Is this the primary ISBN to display?
edition           VARCHAR(255)         -- Edition description
publication_year  INT                  -- Publication year for this edition
created_at        DATETIME             -- When added
```

## Migration

### Automatic Migration Tool

A migration tool is available in the WordPress admin panel:

1. Navigate to **Tools → ISBN Migration**
2. Click "Migrate ISBNs"
3. The tool will:
   - Create GID entries for all existing books
   - Move ISBNs from ACF `book_isbn` field to the new table
   - Mark them as "primary" ISBNs
   - Skip books that already have ISBNs in the new table

## PHP Functions

### GID Functions (includes/gid.php)

```php
// Get or create a GID for a book
hs_get_or_create_gid($post_id)

// Get the GID for a post
hs_get_gid($post_id)

// Get all post IDs for a GID
hs_get_posts_by_gid($gid)

// Get the canonical post for a GID
hs_get_canonical_post($gid)
```

### ISBN Functions (includes/bookdb.php)

```php
// Add an ISBN to a book
hs_add_book_isbn($post_id, $isbn, $edition = '', $year = null, $is_primary = false)

// Get a book by ISBN
hs_get_book_by_isbn($isbn)  // Returns: {post_id, gid}

// Get all ISBNs for a book
hs_get_book_isbns($post_id)  // Returns array of ISBN objects

// Get all ISBNs for a GID
hs_get_isbns_by_gid($gid)

// Get the primary ISBN for a book
hs_get_primary_isbn($post_id)

// Set an ISBN as primary
hs_set_primary_isbn($post_id, $isbn)

// Remove an ISBN
hs_remove_book_isbn($isbn)
```

## REST API Endpoints

### Get All ISBNs for a Book
```
GET /wp-json/gread/v1/books/{book_id}/isbns
```

**Response:**
```json
{
  "book_id": 123,
  "isbns": [
    {
      "isbn": "9780140449136",
      "edition": "Penguin Classics",
      "publication_year": 2003,
      "is_primary": true,
      "post_id": 123
    },
    {
      "isbn": "9780140449143",
      "edition": "Revised Edition",
      "publication_year": 2010,
      "is_primary": false,
      "post_id": 123
    }
  ]
}
```

### Add ISBN to a Book
```
POST /wp-json/gread/v1/books/{book_id}/isbns
```

**Request Body:**
```json
{
  "isbn": "9780140449136",
  "edition": "Penguin Classics",
  "publication_year": 2003,
  "is_primary": true
}
```

**Response:**
```json
{
  "success": true,
  "message": "ISBN added successfully",
  "isbn": "9780140449136"
}
```

### Remove an ISBN
```
DELETE /wp-json/gread/v1/books/isbn/{isbn}
```

**Response:**
```json
{
  "success": true,
  "message": "ISBN removed successfully"
}
```

### Set Primary ISBN
```
PUT /wp-json/gread/v1/books/{book_id}/isbns/primary
```

**Request Body:**
```json
{
  "isbn": "9780140449136"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Primary ISBN set successfully",
  "isbn": "9780140449136"
}
```

## Search Integration

The search index (`hs_book_search_index`) has been updated to support multiple ISBNs:

- When a book is saved, all its ISBNs are indexed
- Searching by ISBN will find books with any of their ISBNs
- Each ISBN creates a separate entry in the search index for better search performance

## Usage Examples

### Example 1: Adding Multiple ISBNs to a Book

```php
$book_id = 123;

// Add first ISBN as primary
hs_add_book_isbn($book_id, '9780140449136', 'Penguin Classics', 2003, true);

// Add second ISBN
hs_add_book_isbn($book_id, '9780140449143', 'Revised Edition', 2010, false);

// Add third ISBN
hs_add_book_isbn($book_id, '0140449132', 'Original Edition', 1998, false);

// Get all ISBNs for the book
$isbns = hs_get_book_isbns($book_id);
print_r($isbns);

// Get only the primary ISBN
$primary = hs_get_primary_isbn($book_id);
echo $primary; // "9780140449136"
```

### Example 2: Finding a Book by Any ISBN

```php
$isbn = '9780140449143';

// Find the book
$result = hs_get_book_by_isbn($isbn);

if ($result) {
    $book_id = $result->post_id;
    $gid = $result->gid;

    // Get all ISBNs for this book (including the searched one)
    $all_isbns = hs_get_book_isbns($book_id);
}
```

### Example 3: Merging Duplicate Books

```php
// If you have two book posts for the same book:
$book1_id = 123;
$book2_id = 456;

// Get ISBNs from both books
$isbn1 = get_field('book_isbn', $book1_id);
$isbn2 = get_field('book_isbn', $book2_id);

// Add both ISBNs to the first book
hs_add_book_isbn($book1_id, $isbn1, 'First Edition', 2003, true);
hs_add_book_isbn($book1_id, $isbn2, 'Second Edition', 2010, false);

// Now both ISBNs will search to the same book
```

## Benefits

1. **No More Duplicates**: Same book with different ISBNs = one page
2. **Better Search**: Users can find books by any ISBN
3. **Edition Tracking**: Track different editions and printings
4. **Data Integrity**: Each ISBN is unique across the database
5. **Backward Compatible**: Falls back to ACF field if needed
6. **Flexible**: Mark any ISBN as primary for display

## Activation

To activate the new tables and migrate data:

1. The tables are created automatically when `hs_gid_activate()` is called
2. Navigate to **Tools → ISBN Migration** to migrate existing data
3. Optionally, navigate to **Tools → HotSoup Search** to rebuild the search index

## Technical Notes

- The original ACF `book_isbn` field is still present for backward compatibility
- `hs_get_primary_isbn()` falls back to the ACF field if no ISBN is found in the new table
- The search indexing function `hs_search_add_to_index()` now indexes all ISBNs
- All ISBN functions use WordPress's `$wpdb` for database operations with proper sanitization
