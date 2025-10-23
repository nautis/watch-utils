<?php
/**
 * API Functions - Native PHP Database Implementation
 * No external Flask backend required - all logic runs in WordPress
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get database statistics
 */
function fwd_get_stats() {
    return fwd_db()->get_stats();
}

/**
 * Query by actor name
 */
function fwd_query_actor($actor_name) {
    return fwd_db()->query_actor($actor_name);
}

/**
 * Query by brand name
 */
function fwd_query_brand($brand_name) {
    return fwd_db()->query_brand($brand_name);
}

/**
 * Query by film title
 */
function fwd_query_film($film_title) {
    return fwd_db()->query_film($film_title);
}

/**
 * Add new entry to database
 */
function fwd_add_entry($entry_text, $narrative = '') {
    try {
        $db = fwd_db();
        $parsed = $db->parse_entry($entry_text);

        if ($narrative) {
            $parsed['narrative'] = $narrative;
        }

        $db->insert_entry($parsed);

        return array(
            'success' => true,
            'message' => "Successfully added: {$parsed['actor']} wearing {$parsed['brand']} {$parsed['model']} in {$parsed['title']} ({$parsed['year']})",
            'data' => $parsed
        );
    } catch (Exception $e) {
        return array(
            'success' => false,
            'error' => $e->getMessage()
        );
    }
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
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_fwd_add_entry', 'fwd_ajax_add_entry');
