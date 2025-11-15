<?php
/**
 * Citation Management System
 *
 * Handles creation, storage, and generation of book citations
 * in multiple academic formats (APA, MLA, Chicago, Harvard)
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create the citations table on plugin activation
 */
function hs_citations_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_book_citations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        book_id BIGINT(20) UNSIGNED NOT NULL,
        citation_format VARCHAR(50) NOT NULL,
        citation_text TEXT NOT NULL,
        custom_data TEXT NULL,
        date_created DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
        date_modified DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY (id),
        INDEX user_id_index (user_id),
        INDEX book_id_index (book_id),
        INDEX format_index (citation_format)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Generate citation in specified format
 *
 * @param int $book_id The book post ID
 * @param string $format Citation format (apa, mla, chicago, harvard)
 * @param array $custom_data Additional citation data (pages, access_date, url, edition, translator, etc.)
 * @return string|WP_Error Generated citation or error
 */
function hs_generate_citation($book_id, $format, $custom_data = array()) {
    // Get book metadata
    $title = get_the_title($book_id);
    $author = get_post_meta($book_id, 'book_author', true);
    $pub_year = get_post_meta($book_id, 'publication_year', true);
    $isbn = get_post_meta($book_id, 'book_isbn', true);

    // Extract custom data
    $pages = isset($custom_data['pages']) ? sanitize_text_field($custom_data['pages']) : '';
    $access_date = isset($custom_data['access_date']) ? sanitize_text_field($custom_data['access_date']) : '';
    $url = isset($custom_data['url']) ? esc_url_raw($custom_data['url']) : '';
    $edition = isset($custom_data['edition']) ? sanitize_text_field($custom_data['edition']) : '';
    $publisher = isset($custom_data['publisher']) ? sanitize_text_field($custom_data['publisher']) : '';
    $translator = isset($custom_data['translator']) ? sanitize_text_field($custom_data['translator']) : '';
    $editor = isset($custom_data['editor']) ? sanitize_text_field($custom_data['editor']) : '';
    $city = isset($custom_data['city']) ? sanitize_text_field($custom_data['city']) : '';

    // Validate required fields
    if (empty($title)) {
        return new WP_Error('missing_title', 'Book title is required');
    }

    // Generate citation based on format
    switch (strtolower($format)) {
        case 'apa':
            return hs_generate_apa_citation($title, $author, $pub_year, $publisher, $city, $edition, $pages, $url, $access_date);

        case 'mla':
            return hs_generate_mla_citation($title, $author, $pub_year, $publisher, $city, $edition, $translator, $pages, $url, $access_date);

        case 'chicago':
            return hs_generate_chicago_citation($title, $author, $pub_year, $publisher, $city, $edition, $pages);

        case 'harvard':
            return hs_generate_harvard_citation($title, $author, $pub_year, $publisher, $city, $edition, $pages);

        default:
            return new WP_Error('invalid_format', 'Invalid citation format');
    }
}

/**
 * Generate APA format citation
 * Format: Author, A. A. (Year). Title of work. Publisher.
 */
function hs_generate_apa_citation($title, $author, $pub_year, $publisher, $city, $edition, $pages, $url, $access_date) {
    $citation = '';

    // Author (Last name, First initial.)
    if (!empty($author)) {
        $citation .= hs_format_author_apa($author) . ' ';
    }

    // Year
    if (!empty($pub_year)) {
        $citation .= '(' . $pub_year . '). ';
    } else {
        $citation .= '(n.d.). ';
    }

    // Title (italicized)
    $citation .= '<em>' . $title . '</em>';

    // Edition
    if (!empty($edition)) {
        $citation .= ' (' . $edition . ')';
    }

    $citation .= '. ';

    // Publisher
    if (!empty($publisher)) {
        $citation .= $publisher . '.';
    }

    // Pages (if specific pages cited)
    if (!empty($pages)) {
        $citation .= ' pp. ' . $pages . '.';
    }

    // URL and access date for online sources
    if (!empty($url)) {
        $citation .= ' Retrieved';
        if (!empty($access_date)) {
            $citation .= ' ' . $access_date . ',';
        }
        $citation .= ' from ' . $url;
    }

    return trim($citation);
}

/**
 * Generate MLA format citation
 * Format: Author. Title. Publisher, Year.
 */
function hs_generate_mla_citation($title, $author, $pub_year, $publisher, $city, $edition, $translator, $pages, $url, $access_date) {
    $citation = '';

    // Author (Last, First.)
    if (!empty($author)) {
        $citation .= hs_format_author_mla($author) . ' ';
    }

    // Title (italicized)
    $citation .= '<em>' . $title . '</em>. ';

    // Translator
    if (!empty($translator)) {
        $citation .= 'Translated by ' . $translator . ', ';
    }

    // Edition
    if (!empty($edition)) {
        $citation .= $edition . ', ';
    }

    // Publisher
    if (!empty($publisher)) {
        $citation .= $publisher . ', ';
    }

    // Year
    if (!empty($pub_year)) {
        $citation .= $pub_year . '.';
    }

    // Pages (if specific pages cited)
    if (!empty($pages)) {
        $citation .= ' pp. ' . $pages . '.';
    }

    // URL and access date for online sources
    if (!empty($url)) {
        $citation .= ' ' . $url . '.';
        if (!empty($access_date)) {
            $citation .= ' Accessed ' . $access_date . '.';
        }
    }

    return trim($citation);
}

