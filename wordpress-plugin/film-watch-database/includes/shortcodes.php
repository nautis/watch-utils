<?php
/**
 * Shortcodes for Film Watch Database
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode: [film_watch_search]
 * Displays a search form for the database
 */
function fwd_search_shortcode($atts) {
    $atts = shortcode_atts(array(
        'type' => 'all', // all, actor, brand, film
        'placeholder' => 'Search movies, actors, or watch brands...',
    ), $atts);

    ob_start();
    ?>
    <div class="fwd-search-container">
        <div class="fwd-search-form">
            <?php if ($atts['type'] === 'all'): ?>
            <select id="fwd-search-type" class="fwd-select">
                <option value="actor">Actor</option>
                <option value="brand">Watch Brand</option>
                <option value="film">Film Title</option>
            </select>
            <?php else: ?>
            <input type="hidden" id="fwd-search-type" value="<?php echo esc_attr($atts['type']); ?>">
            <?php endif; ?>

            <input
                type="text"
                id="fwd-search-input"
                class="fwd-input"
                placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
            >
            <button id="fwd-search-btn" class="fwd-button">Search</button>
        </div>

        <div id="fwd-search-results" class="fwd-results-container"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('film_watch_search', 'fwd_search_shortcode');

/**
 * Shortcode: [film_watch_stats]
 * Displays database statistics
 */
function fwd_stats_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_top_brands' => 'yes',
    ), $atts);

    $stats_data = fwd_get_stats();

    if (!isset($stats_data['success']) || !$stats_data['success']) {
        return '<div class="fwd-error">Unable to load statistics. Please check your Flask backend connection.</div>';
    }

    $stats = $stats_data['stats'];

    ob_start();
    ?>
    <div class="fwd-stats-container">
        <div class="fwd-stat-grid">
            <div class="fwd-stat-card">
                <div class="fwd-stat-number"><?php echo esc_html($stats['films']); ?></div>
                <div class="fwd-stat-label">Films</div>
            </div>
            <div class="fwd-stat-card">
                <div class="fwd-stat-number"><?php echo esc_html($stats['actors']); ?></div>
                <div class="fwd-stat-label">Actors</div>
            </div>
            <div class="fwd-stat-card">
                <div class="fwd-stat-number"><?php echo esc_html($stats['brands']); ?></div>
                <div class="fwd-stat-label">Brands</div>
            </div>
            <div class="fwd-stat-card">
                <div class="fwd-stat-number"><?php echo esc_html($stats['entries']); ?></div>
                <div class="fwd-stat-label">Total Entries</div>
            </div>
        </div>

        <?php if ($atts['show_top_brands'] === 'yes' && !empty($stats['top_brands'])): ?>
        <div class="fwd-top-brands">
            <h3>Top Watch Brands</h3>
            <div class="fwd-brands-list">
                <?php foreach ($stats['top_brands'] as $index => $brand): ?>
                <div class="fwd-brand-item">
                    <span class="fwd-brand-rank"><?php echo ($index + 1); ?>.</span>
                    <span class="fwd-brand-name"><?php echo esc_html($brand['brand']); ?></span>
                    <span class="fwd-brand-count"><?php echo esc_html($brand['count']); ?> film<?php echo $brand['count'] !== 1 ? 's' : ''; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('film_watch_stats', 'fwd_stats_shortcode');

/**
 * Shortcode: [film_watch_actor name="Tom Cruise"]
 * Displays watches for a specific actor
 */
