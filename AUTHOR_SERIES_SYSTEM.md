# Author and Series ID System Documentation

## Overview

The Author and Series ID system provides persistent, unique identifiers for authors and book series in HotSoup. This system allows for:

- **Unique Author IDs**: Each author gets a permanent ID that persists across database re-indexing
- **Author Name Variations**: Handle multiple spellings/variations of the same author (e.g., "R.L. Stine" vs "Robert Lawrence Stine")
- **Clickable Authors**: Display author names as clickable links that show all books by that author
- **Series Management**: Group books into series with proper ordering
- **Mistake Correction**: Merge duplicate authors, delete incorrect entries, and manage aliases

## Database Structure

### Tables Created

1. **wp_hs_authors** - Main author records
   - `id`: Unique author ID (persistent)
   - `name`: Author's display name
   - `canonical_name`: Standardized form of the name
   - `slug`: URL-friendly identifier
   - `bio`: Author biography (optional)

2. **wp_hs_author_aliases** - Alternative names for authors
   - Links variations like "R.L. Stine" to the same author ID

3. **wp_hs_book_authors** - Book-to-author relationships
   - Many-to-many: books can have multiple authors
   - `author_order`: For books with multiple authors (1st, 2nd, etc.)

4. **wp_hs_series** - Book series
   - Series names and descriptions

5. **wp_hs_book_series** - Book-to-series relationships
   - `position`: Book's position in series (supports decimals for novellas, e.g., 2.5)

6. **wp_hs_author_merges** - Merge history
   - Audit trail of author merges

## Features

### Persistent IDs

**Problem Solved**: Previously, author names were just text strings. If you had "R.L. Stine" and "Robert Lawrence Stine" in different books, they were treated as different authors.

**Solution**: Each author now has a unique ID that never changes. Books are linked to author IDs, not author names.

### Automatic Processing

When a book is created or updated, the system automatically:
1. Checks if author relationships exist for the book
2. If NOT, parses the `book_author` field
3. Creates or finds existing author records
4. Links the book to those authors

**Important**: Once author relationships exist for a book, they are NOT recalculated during re-indexing. This preserves manual corrections.

### Author Name Variations (Aliases)

If you discover that "R.L. Stine" and "Robert Lawrence Stine" are the same person:

**Option 1: Merge Authors**
- Merges all books from one author into another
- Deletes the duplicate author
- Adds old name as alias
- Records merge in history

**Option 2: Add Alias**
- Keeps both records separate
- Adds name variation that maps to the same author

## Admin Interface

### Accessing the Manager

1. Go to WordPress Admin
2. Navigate to **Books → Authors & Series**

### Authors Tab

**Search Authors**: Find authors by name or alias

**For Each Author**:
- View book count and aliases
- **Add Alias**: Add alternative spellings/names
- **Merge Author**: Combine with another author
- **View Books**: See all books by this author
- **Delete**: Remove author (only if they have no books)

**Merging Authors Example**:
```
Author ID 123: "R.L. Stine" (15 books)
Author ID 456: "Robert Lawrence Stine" (3 books)

Merge 456 into 123:
- All 3 books from #456 move to #123
- "Robert Lawrence Stine" becomes an alias of #123
- Author #456 is deleted
- Result: "R.L. Stine" now has 18 books
```

### Series Tab

- Create new series
- View books in each series
- Delete empty series

### Migration Tab

**First-Time Setup**: Run the migration to process existing books

