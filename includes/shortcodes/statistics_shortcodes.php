<?php
/**
 * Statistics Shortcodes
 *
 * Provides shortcodes for displaying site-wide and user-specific statistics
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ========================================
 * SITE-WIDE STATISTICS SHORTCODES
 * ========================================
 */

/**
 * Display total books in all user libraries
 * Usage: [total_books_in_libraries]
 */
function hs_shortcode_total_books_in_libraries($atts) {
    $atts = shortcode_atts(array(
        'format' => 'number', // 'number' or 'text'
    ), $atts);

    $count = hs_get_total_books_in_libraries();

    if ($atts['format'] === 'text') {
        return number_format($count) . ' ' . _n('book', 'books', $count, 'hotsoup');
    }

    return number_format($count);
}
add_shortcode('total_books_in_libraries', 'hs_shortcode_total_books_in_libraries');

/**
 * Display total pages available to read
 * Usage: [total_pages_available]
 */
function hs_shortcode_total_pages_available($atts) {
    $atts = shortcode_atts(array(
        'format' => 'number', // 'number' or 'text'
    ), $atts);

    $count = hs_get_total_pages_available();

    if ($atts['format'] === 'text') {
        return number_format($count) . ' ' . _n('page', 'pages', $count, 'hotsoup');
    }

    return number_format($count);
}
add_shortcode('total_pages_available', 'hs_shortcode_total_pages_available');

/**
 * Display total points earned across all users
 * Usage: [total_points_earned]
 */
function hs_shortcode_total_points_earned($atts) {
    $atts = shortcode_atts(array(
        'format' => 'number', // 'number' or 'text'
    ), $atts);

    $count = hs_get_total_points_earned();

    if ($atts['format'] === 'text') {
        return number_format($count) . ' ' . _n('point', 'points', $count, 'hotsoup');
    }

    return number_format($count);
}
add_shortcode('total_points_earned', 'hs_shortcode_total_points_earned');

/**
 * Display total pages read across all users
 * Usage: [total_pages_read]
 */
function hs_shortcode_total_pages_read($atts) {
    $atts = shortcode_atts(array(
        'format' => 'number', // 'number' or 'text'
    ), $atts);

    $count = hs_get_total_pages_read();

    if ($atts['format'] === 'text') {
        return number_format($count) . ' ' . _n('page', 'pages', $count, 'hotsoup') . ' read';
    }

    return number_format($count);
}
add_shortcode('total_pages_read', 'hs_shortcode_total_pages_read');

/**
 * Display total books completed across all users
 * Usage: [total_books_completed]
 */
function hs_shortcode_total_books_completed($atts) {
    $atts = shortcode_atts(array(
        'format' => 'number', // 'number' or 'text'
    ), $atts);

    $count = hs_get_total_books_completed();

    if ($atts['format'] === 'text') {
        return number_format($count) . ' ' . _n('book', 'books', $count, 'hotsoup') . ' completed';
    }

    return number_format($count);
}
add_shortcode('total_books_completed', 'hs_shortcode_total_books_completed');

/**
 * Display total registered users
 * Usage: [total_users_registered]
 */
function hs_shortcode_total_users_registered($atts) {
    $atts = shortcode_atts(array(
        'format' => 'number', // 'number' or 'text'
    ), $atts);

    $count = hs_get_total_users_registered();

    if ($atts['format'] === 'text') {
        return number_format($count) . ' registered ' . _n('user', 'users', $count, 'hotsoup');
    }

    return number_format($count);
}
add_shortcode('total_users_registered', 'hs_shortcode_total_users_registered');

/**
 * Display all site statistics in a formatted table
 * Usage: [site_statistics]
 */