/**
 * Generate Chicago format citation
 * Format: Author. Title. City: Publisher, Year.
 */
function hs_generate_chicago_citation($title, $author, $pub_year, $publisher, $city, $edition, $pages) {
    $citation = '';

    // Author (Last, First.)
    if (!empty($author)) {
        $citation .= hs_format_author_chicago($author) . ' ';
    }

    // Title (italicized)
    $citation .= '<em>' . $title . '</em>. ';

    // Edition
    if (!empty($edition)) {
        $citation .= $edition . '. ';
    }

    // City: Publisher, Year
    $location_parts = array();
    if (!empty($city)) {
        $location_parts[] = $city;
    }
    if (!empty($publisher)) {
        $location_parts[] = $publisher;
    }

    if (!empty($location_parts)) {
        $citation .= implode(': ', $location_parts);
        if (!empty($pub_year)) {
            $citation .= ', ' . $pub_year;
        }
        $citation .= '.';
    } elseif (!empty($pub_year)) {
        $citation .= $pub_year . '.';
    }

    // Pages (if specific pages cited)
    if (!empty($pages)) {
        $citation .= ' ' . $pages . '.';
    }

    return trim($citation);
}

/**
 * Generate Harvard format citation
 * Format: Author (Year) Title. City: Publisher.
 */
function hs_generate_harvard_citation($title, $author, $pub_year, $publisher, $city, $edition, $pages) {
    $citation = '';

    // Author
    if (!empty($author)) {
        $citation .= hs_format_author_harvard($author) . ' ';
    }

    // Year
    if (!empty($pub_year)) {
        $citation .= '(' . $pub_year . ') ';
    }

    // Title (italicized)
    $citation .= '<em>' . $title . '</em>';

    // Edition
    if (!empty($edition)) {
        $citation .= ', ' . $edition;
    }

    $citation .= '. ';

    // City: Publisher
    if (!empty($city) && !empty($publisher)) {
        $citation .= $city . ': ' . $publisher . '.';
    } elseif (!empty($publisher)) {
        $citation .= $publisher . '.';
    }

    // Pages (if specific pages cited)
    if (!empty($pages)) {
        $citation .= ' pp. ' . $pages . '.';
    }

    return trim($citation);
}

/**
 * Format author name for APA style
 * Example: "John Smith" -> "Smith, J."
 */
function hs_format_author_apa($author) {
    $parts = explode(' ', trim($author));
    if (count($parts) === 1) {
        return $author . '.';
    }

    $last_name = array_pop($parts);
    $initials = array();
    foreach ($parts as $part) {
        if (!empty($part)) {
            $initials[] = strtoupper(substr($part, 0, 1)) . '.';
        }
    }

    return $last_name . ', ' . implode(' ', $initials);
}

/**
 * Format author name for MLA style
 * Example: "John Smith" -> "Smith, John."
 */
function hs_format_author_mla($author) {
    $parts = explode(' ', trim($author));
    if (count($parts) === 1) {
        return $author . '.';
    }

    $last_name = array_pop($parts);
    $first_names = implode(' ', $parts);

    return $last_name . ', ' . $first_names . '.';
}

/**
 * Format author name for Chicago style
 * Example: "John Smith" -> "Smith, John."
 */
function hs_format_author_chicago($author) {
    return hs_format_author_mla($author); // Same as MLA
}

/**
 * Format author name for Harvard style
 * Example: "John Smith" -> "Smith, J."
 */
function hs_format_author_harvard($author) {
    return hs_format_author_apa($author); // Same as APA
}

/**
 * Save a citation to the database
 */
function hs_save_citation($user_id, $book_id, $format, $citation_text, $custom_data = array()) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_book_citations';

    $result = $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'book_id' => $book_id,
            'citation_format' => $format,
            'citation_text' => $citation_text,
            'custom_data' => json_encode($custom_data),
            'date_created' => current_time('mysql'),
            'date_modified' => current_time('mysql')
        ),
        array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
    );

    if ($result) {
        return $wpdb->insert_id;
    }

    return false;
}

/**
 * Get user's citations for a book
 */
function hs_get_user_citations($user_id, $book_id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_book_citations';

    if ($book_id) {
        $citations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND book_id = %d ORDER BY date_created DESC",
            $user_id,
            $book_id
        ));
    } else {
        $citations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY date_created DESC",
            $user_id
        ));
    }

    // Decode custom_data JSON
    foreach ($citations as $citation) {
        $citation->custom_data = json_decode($citation->custom_data, true);
    }

    return $citations;
}

/**
 * Delete a citation
 */
function hs_delete_citation($citation_id, $user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_book_citations';

    $result = $wpdb->delete(
        $table_name,
        array('id' => $citation_id, 'user_id' => $user_id),
        array('%d', '%d')
    );

    return $result !== false;
}

