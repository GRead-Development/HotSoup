<?php
/**
 * Statistics Tracking and Calculation Functions
 *
 * Provides comprehensive statistics for both site-wide and user-specific metrics
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ========================================
 * SITE-WIDE STATISTICS
 * ========================================
 */

/**
 * Get total number of books added to user libraries across all users
 *
 * @return int Total count of library entries
 */
function hs_get_total_books_in_libraries() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_books';

    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    return (int) $count;
}

/**
 * Get total number of pages available to read (sum of all book pages in database)
 *
 * @return int Total pages across all books
 */
function hs_get_total_pages_available() {
    global $wpdb;

    // Get sum of all 'nop' (number of pages) meta values for published books
    $total_pages = $wpdb->get_var($wpdb->prepare("
        SELECT SUM(CAST(pm.meta_value AS UNSIGNED))
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE pm.meta_key = %s
        AND p.post_type = %s
        AND p.post_status = %s
        AND pm.meta_value REGEXP '^[0-9]+$'
    ", 'nop', 'book', 'publish'));

    return (int) $total_pages;
}

/**
 * Get total points earned across all users
 *
 * @return int Total points
 */
function hs_get_total_points_earned() {
    global $wpdb;

    $total_points = $wpdb->get_var($wpdb->prepare("
        SELECT SUM(CAST(meta_value AS UNSIGNED))
        FROM {$wpdb->usermeta}
        WHERE meta_key = %s
    ", 'user_points'));

    return (int) $total_points;
}

/**
 * Get total pages read across all users
 *
 * @return int Total pages read
 */
function hs_get_total_pages_read() {
    global $wpdb;

    $total_pages = $wpdb->get_var($wpdb->prepare("
        SELECT SUM(CAST(meta_value AS UNSIGNED))
        FROM {$wpdb->usermeta}
        WHERE meta_key = %s
    ", 'hs_total_pages_read'));

    return (int) $total_pages;
}

/**
 * Get total books completed across all users
 *
 * @return int Total completed books
 */
function hs_get_total_books_completed() {
    global $wpdb;

    $total_completed = $wpdb->get_var($wpdb->prepare("
        SELECT SUM(CAST(meta_value AS UNSIGNED))
        FROM {$wpdb->usermeta}
        WHERE meta_key = %s
    ", 'hs_completed_books_count'));

    return (int) $total_completed;
}

/**
 * Get total number of registered users
 *
 * @return int Total users
 */
function hs_get_total_users_registered() {
    $user_count = count_users();
    return (int) $user_count['total_users'];
}

/**
 * Get all site-wide statistics in a single array
 *
 * @return array Associative array of all site statistics
 */
function hs_get_site_statistics() {
    return array(
        'books_in_libraries' => hs_get_total_books_in_libraries(),
        'pages_available' => hs_get_total_pages_available(),
        'total_points' => hs_get_total_points_earned(),
        'total_pages_read' => hs_get_total_pages_read(),
        'books_completed' => hs_get_total_books_completed(),
        'users_registered' => hs_get_total_users_registered(),
    );
}

/**
 * ========================================
 * USER-SPECIFIC STATISTICS
 * ========================================
 */

/**
 * Get total pages available for a specific user to read (from their library)
 *
 * @param int $user_id User ID (defaults to current user)
 * @return int Total pages available in user's library
 */
function hs_get_user_pages_available($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return 0;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'user_books';

    $total_pages = $wpdb->get_var($wpdb->prepare("
        SELECT SUM(CAST(pm.meta_value AS UNSIGNED))
        FROM {$table_name} ub
        INNER JOIN {$wpdb->postmeta} pm ON ub.book_id = pm.post_id
        WHERE ub.user_id = %d
        AND pm.meta_key = %s
        AND pm.meta_value REGEXP '^[0-9]+$'
    ", $user_id, 'nop'));

    return (int) $total_pages;
}

/**
 * Get number of BuddyPress activity posts made by user
 *
 * @param int $user_id User ID (defaults to current user)
 * @return int Number of activity posts
 */
function hs_get_user_posts_count($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return 0;
    }

    // Check if BuddyPress is active
    if (function_exists('bp_activity_get')) {
        global $wpdb;
        $bp_prefix = $wpdb->prefix . 'bp_activity';

        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$bp_prefix}
            WHERE user_id = %d
            AND type = 'activity_update'
        ", $user_id));

        return (int) $count;
    }

    return 0;
}

/**
 * Get breakdown of where user's points came from
 *
 * @param int $user_id User ID (defaults to current user)
 * @return array Array of point sources with counts and totals
 */
function hs_get_user_points_breakdown($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return array();
    }

    global $wpdb;

    $breakdown = array();

    // Books published (10 points each)
    $books_count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->posts}
        WHERE post_author = %d
        AND post_type = %s
        AND post_status = %s
    ", $user_id, 'book', 'publish'));
    $breakdown['books_published'] = array(
        'count' => (int) $books_count,
        'points_each' => 10,
        'total_points' => (int) $books_count * 10
    );

    // Approved comments (2 points each)
    $comments_count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->comments}
        WHERE user_id = %d
        AND comment_approved = '1'
    ", $user_id));
    $breakdown['comments_approved'] = array(
        'count' => (int) $comments_count,
        'points_each' => 2,
        'total_points' => (int) $comments_count * 2
    );

    // BuddyPress activities (2 points each)
    if (function_exists('bp_activity_get')) {
        $bp_prefix = $wpdb->prefix . 'bp_activity';
        $activities_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$bp_prefix}
            WHERE user_id = %d
            AND type = 'activity_update'
        ", $user_id));
        $breakdown['activity_posts'] = array(
            'count' => (int) $activities_count,
            'points_each' => 2,
            'total_points' => (int) $activities_count * 2
        );
    }

    // Approved inaccuracy reports (25 points each)
    $reports_table = $wpdb->prefix . 'hs_book_reports';
    $reports_count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$reports_table}
        WHERE user_id = %d
        AND status = %s
    ", $user_id, 'approved'));
    $breakdown['inaccuracy_reports'] = array(
        'count' => (int) $reports_count,
        'points_each' => 25,
        'total_points' => (int) $reports_count * 25
    );

    // Reviews with ratings and/or text (5-25 points)
    $reviews_table = $wpdb->prefix . 'hs_book_reviews';

    // Rating only (5 points)
    $rating_only = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$reviews_table}
        WHERE user_id = %d
        AND rating IS NOT NULL
        AND (review_text IS NULL OR review_text = '')
    ", $user_id));
    $breakdown['reviews_rating_only'] = array(
        'count' => (int) $rating_only,
        'points_each' => 5,
        'total_points' => (int) $rating_only * 5
    );

    // Text only (20 points)
    $text_only = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$reviews_table}
        WHERE user_id = %d
        AND (rating IS NULL)
        AND review_text IS NOT NULL
        AND review_text != ''
    ", $user_id));
    $breakdown['reviews_text_only'] = array(
        'count' => (int) $text_only,
        'points_each' => 20,
        'total_points' => (int) $text_only * 20
    );

    // Rating + text (25 points)
    $rating_and_text = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$reviews_table}
        WHERE user_id = %d
        AND rating IS NOT NULL
        AND review_text IS NOT NULL
        AND review_text != ''
    ", $user_id));
    $breakdown['reviews_rating_and_text'] = array(
        'count' => (int) $rating_and_text,
        'points_each' => 25,
        'total_points' => (int) $rating_and_text * 25
    );

    return $breakdown;
}