function hs_shortcode_site_statistics($atts) {
    $atts = shortcode_atts(array(
        'format' => 'table', // 'table', 'list', or 'json'
    ), $atts);

    $stats = hs_get_site_statistics();

    if ($atts['format'] === 'json') {
        return json_encode($stats);
    }

    if ($atts['format'] === 'list') {
        $output = '<ul class="hs-site-statistics">';
        $output .= '<li><strong>Books in Libraries:</strong> ' . number_format($stats['books_in_libraries']) . '</li>';
        $output .= '<li><strong>Pages Available:</strong> ' . number_format($stats['pages_available']) . '</li>';
        $output .= '<li><strong>Total Points Earned:</strong> ' . number_format($stats['total_points']) . '</li>';
        $output .= '<li><strong>Total Pages Read:</strong> ' . number_format($stats['total_pages_read']) . '</li>';
        $output .= '<li><strong>Books Completed:</strong> ' . number_format($stats['books_completed']) . '</li>';
        $output .= '<li><strong>Registered Users:</strong> ' . number_format($stats['users_registered']) . '</li>';
        $output .= '</ul>';
        return $output;
    }

    // Default: table format
    $output = '<table class="hs-site-statistics">';
    $output .= '<thead><tr><th>Statistic</th><th>Value</th></tr></thead>';
    $output .= '<tbody>';
    $output .= '<tr><td>Books in Libraries</td><td>' . number_format($stats['books_in_libraries']) . '</td></tr>';
    $output .= '<tr><td>Pages Available</td><td>' . number_format($stats['pages_available']) . '</td></tr>';
    $output .= '<tr><td>Total Points Earned</td><td>' . number_format($stats['total_points']) . '</td></tr>';
    $output .= '<tr><td>Total Pages Read</td><td>' . number_format($stats['total_pages_read']) . '</td></tr>';
    $output .= '<tr><td>Books Completed</td><td>' . number_format($stats['books_completed']) . '</td></tr>';
    $output .= '<tr><td>Registered Users</td><td>' . number_format($stats['users_registered']) . '</td></tr>';
    $output .= '</tbody>';
    $output .= '</table>';

    return $output;
}
add_shortcode('site_statistics', 'hs_shortcode_site_statistics');

/**
 * ========================================
 * USER-SPECIFIC STATISTICS SHORTCODES
 * ========================================
 */

/**
 * Display pages available in user's library
 * Usage: [user_pages_available] or [user_pages_available user_id="123"]
 */
function hs_shortcode_user_pages_available($atts) {
    $atts = shortcode_atts(array(
        'user_id' => get_current_user_id(),
        'format' => 'number', // 'number' or 'text'
    ), $atts);

    $count = hs_get_user_pages_available($atts['user_id']);

    if ($atts['format'] === 'text') {
        return number_format($count) . ' ' . _n('page', 'pages', $count, 'hotsoup') . ' available';
    }

    return number_format($count);
}
add_shortcode('user_pages_available', 'hs_shortcode_user_pages_available');

/**
 * Display user's post count
 * Usage: [user_posts_count] or [user_posts_count user_id="123"]
 */
function hs_shortcode_user_posts_count($atts) {
    $atts = shortcode_atts(array(
        'user_id' => get_current_user_id(),
        'format' => 'number', // 'number' or 'text'
    ), $atts);

    $count = hs_get_user_posts_count($atts['user_id']);

    if ($atts['format'] === 'text') {
        return number_format($count) . ' ' . _n('post', 'posts', $count, 'hotsoup');
    }

    return number_format($count);
}
add_shortcode('user_posts_count', 'hs_shortcode_user_posts_count');

/**
 * Display user's review count
 * Usage: [user_reviews_count] or [user_reviews_count user_id="123"]
 */
function hs_shortcode_user_reviews_count($atts) {
    $atts = shortcode_atts(array(
        'user_id' => get_current_user_id(),
        'format' => 'number', // 'number' or 'text'
    ), $atts);

    $count = hs_get_user_reviews_count($atts['user_id']);

    if ($atts['format'] === 'text') {
        return number_format($count) . ' ' . _n('review', 'reviews', $count, 'hotsoup');
    }

    return number_format($count);
}
add_shortcode('user_reviews_count', 'hs_shortcode_user_reviews_count');

/**
 * Display user's points breakdown
 * Usage: [user_points_breakdown] or [user_points_breakdown user_id="123"]
 */