function fwd_actor_shortcode($atts) {
    $atts = shortcode_atts(array(
        'name' => '',
    ), $atts);

    if (empty($atts['name'])) {
        return '<div class="fwd-error">Please specify an actor name using the "name" attribute.</div>';
    }

    $result = fwd_query_actor($atts['name']);

    if (!isset($result['success']) || !$result['success']) {
        return '<div class="fwd-error">Unable to load data for ' . esc_html($atts['name']) . '.</div>';
    }

    if ($result['count'] === 0) {
        return '<div class="fwd-no-results">No watches found for ' . esc_html($atts['name']) . '.</div>';
    }

    ob_start();
    ?>
    <div class="fwd-actor-container">
        <h3><?php echo esc_html($atts['name']); ?>'s Watches in Film</h3>
        <p class="fwd-count">Found <?php echo esc_html($result['count']); ?> result(s)</p>

        <div class="fwd-items-list">
            <?php foreach ($result['films'] as $film): ?>
            <div class="fwd-item">
                <div class="fwd-item-title"><?php echo esc_html($film['title']); ?> (<?php echo esc_html($film['year']); ?>)</div>
                <div class="fwd-item-details">
                    <strong>Character:</strong> <?php echo esc_html($film['character']); ?><br>
                    <strong>Watch:</strong> <?php echo esc_html($film['brand']); ?> <?php echo esc_html($film['model']); ?><br>
                    <strong>Role:</strong> <?php echo esc_html($film['narrative']); ?>
                    <?php if (!empty($film['source_url'])): ?>
                        <br><strong>Source:</strong> <a href="<?php echo esc_url($film['source_url']); ?>" target="_blank" rel="noopener">View Reference</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('film_watch_actor', 'fwd_actor_shortcode');

/**
 * Shortcode: [film_watch_brand name="Rolex"]
 * Displays films featuring a specific watch brand
 */
function fwd_brand_shortcode($atts) {
    $atts = shortcode_atts(array(
        'name' => '',
    ), $atts);

    if (empty($atts['name'])) {
        return '<div class="fwd-error">Please specify a brand name using the "name" attribute.</div>';
    }

    $result = fwd_query_brand($atts['name']);

    if (!isset($result['success']) || !$result['success']) {
        return '<div class="fwd-error">Unable to load data for ' . esc_html($atts['name']) . '.</div>';
    }

    if ($result['count'] === 0) {
        return '<div class="fwd-no-results">No films found featuring ' . esc_html($atts['name']) . ' watches.</div>';
    }

    ob_start();
    ?>
    <div class="fwd-brand-container">
        <h3><?php echo esc_html($atts['name']); ?> in Film</h3>
        <p class="fwd-count">Found <?php echo esc_html($result['count']); ?> result(s)</p>

        <div class="fwd-items-list">
            <?php foreach ($result['films'] as $film): ?>
            <div class="fwd-item">
                <div class="fwd-item-title"><?php echo esc_html($film['title']); ?> (<?php echo esc_html($film['year']); ?>)</div>
                <div class="fwd-item-details">
                    <strong>Actor:</strong> <?php echo esc_html($film['actor']); ?> as <?php echo esc_html($film['character']); ?><br>
                    <strong>Watch:</strong> <?php echo esc_html($film['model']); ?><br>
                    <strong>Role:</strong> <?php echo esc_html($film['narrative']); ?>
                    <?php if (!empty($film['source_url'])): ?>
                        <br><strong>Source:</strong> <a href="<?php echo esc_url($film['source_url']); ?>" target="_blank" rel="noopener">View Reference</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('film_watch_brand', 'fwd_brand_shortcode');

/**
 * Shortcode: [film_watch_film title="Casino Royale"]
 * Displays watches featured in a specific film
 */
function fwd_film_shortcode($atts) {
    $atts = shortcode_atts(array(
        'title' => '',
    ), $atts);

    if (empty($atts['title'])) {
        return '<div class="fwd-error">Please specify a film title using the "title" attribute.</div>';
    }

    $result = fwd_query_film($atts['title']);

    if (!isset($result['success']) || !$result['success']) {
        return '<div class="fwd-error">Unable to load data for ' . esc_html($atts['title']) . '.</div>';
    }

    if ($result['count'] === 0) {
        return '<div class="fwd-no-results">No watches found in ' . esc_html($atts['title']) . '.</div>';
    }

    ob_start();
    ?>
    <div class="fwd-film-container">
        <h3>Watches in <?php echo esc_html($atts['title']); ?></h3>
        <p class="fwd-count">Found <?php echo esc_html($result['count']); ?> watch(es)</p>

        <div class="fwd-items-list">
            <?php foreach ($result['watches'] as $watch): ?>
            <div class="fwd-item">
                <div class="fwd-item-title"><?php echo esc_html($watch['actor']); ?> as <?php echo esc_html($watch['character']); ?></div>
                <div class="fwd-item-details">
                    <strong>Watch:</strong> <?php echo esc_html($watch['brand']); ?> <?php echo esc_html($watch['model']); ?><br>
                    <strong>Role:</strong> <?php echo esc_html($watch['narrative']); ?>
                    <?php if (!empty($watch['source_url'])): ?>
                        <br><strong>Source:</strong> <a href="<?php echo esc_url($watch['source_url']); ?>" target="_blank" rel="noopener">View Reference</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('film_watch_film', 'fwd_film_shortcode');

/**
 * Shortcode: [film_watch_add]
 * Admin-only form to add new entries (requires manage_options capability)
 */
function fwd_add_shortcode($atts) {
    if (!current_user_can('manage_options')) {
        return '<div class="fwd-error">You do not have permission to add entries.</div>';
    }

    ob_start();
    ?>
    <div class="fwd-add-container">
        <h3>Add New Entry</h3>
        <div class="fwd-examples">
            <strong>Examples:</strong><br>
            • "Jakob Cedergren wears a Citizen Eco-Drive Divers 200M in The Guilty (2018)"<br>
            • "Tom Cruise wore a Breitling Navitimer in Top Gun: Maverick (2022)"<br>
            • "Matthew Clapp wore Rolex Yacht-Master in the movie Pizza with Matt (2025)"<br>
            • "In Interstellar (2014), Matthew McConaughey as Cooper wears Hamilton Khaki Pilot"
        </div>

        <div class="fwd-form-group">
            <label for="fwd-entry-text">Entry Text:</label>
            <textarea
                id="fwd-entry-text"
                class="fwd-input"
                cols="80"
                rows="3"
                placeholder="Actor wears Brand Model in Film (Year)"
            ></textarea>
        </div>

        <div class="fwd-form-group">
            <label for="fwd-narrative">Narrative Role (optional):</label>
            <textarea
                id="fwd-narrative"
                class="fwd-textarea"
                placeholder="Describe the watch's role in the film..."
            ></textarea>
        </div>

        <div class="fwd-form-group">
            <label for="fwd-source-url">Source URL (optional):</label>
            <input
                type="url"
                id="fwd-source-url"
                class="fwd-input"
                size="80"
                placeholder="https://www.watch-id.com/sightings/..."
            >
            <small style="display: block; margin-top: 5px; color: #666;">
                Add a reference URL for verification (e.g., watch-id.com, IMDB, etc.)
            </small>
        </div>

        <button id="fwd-add-btn" class="fwd-button">Add to Database</button>

        <div id="fwd-add-result" class="fwd-result"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('film_watch_add', 'fwd_add_shortcode');