/**
 * Update a citation
 */
function hs_update_citation($citation_id, $user_id, $format, $citation_text, $custom_data = array()) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_book_citations';

    $result = $wpdb->update(
        $table_name,
        array(
            'citation_format' => $format,
            'citation_text' => $citation_text,
            'custom_data' => json_encode($custom_data),
            'date_modified' => current_time('mysql')
        ),
        array('id' => $citation_id, 'user_id' => $user_id),
        array('%s', '%s', '%s', '%s'),
        array('%d', '%d')
    );

    return $result !== false;
}

/**
 * AJAX handler: Create citation
 */
function hs_ajax_create_citation() {
    check_ajax_referer('hs_ajax_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'You must be logged in to create citations.'));
        return;
    }

    $user_id = get_current_user_id();
    $book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;
    $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : '';

    if (!$book_id || !$format) {
        wp_send_json_error(array('message' => 'Book ID and format are required.'));
        return;
    }

    // Validate that the user has this book in their library
    global $wpdb;
    $user_books_table = $wpdb->prefix . 'user_books';
    $has_book = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $user_books_table WHERE user_id = %d AND book_id = %d",
        $user_id,
        $book_id
    ));

    if (!$has_book) {
        wp_send_json_error(array('message' => 'You can only create citations for books in your library.'));
        return;
    }

    // Collect custom data
    $custom_data = array();
    $allowed_fields = array('pages', 'access_date', 'url', 'edition', 'publisher', 'city', 'translator', 'editor');

    foreach ($allowed_fields as $field) {
        if (isset($_POST[$field]) && !empty($_POST[$field])) {
            $custom_data[$field] = sanitize_text_field($_POST[$field]);
        }
    }

    // Generate citation
    $citation_text = hs_generate_citation($book_id, $format, $custom_data);

    if (is_wp_error($citation_text)) {
        wp_send_json_error(array('message' => $citation_text->get_error_message()));
        return;
    }

    // Save citation
    $citation_id = hs_save_citation($user_id, $book_id, $format, $citation_text, $custom_data);

    if ($citation_id) {
        wp_send_json_success(array(
            'message' => 'Citation created successfully!',
            'citation_id' => $citation_id,
            'citation_text' => $citation_text,
            'format' => $format
        ));
    } else {
        wp_send_json_error(array('message' => 'Failed to save citation.'));
    }
}
add_action('wp_ajax_hs_create_citation', 'hs_ajax_create_citation');

/**
 * AJAX handler: Get citations for a book
 */
function hs_ajax_get_citations() {
    check_ajax_referer('hs_ajax_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'You must be logged in.'));
        return;
    }

    $user_id = get_current_user_id();
    $book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;

    if (!$book_id) {
        wp_send_json_error(array('message' => 'Book ID is required.'));
        return;
    }

    $citations = hs_get_user_citations($user_id, $book_id);

    wp_send_json_success(array('citations' => $citations));
}
add_action('wp_ajax_hs_get_citations', 'hs_ajax_get_citations');

/**
 * AJAX handler: Delete citation
 */
function hs_ajax_delete_citation() {
    check_ajax_referer('hs_ajax_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'You must be logged in.'));
        return;
    }

    $user_id = get_current_user_id();
    $citation_id = isset($_POST['citation_id']) ? intval($_POST['citation_id']) : 0;

    if (!$citation_id) {
        wp_send_json_error(array('message' => 'Citation ID is required.'));
        return;
    }

    $result = hs_delete_citation($citation_id, $user_id);

    if ($result) {
        wp_send_json_success(array('message' => 'Citation deleted successfully.'));
    } else {
        wp_send_json_error(array('message' => 'Failed to delete citation.'));
    }
}
add_action('wp_ajax_hs_delete_citation', 'hs_ajax_delete_citation');

/**
 * AJAX handler: Regenerate citation with new data
 */
function hs_ajax_regenerate_citation() {
    check_ajax_referer('hs_ajax_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'You must be logged in.'));
        return;
    }

    $book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;
    $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : '';

    if (!$book_id || !$format) {
        wp_send_json_error(array('message' => 'Book ID and format are required.'));
        return;
    }

    // Collect custom data
    $custom_data = array();
    $allowed_fields = array('pages', 'access_date', 'url', 'edition', 'publisher', 'city', 'translator', 'editor');

    foreach ($allowed_fields as $field) {
        if (isset($_POST[$field]) && !empty($_POST[$field])) {
            $custom_data[$field] = sanitize_text_field($_POST[$field]);
        }
    }

    // Generate citation (preview only, not saved)
    $citation_text = hs_generate_citation($book_id, $format, $custom_data);

    if (is_wp_error($citation_text)) {
        wp_send_json_error(array('message' => $citation_text->get_error_message()));
        return;
    }

    wp_send_json_success(array(
        'citation_text' => $citation_text,
        'format' => $format
    ));
}
add_action('wp_ajax_hs_regenerate_citation', 'hs_ajax_regenerate_citation');