/**
 * Get number of reviews posted by user
 *
 * @param int $user_id User ID (defaults to current user)
 * @return int Number of reviews
 */
function hs_get_user_reviews_count($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return 0;
    }

    global $wpdb;
    $reviews_table = $wpdb->prefix . 'hs_book_reviews';

    $count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$reviews_table}
        WHERE user_id = %d
    ", $user_id));

    return (int) $count;
}

/**
 * Get all statistics for a specific user
 *
 * @param int $user_id User ID (defaults to current user)
 * @return array Associative array of all user statistics
 */
function hs_get_user_statistics($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return array('error' => 'Invalid user ID');
    }

    // Get existing stats from user meta
    $total_pages_read = get_user_meta($user_id, 'hs_total_pages_read', true);
    $books_completed = get_user_meta($user_id, 'hs_completed_books_count', true);
    $books_added = get_user_meta($user_id, 'hs_books_added_count', true);
    $user_points = get_user_meta($user_id, 'user_points', true);

    return array(
        'pages_available' => hs_get_user_pages_available($user_id),
        'posts_count' => hs_get_user_posts_count($user_id),
        'reviews_count' => hs_get_user_reviews_count($user_id),
        'total_pages_read' => (int) $total_pages_read,
        'books_completed' => (int) $books_completed,
        'books_added' => (int) $books_added,
        'total_points' => (int) $user_points,
        'points_breakdown' => hs_get_user_points_breakdown($user_id),
    );
}
