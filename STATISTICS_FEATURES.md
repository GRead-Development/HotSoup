# Statistics Tracking Features

This document describes the comprehensive statistics tracking system added to HotSoup.

## Overview

The statistics system provides both site-wide and user-specific metrics accessible via shortcodes and REST API endpoints.

## Files Created

- `/includes/statistics.php` - Core statistics calculation functions
- `/includes/shortcodes/statistics_shortcodes.php` - Shortcodes for displaying statistics
- `/includes/api/statistics.php` - REST API endpoints for statistics

## Site-Wide Statistics

### Available Metrics

1. **Books in Libraries** - Total number of books added to all user libraries
2. **Pages Available** - Total pages available to read (sum of all book pages in database)
3. **Total Points Earned** - Sum of all points earned by all users
4. **Total Pages Read** - Total pages read across all users
5. **Books Completed** - Total books completed by all users
6. **Users Registered** - Total number of registered users

### Shortcodes

```
[total_books_in_libraries]           - Display total books in libraries
[total_books_in_libraries format="text"]  - Display with text label

[total_pages_available]              - Display total pages available
[total_pages_available format="text"]     - Display with text label

[total_points_earned]                - Display total points earned
[total_points_earned format="text"]       - Display with text label

[total_pages_read]                   - Display total pages read
[total_pages_read format="text"]          - Display with text label

[total_books_completed]              - Display total books completed
[total_books_completed format="text"]     - Display with text label

[total_users_registered]             - Display total users registered
[total_users_registered format="text"]    - Display with text label

[site_statistics]                    - Display all stats in a table
[site_statistics format="list"]           - Display all stats in a list
[site_statistics format="json"]           - Display all stats as JSON
```

### REST API Endpoints

```
GET /wp-json/gread/v1/statistics/site
GET /wp-json/gread/v1/statistics/site/books-in-libraries
GET /wp-json/gread/v1/statistics/site/pages-available
GET /wp-json/gread/v1/statistics/site/total-points
GET /wp-json/gread/v1/statistics/site/total-pages-read
GET /wp-json/gread/v1/statistics/site/books-completed
GET /wp-json/gread/v1/statistics/site/users-registered
```

## User-Specific Statistics

### Available Metrics

1. **Pages Available** - Total pages available in user's library
2. **Posts Count** - Number of BuddyPress activity posts made
3. **Reviews Count** - Number of book reviews posted
4. **Total Pages Read** - Total pages read by user
5. **Books Completed** - Number of books completed
6. **Books Added** - Number of books added to library
7. **Total Points** - Total points earned
8. **Points Breakdown** - Detailed breakdown of point sources

### Shortcodes

```
[user_pages_available]               - Display current user's pages available
[user_pages_available user_id="123"]      - Display specific user's pages available
[user_pages_available format="text"]      - Display with text label

[user_posts_count]                   - Display current user's post count
[user_posts_count user_id="123"]          - Display specific user's post count
[user_posts_count format="text"]          - Display with text label

[user_reviews_count]                 - Display current user's review count
[user_reviews_count user_id="123"]        - Display specific user's review count
[user_reviews_count format="text"]        - Display with text label

[user_points_breakdown]              - Display current user's points breakdown
[user_points_breakdown user_id="123"]     - Display specific user's points breakdown
[user_points_breakdown format="list"]     - Display as list
[user_points_breakdown format="json"]     - Display as JSON

[user_statistics]                    - Display all user stats in a table
[user_statistics user_id="123"]           - Display specific user's stats
[user_statistics format="list"]           - Display all stats in a list
[user_statistics format="json"]           - Display all stats as JSON
```

### REST API Endpoints

```
GET /wp-json/gread/v1/statistics/user/{id}
GET /wp-json/gread/v1/statistics/user/me
GET /wp-json/gread/v1/statistics/user/{id}/pages-available
GET /wp-json/gread/v1/statistics/user/{id}/posts-count
GET /wp-json/gread/v1/statistics/user/{id}/reviews-count
GET /wp-json/gread/v1/statistics/user/{id}/points-breakdown
```

### Points Breakdown

The points breakdown shows exactly where a user's points came from:

- **Books Published** - 10 points each
- **Comments Approved** - 2 points each
- **Activity Posts** - 2 points each
- **Inaccuracy Reports** - 25 points each
- **Reviews (Rating Only)** - 5 points each
- **Reviews (Text Only)** - 20 points each
- **Reviews (Rating + Text)** - 25 points each

## API Authentication

User-specific endpoints require authentication and will only allow:
- The user viewing their own statistics
- Administrators viewing any user's statistics

## Points System Integration

Points are automatically awarded through WordPress/BuddyPress action hooks:

- **Book publishing**: 10 points (via `publish_book` hook)
- **BuddyPress activities**: 2 points (via `bp_activity_posted_update` hook)
- **Comments**: 2 points (via `comment_post` hook)
- **Reviews**: 5-25 points (via `hs_submit_review()` function)

These hooks fire automatically when actions occur via API endpoints, ensuring that iOS app users and other API consumers receive points for their contributions.

### New Activity Posting Endpoint

A new REST API endpoint has been added for posting activities:

```
POST /wp-json/gread/v1/activity
Content-Type: application/json
Authorization: Bearer {token}

{
  "content": "Your activity update text here"
}
```

This endpoint automatically awards 2 points when an activity is posted, making it perfect for mobile app integration.

## Usage Examples

### Display Site Statistics on Homepage

```html
<div class="site-stats">
  <h2>Community Statistics</h2>
  [site_statistics format="list"]
</div>
```

### Display User Statistics on Profile

```html
<div class="user-stats">
  <h3>Your Reading Stats</h3>
  <p>Pages Available: [user_pages_available format="text"]</p>
  <p>Books Completed: You've completed [user_statistics format="json"] books!</p>

  <h4>Points Breakdown</h4>
  [user_points_breakdown format="table"]
</div>
```

### Fetch Statistics via JavaScript

```javascript
// Get site statistics
fetch('/wp-json/gread/v1/statistics/site')
  .then(response => response.json())
  .then(data => {
    console.log('Total users:', data.data.users_registered);
    console.log('Total books completed:', data.data.books_completed);
  });

// Get current user's statistics
fetch('/wp-json/gread/v1/statistics/user/me', {
  headers: {
    'Authorization': 'Bearer ' + token
  }
})
  .then(response => response.json())
  .then(data => {
    console.log('Your points:', data.data.total_points);
    console.log('Your pages read:', data.data.total_pages_read);
  });

// Post an activity update (iOS app example)
fetch('/wp-json/gread/v1/activity', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    content: 'Just finished reading another chapter!'
  })
})
  .then(response => response.json())
  .then(data => {
    console.log('Activity posted! Points awarded:', data.points_awarded);
  });
```

## Performance Considerations

Statistics are calculated on-demand from the database. For high-traffic sites, consider:

1. Caching results using WordPress transients
2. Implementing a scheduled task to pre-calculate statistics
3. Using a separate statistics table for frequently accessed metrics

## Future Enhancements

Potential improvements to consider:

- Time-based statistics (weekly/monthly summaries)
- Comparative statistics (user vs. site average)
- Achievement triggers based on statistics
- Historical tracking with charts/graphs
- Export functionality for statistics data