function hs_shortcode_user_points_breakdown($atts) {
    $atts = shortcode_atts(array(
        'user_id' => get_current_user_id(),
        'format' => 'table', // 'table', 'list', or 'json'
    ), $atts);

    $breakdown = hs_get_user_points_breakdown($atts['user_id']);

    if (empty($breakdown)) {
        return '<p>No points data available.</p>';
    }

    if ($atts['format'] === 'json') {
        return json_encode($breakdown);
    }

    if ($atts['format'] === 'list') {
        $output = '<ul class="hs-points-breakdown">';
        foreach ($breakdown as $source => $data) {
            $label = ucwords(str_replace('_', ' ', $source));
            $output .= sprintf(
                '<li><strong>%s:</strong> %d &times; %d = %d points</li>',
                esc_html($label),
                $data['count'],
                $data['points_each'],
                $data['total_points']
            );
        }
        $output .= '</ul>';
        return $output;
    }

    // Default: table format
    $output = '<table class="hs-points-breakdown">';
    $output .= '<thead><tr><th>Source</th><th>Count</th><th>Points Each</th><th>Total Points</th></tr></thead>';
    $output .= '<tbody>';
    foreach ($breakdown as $source => $data) {
        $label = ucwords(str_replace('_', ' ', $source));
        $output .= sprintf(
            '<tr><td>%s</td><td>%d</td><td>%d</td><td>%d</td></tr>',
            esc_html($label),
            $data['count'],
            $data['points_each'],
            $data['total_points']
        );
    }
    $output .= '</tbody>';
    $output .= '</table>';

    return $output;
}
add_shortcode('user_points_breakdown', 'hs_shortcode_user_points_breakdown');

/**
 * Display all user statistics
 * Usage: [user_statistics] or [user_statistics user_id="123"]
 */
function hs_shortcode_user_statistics($atts) {
    $atts = shortcode_atts(array(
        'user_id' => get_current_user_id(),
        'format' => 'table', // 'table', 'list', or 'json'
    ), $atts);

    $stats = hs_get_user_statistics($atts['user_id']);

    if (isset($stats['error'])) {
        return '<p>' . esc_html($stats['error']) . '</p>';
    }

    if ($atts['format'] === 'json') {
        return json_encode($stats);
    }

    if ($atts['format'] === 'list') {
        $output = '<ul class="hs-user-statistics">';
        $output .= '<li><strong>Pages Available:</strong> ' . number_format($stats['pages_available']) . '</li>';
        $output .= '<li><strong>Posts Made:</strong> ' . number_format($stats['posts_count']) . '</li>';
        $output .= '<li><strong>Reviews Posted:</strong> ' . number_format($stats['reviews_count']) . '</li>';
        $output .= '<li><strong>Total Pages Read:</strong> ' . number_format($stats['total_pages_read']) . '</li>';
        $output .= '<li><strong>Books Completed:</strong> ' . number_format($stats['books_completed']) . '</li>';
        $output .= '<li><strong>Books Added:</strong> ' . number_format($stats['books_added']) . '</li>';
        $output .= '<li><strong>Total Points:</strong> ' . number_format($stats['total_points']) . '</li>';
        $output .= '</ul>';
        return $output;
    }

    // Default: table format
    $output = '<table class="hs-user-statistics">';
    $output .= '<thead><tr><th>Statistic</th><th>Value</th></tr></thead>';
    $output .= '<tbody>';
    $output .= '<tr><td>Pages Available</td><td>' . number_format($stats['pages_available']) . '</td></tr>';
    $output .= '<tr><td>Posts Made</td><td>' . number_format($stats['posts_count']) . '</td></tr>';
    $output .= '<tr><td>Reviews Posted</td><td>' . number_format($stats['reviews_count']) . '</td></tr>';
    $output .= '<tr><td>Total Pages Read</td><td>' . number_format($stats['total_pages_read']) . '</td></tr>';
    $output .= '<tr><td>Books Completed</td><td>' . number_format($stats['books_completed']) . '</td></tr>';
    $output .= '<tr><td>Books Added</td><td>' . number_format($stats['books_added']) . '</td></tr>';
    $output .= '<tr><td>Total Points</td><td>' . number_format($stats['total_points']) . '</td></tr>';
    $output .= '</tbody>';
    $output .= '</table>';

    return $output;
}
add_shortcode('user_statistics', 'hs_shortcode_user_statistics');
