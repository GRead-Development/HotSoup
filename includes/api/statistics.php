<?php
/**
 * Statistics REST API Endpoints
 *
 * Provides REST API endpoints for accessing site-wide and user-specific statistics
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all statistics REST API routes
 */
function hs_register_statistics_api_routes() {
    // Site-wide statistics endpoints
    register_rest_route('gread/v1', '/statistics/site', array(
        'methods' => 'GET',
        'callback' => 'hs_api_get_site_statistics',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('gread/v1', '/statistics/site/books-in-libraries', array(
        'methods' => 'GET',
        'callback' => 'hs_api_get_total_books_in_libraries',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('gread/v1', '/statistics/site/pages-available', array(
        'methods' => 'GET',
        'callback' => 'hs_api_get_total_pages_available',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('gread/v1', '/statistics/site/total-points', array(
        'methods' => 'GET',
        'callback' => 'hs_api_get_total_points_earned',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('gread/v1', '/statistics/site/total-pages-read', array(
        'methods' => 'GET',
        'callback' => 'hs_api_get_total_pages_read',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('gread/v1', '/statistics/site/books-completed', array(
        'methods' => 'GET',
        'callback' => 'hs_api_get_total_books_completed',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('gread/v1', '/statistics/site/users-registered', array(
        'methods' => 'GET',
        'callback' => 'hs_api_get_total_users_registered',
        'permission_callback' => '__return_true',
    ));

    // User-specific statistics endpoints
    register_rest_route('gread/v1', '/statistics/user/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'hs_api_get_user_statistics',
        'permission_callback' => 'hs_api_statistics_user_permission_check',
        'args' => array(
            'id' => array(
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));

    register_rest_route('gread/v1', '/statistics/user/me', array(
        'methods' => 'GET',
        'callback' => 'hs_api_get_current_user_statistics',
        'permission_callback' => 'is_user_logged_in',
    ));

    register_rest_route('gread/v1', '/statistics/user/(?P<id>\d+)/pages-available', array(
        'methods' => 'GET',
        'callback' => 'hs_api_get_user_pages_available',
        'permission_callback' => 'hs_api_statistics_user_permission_check',
        'args' => array(
            'id' => array(
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));

    register_rest_route('gread/v1', '/statistics/user/(?P<id>\d+)/posts-count', array(
        'methods' => 'GET',
        'callback' => 'hs_api_get_user_posts_count',
        'permission_callback' => '__return_true',
        'args' => array(
            'id' => array(
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));

    register_rest_route('gread/v1', '/statistics/user/(?P<id>\d+)/reviews-count', array(
        'methods' => 'GET',
        'callback' => 'hs_api_get_user_reviews_count',
        'permission_callback' => '__return_true',
        'args' => array(
            'id' => array(
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));

    register_rest_route('gread/v1', '/statistics/user/(?P<id>\d+)/points-breakdown', array(
        'methods' => 'GET',
        'callback' => 'hs_api_get_user_points_breakdown',
        'permission_callback' => 'hs_api_statistics_user_permission_check',
        'args' => array(
            'id' => array(
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
}
add_action('rest_api_init', 'hs_register_statistics_api_routes');

/**
 * Permission check for user-specific endpoints
 * Allows access if user is viewing their own stats or is an admin
 */
function hs_api_statistics_user_permission_check($request) {
    $user_id = $request->get_param('id');
    $current_user_id = get_current_user_id();

    // Allow if user is logged in and viewing their own stats
    if ($current_user_id && $current_user_id == $user_id) {
        return true;
    }

    // Allow if user is an admin
    if (current_user_can('manage_options')) {
        return true;
    }

    return new WP_Error(
        'rest_forbidden',
        __('You do not have permission to view these statistics.', 'hotsoup'),
        array('status' => 403)
    );
}

/**
 * ========================================
 * SITE-WIDE STATISTICS API CALLBACKS
 * ========================================
 */

/**
 * Get all site statistics
 */
function hs_api_get_site_statistics($request) {
    $stats = hs_get_site_statistics();

    return rest_ensure_response(array(
        'success' => true,
        'data' => $stats,
    ));
}

/**
 * Get total books in libraries
 */
function hs_api_get_total_books_in_libraries($request) {
    $count = hs_get_total_books_in_libraries();

    return rest_ensure_response(array(
        'success' => true,
        'data' => array(
            'count' => $count,
        ),
    ));
}

/**
 * Get total pages available
 */
function hs_api_get_total_pages_available($request) {
    $count = hs_get_total_pages_available();

    return rest_ensure_response(array(
        'success' => true,
        'data' => array(
            'count' => $count,
        ),
    ));
}

/**
 * Get total points earned
 */
function hs_api_get_total_points_earned($request) {
    $count = hs_get_total_points_earned();

    return rest_ensure_response(array(
        'success' => true,
        'data' => array(
            'count' => $count,
        ),
    ));
}

/**
 * Get total pages read
 */
function hs_api_get_total_pages_read($request) {
    $count = hs_get_total_pages_read();

    return rest_ensure_response(array(
        'success' => true,
        'data' => array(
            'count' => $count,
        ),
    ));
}

/**
 * Get total books completed
 */
function hs_api_get_total_books_completed($request) {
    $count = hs_get_total_books_completed();

    return rest_ensure_response(array(
        'success' => true,
        'data' => array(
            'count' => $count,
        ),
    ));
}

/**
 * Get total users registered
 */
function hs_api_get_total_users_registered($request) {
    $count = hs_get_total_users_registered();

    return rest_ensure_response(array(
        'success' => true,
        'data' => array(
            'count' => $count,
        ),
    ));
}

/**
 * ========================================
 * USER-SPECIFIC STATISTICS API CALLBACKS
 * ========================================
 */

/**
 * Get all statistics for a specific user
 */
function hs_api_get_user_statistics($request) {
    $user_id = $request->get_param('id');
    $stats = hs_get_user_statistics($user_id);

    if (isset($stats['error'])) {
        return new WP_Error(
            'invalid_user',
            $stats['error'],
            array('status' => 404)
        );
    }

    return rest_ensure_response(array(
        'success' => true,
        'data' => $stats,
    ));
}

/**
 * Get statistics for current logged-in user
 */
function hs_api_get_current_user_statistics($request) {
    $user_id = get_current_user_id();
    $stats = hs_get_user_statistics($user_id);

    if (isset($stats['error'])) {
        return new WP_Error(
            'invalid_user',
            $stats['error'],
            array('status' => 404)
        );
    }

    return rest_ensure_response(array(
        'success' => true,
        'data' => $stats,
    ));
}

/**
 * Get user's available pages
 */
function hs_api_get_user_pages_available($request) {
    $user_id = $request->get_param('id');
    $count = hs_get_user_pages_available($user_id);

    return rest_ensure_response(array(
        'success' => true,
        'data' => array(
            'count' => $count,
        ),
    ));
}

/**
 * Get user's posts count
 */
function hs_api_get_user_posts_count($request) {
    $user_id = $request->get_param('id');
    $count = hs_get_user_posts_count($user_id);

    return rest_ensure_response(array(
        'success' => true,
        'data' => array(
            'count' => $count,
        ),
    ));
}

/**
 * Get user's reviews count
 */
function hs_api_get_user_reviews_count($request) {
    $user_id = $request->get_param('id');
    $count = hs_get_user_reviews_count($user_id);

    return rest_ensure_response(array(
        'success' => true,
        'data' => array(
            'count' => $count,
        ),
    ));
}

/**
 * Get user's points breakdown
 */
function hs_api_get_user_points_breakdown($request) {
    $user_id = $request->get_param('id');
    $breakdown = hs_get_user_points_breakdown($user_id);

    return rest_ensure_response(array(
        'success' => true,
        'data' => $breakdown,
    ));
}
