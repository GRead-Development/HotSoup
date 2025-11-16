<?php
/**
 * ISBN Diagnostic Script
 * This script helps diagnose issues with the search index and book permalinks
 *
 * Usage: Place this file in the WordPress root and access it via browser
 * Then delete it when done.
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('You must be an administrator to run this diagnostic.');
}

global $wpdb;

echo "<h1>ISBN System Diagnostic</h1>";

// Check if tables exist
echo "<h2>1. Database Tables Check</h2>";
$tables_to_check = [
    'hs_gid',
    'hs_book_isbns',
    'hs_book_search_index',
    'user_books'
];

foreach ($tables_to_check as $table) {
    $full_table_name = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name");
        echo "✅ Table <code>$full_table_name</code> exists with <strong>$count</strong> rows<br>";
    } else {
        echo "❌ Table <code>$full_table_name</code> does NOT exist<br>";
    }
}

// Check search index
echo "<h2>2. Search Index Analysis</h2>";
$search_table = $wpdb->prefix . 'hs_book_search_index';
$search_results = $wpdb->get_results("SELECT * FROM $search_table LIMIT 5");

if ($search_results) {
    echo "<strong>Sample search index entries:</strong><br>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Book ID</th><th>Title</th><th>Author</th><th>ISBN</th><th>Permalink</th><th>Post Status</th></tr>";

    foreach ($search_results as $row) {
        $post = get_post($row->book_id);
        $post_status = $post ? $post->post_status : 'NOT FOUND';
        $post_type = $post ? $post->post_type : 'N/A';

        echo "<tr>";
        echo "<td>{$row->book_id}</td>";
        echo "<td>" . esc_html($row->title) . "</td>";
        echo "<td>" . esc_html($row->author) . "</td>";
        echo "<td>" . esc_html($row->isbn) . "</td>";
        echo "<td><a href='" . esc_url($row->permalink) . "' target='_blank'>" . esc_html($row->permalink) . "</a></td>";
        echo "<td>{$post_status} ($post_type)</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "⚠️ Search index is EMPTY! You need to rebuild it.<br>";
}

// Check published books
echo "<h2>3. Published Books Check</h2>";
$book_count = wp_count_posts('book');
echo "📚 Total books:<br>";
echo "- Published: <strong>{$book_count->publish}</strong><br>";
echo "- Draft: {$book_count->draft}<br>";
echo "- Pending: {$book_count->pending}<br>";

// Check GID entries
echo "<h2>4. GID System Status</h2>";
$gid_table = $wpdb->prefix . 'hs_gid';
if ($wpdb->get_var("SHOW TABLES LIKE '$gid_table'")) {
    $gid_count = $wpdb->get_var("SELECT COUNT(*) FROM $gid_table");
    echo "GID entries: <strong>$gid_count</strong><br>";

    if ($gid_count > 0) {
        echo "<strong>Sample GID entries:</strong><br>";
        $sample_gids = $wpdb->get_results("SELECT * FROM $gid_table LIMIT 5");
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Post ID</th><th>GID</th><th>Is Canonical</th><th>Date Merged</th></tr>";
        foreach ($sample_gids as $gid) {
            echo "<tr>";
            echo "<td>{$gid->id}</td>";
            echo "<td>{$gid->post_id}</td>";
            echo "<td>{$gid->gid}</td>";
            echo "<td>{$gid->is_canonical}</td>";
            echo "<td>{$gid->date_merged}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// Check ISBN entries
echo "<h2>5. ISBN System Status</h2>";
$isbn_table = $wpdb->prefix . 'hs_book_isbns';
if ($wpdb->get_var("SHOW TABLES LIKE '$isbn_table'")) {
    $isbn_count = $wpdb->get_var("SELECT COUNT(*) FROM $isbn_table");
    echo "ISBN entries: <strong>$isbn_count</strong><br>";

    if ($isbn_count > 0) {
        echo "<strong>Sample ISBN entries:</strong><br>";
        $sample_isbns = $wpdb->get_results("SELECT * FROM $isbn_table LIMIT 5");
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>GID</th><th>Post ID</th><th>ISBN</th><th>Is Primary</th><th>Edition</th></tr>";
        foreach ($sample_isbns as $isbn) {
            echo "<tr>";
            echo "<td>{$isbn->id}</td>";
            echo "<td>{$isbn->gid}</td>";
            echo "<td>{$isbn->post_id}</td>";
            echo "<td>{$isbn->isbn}</td>";
            echo "<td>{$isbn->is_primary}</td>";
            echo "<td>" . esc_html($isbn->edition) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "⚠️ No ISBNs have been migrated yet. You need to run the migration from Tools → ISBN Migration<br>";
    }
}

// Check sample book data
echo "<h2>6. Sample Book Data</h2>";
$sample_books = get_posts([
    'post_type' => 'book',
    'post_status' => 'publish',
    'posts_per_page' => 3
]);

if ($sample_books) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Title</th><th>Author (ACF)</th><th>ISBN (ACF)</th><th>Permalink</th><th>Permalink Works?</th></tr>";

    foreach ($sample_books as $book) {
        $author = get_field('book_author', $book->ID);
        $isbn = get_field('book_isbn', $book->ID);
        $permalink = get_permalink($book->ID);

        // Test if permalink is accessible
        $response = wp_remote_head($permalink);
        $works = is_wp_error($response) ? '❌ Error' : (wp_remote_retrieve_response_code($response) == 200 ? '✅ Yes' : '❌ No (' . wp_remote_retrieve_response_code($response) . ')');

        echo "<tr>";
        echo "<td>{$book->ID}</td>";
        echo "<td>" . esc_html($book->post_title) . "</td>";
        echo "<td>" . esc_html($author) . "</td>";
        echo "<td>" . esc_html($isbn) . "</td>";
        echo "<td><a href='" . esc_url($permalink) . "' target='_blank'>" . esc_html($permalink) . "</a></td>";
        echo "<td>$works</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ No published books found!<br>";
}

// Recommendations
echo "<h2>7. Recommendations</h2>";

$isbn_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_book_isbns");
$search_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hs_book_search_index");

if ($isbn_count == 0) {
    echo "⚠️ <strong>Action Required:</strong> Go to WordPress Admin → Tools → ISBN Migration and click 'Migrate ISBNs'<br>";
}

if ($search_count == 0 || $search_count < $book_count->publish) {
    echo "⚠️ <strong>Action Required:</strong> Go to WordPress Admin → Tools → HotSoup Search and click 'Build Index'<br>";
}

// Check permalink structure
echo "<h2>8. Permalink Structure</h2>";
$permalink_structure = get_option('permalink_structure');
echo "Current permalink structure: <code>" . ($permalink_structure ? $permalink_structure : 'Default (not SEO friendly)') . "</code><br>";

if (empty($permalink_structure)) {
    echo "⚠️ <strong>Warning:</strong> You're using the default permalink structure. This might cause issues. Go to Settings → Permalinks and choose a different structure, then click 'Save Changes' to flush rewrite rules.<br>";
}

echo "<br><br><hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>If search index is empty or incomplete, rebuild it from Tools → HotSoup Search</li>";
echo "<li>If ISBN entries are 0, run migration from Tools → ISBN Migration</li>";
echo "<li>If permalinks don't work, go to Settings → Permalinks and click 'Save Changes'</li>";
echo "<li>Delete this diagnostic file when done</li>";
echo "</ol>";