- Shows progress bar
- Processes books in batches
- Safe to run multiple times (won't overwrite existing relationships)
- Creates author records from `book_author` field

**Statistics Shown**:
- Total books in database
- Books with author IDs assigned
- Books remaining to process

## Frontend Display

### Automatic Display on Book Pages

When viewing a single book, author and series information automatically appears at the top of the content:

```
Authors: [Stephen King] (clickable)
Part of: [The Dark Tower] #3 (clickable)
```

### Shortcodes

#### [author_books]

Display all books by a specific author.

**Usage**:
```
[author_books author_id="123"]
[author_books author_slug="stephen-king"]
```

**URL Parameters**:
```
yoursite.com/authors/?author_id=123
yoursite.com/authors/?author_slug=stephen-king
```

**What It Shows**:
- Author name
- Aliases (if any)
- Biography (if set)
- Grid of book covers
- Book count

#### [author_directory]

Display a searchable directory of all authors.

**Usage**:
```
[author_directory]
[author_directory limit="100"]
```

**Features**:
- Search box for finding authors
- Grid layout
- Shows book count per author
- Clickable to view author's books

### Template Tags

For theme developers:

```php
// Display clickable author names
<?php hs_the_book_authors($book_id); ?>

// Display series information
<?php hs_the_book_series($book_id); ?>

// Get author URL
<?php echo hs_get_author_url($author_id); ?>

// Get series URL
<?php echo hs_get_series_url($series_id); ?>
```

## Common Workflows

### 1. Setting Up Author Pages

1. Create a new page (e.g., "Authors")
2. Add shortcode: `[author_books]`
3. Go to **Settings → Reading**
4. Set "Author Page" to your new page
5. Now all author links will go to that page

### 2. Fixing Duplicate Authors

**Scenario**: You have "J.K. Rowling" and "J. K. Rowling" as separate authors

**Steps**:
1. Go to **Books → Authors & Series**
2. Search for "Rowling"
3. Note the IDs (e.g., #100 and #101)
4. Click "Merge Author" on one of them
5. Enter the target author ID
6. Add reason: "Same author, different spacing"
7. Click "Merge Authors"

**Result**: All books now under one author, with both names as aliases

### 3. Adding Name Variations

**Scenario**: An author publishes under multiple names (pen names)

**Steps**:
1. Find the author in **Books → Authors & Series**
2. Click "Add Alias"
3. Enter the pen name
4. Save

**Result**: Books can be found under either name, both link to the same author profile

### 4. Managing Book Series

**Current Implementation**: Basic series tracking is available via database functions

**To Link a Book to a Series** (requires custom code or future UI):
```php
// Create series
$series_id = hs_create_series('Harry Potter');

// Link book to series at position 1
hs_link_book_series($book_id, $series_id, 1);
```

**Note**: Full series management UI can be added in future updates

## Technical Details

### How IDs Stay Persistent

1. **On Book Creation/Update**:
   - Check if `wp_hs_book_authors` has records for this book
   - If YES: Skip processing (preserves manual corrections)
   - If NO: Process `book_author` field and create relationships

2. **During Database Re-indexing**:
   - Same logic applies
   - Existing relationships are never overwritten
   - Only new books or books without relationships are processed

### Database Functions

**Author Management**:
- `hs_create_author($name, $args)` - Create new author
- `hs_get_author($id)` - Get author by ID
- `hs_get_author_by_name($name)` - Find by name or alias
- `hs_merge_authors($from_id, $to_id, $reason)` - Merge two authors
- `hs_add_author_alias($author_id, $alias)` - Add name variation
- `hs_get_author_books($author_id)` - Get all books by author

**Book-Author Linking**:
- `hs_link_book_author($book_id, $author_id, $order)` - Link book to author
- `hs_unlink_book_author($book_id, $author_id)` - Remove relationship
- `hs_get_book_authors($book_id)` - Get authors for a book

**Series Management**:
- `hs_create_series($name, $args)` - Create series
- `hs_link_book_series($book_id, $series_id, $position)` - Add book to series
- `hs_get_series_books($series_id)` - Get all books in series
- `hs_get_book_series($book_id)` - Get series for a book

**Search**:
- `hs_search_authors($term, $limit)` - Search authors and aliases
- `hs_search_series($term, $limit)` - Search series

### Migration Function

```php
// Migrate in batches (safe to run multiple times)
$result = hs_migrate_book_authors($batch_size = 50, $offset = 0);

// Returns:
// - processed: Number processed in this batch
// - total: Total books
// - created_authors: New author records created
// - created_links: New book-author relationships created
// - remaining: Books left to process
// - complete: true if migration finished
```

## Security & Data Integrity

### Protection Against Data Loss

1. **Merge History**: All author merges are logged
2. **Non-Destructive Migration**: Existing relationships never overwritten
3. **Validation**: Can't delete authors with books
4. **Transaction Safety**: Merges use database transactions

### Permissions

All admin functions require `manage_options` capability (Administrator role)

## Performance Considerations

### Indexed Fields

All relationship tables have proper indexes:
- `book_id` and `author_id` are indexed
- `slug` fields are indexed
- Unique constraints prevent duplicates

### Batch Processing

Migration processes books in batches (default 50) to avoid timeouts on large databases

## Future Enhancements

Potential additions:
- [ ] Visual series management UI in admin
- [ ] Bulk author operations
- [ ] Author import/export
- [ ] Series ordering tools
- [ ] Author profile pages with bios
- [ ] Author images/photos
- [ ] Co-author relationship types

## Troubleshooting

### "No authors found" after installation

**Solution**: Run the migration in **Books → Authors & Series → Migration tab**

### Author links don't work

**Solution**: Set up author page in **Settings → Reading** and create a page with `[author_books]` shortcode

### Books showing duplicate authors

**Solution**: Use the Merge Authors function to combine them

### Changes not appearing

**Solution**: Clear WordPress cache if using caching plugins

## Support

For issues or questions about the Author and Series ID system:
1. Check this documentation
2. Review the admin interface tooltips
3. Check database tables to verify data integrity

## File Structure

```
includes/
  authors_series.php                    - Core functions and database tables
  authors_series_display.php            - Frontend display functions
  admin/authors_series_manager.php      - Admin interface
  shortcodes/author_books.php           - Author browsing shortcodes
```
