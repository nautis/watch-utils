<?php
/**
 * API Functions - Handle communication with Flask backend
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get plugin settings
 */
function fwd_get_settings() {
    $defaults = array(
        'api_url' => 'http://127.0.0.1:5000',
        'cache_enabled' => true,
        'cache_duration' => 300,
    );

    $settings = get_option('fwd_settings', $defaults);
    return wp_parse_args($settings, $defaults);
}

/**
 * Make API request to Flask backend
 */
function fwd_api_request($endpoint, $method = 'GET', $data = null) {
    $settings = fwd_get_settings();
    $api_url = rtrim($settings['api_url'], '/');
    $url = $api_url . '/' . ltrim($endpoint, '/');

    // Check cache first for GET requests
    if ($method === 'GET' && $settings['cache_enabled']) {
        $cache_key = 'fwd_' . md5($url);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
    }

    // Prepare request arguments
    $args = array(
        'timeout' => 15,
        'method' => $method,
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
    );

    if ($data !== null && $method === 'POST') {
        $args['body'] = json_encode($data);
    }

    // Make the request
    $response = wp_remote_request($url, $args);

    // Check for errors
    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'error' => $response->get_error_message()
        );
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);

    if ($status_code !== 200) {
        return array(
            'success' => false,
            'error' => isset($result['error']) ? $result['error'] : 'API request failed'
        );
    }

    // Cache successful GET requests
    if ($method === 'GET' && $settings['cache_enabled'] && isset($result)) {
        set_transient($cache_key, $result, $settings['cache_duration']);
    }

    return $result;
}

/**
 * Check if Flask backend is online
 */
function fwd_check_backend_status() {
    $result = fwd_api_request('/');
    return isset($result['status']) && $result['status'] === 'running';
}

/**
 * Get database statistics
 */
function fwd_get_stats() {
    return fwd_api_request('/api/stats');
}

/**
 * Query by actor name
 */
function fwd_query_actor($actor_name) {
    return fwd_api_request('/api/query/actor/' . urlencode($actor_name));
}

/**
 * Query by brand name
 */
function fwd_query_brand($brand_name) {
    return fwd_api_request('/api/query/brand/' . urlencode($brand_name));
}

/**
 * Query by film title
 */
function fwd_query_film($film_title) {
    return fwd_api_request('/api/query/film/' . urlencode($film_title));
}

/**
 * Add new entry to database
 */
function fwd_add_entry($entry_text, $narrative = '') {
    $data = array(
        'entry' => $entry_text,
        'narrative' => $narrative ? $narrative : 'Watch worn in film.'
    );

    return fwd_api_request('/api/add', 'POST', $data);
}

/**
 * AJAX handler for search requests
 */
function fwd_ajax_search() {
    check_ajax_referer('fwd_ajax_nonce', 'nonce');

    $query_type = sanitize_text_field($_POST['query_type']);
    $search_term = sanitize_text_field($_POST['search_term']);

    if (empty($search_term)) {
        wp_send_json_error(array('message' => 'Search term is required'));
    }

    $result = null;
    switch ($query_type) {
        case 'actor':
            $result = fwd_query_actor($search_term);
            break;
        case 'brand':
            $result = fwd_query_brand($search_term);
            break;
        case 'film':
            $result = fwd_query_film($search_term);
            break;
        default:
            wp_send_json_error(array('message' => 'Invalid query type'));
    }

    if (isset($result['success']) && $result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_fwd_search', 'fwd_ajax_search');
add_action('wp_ajax_nopriv_fwd_search', 'fwd_ajax_search');

/**
 * AJAX handler for adding entries (admin only)
 */
function fwd_ajax_add_entry() {
    check_ajax_referer('fwd_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $entry_text = sanitize_text_field($_POST['entry_text']);
    $narrative = sanitize_textarea_field($_POST['narrative']);

    if (empty($entry_text)) {
        wp_send_json_error(array('message' => 'Entry text is required'));
    }

    $result = fwd_add_entry($entry_text, $narrative);

    if (isset($result['success']) && $result['success']) {
        // Clear cache when adding new entry
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fwd_%'");

        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_fwd_add_entry', 'fwd_ajax_add_entry');
